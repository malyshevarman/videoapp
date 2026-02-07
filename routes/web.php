<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ServiceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;


Auth::routes();
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/video/play/{id}', [HomeController::class, 'videoplay'])
    ->name('videos.play');

Route::get('/services/{public_url}/show', [HomeController::class, 'showservices'])->name('services.show');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard'])
            ->name('dashboard');

        Route::resource('services', ServiceController::class)
            ->only(['index', 'edit', 'update', 'destroy']);

        Route::get('services/{service}/video', [ServiceController::class, 'video'])->name('services.video');

    });
