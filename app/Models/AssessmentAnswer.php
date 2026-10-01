<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'question_id',
        'question_text',
        'answer',
        'info_gain',
        'reason',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'info_gain' => 'decimal:2',
            'order' => 'integer',
        ];
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }
}