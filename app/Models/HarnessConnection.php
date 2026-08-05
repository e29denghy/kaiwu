<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HarnessConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'driver',
        'status',
        'configuration',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(HarnessEvent::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(QuestExecution::class);
    }
}
