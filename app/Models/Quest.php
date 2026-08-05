<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'todo_id',
        'title',
        'goal',
        'acceptance_criteria',
        'constraints',
        'verification',
        'risk_level',
        'requires_write',
        'approval_status',
        'execution_mode',
        'scheduled_for',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'acceptance_criteria' => 'array',
            'constraints' => 'array',
            'verification' => 'array',
            'requires_write' => 'boolean',
            'scheduled_for' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(QuestExecution::class)->orderBy('attempt');
    }
}
