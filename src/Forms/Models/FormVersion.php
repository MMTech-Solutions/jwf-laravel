<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mmt\Jwf\Forms\Domain\FormState;

final class FormVersion extends Model
{
    use HasUuids;

    protected $table = 'jwf_form_versions';

    protected $fillable = ['id', 'template_id', 'number', 'state'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'state' => FormState::class];
    }

    /** @return BelongsTo<FormTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'template_id');
    }

    /** @return HasMany<FormNodeModel, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(FormNodeModel::class, 'form_version_id');
    }
}
