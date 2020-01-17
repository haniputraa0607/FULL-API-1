<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductProductVariantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_product_variants', function (Blueprint $table) {
            $table->unsignedInteger('id_product');
            $table->unsignedInteger('id_product_variant');
            $table->decimal('product_variant_price',8,2);

            $table->foreign('id_product', 'fk_id_product_products')->references('id_product')->on('products');
            $table->foreign('id_product_variant', 'fk_id_product_variant_product_variants')->references('id_product_variant')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_product_variants');
    }
}
