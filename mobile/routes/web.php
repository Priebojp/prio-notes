<?php

use App\Http\Middleware\EnsureSignedIn;
use App\Livewire\Login;
use App\Livewire\Now;
use App\Livewire\Record;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', Login::class)->name('login');

Route::middleware(EnsureSignedIn::class)->group(function () {
    Route::livewire('/', Now::class)->name('now');
    Route::livewire('/record', Record::class)->name('record');
});
