<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FormTemplate extends Model
{
    use HasUuids;

    protected $table = 'jwf_form_templates';

    protected $fillable = ['id', 'name'];

    /** @return HasMany<FormVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class, 'template_id');
    }
}
