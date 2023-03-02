<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeIdProductVariantToSizeAndType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_variant_deals_productcategory_category_requirements', function (Blueprint $table) {
            $table->dropColumn('id_product_variant');
            $table->integer('size')->after('id_deals_productcategory_category_requirement');
            $table->integer('type')->after('size');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_variant_deals_productcategory_category_requirements', function (Blueprint $table) {
            $table->integer('id_product_variant')->after('id_deals_productcategory_category_requirement');
            $table->dropColumn('size');
            $table->dropColumn('size');
        });
    }
}
