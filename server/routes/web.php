<?php

use App\Http\Controllers\MeetingAudioController;
use App\Livewire\Board;
use App\Livewire\Meetings;
use App\Livewire\Now;
use App\Livewire\Projects;
use App\Livewire\TimeReport;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Now::class)->name('dashboard');
    Route::livewire('priority', Board::class)->name('board');
    Route::livewire('time', TimeReport::class)->name('time');
    Route::livewire('meetings', Meetings\Index::class)->name('meetings.index');
    Route::livewire('meetings/new', Meetings\Create::class)->name('meetings.create');
    Route::livewire('meetings/{meeting}', Meetings\Show::class)->name('meetings.show');
    Route::get('meetings/{meeting}/audio', MeetingAudioController::class)->name('meetings.audio');
    Route::livewire('projects', Projects\Index::class)->name('projects.index');
    Route::livewire('projects/{project}', Projects\Show::class)->name('projects.show');
});

require __DIR__.'/settings.php';
