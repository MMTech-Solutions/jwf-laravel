<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\ValidationProfiles\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ValidationProfileModel extends Model
{
    use HasUuids;

    protected $table = 'jwf_validation_profiles';

    protected $fillable = ['id', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<ValidationProfileVersionModel, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ValidationProfileVersionModel::class, 'profile_id');
    }
}
