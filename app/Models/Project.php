<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'priority',
        'sort_order',
        'status',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function scopeInDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderByRaw("case priority when 'P0' then 0 when 'P1' then 1 when 'P2' then 2 else 3 end")
            ->orderBy('name');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ProjectModule::class)->orderBy('sort_order');
    }

    public function memorySources(): HasMany
    {
        return $this->hasMany(ProjectMemorySource::class);
    }

    public function harnessEvents(): HasMany
    {
        return $this->hasMany(HarnessEvent::class);
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }
}
