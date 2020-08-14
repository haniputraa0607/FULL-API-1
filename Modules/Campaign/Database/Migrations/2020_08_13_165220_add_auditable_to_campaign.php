<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToCampaign extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('campaigns', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('campaign_rules', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('campaign_rule_parents', function (Blueprint $table) {
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
        Schema::table('campaigns', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('campaign_rules', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('campaign_rule_parents', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
