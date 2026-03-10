<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExternalServiceController;

Route::middleware('api.bearer')->group(function () {
    Route::post('/services', [ExternalServiceController::class, 'store']);
});
