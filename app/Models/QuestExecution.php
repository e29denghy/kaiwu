<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'quest_id',
        'harness_connection_id',
        'attempt',
        'dispatch_id',
        'status',
        'outbox_path',
        'result',
        'error',
        'metadata',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(HarnessConnection::class, 'harness_connection_id');
    }
}
