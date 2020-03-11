<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    public function run()
    {


        \DB::table('users')->delete();

        \DB::table('users')->insert(array (
            0 =>
            array (
                'id' => 2,
                'name' => 'Admin Technopartner',
                'phone' => '081111111111',
                'id_membership' => NULL,
                'email' => 'admin@mail.com',
                'password' => '$2y$10$gNOiaK3g13K5Ul6fNobv4.ERbd7QX7UGRiOHneiD1Lk9Xp2IuTFn.',
                'id_city' => 501,
                'gender' => NULL,
                'provider' => 'Telkomsel',
                'birthday' => NULL,
                'phone_verified' => '1',
                'email_verified' => '0',
                'level' => 'Super Admin',
                'points' => 0,
                'android_device' => NULL,
                'ios_device' => NULL,
                'is_suspended' => '0',
                'remember_token' => NULL,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            )
        ));


    }
}