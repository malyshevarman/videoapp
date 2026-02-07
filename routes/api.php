<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExternalServiceController;

Route::post('/services', [ExternalServiceController::class, 'store']);
Route::prefix('video')->group(function () {
    Route::post('upload-chunks', [ExternalServiceController::class, 'uploadChunks']);
    Route::get('/', [ExternalServiceController::class, 'show']);
    Route::post('defects', [ExternalServiceController::class, 'defects']);
    Route::delete('/', [ExternalServiceController::class, 'destroy']);
});
