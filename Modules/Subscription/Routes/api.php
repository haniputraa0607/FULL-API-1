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
Route::group(['middleware' => ['auth:api', 'log_activities', 'user_agent', 'scopes:*'], 'prefix' => 'subscription'], function () {

    /* MASTER SUBSCRIPTION */
    Route::any('list', 'ApiSubscription@listSubscription');
    Route::any('detail', 'ApiSubscriptionWebview@subscriptionDetail');
    Route::any('me', 'ApiSubscription@mySubscription');

    /* CLAIM */
    Route::group(['prefix' => 'claim'], function () {
        Route::post('/', 'ApiSubscriptionClaim@claim');
        Route::post('paid', 'ApiSubscriptionClaimPay@claim');
        Route::post('pay-now', 'ApiSubscriptionClaimPay@bayarSekarang');
    });
});
/* Webview */
Route::group(['middleware' => ['web', 'user_agent'], 'prefix' => 'webview'], function () {
    Route::any('subscription/{id_subscription}', 'ApiSubscriptionWebview@webviewSubscriptionDetail');
    Route::any('mysubscription/{id_subscription_user}', 'ApiSubscriptionWebview@mySubscription');
    Route::any('subscription/success/{id_subscription_user}', 'ApiSubscriptionWebview@subscriptionSuccess');
});