<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToDealsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deals', function (Blueprint $table) {
        	$table->auditable();
        });

        Schema::table('deals_contents', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_buyxgety_product_requirements', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_buyxgety_rules', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_content_details', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_outlets', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_product_discounts', function (Blueprint $table) {
        	$table->auditable();
        });
        
        Schema::table('deals_product_discount_rules', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_subscriptions', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_tier_discount_products', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_tier_discount_rules', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_total', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('featured_deals', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('deals_vouchers', function (Blueprint $table) {
        	$table->auditable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deals', function (Blueprint $table) {
        	$table->dropAuditable();
        });

        Schema::table('deals_contents', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_buyxgety_product_requirements', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_buyxgety_rules', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_content_details', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_outlets', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_product_discounts', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_product_discount_rules', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_subscriptions', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('deals_tier_discount_products', function (Blueprint $table) {
        	$table->dropAuditable();
        });
        
        Schema::table('deals_tier_discount_rules', function (Blueprint $table) {
        	$table->dropAuditable();
        });
        
        Schema::table('deals_total', function (Blueprint $table) {
        	$table->dropAuditable();
        });
        
        Schema::table('featured_deals', function (Blueprint $table) {
        	$table->dropAuditable();
        });
        
        Schema::table('deals_vouchers', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
