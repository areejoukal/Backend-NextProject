<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'headline',
        'summary',
        'known',
        'unknown',
        'controllable',
        'uncontrollable',
        'ai_model',
        'ai_tokens',
        'ai_latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'known' => 'array',
            'unknown' => 'array',
            'controllable' => 'array',
            'uncontrollable' => 'array',
            'ai_tokens' => 'integer',
            'ai_latency_ms' => 'integer',
        ];
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function nextSteps()
    {
        return $this->hasMany(NextStep::class)->orderBy('order');
    }
}