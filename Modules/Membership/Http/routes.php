<?php
Route::group(['middleware' => 'auth:api', 'prefix' => 'api/membership', 'namespace' => 'Modules\Membership\Http\Controllers'], function()
{
    Route::post('list', 'ApiMembership@listMembership');
	Route::post('create', 'ApiMembership@create');
    Route::post('update', 'ApiMembership@update');
    Route::post('delete', 'ApiMembership@delete');
});