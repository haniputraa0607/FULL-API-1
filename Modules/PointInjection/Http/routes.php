<?php

Route::group(['middleware' => ['auth:api-be', 'log_activities', 'user_agent'], 'prefix' => 'api/point-injection', 'namespace' => 'Modules\PointInjection\Http\Controllers'], function () {
    Route::post('list', ['middleware' => 'feature_control:206', 'uses' => 'ApiPointInjectionController@index']);
    Route::post('create', ['middleware' => 'feature_control:208', 'uses' => 'ApiPointInjectionController@store']);
    Route::post('update', ['middleware' => 'feature_control:209', 'uses' => 'ApiPointInjectionController@update']);
    Route::post('delete', ['middleware' => 'feature_control:210', 'uses' => 'ApiPointInjectionController@destroy']);
    Route::post('review', ['middleware' => 'feature_control:207', 'uses' => 'ApiPointInjectionController@review']);
    Route::post('getUserList', ['middleware' => 'feature_control:207', 'uses' => 'ApiPointInjectionController@getUserList']);
});
Route::group(['prefix' => 'api/point-injection', 'namespace' => 'Modules\PointInjection\Http\Controllers'], function () {
    Route::get('getPointInjection', 'ApiPointInjectionController@getPointInjection');
});