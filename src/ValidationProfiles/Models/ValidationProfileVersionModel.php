<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\ValidationProfiles\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ValidationProfileVersionModel extends Model
{
    use HasUuids;

    protected $table = 'jwf_validation_profile_versions';

    protected $fillable = ['id', 'profile_id', 'number', 'compatible_types', 'rules'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'compatible_types' => 'array', 'rules' => 'array'];
    }

    /** @return BelongsTo<ValidationProfileModel, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ValidationProfileModel::class, 'profile_id');
    }
}
