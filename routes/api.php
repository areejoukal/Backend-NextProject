<?php

use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\NextStepController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ReassessmentController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoogleController;
use App\Http\Controllers\Api\PasswordResetController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:6,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    // Google OAuth
    Route::get('/auth/google', [GoogleController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

    // Password Reset
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);

    
});
/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // ---------- Auth ----------
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateMe']);

    // ---------- Profile ----------
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // ---------- Goals ----------
    Route::prefix('goals')->group(function () {
        Route::get('/active', [GoalController::class, 'active']);
        Route::get('/', [GoalController::class, 'index']);
        Route::post('/', [GoalController::class, 'store']);
        Route::get('/{goal}', [GoalController::class, 'show']);
        Route::put('/{goal}', [GoalController::class, 'update']);
        Route::delete('/{goal}', [GoalController::class, 'destroy']);
    });

    // ---------- Assessments ----------
    Route::prefix('assessments')->group(function () {
        Route::post('/', [AssessmentController::class, 'store']);
        Route::get('/{assessment}', [AssessmentController::class, 'show']);
        Route::get('/{assessment}/next-question', [AssessmentController::class, 'nextQuestion']);
        Route::post('/{assessment}/answer', [AssessmentController::class, 'answer']);
        Route::post('/{assessment}/complete', [AssessmentController::class, 'complete']);
        Route::post('/{assessment}/abandon', [AssessmentController::class, 'abandon']);

        // Analysis
        Route::post('/{assessment}/analyze', [AnalysisController::class, 'generate']);
        Route::get('/{assessment}/analysis', [AnalysisController::class, 'forAssessment']);
        Route::get('/assessments/current', [AssessmentController::class, 'current']);
    });

    // ---------- Analyses ----------
    Route::get('/analyses/{analysis}', [AnalysisController::class, 'show']);
    Route::post('/analyses/{analysis}/generate-steps', [NextStepController::class, 'generate']);

    // ---------- Next Steps ----------
    Route::prefix('next-steps')->group(function () {
        Route::get('/current', [NextStepController::class, 'current']);
        Route::get('/', [NextStepController::class, 'index']);
        Route::get('/{nextStep}', [NextStepController::class, 'show']);
        Route::post('/{nextStep}/complete', [NextStepController::class, 'complete']);
        Route::put('/{nextStep}/note', [NextStepController::class, 'note']);
    });

    // ---------- Progress ----------
    Route::prefix('progress')->group(function () {
        Route::get('/', [ProgressController::class, 'summary']);
        Route::get('/events', [ProgressController::class, 'events']);
    });

    // ---------- Notifications ----------
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllRead']);
    });

    // ---------- Reassessment ----------
    Route::post('/reassessment/start', [ReassessmentController::class, 'start']);

    // ---------- Settings ----------
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::delete('/account', [SettingsController::class, 'deleteAccount']);
    
});