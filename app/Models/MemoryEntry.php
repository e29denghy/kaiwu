<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_memory_source_id',
        'todo_id',
        'source_key',
        'source_file',
        'source_heading',
        'source_updated_at',
        'outcome',
        'title',
        'summary',
        'evidence',
        'content_hash',
        'is_current',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'source_updated_at' => 'datetime',
            'evidence' => 'array',
            'is_current' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProjectMemorySource::class, 'project_memory_source_id');
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }
}
