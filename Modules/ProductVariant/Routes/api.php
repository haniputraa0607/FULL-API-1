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

Route::group(['middleware'=>['log_activities','auth:api-be'],'prefix'=>'product-variant'],function(){
	Route::group(['prefix'=>'group'],function(){
		Route::any('/',['middleware' => 'feature_control:1', 'uses' => 'ApiProductGroupController@index']);
		Route::post('detail',['middleware' => 'feature_control:1', 'uses' => 'ApiProductGroupController@show']);
		Route::post('create',['middleware' => 'feature_control:1', 'uses' => 'ApiProductGroupController@store']);
		Route::post('update',['middleware' => 'feature_control:1', 'uses' => 'ApiProductGroupController@store']);
		Route::post('delete',['middleware' => 'feature_control:1', 'uses' => 'ApiProductGroupController@store']);
	});
	Route::any('/',['middleware' => 'feature_control:1', 'uses' => 'ApiProductVariantController@index']);
	Route::post('detail',['middleware' => 'feature_control:1', 'uses' => 'ApiProductVariantController@show']);
	Route::post('create',['middleware' => 'feature_control:1', 'uses' => 'ApiProductVariantController@store']);
	Route::post('update',['middleware' => 'feature_control:1', 'uses' => 'ApiProductVariantController@store']);
	Route::post('delete',['middleware' => 'feature_control:1', 'uses' => 'ApiProductVariantController@store']);
});