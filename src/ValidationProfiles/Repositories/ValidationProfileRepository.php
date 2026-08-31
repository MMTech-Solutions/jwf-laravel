<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\ValidationProfiles\Repositories;

use Illuminate\Database\DatabaseManager;
use JsonException;
use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\Jwf\Submissions\Contracts\ValidationProfileVersionResolver;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfile;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersion;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersionReference;
use Mmt\JwfLaravel\Exceptions\JwfLaravelException;
use Mmt\JwfLaravel\Support\DefaultValidationProfile;
use stdClass;

final readonly class ValidationProfileRepository implements ValidationProfileVersionResolver
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function create(ValidationProfile $profile): ValidationProfile
    {
        $this->database->table('jwf_validation_profiles')->insert([
            'id' => $profile->id->toString(),
            'name' => $profile->name,
            'active' => $profile->active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $profile;
    }

    public function setActive(string $profileId, bool $active): void
    {
        $updated = $this->database->table('jwf_validation_profiles')->where('id', $profileId)->update([
            'active' => $active,
            'updated_at' => now(),
        ]);
        if ($updated === 0) {
            throw new JwfLaravelException('Validation profile not found.');
        }
    }

    /** @param list<InputType> $types
     * @param list<RuleDefinition> $rules
     */
    public function createVersion(string $profileId, array $types, array $rules): ValidationProfileVersion
    {
        return $this->database->connection()->transaction(function () use ($profileId, $types, $rules): ValidationProfileVersion {
            if (!$this->database->table('jwf_validation_profiles')->where('id', $profileId)->lockForUpdate()->exists()) {
                throw new JwfLaravelException('Validation profile not found.');
            }
            $maximum = $this->database->table('jwf_validation_profile_versions')
                ->where('profile_id', $profileId)
                ->max('number');
            $number = ($maximum === null ? 0 : $this->integer($maximum)) + 1;
            $version = new ValidationProfileVersion(NodeId::generate(), NodeId::fromString($profileId), $number, $types, $rules);
            $this->database->table('jwf_validation_profile_versions')->insert([
                'id' => $version->id->toString(),
                'profile_id' => $profileId,
                'number' => $number,
                'compatible_types' => json_encode(array_map(
                    static fn (InputType $type): string => $type->value,
                    $types,
                ), JSON_THROW_ON_ERROR),
                'rules' => json_encode(array_map(
                    static fn (RuleDefinition $rule): array => $rule->toArray(),
                    $rules,
                ), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $version;
        });
    }

    public function resolve(ValidationProfileVersionReference $reference): ValidationProfileVersion
    {
        return $this->findVersion($reference->versionId->toString());
    }

    public function ensureDefault(DefaultValidationProfile $profile): ValidationProfileVersionReference
    {
        return $this->database->connection()->transaction(function () use ($profile): ValidationProfileVersionReference {
            $profileRecord = $this->database->table('jwf_validation_profiles')
                ->where('name', $profile->name)
                ->lockForUpdate()
                ->first();
            $now = now();
            if (!$profileRecord instanceof stdClass) {
                $profileId = NodeId::generate();
                $this->database->table('jwf_validation_profiles')->insert([
                    'id' => $profileId->toString(),
                    'name' => $profile->name,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $profileId = NodeId::fromString($this->string($profileRecord->id));
            }

            $types = $this->encodeTypes($profile->compatibleTypes);
            $rules = $this->encodeRules($profile->rules);
            $versionRecord = $this->database->table('jwf_validation_profile_versions')
                ->where('profile_id', $profileId->toString())
                ->orderByDesc('number')
                ->first();
            if ($versionRecord instanceof stdClass
                && $this->string($versionRecord->compatible_types) === $types
                && $this->string($versionRecord->rules) === $rules
            ) {
                return new ValidationProfileVersionReference(
                    $profileId,
                    NodeId::fromString($this->string($versionRecord->id)),
                    $this->integer($versionRecord->number),
                    $profile->compatibleTypes,
                );
            }

            $number = $versionRecord instanceof stdClass ? $this->integer($versionRecord->number) + 1 : 1;
            $versionId = NodeId::generate();
            $this->database->table('jwf_validation_profile_versions')->insert([
                'id' => $versionId->toString(),
                'profile_id' => $profileId->toString(),
                'number' => $number,
                'compatible_types' => $types,
                'rules' => $rules,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return new ValidationProfileVersionReference($profileId, $versionId, $number, $profile->compatibleTypes);
        });
    }

    public function findVersion(string $versionId): ValidationProfileVersion
    {
        $record = $this->database->table('jwf_validation_profile_versions')->where('id', $versionId)->first();
        if (!$record instanceof stdClass) {
            throw new JwfLaravelException('Validation profile version not found.');
        }
        $types = $this->decodeList($this->string($record->compatible_types));
        $rules = $this->decodeList($this->string($record->rules));

        return new ValidationProfileVersion(
            NodeId::fromString($this->string($record->id)),
            NodeId::fromString($this->string($record->profile_id)),
            $this->integer($record->number),
            array_map(static function (mixed $type): InputType {
                if (!is_string($type)) {
                    throw new JsonException('Profile input type must be a string.');
                }
                return InputType::from($type);
            }, $types),
            array_map(static function (mixed $value): RuleDefinition {
                if (!is_array($value) || !is_string($value['name'] ?? null) || !is_array($value['parameters'] ?? null)) {
                    throw new JsonException('Persisted validation rule is invalid.');
                }
                $parameters = [];
                foreach ($value['parameters'] as $key => $parameter) {
                    if (!is_string($key)) {
                        throw new JsonException('Validation rule parameter names must be strings.');
                    }
                    $parameters[$key] = $parameter;
                }
                return new RuleDefinition($value['name'], $parameters);
            }, $rules),
        );
    }

    /** @return list<mixed> */
    private function decodeList(string $json): array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value) || !array_is_list($value)) {
            throw new JsonException('Persisted profile data must be a list.');
        }

        return $value;
    }

    private function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new JsonException('Persisted profile data must contain strings.');
        }

        return $value;
    }

    private function integer(mixed $value): int
    {
        if (!is_int($value)) {
            throw new JsonException('Persisted profile data must contain integers.');
        }

        return $value;
    }

    /** @param list<InputType> $types */
    private function encodeTypes(array $types): string
    {
        return json_encode(
            array_map(static fn (InputType $type): string => $type->value, $types),
            JSON_THROW_ON_ERROR,
        );
    }

    /** @param list<RuleDefinition> $rules */
    private function encodeRules(array $rules): string
    {
        return json_encode(
            array_map(static fn (RuleDefinition $rule): array => $rule->toArray(), $rules),
            JSON_THROW_ON_ERROR,
        );
    }
}
