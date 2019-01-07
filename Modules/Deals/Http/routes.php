<?php
Route::group(['middleware' => 'auth:api', 'prefix' => 'api/deals', 'namespace' => 'Modules\Deals\Http\Controllers'], function()
{
    /* MASTER DEALS */
    // Route::any('list', 'ApiDeals@listDeal');
    Route::post('create', 'ApiDeals@createReq');
    Route::post('update', 'ApiDeals@updateReq');
    Route::post('delete', 'ApiDeals@deleteReq');
    Route::post('user', 'ApiDeals@listUserVoucher');
    Route::post('voucher', 'ApiDeals@listVoucher');

    /* DEAL VOUCHER */
    Route::group(['prefix' => 'voucher'], function()
    {
        Route::post('create', 'ApiDealsVoucher@createReq');
        Route::post('delete', 'ApiDealsVoucher@deleteReq'); 
    });

    /* CLAIM */
    Route::group(['prefix' => 'claim'], function()
    {
        Route::post('/', 'ApiDealsClaim@claim');    
        Route::post('paid', 'ApiDealsClaimPay@claim');    
        Route::post('pay-now', 'ApiDealsClaimPay@bayarSekarang');    
    });

    /* INVALIDATE */
    Route::group(['prefix' => 'invalidate'], function()
    {
        Route::post('/', 'ApiDealsInvalidate@invalidate');  
    });

    /* TRANSACTION */
    Route::group(['prefix' => 'transaction'], function()
    {
        Route::any('/', 'ApiDealsTransaction@listTrx');  
    });
    
    /* MANUAL PAYMENT */
    Route::group(['prefix' => 'manualpayment'], function()
    {
        Route::get('/{type}', 'ApiDealsPaymentManual@manualPaymentList');
        Route::post('/detail', 'ApiDealsPaymentManual@detailManualPaymentUnpay');
        Route::post('/confirm', 'ApiDealsPaymentManual@manualPaymentConfirm');
        Route::post('/filter/{type}', 'ApiDealsPaymentManual@transactionPaymentManualFilter');

    });
});

Route::group(['prefix' => 'api/deals', 'namespace' => 'Modules\Deals\Http\Controllers'], function()
{
    /* MASTER DEALS */
    Route::any('list', 'ApiDeals@listDeal');
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'api/voucher', 'namespace' => 'Modules\Deals\Http\Controllers'], function()
{
    Route::any('me', 'ApiDealsVoucher@myVoucher');
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'api/hidden-deals', 'namespace' => 'Modules\Deals\Http\Controllers'], function()
{
    /* MASTER DEALS */
    Route::post('create', 'ApiHiddenDeals@createReq');
	Route::post('create/autoassign', 'ApiHiddenDeals@autoAssign');

    
});


/* DEALS SUBSCRIPTION */
Route::group(['middleware' => 'auth:api', 'prefix' => 'api/deals-subscription', 'namespace' => 'Modules\Deals\Http\Controllers'], function()
{
    Route::post('create', 'ApiDealsSubscription@create');
    Route::post('update', 'ApiDealsSubscription@update');
    Route::get('delete/{id_deals}', 'ApiDealsSubscription@destroy');
});


/* WEBVIEW */
Route::group(['middleware' => 'api', 'prefix' => 'api/webview', 'namespace' => 'Modules\Deals\Http\Controllers'], function()
{
    /* deals detail */
    Route::get('/deals/{id_deals}/{deals_type}', 'ApiDealsWebview@dealsDetail');
    Route::get('/voucher/{id_deals_user}', 'ApiDealsWebview@voucherDetail');
});