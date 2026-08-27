<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Submissions;

use DateTimeImmutable;
use DateTimeZone;
use Mmt\Jwf\Submissions\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
