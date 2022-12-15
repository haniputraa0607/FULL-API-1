<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAutoresponseInboxToDealsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->text('autoresponse_inbox')->nullable()->after('custom_outlet_text');
            $table->text('autoresponse_notification')->nullable()->after('custom_outlet_text');
        });

        Schema::table('deals_productcategory_category_requirements', function (Blueprint $table) {
            $table->dropColumn('id_product_variant');
        });

        Schema::create('second_deals_totals', function (Blueprint $table) {
            $table->bigIncrements('id_second_deals_total');
            $table->unsignedInteger('id_deals');
            $table->integer('deals_total')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
            $table->timestamps();

            $table->foreign('id_deals', 'fk_second_deals_totals_deals')->references('id_deals')->on('deals')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::create('product_variant_deals_productcategory_category_requirements', function (Blueprint $table) {
            $table->increments('id_product_variant_deals_productcategory_category_requirement');
            $table->unsignedInteger('id_deals_productcategory_category_requirement');
            $table->integer('id_product_variant');
            $table->timestamps();
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
            $table->dropColumn('autoresponse_inbox');
            $table->dropColumn('autoresponse_notification');
        });

        Schema::table('deals_productcategory_category_requirements', function (Blueprint $table) {
            $table->integer('id_product_variant')->nullable()->after('product_type');
        });

        Schema::dropIfExists('second_deals_total');
        Schema::dropIfExists('product_variant_deals_productcategory_category_requirements');
    }
}
