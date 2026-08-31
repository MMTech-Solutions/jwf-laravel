<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Support;

use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;

final readonly class DefaultValidationProfile
{
    /**
     * @param list<InputType> $compatibleTypes
     * @param list<RuleDefinition> $rules
     */
    public function __construct(
        public string $name,
        public array $compatibleTypes,
        public array $rules,
    ) {
    }
}
