<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangePriceCashToDecimals extends Migration
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
                CHANGE COLUMN `deals_voucher_price_cash` `deals_voucher_price_cash` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `deals_users` 
                CHANGE COLUMN `voucher_price_cash` `voucher_price_cash` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `product_discounts` 
                CHANGE COLUMN `discount_nominal` `discount_nominal` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `product_prices` 
                CHANGE COLUMN `product_price` `product_price` DOUBLE(10,2) UNSIGNED NULL DEFAULT NULL ,
                CHANGE COLUMN `product_price_periode` `product_price_periode` DOUBLE(10,2) NULL DEFAULT NULL ;
            ALTER TABLE `transaction_balances` 
                CHANGE COLUMN `nominal` `nominal` DOUBLE(10,2) NOT NULL ;
            ALTER TABLE `transaction_product_modifiers` 
                CHANGE COLUMN `transaction_product_modifier_price` `transaction_product_modifier_price` DOUBLE(10,2) UNSIGNED NOT NULL ;
            ALTER TABLE `transaction_products` 
                CHANGE COLUMN `transaction_modifier_subtotal` `transaction_modifier_subtotal` DOUBLE(10,2) UNSIGNED NOT NULL ,
                CHANGE COLUMN `transaction_product_subtotal` `transaction_product_subtotal` DOUBLE(10,2) NOT NULL ;
            ALTER TABLE `transactions` 
                CHANGE COLUMN `transaction_subtotal` `transaction_subtotal` DOUBLE(10,2) NOT NULL ,
                CHANGE COLUMN `transaction_shipment` `transaction_shipment` DOUBLE(10,2) NOT NULL ,
                CHANGE COLUMN `transaction_shipment_go_send` `transaction_shipment_go_send` DOUBLE(10,2) NULL DEFAULT NULL ,
                CHANGE COLUMN `transaction_service` `transaction_service` DOUBLE(10,2) NOT NULL ,
                CHANGE COLUMN `transaction_discount` `transaction_discount` DOUBLE(10,2) NOT NULL ,
                CHANGE COLUMN `transaction_tax` `transaction_tax` DOUBLE(10,2) NOT NULL ,
                CHANGE COLUMN `transaction_grandtotal` `transaction_grandtotal` DOUBLE(10,2) NOT NULL ;
            ALTER TABLE `log_balances` 
                CHANGE COLUMN `grand_total` `grand_total` DOUBLE(10,2) NOT NULL DEFAULT 0 ;
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
                CHANGE COLUMN `deals_voucher_price_cash` `deals_voucher_price_cash` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `deals_users` 
                CHANGE COLUMN `voucher_price_cash` `voucher_price_cash` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `product_discounts` 
                CHANGE COLUMN `discount_nominal` `discount_nominal` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `product_prices` 
                CHANGE COLUMN `product_price` `product_price` INT(11) UNSIGNED NULL DEFAULT NULL ,
                CHANGE COLUMN `product_price_periode` `product_price_periode` INT(11) NULL DEFAULT NULL ;
            ALTER TABLE `transaction_balances` 
                CHANGE COLUMN `nominal` `nominal` INT(11) NOT NULL ;
            ALTER TABLE `transaction_product_modifiers` 
                CHANGE COLUMN `transaction_product_modifier_price` `transaction_product_modifier_price` INT(11) UNSIGNED NOT NULL ;
            ALTER TABLE `transaction_products` 
                CHANGE COLUMN `transaction_modifier_subtotal` `transaction_modifier_subtotal` INT(11) UNSIGNED NOT NULL ,
                CHANGE COLUMN `transaction_product_subtotal` `transaction_product_subtotal` INT(11) NOT NULL ;
            ALTER TABLE `transactions` 
                CHANGE COLUMN `transaction_subtotal` `transaction_subtotal` INT(11) NOT NULL ,
                CHANGE COLUMN `transaction_shipment` `transaction_shipment` INT(11) NOT NULL ,
                CHANGE COLUMN `transaction_shipment_go_send` `transaction_shipment_go_send` INT(11) NULL DEFAULT NULL ,
                CHANGE COLUMN `transaction_service` `transaction_service` INT(11) NOT NULL ,
                CHANGE COLUMN `transaction_discount` `transaction_discount` INT(11) NOT NULL ,
                CHANGE COLUMN `transaction_tax` `transaction_tax` INT(11) NOT NULL ,
                CHANGE COLUMN `transaction_grandtotal` `transaction_grandtotal` INT(11) NOT NULL ;
        ');
    }
}
