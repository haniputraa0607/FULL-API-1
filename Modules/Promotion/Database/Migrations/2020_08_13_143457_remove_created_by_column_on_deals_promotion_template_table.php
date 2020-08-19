<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveCreatedByColumnOnDealsPromotionTemplateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deals_promotion_templates', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deals_promotion_templates', function (Blueprint $table) {
        	$table->integer('created_by')->after('id_deals_promotion_template')->nullable();
        });
    }
}
