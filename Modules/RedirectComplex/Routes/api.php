<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['auth:api', 'log_activities', 'scopes:be'], 'prefix' => 'redirect-complex'], function () {
    Route::get('be/list', 'ApiRedirectComplex@index');
    Route::post('be/detail', 'ApiRedirectComplex@detail');
    Route::post('create', 'ApiRedirectComplex@create');
    Route::post('update', 'ApiRedirectComplex@update');
    Route::post('delete', 'ApiRedirectComplex@delete');
});