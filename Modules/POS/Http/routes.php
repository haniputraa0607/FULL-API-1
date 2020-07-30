<?php
Route::group(['middleware' => 'web', 'prefix' => 'pos', 'namespace' => 'Modules\POS\Http\Controllers'], function()
{
    Route::get('/', 'ApiPOS@index');
});
Route::group(['prefix' => 'api/v1/pos/', 'namespace' => 'Modules\POS\Http\Controllers'], function()
{
    Route::group(['middleware' => ['auth_client','log_activities_pos', 'scopes:pos']], function() {
        Route::any('check/member', 'ApiPOS@checkMember');
        Route::any('check/voucher', 'ApiPOS@checkVoucher');
        Route::any('voucher/void', 'ApiPOS@voidVoucher');
        Route::post('outlet/sync', 'ApiPOS@syncOutlet');
        Route::post('outlet/sync/ovo', 'ApiPOS@syncOutletOvo');
        // Route::any('menu', 'ApiPOS@syncMenuReturn');
        Route::any('outlet/menu', 'ApiPOS@syncOutletMenu');
        Route::post('menu', 'ApiPOS@syncProduct');
        Route::post('add-on', 'ApiPOS@syncAddOn');
        Route::post('menu/sync/price', 'ApiPOS@syncProductPrice2');
        Route::post('add-on/sync/price', 'ApiPOS@syncAddOnPrice');
        Route::post('menu/sync/deactive', 'ApiPOS@syncProductDeactive');
        Route::post('add-on/sync/deactive', 'ApiPOS@syncAddOnDeactive');
        Route::any('transaction/refund', 'ApiPOS@transactionRefund');
        Route::any('transaction/detail', 'ApiPOS@transactionDetail');
    });
    Route::group(['middleware' => 'auth_client', 'scopes:pos'], function() {
        Route::post('transaction/last', 'ApiPOS@getLastTransaction');
        Route::post('order/detail/view', 'ApiOrder@detailWebviewPage');
    });
});
Route::group(['middleware' => ['auth_client','log_activities_pos', 'scopes:pos'], 'prefix' => 'api/v1/pos/', 'namespace' => 'Modules\Brand\Http\Controllers'], function()
{
    Route::post('brand', 'ApiSyncBrandController@syncBrand');
});
Route::group(['prefix' => 'api/v1/pos/', 'namespace' => 'Modules\POS\Http\Controllers'], function()
{
    Route::group(['middleware' => ['auth_client','log_activities_pos_transaction', 'scopes:pos']], function() {
        Route::any('transaction', 'ApiPOS@transaction');
    });
});
Route::group(['prefix' => 'api/v1/pos/', 'namespace' => 'Modules\POS\Http\Controllers'], function()
{
    Route::group(['middleware' => ['auth_client','log_activities_pos', 'scopes:pos']], function() {
        Route::any('/order', 'ApiOrder@listOrder');
        Route::post('order/detail', 'ApiOrder@detailOrder');
        Route::post('order/accept', 'ApiOrder@acceptOrder');
        Route::post('order/ready', 'ApiOrder@setReady');
        Route::post('order/taken', 'ApiOrder@takenOrder');
        Route::post('order/reject', 'ApiOrder@rejectOrder');
        Route::get('profile', 'ApiOrder@profile');
        Route::get('product', 'ApiOrder@listProduct');
        Route::post('product/sold-out', 'ApiOrder@productSoldOut');
    });
});
Route::group(['prefix' => 'api/quinos', 'namespace' => 'Modules\POS\Http\Controllers'], function()
{
    Route::group(['middleware' => ['auth:quinos']], function() {
        Route::any('log', 'ApiQuinos@log');
        Route::get('log/detail/{id}', 'ApiQuinos@detailLog');
    });
    Route::group(['middleware' => ['auth_client', 'scopes:pos']], function() {
        Route::post('user/new', 'ApiQuinos@createQuinosUser');
        Route::post('user/update', 'ApiQuinos@updateQuinosUser');
    });
});