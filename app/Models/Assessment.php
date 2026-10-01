<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'goal_id',
        'type',
        'status',
        'current_step',
        'max_steps',
        'current_question_id',
        'user_state',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_state' => 'array',
            'completed_at' => 'datetime',
            'current_step' => 'integer',
            'max_steps' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    public function answers()
    {
        return $this->hasMany(AssessmentAnswer::class)->orderBy('order');
    }

    public function analysis()
    {
        return $this->hasOne(Analysis::class);
    }

    // Helpers
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}