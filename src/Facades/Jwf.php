<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use Mmt\JwfLaravel\Files\ArtifactManager;
use Mmt\JwfLaravel\Forms\FormManager;
use Mmt\JwfLaravel\ValidationProfiles\ValidationProfileManager;

/**
 * @method static FormManager forms()
 * @method static ValidationProfileManager profiles()
 * @method static ArtifactManager artifacts()
 * @see \Mmt\JwfLaravel\JwfManager
 */
final class Jwf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'jwf';
    }
}
