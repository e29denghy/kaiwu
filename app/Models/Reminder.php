<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'todo_id',
        'todo_step_id',
        'title',
        'body',
        'remind_at',
        'channel',
        'status',
        'read_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'read_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(TodoStep::class, 'todo_step_id');
    }
}
