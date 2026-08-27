<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Authorization;

final readonly class AuthorizationContext
{
    /** @param array<string, string|int|bool|null> $attributes */
    public function __construct(public ?object $actor = null, public array $attributes = [])
    {
    }
}
