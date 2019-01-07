<?php

use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    public function run()
    {
        

        \DB::table('settings')->delete();
        
        \DB::table('settings')->insert(array (
            0 => 
            array (
                'id_setting' => 1,
                'key' => 'transaction_grand_total_order',
                'value' => 'subtotal,service,discount,shipping,tax',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            1 => 
            array (
                'id_setting' => 2,
                'key' => 'transaction_service_formula',
                'value' => '( subtotal ) * value',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            2 => 
            array (
                'id_setting' => 3,
                'key' => 'transaction_discount_formula',
            'value' => '( subtotal + service ) * value',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            3 => 
            array (
                'id_setting' => 4,
                'key' => 'transaction_tax_formula',
            'value' => '( subtotal + service - discount + shipping ) * value',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            4 => 
            array (
                'id_setting' => 5,
                'key' => 'point_acquisition_formula',
                'value' => '( subtotal ) / value',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            5 => 
            array (
                'id_setting' => 6,
                'key' => 'cashback_acquisition_formula',
            'value' => '( subtotal + service ) / value',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            6 => 
            array (
                'id_setting' => 7,
                'key' => 'transaction_delivery_standard',
                'value' => 'subtotal',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            7 => 
            array (
                'id_setting' => 8,
                'key' => 'transaction_delivery_min_value',
                'value' => '100000',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            8 => 
            array (
                'id_setting' => 9,
                'key' => 'transaction_delivery_max_distance',
                'value' => '10',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            9 => 
            array (
                'id_setting' => 10,
                'key' => 'transaction_delivery_pricing',
                'value' => 'By KM',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            10 => 
            array (
                'id_setting' => 11,
                'key' => 'transaction_delivery_price',
                'value' => '5000',
                'value_text' => NULL,
                'created_at' => '2018-05-09 10:54:31',
                'updated_at' => '2018-05-09 10:54:32',
            ),
            11 => 
            array (
                'id_setting' => 12,
                'key' => 'default_outlet',
                'value' => '1',
                'value_text' => NULL,
                'created_at' => '2018-05-09 11:43:51',
                'updated_at' => '2018-05-09 11:43:53',
            ),
            12 => 
            array (
                'id_setting' => 13,
                'key' => 'about',
                'value' => NULL,
                'value_text' => '<h1>About US </h1>',
                'created_at' => '2018-05-09 11:43:51',
                'updated_at' => '2018-05-09 11:43:53',
            ),
            13 => 
            array (
                'id_setting' => 14,
                'key' => 'tos',
                'value' => NULL,
                'value_text' => '<h1>Terms of Service</h1>',
                'created_at' => '2018-05-09 11:43:51',
                'updated_at' => '2018-05-09 11:43:53',
            ),
            14 => 
            array (
                'id_setting' => 15,
                'key' => 'contact',
                'value' => NULL,
                'value_text' => '<h1>Contact US</h1>',
                'created_at' => '2018-05-09 11:43:51',
                'updated_at' => '2018-05-09 11:43:53',
            ),
            15 => 
            array (
                'id_setting' => 16,
                'key' => 'greetings_morning',
                'value' => '05:00:00',
                'value_text' => NULL,
                'created_at' => '2018-05-09 14:47:16',
                'updated_at' => '2018-05-09 14:47:16',
            ),
            16 => 
            array (
                'id_setting' => 17,
                'key' => 'greetings_afternoon',
                'value' => '11:00:00',
                'value_text' => NULL,
                'created_at' => '2018-05-09 14:47:16',
                'updated_at' => '2018-05-09 14:47:16',
            ),
            17 => 
            array (
                'id_setting' => 18,
                'key' => 'greetings_evening',
                'value' => '17:00:00',
                'value_text' => NULL,
                'created_at' => '2018-05-09 14:47:16',
                'updated_at' => '2018-05-09 14:47:16',
            ),
            18 => 
            array (
                'id_setting' => 19,
                'key' => 'greetings_latenight',
                'value' => '22:00:00',
                'value_text' => NULL,
                'created_at' => '2018-05-09 14:47:16',
                'updated_at' => '2018-05-09 14:47:16',
            ),
            19 => 
            array (
                'id_setting' => 20,
                'key' => 'point_conversion_value',
                'value' => '10000',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            20 => 
            array (
                'id_setting' => 21,
                'key' => 'cashback_conversion_value',
                'value' => '10',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            21 => 
            array (
                'id_setting' => 22,
                'key' => 'service',
                'value' => '0.05',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            22 => 
            array (
                'id_setting' => 23,
                'key' => 'tax',
                'value' => '0.1',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            23 => 
            array (
                'id_setting' => 24,
                'key' => 'cashback_maximum',
                'value' => '100000',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
			24 => 
            array (
                'id_setting' => 25,
                'key' => 'default_home_text1',
                'value' => 'Please Login / Register',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
			25 => 
            array (
                'id_setting' => 26,
                'key' => 'default_home_text2',
                'value' => 'to enjoy the full experience',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
			26 => 
            array (
                'id_setting' => 27,
                'key' => 'default_home_text3',
                'value' => 'of Gudeg Techno Mobile Apps',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
			27 => 
            array (
                'id_setting' => 28,
                'key' => ' 	default_home_image',
                'value' => 'img/7991531810380.jpg',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
			28 => 
            array (
                'id_setting' => 29,
                'key' => 'api_key',
                'value' => 'c5d5410e7f14ba184b44f51bf3aaa691',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
			29 => 
            array (
                'id_setting' => 30,
                'key' => 'api_secret',
                'value' => 'C82FBB254221B637AF1CF1E6007C83FD6F5D8FD272DCB5CE915CA486A855C456',
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
			30 => 
            array (
                'id_setting' => 31,
                'key' => 'default_home_splash_screen',
                'value' => 'img/splash.jpg',
				'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            31 => 
            array (
                'id_setting' => 32,
                'key' => 'email_sync_menu',
                'value' => NULL,
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            32 => 
            array (
                'id_setting' => 33,
                'key' => 'qrcode_expired',
                'value' => 10,
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            33 => 
            array (
                'id_setting' => 34,
                'key' => 'processing_time',
                'value' => 10,
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            34 => 
            array (
                'id_setting' => 35,
                'key' => 'complete_profile_point',
                'value' => 10,
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            35 => 
            array (
                'id_setting' => 36,
                'key' => 'complete_profile_cashback',
                'value' => 1000,
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            36 => 
            array (
                'id_setting' => 37,
                'key' => 'complete_profile_interval',
                'value' => 10,
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
            37 => 
            array (
                'id_setting' => 38,
                'key' => 'complete_profile_count',
                'value' => 5,
                'value_text' => NULL,
                'created_at' => '2018-05-15 13:55:55',
                'updated_at' => '2018-05-15 13:55:55',
            ),
        ));
        
        
    }
}