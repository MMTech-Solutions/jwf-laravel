<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Files\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mmt\JwfLaravel\Submissions\Models\SubmissionValueModel;

final class FileArtifactModel extends Model
{
    use HasUuids;

    protected $table = 'jwf_file_artifacts';

    protected $fillable = [
        'id',
        'submission_value_id',
        'disk',
        'path',
        'name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<SubmissionValueModel, $this> */
    public function submissionValue(): BelongsTo
    {
        return $this->belongsTo(SubmissionValueModel::class, 'submission_value_id');
    }
}
