<?php

declare(strict_types=1);

namespace Cardinal\Models;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    protected $table = 'cardinal_issues';

    protected $fillable = [
        'type',
        'fingerprint',
        'template',
        'context_type',
        'context_name',
        'max_ms',
        'total_ms',
        'count',
        'payload',
        'last_seen_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'max_ms'       => 'float',
        'total_ms'     => 'float',
        'count'        => 'integer',
        'last_seen_at' => 'datetime',
    ];
}
