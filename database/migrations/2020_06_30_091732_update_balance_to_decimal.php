<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBalanceToDecimal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('
            ALTER TABLE `deals` 
                CHANGE COLUMN `deals_voucher_price_point` `deals_voucher_price_point` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `deals_users` 
                CHANGE COLUMN `voucher_price_point` `voucher_price_point` DOUBLE(10,2) NULL DEFAULT NULL , 
                CHANGE COLUMN `balance_nominal` `balance_nominal` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `log_balances` 
                CHANGE COLUMN `balance` `balance` DOUBLE(10,2) NOT NULL DEFAULT 0 ,
                CHANGE COLUMN `balance_before` `balance_before` DOUBLE(10,2) NULL DEFAULT NULL ,
                CHANGE COLUMN `balance_after` `balance_after` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `subscriptions` 
                CHANGE COLUMN `subscription_price_point` `subscription_price_point` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `subscription_users` 
                CHANGE COLUMN `subscription_price_point` `subscription_price_point` DOUBLE(10,2) NULL DEFAULT NULL ,
                CHANGE COLUMN `balance_nominal` `balance_nominal` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `transaction_payment_balances` 
                CHANGE COLUMN `balance_nominal` `balance_nominal` DOUBLE(10,2) NOT NULL ;
            ALTER TABLE `transactions` 
                CHANGE COLUMN `transaction_cashback_earned` `transaction_cashback_earned` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `users` 
                CHANGE COLUMN `balance` `balance` DOUBLE(10,2) NOT NULL DEFAULT 0 ;
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('
            ALTER TABLE `deals` 
                CHANGE COLUMN `deals_voucher_price_point` `deals_voucher_price_point` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `deals_users` 
                CHANGE COLUMN `voucher_price_point` `voucher_price_point` INT(11) NULL DEFAULT NULL , 
                CHANGE COLUMN `balance_nominal` `balance_nominal` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `log_balances` 
                CHANGE COLUMN `balance` `balance` INT(11) NOT NULL DEFAULT 0 ,
                CHANGE COLUMN `balance_before` `balance_before` INT(11) NULL DEFAULT NULL ,
                CHANGE COLUMN `balance_after` `balance_after` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `subscriptions` 
                CHANGE COLUMN `subscription_price_point` `subscription_price_point` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `subscription_users` 
                CHANGE COLUMN `subscription_price_point` `subscription_price_point` INT(11) NULL DEFAULT NULL ,
                CHANGE COLUMN `balance_nominal` `balance_nominal` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `transaction_payment_balances` 
                CHANGE COLUMN `balance_nominal` `balance_nominal` VARCHAR(191) NOT NULL ;
            ALTER TABLE `transactions` 
                CHANGE COLUMN `transaction_cashback_earned` `transaction_cashback_earned` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `users` 
                CHANGE COLUMN `balance` `balance` INT(11) NOT NULL DEFAULT 0 ;
        ');
    }
}
