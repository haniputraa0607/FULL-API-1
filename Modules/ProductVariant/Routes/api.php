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

Route::group(['middleware'=>['log_activities','auth:api', 'scopes:be'],'prefix'=>'product-variant'],function(){
	Route::group(['prefix'=>'group'],function(){
		Route::any('/',['middleware' => 'feature_control:217', 'uses' => 'ApiProductGroupController@index']);
		Route::post('detail',['middleware' => 'feature_control:218', 'uses' => 'ApiProductGroupController@show']);
		Route::post('create',['middleware' => 'feature_control:219', 'uses' => 'ApiProductGroupController@store']);
		Route::post('update',['middleware' => 'feature_control:220', 'uses' => 'ApiProductGroupController@update']);
		Route::post('delete',['middleware' => 'feature_control:221', 'uses' => 'ApiProductGroupController@destroy']);
		Route::post('assign',['middleware' => 'feature_control:220', 'uses' => 'ApiProductGroupController@assign']);
		Route::post('reorder',['middleware' => 'feature_control:220', 'uses' => 'ApiProductGroupController@reorder']);
		Route::post('import',['middleware' => 'feature_control:220', 'uses' => 'ApiProductGroupController@import']);
		Route::post('export',['middleware' => 'feature_control:217', 'uses' => 'ApiProductGroupController@export']);
		Route::post('photoAjax',['middleware' => 'feature_control:213', 'uses' => 'ApiProductGroupController@photoAjax']);

	});
	Route::any('/',['middleware' => 'feature_control:212', 'uses' => 'ApiProductVariantController@index']);
	Route::post('detail',['middleware' => 'feature_control:213', 'uses' => 'ApiProductVariantController@show']);
	Route::post('create',['middleware' => 'feature_control:214', 'uses' => 'ApiProductVariantController@store']);
	Route::post('update',['middleware' => 'feature_control:215', 'uses' => 'ApiProductVariantController@update']);
	Route::post('delete',['middleware' => 'feature_control:216', 'uses' => 'ApiProductVariantController@delete']);
	Route::post('assign',['middleware' => 'feature_control:215', 'uses' => 'ApiProductVariantController@assign']);
	Route::post('reorder',['middleware' => 'feature_control:215', 'uses' => 'ApiProductVariantController@reorder']);
	Route::get('tree',['middleware' => 'feature_control:212', 'uses' => 'ApiProductVariantController@tree']);
	Route::post('available-product',['middleware' => 'feature_control:212', 'uses' => 'ApiProductVariantController@availableProduct']);
});

Route::group(['middleware'=>['log_activities','auth:api', 'scopes:apps'],'prefix'=>'product-variant'],function(){
	Route::group(['prefix'=>'group'],function(){
		Route::post('tree',['uses' => 'ApiProductGroupController@tree']);
		Route::post('product',['uses' => 'ApiProductGroupController@product']);
		Route::post('search',['uses' => 'ApiProductGroupController@search']);
	});
});