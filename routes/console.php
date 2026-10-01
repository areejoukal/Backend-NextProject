<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// مثال: تشغيل الأمر كل يوم الساعة 9 صباحاً
Schedule::command('notifications:step-reminders')->dailyAt('09:00');

// مثال: تشغيل الأمر أسبوعياً
Schedule::command('notifications:reassessment-reminders')->weekly();