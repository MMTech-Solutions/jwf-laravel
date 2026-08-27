<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Contracts;

use Mmt\JwfLaravel\Authorization\AuthorizationContext;
use Mmt\JwfLaravel\Authorization\JwfOperation;

interface JwfAuthorizer
{
    public function authorize(JwfOperation $operation, AuthorizationContext $context): void;
}
