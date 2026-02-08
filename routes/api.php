<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExternalServiceController;

Route::post('/services', [ExternalServiceController::class, 'store']);
