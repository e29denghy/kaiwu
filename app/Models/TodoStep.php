<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TodoStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'todo_id',
        'title',
        'description',
        'execution_type',
        'status',
        'sort_order',
        'ai_prompt',
        'ai_result',
        'requires_human_confirmation',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_human_confirmation' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
