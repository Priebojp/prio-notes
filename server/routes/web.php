<?php

use App\Livewire\Board;
use App\Livewire\Now;
use App\Livewire\Projects;
use App\Livewire\TimeReport;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Now::class)->name('dashboard');
    Route::livewire('priority', Board::class)->name('board');
    Route::livewire('time', TimeReport::class)->name('time');
    Route::livewire('projects', Projects\Index::class)->name('projects.index');
    Route::livewire('projects/{project}', Projects\Show::class)->name('projects.show');
});

require __DIR__.'/settings.php';
