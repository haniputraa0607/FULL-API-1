<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateColumnNullableOnProductVariantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('product_variant_subtitle')->nullable()->change();
        });
        Schema::table('product_product_variants', function (Blueprint $table) {
            $table->decimal('product_variant_price',8,2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('product_variant_subtitle')->change();
        });
        Schema::table('product_product_variants', function (Blueprint $table) {
            $table->decimal('product_variant_price',8,2)->default(0)->change();
        });
    }
}
