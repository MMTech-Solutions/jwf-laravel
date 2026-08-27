<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\ValidationProfiles;

use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;
use Mmt\JwfLaravel\Exceptions\UnsafeValidationRuleException;

final class RuleCompiler
{
    private const NO_PARAMETERS = [
        'required',
        'nullable',
        'string',
        'integer',
        'numeric',
        'boolean',
        'email',
        'date',
    ];

    /** @param list<RuleDefinition> $rules
     * @return list<string>
     */
    public function compile(array $rules): array
    {
        return array_map($this->compileRule(...), $rules);
    }

    public function compileRule(RuleDefinition $rule): string
    {
        if (in_array($rule->name, self::NO_PARAMETERS, true)) {
            $this->assertKeys($rule, []);

            return $rule->name;
        }

        return match ($rule->name) {
            'min', 'max' => $rule->name.':'.$this->numeric($rule, 'value'),
            'between' => 'between:'.$this->numeric($rule, 'min').','.$this->numeric($rule, 'max'),
            'in', 'mimes', 'mimetypes' => $rule->name.':'.implode(',', $this->strings($rule, 'values')),
            'date_format' => 'date_format:'.$this->safeString($rule, 'format'),
            default => throw new UnsafeValidationRuleException("Validation rule [{$rule->name}] is not allowed."),
        };
    }

    /** @param list<string> $keys */
    private function assertKeys(RuleDefinition $rule, array $keys): void
    {
        $actual = array_keys($rule->parameters);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new UnsafeValidationRuleException("Validation rule [{$rule->name}] has invalid parameters.");
        }
    }

    private function numeric(RuleDefinition $rule, string $key): string
    {
        $required = $rule->name === 'between' ? ['max', 'min'] : ['value'];
        $this->assertKeys($rule, $required);
        $value = $rule->parameters[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new UnsafeValidationRuleException("Validation rule [{$rule->name}] requires numeric parameters.");
        }

        return (string) $value;
    }

    /** @return non-empty-list<string> */
    private function strings(RuleDefinition $rule, string $key): array
    {
        $this->assertKeys($rule, [$key]);
        $values = $rule->parameters[$key] ?? null;
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new UnsafeValidationRuleException("Validation rule [{$rule->name}] requires a non-empty list.");
        }
        foreach ($values as $value) {
            if (!is_string($value) || $value === '' || str_contains($value, ',')) {
                throw new UnsafeValidationRuleException("Validation rule [{$rule->name}] contains an unsafe value.");
            }
        }

        return $values;
    }

    private function safeString(RuleDefinition $rule, string $key): string
    {
        $this->assertKeys($rule, [$key]);
        $value = $rule->parameters[$key] ?? null;
        if (!is_string($value) || $value === '' || str_contains($value, '|')) {
            throw new UnsafeValidationRuleException("Validation rule [{$rule->name}] contains an unsafe value.");
        }

        return $value;
    }
}
