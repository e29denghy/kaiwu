<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Todo extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'project_id',
        'project_module_id',
        'title',
        'description',
        'priority',
        'status',
        'planning_state',
        'due_at',
        'scheduled_for',
        'focus_rank',
        'completed_at',
        'ai_analysis',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'scheduled_for' => 'date:Y-m-d',
            'focus_rank' => 'integer',
            'completed_at' => 'datetime',
            'ai_analysis' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ProjectModule::class, 'project_module_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TodoStep::class)->orderBy('sort_order');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function memoryEntry(): HasOne
    {
        return $this->hasOne(MemoryEntry::class);
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }
}
