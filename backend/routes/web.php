<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Here is where you can register web routes for your application. These routes are loaded by the 
| RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
*/

#Route::get('/', function () { return  });
Route::redirect('/', 'https://suinpac.com/');

use App\Http\Controllers\TestController;

Route::get('/test', [TestController::class, 'index']);