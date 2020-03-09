<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCategoryIdPosAndMenuIdPosToProductModifiersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_modifiers', function (Blueprint $table) {
            $table->string('category_id_pos')->default(0)->after('type');
            $table->string('menu_id_pos')->default(0)->after('id_product_modifier');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_modifiers', function (Blueprint $table) {
            $table->dropColumn('category_id_pos');
            $table->dropColumn('menu_id_pos');
        });
    }
}
