<?php

Route::group(['middleware' => 'api', 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function()
{
    Route::get('/courier', 'ApiSetting@settingCourier');
});

Route::group(['middleware' => 'api', 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function()
{
    Route::any('email/update', 'ApiSetting@emailUpdate');
    Route::any('/', 'ApiSetting@settingList');
    Route::post('/edit', 'ApiSetting@settingEdit');
    Route::post('/update', 'ApiSetting@settingUpdate');
    Route::post('{type}/update', 'ApiSetting@pointResetUpdate');
    Route::post('/date', 'ApiSetting@date');
	Route::any('/app_logo', 'ApiSetting@appLogo');
    Route::any('/app_navbar', 'ApiSetting@appNavbar');
    Route::any('/app_sidebar', 'ApiSetting@appSidebar');
    Route::get('/level', 'ApiSetting@levelList');
    Route::post('/level/create', 'ApiSetting@levelCreate');
    Route::post('/level/edit', 'ApiSetting@levelEdit');
    Route::post('/level/update', 'ApiSetting@levelUpdate');
    Route::any('/level/delete', 'ApiSetting@levelDelete');

    Route::get('/holiday', 'ApiSetting@holidayList');
    Route::post('/holiday/create', 'ApiSetting@holidayCreate');
    Route::post('/holiday/store', 'ApiSetting@holidayStore');
    Route::post('/holiday/edit', 'ApiSetting@holidayEdit');
    Route::post('/holiday/update', 'ApiSetting@holidayUpdate');
    Route::any('/holiday/delete', 'ApiSetting@holidayDelete');
    Route::any('/holiday/detail', 'ApiSetting@holidayDetail');

    Route::post('/faq/create', 'ApiSetting@faqCreate');
    Route::post('/faq/edit', 'ApiSetting@faqEdit');
    Route::post('/faq/update', 'ApiSetting@faqUpdate');
    Route::post('/faq/delete', 'ApiSetting@faqDelete');
    Route::get('/webview/faq', 'ApiSetting@faqWebview');

    Route::post('email', 'ApiSetting@settingEmail');
    Route::get('email', 'ApiSetting@getSettingEmail');

    Route::any('whatsapp', 'ApiSetting@settingWhatsApp');

    Route::group(['middleware' => 'auth:api', 'prefix' => 'dashboard'], function()
    {
        Route::any('', 'ApiDashboardSetting@getDashboard');
        Route::get('list', 'ApiDashboardSetting@getListDashboard');
        Route::post('update', 'ApiDashboardSetting@updateDashboard');
        Route::post('delete', 'ApiDashboardSetting@deleteDashboard');
        Route::post('update/date-range', 'ApiDashboardSetting@updateDateRange');
        Route::post('order-section', 'ApiDashboardSetting@updateOrderSection');
        Route::post('order-card', 'ApiDashboardSetting@updateOrderCard');
    });

    // banner
    Route::group(['middleware' => 'auth:api', 'prefix' => 'banner'], function()
    {
        Route::get('list', 'ApiBanner@index');
        Route::post('create', 'ApiBanner@create');
        Route::post('update', 'ApiBanner@update');
        Route::post('reorder', 'ApiBanner@reorder');
        Route::post('delete', 'ApiBanner@destroy');
    });

    // complete profile
    Route::group(['middleware' => 'auth:api', 'prefix' => 'complete-profile'], function()
    {
        Route::get('/', 'ApiSetting@getCompleteProfile');
        Route::post('/', 'ApiSetting@completeProfile');
    });

});

Route::group(['middleware' => 'api', 'prefix' => 'api/timesetting', 'namespace' => 'Modules\Setting\Http\Controllers'], function()
{
	Route::get('/', 'ApiGreetings@listTimeSetting');
	Route::post('/', 'ApiGreetings@updateTimeSetting');
});

Route::group(['middleware' => 'api', 'prefix' => 'api/background', 'namespace' => 'Modules\Setting\Http\Controllers'], function()
{
	Route::any('/', 'ApiBackground@listBackground');
	Route::post('create', 'ApiBackground@createBackground');
	Route::post('delete', 'ApiBackground@deleteBackground');
});

Route::group(['middleware' => 'api', 'prefix' => 'api/greetings', 'namespace' => 'Modules\Setting\Http\Controllers'], function()
{
	Route::any('/', 'ApiGreetings@listGreetings');
	Route::post('selected', 'ApiGreetings@selectGreetings');
	Route::post('create', 'ApiGreetings@createGreetings');
	Route::post('update', 'ApiGreetings@updateGreetings');
	Route::post('delete', 'ApiGreetings@deleteGreetings');
});

Route::group(['middleware' => 'auth_client', 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function()
{
    Route::any('/', 'ApiSetting@settingList');
    Route::get('/faq', 'ApiSetting@faqList');
    Route::any('/default_home', 'ApiSetting@homeNotLogin');
	Route::get('/navigation', 'ApiSetting@Navigation');
	Route::get('/navigation-logo', 'ApiSetting@NavigationLogo');
	Route::get('/navigation-sidebar', 'ApiSetting@NavigationSidebar');
	Route::get('/navigation-navbar', 'ApiSetting@NavigationNavbar');
});

Route::group(['prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function()
{
    Route::get('/faq', 'ApiSetting@faqList');
    Route::post('webview', 'ApiSetting@settingWebview');
    
    Route::get('/cron/point-reset', 'ApiSetting@cronPointReset');
});