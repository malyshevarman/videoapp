<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ServiceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\ExternalServiceController;

Auth::routes(['register' => false]);
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
})->name('home');
Route::get('/video/play/{id}', [HomeController::class, 'videoplay'])
    ->name('videos.play');

Route::get('/services/{public_url}/show', [HomeController::class, 'showservices'])->name('services.show');
Route::post('/services/{public_url}/update', [HomeController::class, 'updateservices'])
    ->name('services.update');


Route::get('video', [ExternalServiceController::class, 'show']);
Route::middleware('auth')->prefix('video')->group(function () {
    Route::post('upload-chunks', [ExternalServiceController::class, 'uploadChunks']);
    Route::post('defects', [ExternalServiceController::class, 'defects']);
    Route::delete('/', [ExternalServiceController::class, 'destroy']);
});


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
