<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Submissions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Mmt\JwfLaravel\Files\Models\FileArtifactModel;

final class SubmissionValueModel extends Model
{
    use HasUuids;

    protected $table = 'jwf_submission_values';

    protected $fillable = ['id', 'submission_id', 'input_id', 'value', 'sensitive', 'position'];

    protected function casts(): array
    {
        return ['sensitive' => 'boolean', 'position' => 'integer'];
    }

    /** @return BelongsTo<SubmissionModel, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(SubmissionModel::class, 'submission_id');
    }

    /** @return HasOne<FileArtifactModel, $this> */
    public function artifact(): HasOne
    {
        return $this->hasOne(FileArtifactModel::class, 'submission_value_id');
    }
}
