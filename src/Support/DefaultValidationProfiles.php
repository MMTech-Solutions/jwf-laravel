<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Support;

use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;

final class DefaultValidationProfiles
{
    /** @return list<DefaultValidationProfile> */
    public function forInput(InputType $type): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (DefaultValidationProfile $profile): bool => in_array($type, $profile->compatibleTypes, true),
        ));
    }

    /** @return list<DefaultValidationProfile> */
    private function all(): array
    {
        return [
            new DefaultValidationProfile(
                'jwf.default.email',
                [InputType::Email],
                [new RuleDefinition('email')],
            ),
            new DefaultValidationProfile(
                'jwf.default.url',
                [InputType::Url],
                [new RuleDefinition('url')],
            ),
        ];
    }
}
