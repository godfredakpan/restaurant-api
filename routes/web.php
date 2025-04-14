<?php

use Illuminate\Support\Facades\Route;
// use Intervention\Image\Facades\Image;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // return json 
    return response()->json(['name' => 'OrderRave Solutions Ltd',  'version' => '1.0.0', 'description' => 'OrderRave API : Bring your Restaurant Menu to Life with OrderRave', 'message' => 'Sign up to get started: https://orderrave.ng',], 202);
});


Route::get('/authenticated', function (){
    return response()->json(['message' => 'Unauthorized'], 401);
});

Route::get('/email/confirm', [App\Http\Controllers\AuthController::class, 'confirmEmail'])->name('email.confirm');
// Account security settings
Route::get('/account/security', [App\Http\Controllers\AccountSecurityController::class, 'showSecuritySettings'])->name('account.security');

// Update password
Route::post('/account/update-password', [App\Http\Controllers\AccountSecurityController::class, 'updatePassword'])->name('account.update.password');


// Route::get('/test-image', function () {
//     $img = Image::canvas(200, 200, '#ff0000');
//     return $img->response('png');
// });