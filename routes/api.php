<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BoothController;
use App\Http\Middleware\BoothDevice;
Route::post('/midtrans/notifications',[BoothController::class,'webhook'])->middleware('throttle:60,1');
Route::middleware([BoothDevice::class,'throttle:120,1'])->group(function () {
    Route::get('/orders-by-session/{clientID}/payment-status',[BoothController::class,'recover']);
    Route::post('/orders/{id}/fulfillment',[BoothController::class,'reserveFulfillment']);
    Route::get('/packages',[BoothController::class,'packages']);
    Route::post('/orders',[BoothController::class,'create']);
    Route::post('/orders/{id}/payment',[BoothController::class,'payment']);
    Route::get('/orders/{id}/payment-status',[BoothController::class,'status']);
    Route::get('/orders/{id}/qr',[BoothController::class,'qr']);
});
