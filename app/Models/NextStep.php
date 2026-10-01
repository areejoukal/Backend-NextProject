<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NextStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'analysis_id',
        'order',
        'role',
        'title',
        'reason',
        'why',
        'outcome',
        'steps',
        'completion_criteria',
        'effort',
        'time_estimate',
        'status',
        'note',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'order' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analysis()
    {
        return $this->belongsTo(Analysis::class);
    }

    public function isPrimary(): bool
    {
        return $this->role === 'primary';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}