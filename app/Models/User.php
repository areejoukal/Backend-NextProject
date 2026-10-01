<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',

    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    // ============ Relations ============

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    public function activeGoal()
    {
        return $this->hasOne(Goal::class)->where('is_active', true)->latestOfMany();
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function nextSteps()
    {
        return $this->hasMany(NextStep::class);
    }

    public function progressEvents()
    {
        return $this->hasMany(ProgressEvent::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}