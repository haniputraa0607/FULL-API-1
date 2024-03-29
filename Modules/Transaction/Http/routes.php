<?php
Route::group(['middleware' => ['auth:api'],'prefix' => 'api/transaction', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {
    Route::any('available-payment', 'ApiOnlineTransaction@availablePayment');
    Route::any('available-payment/update', 'ApiOnlineTransaction@availablePaymentUpdate')->middleware('scopes:be');
    Route::any('available-shipment', 'ApiOnlineTransaction@availableShipment');
    Route::any('available-shipment/update', 'ApiOnlineTransaction@availableShipmentUpdate')->middleware('scopes:be');
});

Route::any('api/transaction/update-gosend', 'Modules\Transaction\Http\Controllers\ApiGosendController@updateStatus');
Route::group(['middleware' => ['auth:api', 'log_activities', 'user_agent', 'scopes:be'], 'prefix' => 'api/transaction', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {
    Route::post('/outlet', 'ApiNotification@adminOutlet');
    Route::post('/admin/confirm', 'ApiNotification@adminOutletConfirm');
    Route::get('setting/cashback', 'ApiSettingCashbackController@list');
    Route::post('setting/cashback/update', 'ApiSettingCashbackController@update');
    Route::post('/dump', 'ApiDumpController@dumpData');
    Route::get('rule', 'ApiTransaction@transactionRule');
    Route::post('rule/update', 'ApiTransaction@transactionRuleUpdate');

    Route::get('/courier', 'ApiTransaction@internalCourier');
    Route::post('/point', 'ApiTransaction@pointUser');
    Route::post('/point/filter', 'ApiTransaction@pointUserFilter');
    Route::post('/balance', 'ApiTransaction@balanceUser');
    Route::post('/balance/filter', 'ApiTransaction@balanceUserFilter');
    Route::post('/admin', 'ApiNotification@adminOutletNotification');
    Route::post('/setting', 'ApiSettingTransaction@settingTrx');
    Route::any('be/filter', 'ApiTransaction@transactionFilter');
    Route::any('/setting/timer-ovo', 'ApiSettingTransaction@settingTimerOvo');

    Route::group(['prefix' => 'manualpayment'], function () {
        Route::get('/bank', ['middleware' => 'feature_control:64', 'uses' => 'ApiTransactionPaymentManual@bankList']);
        Route::post('/bank/delete', ['middleware' => 'feature_control:68', 'uses' => 'ApiTransactionPaymentManual@bankDelete']);
        Route::post('/bank/create', ['middleware' => 'feature_control:66', 'uses' => 'ApiTransactionPaymentManual@bankCreate']);
        Route::get('/bankmethod', ['middleware' => 'feature_control:64', 'uses' => 'ApiTransactionPaymentManual@bankmethodList']);
        Route::post('/bankmethod/delete', ['middleware' => 'feature_control:68', 'uses' => 'ApiTransactionPaymentManual@bankmethodDelete']);
        Route::post('/bankmethod/create', ['middleware' => '66', 'uses' => 'ApiTransactionPaymentManual@bankmethodCreate']);
        Route::get('/list', ['middleware' => '64', 'uses' => 'ApiTransaction@manualPaymentList']);
        Route::post('/edit', ['middleware' => '67', 'uses' => 'ApiTransaction@manualPaymentEdit']);
        Route::post('/update', ['middleware' => '67', 'uses' => 'ApiTransaction@manualPaymentUpdate']);
        Route::post('/create', ['middleware' => '66', 'uses' => 'ApiTransaction@manualPaymentCreate']);
        Route::post('/detail', ['middleware' => '65', 'uses' => 'ApiTransaction@manualPaymentDetail']);
        Route::post('/delete', ['middleware' => '68', 'uses' => 'ApiTransaction@manualPaymentDelete']);

        Route::group(['prefix' => 'data'], function () {
            Route::get('/{type}', ['middleware' => '64', 'uses' => 'ApiTransactionPaymentManual@manualPaymentList']);
            Route::post('/detail', ['middleware' => '65', 'uses' => 'ApiTransactionPaymentManual@detailManualPaymentUnpay']);
            Route::post('/confirm', 'ApiTransactionPaymentManual@manualPaymentConfirm');
            Route::post('/filter/{type}', 'ApiTransactionPaymentManual@transactionPaymentManualFilter');
        });

        Route::post('/method/save', ['middleware' => '67', 'uses' => 'ApiTransaction@manualPaymentMethod']);
        Route::post('/method/delete', ['middleware' => '68', 'uses' => 'ApiTransaction@manualPaymentMethodDelete']);
    });
    Route::post('/be/new', 'ApiOnlineTransaction@newTransaction');
    Route::post('be/detail', 'ApiTransaction@transactionDetail');
    Route::post('be/delivery-rejected', 'ApiTransaction@transactionDeliveryRejected');
    Route::get('be/{key}', 'ApiTransaction@transactionList');
    Route::post('be/detail/webview/{mode?}', 'ApiWebviewController@webview');

    Route::post('failed-void-payment', 'ApiManualRefundController@listFailedVoidPayment');
    Route::post('failed-void-payment/confirm', 'ApiManualRefundController@confirmManualRefund');

    Route::post('retry-void-payment', 'TransactionVoidFailedController@index');
    Route::post('retry-void-payment/retry', 'TransactionVoidFailedController@retry');

    /*[POS] Transaction online failed*/
    Route::any('online-pos', 'ApiTransactionOnlinePos@listTransaction');
    Route::post('online-pos/resend', 'ApiTransactionOnlinePos@resendTransaction');
    Route::any('online-pos/autoresponse', 'ApiTransactionOnlinePos@autoresponse');

    /*[POS] Cancel Transaction online failed*/
    Route::any('cancel-online-pos', 'ApiTransactionOnlinePos@listCancelTransaction');
    Route::post('cancel-online-pos/resend', 'ApiTransactionOnlinePos@resendCancelTransaction');
    Route::any('cancel-online-pos/autoresponse', 'ApiTransactionOnlinePos@autoresponseCancel');
});

Route::group(['middleware' => ['auth:api', 'log_activities', 'scopes:apps'], 'prefix' => 'api/transaction', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {

    Route::get('/', 'ApiTransaction@transactionList');
    Route::any('/filter', 'ApiTransaction@transactionFilter');
    Route::post('/detail', 'ApiTransaction@transactionDetail');
    Route::post('/item', 'ApiTransaction@transactionDetailTrx');
    Route::post('/point/detail', 'ApiTransaction@transactionPointDetail');
    Route::post('/balance/detail', 'ApiTransaction@transactionBalanceDetail');
    Route::get('/list-no-driver', 'ApiTransaction@listNoDriver');

    // Route::post('history', 'ApiHistoryController@historyAll');
    Route::post('history-trx/{mode?}', 'ApiHistoryController@historyTrx');
    Route::post('history-ongoing/{mode?}', 'ApiHistoryController@historyTrxOnGoing');
    // Route::post('history-point', 'ApiHistoryController@historyPoint');
    Route::post('history-balance/{mode?}', 'ApiHistoryController@historyBalance');

    Route::post('/shipping', 'ApiTransaction@getShippingFee');
    Route::any('/address', 'ApiTransaction@getAddress');
    Route::post('/address/nearby', 'ApiTransaction@getNearbyAddress');
    Route::post('/address/recently', 'ApiTransaction@getRecentlyAddress');
    Route::post('/address/default', 'ApiTransaction@getDefaultAddress');
    Route::post('/address/detail', 'ApiTransaction@detailAddress');
    Route::post('/address/add', 'ApiTransaction@addAddress');
    Route::post('/address/update', 'ApiTransaction@updateAddress');
    Route::post('/address/delete', 'ApiTransaction@deleteAddress');
    Route::post('/void', 'ApiTransaction@transactionVoid');

    Route::post('/check', 'ApiOnlineTransaction@checkTransaction');
    Route::post('/new', 'ApiOnlineTransaction@newTransaction');
    Route::post('/confirm', 'ApiConfirm@confirmTransaction');
    Route::post('/cancel', 'ApiOnlineTransaction@cancelTransaction');
    Route::post('/book-delivery', 'ApiOnlineTransaction@bookDelivery');
    Route::post('/prod/confirm', 'ApiTransactionProductionController@confirmTransaction2');
    Route::get('/{key}', 'ApiTransaction@transactionList');
});

Route::group(['middleware' => ['auth_client', 'user_agent'], 'prefix' => 'api/transaction', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {
    Route::post('/province', 'ApiTransaction@getProvince');
    Route::post('/city', 'ApiTransaction@getCity');
    Route::post('/subdistrict', 'ApiTransaction@getSubdistrict');
    Route::post('/courier', 'ApiTransaction@getCourier');
    Route::any('/grand-total', 'ApiSettingTransactionV2@grandTotal');

    Route::post('/new-transaction', 'ApiTransaction@transaction');

    Route::post('/shipping/gosend', 'ApiTransaction@shippingCostGoSend');
});

Route::group(['prefix' => 'api/transaction', 'middleware' => ['log_activities', 'user_agent'], 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {
    Route::any('/finish', 'ApiTransaction@transactionFinish');
    // Route::any('/cancel', 'ApiTransaction@transactionCancel');
    Route::any('/error', 'ApiTransaction@transactionError');
    Route::any('/notif', 'ApiNotification@logReceiveNotification');
});

Route::group(['prefix' => 'api/transaction', 'middleware' => ['log_activities', 'auth:api', 'scopes:apps'], 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {

    Route::post('/detail/webview/point', 'ApiWebviewController@webviewPoint');
    Route::post('/detail/webview/balance', 'ApiWebviewController@webviewBalance');
    Route::post('/detail/webview/{mode?}', 'ApiWebviewController@webview');
    Route::post('/detail/webview/success', 'ApiWebviewController@trxSuccess');
});

Route::group(['middleware' => ['auth:api', 'user_agent', 'scopes:apps'], 'prefix' => 'api/transaction', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {

    Route::post('/gen', 'ApiDumpController@generateNumber');
});

Route::group(['middleware' => ['auth_client', 'user_agent'], 'prefix' => 'api/manual-payment', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {
    Route::get('/', 'ApiTransactionPaymentManual@listPaymentMethod');
    Route::get('/list', 'ApiTransactionPaymentManual@list');
    Route::post('/method', 'ApiTransactionPaymentManual@paymentMethod');
});


Route::group(['middleware' => ['auth:api', 'log_activities','feature_control:227', 'scopes:be'],'prefix' => 'api/transaction/void', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {
    Route::any('ovo', 'ApiOvoReversal@void');
});

Route::group(['prefix' => 'api/transaction', 'namespace' => 'Modules\Transaction\Http\Controllers'], function () {
    Route::get('/data/decript/{data}', function ($data) {
        return response()->json(App\Lib\MyHelper::decrypt2019($data));
    });
    Route::get('/data/encrypt/{data}', function ($data) {
        // return response()->json(App\Lib\MyHelper::decrypt2019($data));

        return response()->json(App\Lib\MyHelper::encrypt2019($data));
    });
});

Route::group(['prefix' => 'api/transaction', 'namespace' => 'Modules\Transaction\Http\Controllers'], function() {
    Route::any('callback/cimb', 'ApiTransactionCIMB@callback');
    Route::any('callback/cimb/deals', 'ApiTransactionCIMB@callbackDeals');
    Route::any('curl_cimb', 'ApiTransactionCIMB@curlCimb');
});

Route::group(['prefix' => 'api/transaction', 'middleware' => ['log_activities', 'auth:api', 'scopes:apps'], 'namespace' => 'Modules\Transaction\Http\Controllers'], function() {
    Route::any('/web/view/detail', 'ApiWebviewController@detail');
    Route::any('/web/view/detail/check', 'ApiWebviewController@check');
    Route::any('/web/view/detail/point', 'ApiWebviewController@detailPoint');
    Route::any('/web/view/detail/balance', 'ApiWebviewController@detailBalance');
    Route::any('/web/view/trx', 'ApiWebviewController@success');
    Route::any('/web/view/trx/{receipt}', 'ApiWebviewController@detailTrx');
    Route::any('/web/view/outletapp', 'ApiWebviewController@receiptOutletapp');
});

Route::group(['prefix'=>'api/nobu','namespace' => 'Modules\Transaction\Http\Controllers'],function(){
	Route::post('notif', 'ApiNobuController@notifNobu')->name('notif_nobu');
});