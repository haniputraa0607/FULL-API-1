<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        /**
         * sending the campaign schedule
         * run every 5 minute
         */
        $schedule->call('Modules\Campaign\Http\Controllers\ApiCampaign@insertQueue')->everyFiveMinutes();

        /**
         * insert the promotion data that must be sent to the promotion_queue table
         * run every 5 minute
         */
        $schedule->call('Modules\Promotion\Http\Controllers\ApiPromotion@addPromotionQueue')->everyFiveMinutes();

        /**
         * send 100 data from the promotion_queue table
         * run every 6 minute
         */
        $schedule->call('Modules\Promotion\Http\Controllers\ApiPromotion@sendPromotion')->cron('*/6 * * * *');

        /**
         * reset all member points / balance
         * run every day at 01:00
         */
        $schedule->call('Modules\Setting\Http\Controllers\ApiSetting@cronPointReset')->dailyAt('01:00');

        /**
         * detect transaction fraud and member balance by comparing the encryption of each data in the log_balances table
         * run every day at 02:00
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiCronTrxController@checkSchedule')->dailyAt('02:00');

        /**
         * cancel all pending transaction that have been more than 5 minutes
         * run every 2 minutes
         */
        // $schedule->call('Modules\Transaction\Http\Controllers\ApiCronTrxController@cron')->cron('*/2 * * * *');

        /**
         * cancel all pending deals that have been more than 5 minutes
         * run every 2 minutes
         */
        $schedule->call('Modules\Deals\Http\Controllers\ApiCronDealsController@cancel')->cron('*/2 * * * *');

        /**
         * update all pickup transaction that have been more than 1 x 24 hours
         * run every day at 00:01
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiCronTrxController@completeTransactionPickup')->dailyAt('00:01');

        /**
         * To process injection point
         * run every hour
         */
        $schedule->call('Modules\PointInjection\Http\Controllers\ApiPointInjectionController@getPointInjection')->hourly();

        /**
         * To process transaction sync from POS
         * Run every 2 minutes
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiTransactionSync@transaction')->cron('*/2 * * * *');

        /**
         * To process transaction refund from POS
         * Run every 5 minutes
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiTransactionSync@transactionRefund')->cron('*/5 * * * *');

        /**
         * To process sync menu outlets from the POS
         * Run every 3 minutes
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiPOS@syncOutletMenuCron')->cron('*/3 * * * *');

        /**
         * To process sync menu price for priority outlet from the POS
         * Run every day at 00:01
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiPOS@cronProductPricePriority')->dailyAt('00:01');

        /**
         * To process sync menu price from the POS
         * Run every day at 00:05
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiPOS@cronProductPrice')->dailyAt('00:05');

        /**
         * To delete temporary product price data
         * Run every day at 01:30
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiPOS@cronResetProductPriceTemp')->dailyAt('01:30');

        /**
         * To process sync add on price from the POS
         * Run every day at 00:15
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiPOS@cronAddOnPrice')->dailyAt('00:15');

        /**
         * To make daily transaction reports (offline and online transactions)
         * Run every day at 03:00
         */
        $schedule->call('Modules\Report\Http\Controllers\ApiCronReport@transactionCron')->dailyAt('03:00');

        /**
         * To enter an ovo transaction that needs to be reversed
         * Run every day 9 minute
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiOvoReversal@insertReversal')->cron('*/9 * * * *');

        /**
         * To process the Ovo reversal queue
         * Run every 10 minute
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiOvoReversal@processReversal')->cron('*/10 * * * *');
        /**
         * To process fraud
         */

        /**
         * To enter an ovo transaction that needs to be reversed
         * Run every day 9 minute
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiOvoReversal@insertReversalDeals')->cron('*/9 * * * *');

        /**
         * To process the Ovo reversal queue
         * Run every 10 minute
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiOvoReversal@processReversalDeals')->cron('*/10 * * * *');
        /**
         * To process fraud
         */

        $schedule->call('Modules\SettingFraud\Http\Controllers\ApiFraud@fraudCron')->dailyAt('02:00');

        /**
         * reset notify outlet flag
         * run every day at 01:00
         */
        $schedule->call('Modules\Outlet\Http\Controllers\ApiOutletController@resetNotify')->dailyAt('00:30');

        /**
         * Void failed transaction shopeepay
         */
        $schedule->call('Modules\ShopeePay\Http\Controllers\ShopeePayController@cronCancel')->cron('*/1 * * * *');
        /**
         * Void failed transaction shopeepay
         */
        $schedule->call('Modules\ShopeePay\Http\Controllers\ShopeePayController@cronCancelDeals')->cron('*/1 * * * *');

        /**
         * process refund shopeepay at 06:00
         */
        $schedule->call('Modules\ShopeePay\Http\Controllers\ShopeePayController@cronRefund')->dailyAt('03:05');

        /**
         * Send Email Failed Send to POS,
         * Run every 7 minutes
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiTransactionOnlinePOS@sendEmail')->cron('*/7 * * * *');

        /**
         * Send Cancel Email Failed Send to POS,
         * Run every 7 minutes
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiTransactionOnlinePOS@sendEmailCancel')->cron('*/7 * * * *');

        /**
         * Check the status of Gosend which is not updated after 5 minutes
         * run every 3 minutes
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiGosendController@cronCheckStatus')->cron('*/3 * * * *');

        /**
         * To cancel no driver transaction
         * run every minute
         */
        $schedule->call('Modules\Transaction\Http\Controllers\ApiCronTrxController@cronCancelDriverNotFound')->everyMinute();

        /**
         * To reset send to pos status
         * run every 5 minutes
         */
        $schedule->call('Modules\POS\Http\Controllers\ApiPOS@cronResetStatus')->everyFiveMinutes();

        /**
         * To retry vailed refund
         * run at specific timeframe
         */
        foreach (['03:05', '11:00', '19:00'] as $time) {
            $schedule->call('Modules\Transaction\Http\Controllers\TransactionVoidFailedController@cronRetry')->dailyAt($time);
        }

        /**
         * To send notification expiry point
         * run every hour
         */
        $schedule->call('Modules\Balance\Http\Controllers\ApiExpiryPoint@sendNotificationExpiryPoint')->hourly();

        /**
         * To adjustment point
         * run every hour
         */
        $schedule->call('Modules\Balance\Http\Controllers\ApiExpiryPoint@adjustmentPointUser')->hourly();

        /**
         * cancel all pending transaction nobu that have been more than 5 minutes
         * run every 1 minutes
         */
        // $schedule->call('Modules\Transaction\Http\Controllers\ApiNobuController@cronCheck')->cron('*/1 * * * *');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
