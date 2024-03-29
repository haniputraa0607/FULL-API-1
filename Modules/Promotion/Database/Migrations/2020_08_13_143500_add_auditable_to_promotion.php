<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToPromotion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotions', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('promotion_contents', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('promotion_content_shorten_links', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('promotion_rules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('promotion_rule_parents', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('promotion_schedules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_templates', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_buyxgety_product_requirements', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_buyxgety_rules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_contents', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_content_details', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_product_discounts', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_product_discount_rules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_tier_discount_products', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('deals_promotion_tier_discount_rules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotions', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promotion_contents', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promotion_content_shorten_links', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promotion_rules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promotion_rule_parents', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promotion_schedules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_templates', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_buyxgety_product_requirements', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_buyxgety_rules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_contents', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_content_details', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_product_discounts', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_product_discount_rules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_tier_discount_products', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('deals_promotion_tier_discount_rules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    }
}
