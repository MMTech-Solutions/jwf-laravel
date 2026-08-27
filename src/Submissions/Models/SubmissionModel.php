<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Submissions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubmissionModel extends Model
{
    use HasUuids;

    protected $table = 'jwf_submissions';

    protected $fillable = ['id', 'form_version_id', 'form_id', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime'];
    }

    /** @return HasMany<SubmissionValueModel, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(SubmissionValueModel::class, 'submission_id');
    }
}
