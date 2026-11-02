<?php

use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\NowController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TimerController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

// Used by the phone app (mobile/). Bearer tokens via Sanctum.
Route::name('api.')->group(function () {
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::get('now', NowController::class)->name('now');
        Route::get('projects', ProjectController::class)->name('projects');

        Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::post('tasks/{task}/place', [TaskController::class, 'place'])->name('tasks.place');
        Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
        Route::post('tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');

        Route::get('timer', [TimerController::class, 'show'])->name('timer.show');
        Route::post('tasks/{task}/timer', [TimerController::class, 'start'])->name('timer.start');
        Route::delete('timer', [TimerController::class, 'stop'])->name('timer.stop');

        Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
        Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
        Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    });
});
