<?php

namespace App\Models;

use App\Enums\CancellationType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property CancellationType $type
 * @property int $next_value
 */
final class RadicadoSequence extends Model
{
    protected $fillable = [
        'type',
        'next_value',
    ];

    protected function casts(): array
    {
        return [
            'type' => CancellationType::class,
            'next_value' => 'int',
        ];
    }
}
