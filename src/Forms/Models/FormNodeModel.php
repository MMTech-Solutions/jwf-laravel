<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mmt\JwfLaravel\ValidationProfiles\Models\ValidationProfileVersionModel;

final class FormNodeModel extends Model
{
    use HasUuids;

    protected $table = 'jwf_form_nodes';

    protected $fillable = [
        'id',
        'form_version_id',
        'node_id',
        'parent_id',
        'kind',
        'position',
        'type',
        'name',
        'label',
        'description',
        'attributes',
        'configuration',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer', 'attributes' => 'array', 'configuration' => 'array'];
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    /** @return HasMany<FormNodeOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(FormNodeOption::class, 'form_node_id');
    }

    /** @return BelongsToMany<ValidationProfileVersionModel, $this> */
    public function profileVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            ValidationProfileVersionModel::class,
            'jwf_form_node_profile_versions',
            'form_node_id',
            'profile_version_id',
        )->withPivot('position')->orderByPivot('position');
    }
}
