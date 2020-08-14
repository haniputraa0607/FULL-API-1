<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToProduct extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_categories', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_discounts', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_groups', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_group_product_promo_categories', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_photos', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_prices', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_product_promo_categories', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_product_variants', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_promo_categories', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_tags', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_variants', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_modifiers', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_modifier_brands', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_modifier_prices', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_modifier_products', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('product_modifier_product_categories', function (Blueprint $table) {
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
        Schema::table('products', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_categories', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_discounts', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_groups', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_group_product_promo_categories', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_photos', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_prices', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_product_promo_categories', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_product_variants', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_promo_categories', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_tags', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_variants', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_modifiers', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_modifier_brands', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_modifier_prices', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_modifier_products', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('product_modifier_product_categories', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
