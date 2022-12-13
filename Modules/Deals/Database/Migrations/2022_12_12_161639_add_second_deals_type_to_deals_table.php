<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSecondDealsTypeToDealsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('deals_productcategory_category_requirements', function (Blueprint $table) {
            $table->increments('id_deals_productcategory_category_requirement');
            $table->unsignedInteger('id_deals');
            $table->enum('product_type', ['single', 'group']);
            $table->unsignedInteger('id_product_variant')->nullable();
            $table->tinyInteger('auto_apply')->default(0);
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
            $table->timestamps();
        });

        Schema::create('deals_productcategory_rules', function (Blueprint $table) {
            $table->increments('id_deals_productcategory_rules');
            $table->unsignedInteger('id_deals');
            $table->integer('min_qty_requirement');
            $table->integer('benefit_qty');
            $table->enum('discount_type', ['percent', 'nominal']);
            $table->integer('discount_value');
            $table->integer('max_percent_discount')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
            $table->timestamps();
        });

        Schema::create('category_deals_productcategory_category_requirements', function (Blueprint $table) {
            $table->increments('id_category_deals_productcategory_category_requirement');
            $table->unsignedInteger('id_deals_productcategory_category_requirement');
            $table->integer('id_product_category');
            $table->timestamps();
        });

        \DB::statement("ALTER TABLE `deals` CHANGE COLUMN `deals_type` `deals_type` ENUM('Deals', 'Hidden', 'Point', 'Spin', 'Promotion', 'WelcomeVoucher', 'Second Deals') NOT NULL default('Deals')");
        \DB::statement('ALTER TABLE `deals` CHANGE `promo_type` `promo_type` ENUM("Product discount","Tier discount","Buy X Get Y","Discount delivery","Voucher Product Category") NULL;');

        Schema::create('deals_days', function (Blueprint $table) {
            $table->increments('id_deals_day');
            $table->unsignedInteger('id_deals');
            $table->string('day');
            $table->timestamps();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->tinyInteger('is_all_days')->default(1)->after('step_complete');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deals_productcategory_category_requirements');
        Schema::dropIfExists('deals_productcategory_rules');

        \DB::statement("ALTER TABLE `deals` CHANGE COLUMN `deals_type` `deals_type` ENUM('Deals', 'Hidden', 'Point', 'Spin', 'Promotion', 'WelcomeVoucher') NOT NULL default('Deals')");
        \DB::statement('ALTER TABLE `deals` CHANGE `promo_type` `promo_type` ENUM("Product discount","Tier discount","Buy X Get Y","Discount delivery") NULL;');

        Schema::dropIfExists('deals_days');

        Schema::table('deals', function (Blueprint $table) {
        	$table->dropColumn('is_all_days');
        });
    }
}
