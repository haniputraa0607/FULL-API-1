<?php

use Illuminate\Database\Seeder;

class UserAddressesTableSeeder extends Seeder
{
    public function run()
    {


        \DB::table('user_addresses')->delete();

        \DB::table('user_addresses')->insert(array (
            0 =>
            array (
                'id_user_address' => 1,
                'name' => 'Admin Technopartner',
                'phone' => '081111111111',
                'id_user' => 3,
                'id_city' => 501,
                'address' => 'Jalan Garuda',
                'postal_code' => '78112',
                'description' => 'Lorem',
                'primary' => '1',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));


    }
}