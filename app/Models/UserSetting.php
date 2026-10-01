<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'step_reminders',
        'reassessment_reminders',
        'change_summary',
    ];

    protected function casts(): array
    {
        return [
            'step_reminders' => 'boolean',
            'reassessment_reminders' => 'boolean',
            'change_summary' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}