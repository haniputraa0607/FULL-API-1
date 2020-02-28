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

Route::group(['middleware' => ['auth:api', 'log_activities', 'user_agent', 'scopes:apps'], 'prefix' => 'user-rating'], function () {
    Route::post('create', 'ApiUserRatingController@store');
    Route::post('get-detail', 'ApiUserRatingController@getDetail');
});

Route::group(['middleware' => ['auth:api', 'log_activities', 'user_agent', 'scopes:be'], 'prefix' => 'user-rating'], function () {
    Route::post('/', ['middleware' => 'feature_control:197', 'uses' => 'ApiUserRatingController@index']);
    Route::post('detail', ['middleware' => 'feature_control:197', 'uses' => 'ApiUserRatingController@show']);
    Route::post('delete', ['middleware' => 'feature_control:198', 'uses' => 'ApiUserRatingController@destroy']);
    Route::post('report', ['middleware' => 'feature_control:197', 'uses' => 'ApiUserRatingController@report']);
    Route::post('report/outlet', ['middleware' => 'feature_control:197', 'uses' => 'ApiUserRatingController@reportOutlet']);
    Route::group(['prefix'=>'option'],function(){
    	Route::get('/',['middleware' => 'feature_control:199', 'uses' => 'ApiRatingOptionController@index']);
    	Route::post('update',['middleware' => 'feature_control:201', 'uses' => 'ApiRatingOptionController@update']);
    });
});
