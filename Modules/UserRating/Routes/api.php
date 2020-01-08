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

Route::group(['middleware' => ['auth:api', 'log_activities', 'user_agent'], 'prefix' => 'user-rating'], function () {
    Route::post('create', 'ApiUserRatingController@store');
    Route::post('get-detail', 'ApiUserRatingController@getDetail');
});

Route::group(['middleware' => ['auth:api-be', 'log_activities', 'user_agent'], 'prefix' => 'user-rating'], function () {
    Route::post('/', ['middleware' => 'feature_control:179', 'uses' => 'ApiUserRatingController@index']);
    Route::post('detail', 'ApiUserRatingController@show');
    Route::post('delete', 'ApiUserRatingController@destroy');
});
