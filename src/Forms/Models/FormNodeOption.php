<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FormNodeOption extends Model
{
    use HasUuids;

    protected $table = 'jwf_input_options';

    protected $fillable = [
        'id',
        'form_node_id',
        'option_id',
        'value',
        'label',
        'disabled',
        'attributes',
        'position',
    ];

    protected function casts(): array
    {
        return ['disabled' => 'boolean', 'attributes' => 'array', 'position' => 'integer'];
    }

    /** @return BelongsTo<FormNodeModel, $this> */
    public function node(): BelongsTo
    {
        return $this->belongsTo(FormNodeModel::class, 'form_node_id');
    }
}
