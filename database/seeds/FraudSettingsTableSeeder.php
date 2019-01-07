<?php

use Illuminate\Database\Seeder;

class FraudSettingsTableSeeder extends Seeder
{
    public function run()
    {
        

        \DB::table('fraud_settings')->delete();
        
        \DB::table('fraud_settings')->insert(array (
            0 => 
            array (
                'parameter' => 'Number of transactions in 1 day for each customer',
                'parameter_detail' => 2,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            1 => 
            array (
                'parameter' => 'Number of transactions in 1 week for each customer',
                'parameter_detail' => 5,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            2 => 
            array (
                'parameter' => 'Customer login using a device ID that has been used by another customer',
                'parameter_detail' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ),
        ));
    }
}