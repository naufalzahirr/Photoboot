<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffController;
Route::get('/', fn () => redirect('/petugas'));
Route::get('/petugas/login', fn () => view('staff.login'))->name('login');
Route::post('/petugas/login',[StaffController::class,'login'])->middleware('throttle:5,1');
Route::middleware('auth')->group(function () {
    Route::get('/petugas',[StaffController::class,'index']);
    Route::post('/petugas/logout',[StaffController::class,'logout']);
    Route::post('/petugas/codes/{code}/used',[StaffController::class,'markUsed']);
    Route::post('/petugas/codes/{code}/distribute',[StaffController::class,'distribute']);
});

Route::get('/foto/{photo}', [\App\Http\Controllers\PhotoDownloadController::class, 'show'])->name('photos.show')->middleware(['signed', 'throttle:60,1']);
