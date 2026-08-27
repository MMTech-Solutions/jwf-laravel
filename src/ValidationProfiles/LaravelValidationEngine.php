<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\ValidationProfiles;

use Illuminate\Contracts\Validation\Factory;
use Mmt\Jwf\Forms\Domain\Input\FileReference;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\ValidationProfiles\Contracts\ValidationEngine;
use Mmt\Jwf\ValidationProfiles\Contracts\ValidationError;
use Mmt\Jwf\ValidationProfiles\Contracts\ValidationResult;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;

final readonly class LaravelValidationEngine implements ValidationEngine
{
    public function __construct(private Factory $validator, private RuleCompiler $compiler)
    {
    }

    public function validate(
        InputNode $input,
        string|int|float|bool|FileReference|null $value,
        array $rules,
    ): ValidationResult {
        $fileErrors = $this->validateFileMetadata($input, $value, $rules);
        $laravelRules = array_values(array_filter(
            $rules,
            static fn (RuleDefinition $rule): bool => !in_array($rule->name, ['mimes', 'mimetypes'], true),
        ));
        $compiled = $this->compiler->compile($laravelRules);
        if ($value instanceof FileReference) {
            $compiled = array_values(array_filter(
                $compiled,
                static fn (string $rule): bool => !in_array($rule, ['string'], true),
            ));
        }
        $validator = $this->validator->make(['value' => $value], ['value' => $compiled]);
        $errors = $fileErrors;
        if ($validator->fails()) {
            foreach ($validator->errors()->get('value') as $message) {
                if (is_string($message)) {
                    $errors[] = new ValidationError($input->id, $this->failedRule($validator->failed()), $message);
                }
            }
        }

        return new ValidationResult($errors);
    }

    /** @param list<RuleDefinition> $rules
     * @return list<ValidationError>
     */
    private function validateFileMetadata(
        InputNode $input,
        string|int|float|bool|FileReference|null $value,
        array $rules,
    ): array {
        if (!$value instanceof FileReference) {
            return [];
        }
        foreach ($rules as $rule) {
            if ($rule->name === 'mimetypes') {
                $allowed = $rule->parameters['values'] ?? [];
                if (is_array($allowed) && !in_array($value->mimeType, $allowed, true)) {
                    return [new ValidationError($input->id, 'mimetypes', 'The file MIME type is not allowed.')];
                }
            }
            if ($rule->name === 'mimes') {
                $allowed = $rule->parameters['values'] ?? [];
                $extension = strtolower(pathinfo($value->name, PATHINFO_EXTENSION));
                if (is_array($allowed) && !in_array($extension, $allowed, true)) {
                    return [new ValidationError($input->id, 'mimes', 'The file extension is not allowed.')];
                }
            }
        }

        return [];
    }

    /** @param array<mixed, mixed> $failed */
    private function failedRule(array $failed): string
    {
        $valueRules = $failed['value'] ?? [];
        if (!is_array($valueRules)) {
            return 'validation';
        }
        $rules = array_keys($valueRules);

        return isset($rules[0]) && is_string($rules[0]) ? strtolower($rules[0]) : 'validation';
    }
}
