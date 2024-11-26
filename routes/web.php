<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\MessageController;

// Route::get('/messages', [MessageController::class, 'index']);
Route::post('/send-message', [MessageController::class, 'sendMessage']);

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test', function () {
    return view('test');
});