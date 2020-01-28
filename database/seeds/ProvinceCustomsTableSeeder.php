<?php

use Illuminate\Database\Seeder;

class ProvinceCustomsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('province_customs')->delete();
        
        \DB::table('province_customs')->insert(array (
            0 => 
            array (
                'id_province_custom' => 1,
                'province_name' => 'Aceh',
            ),
            1 => 
            array (
                'id_province_custom' => 2,
                'province_name' => 'Bali',
            ),
            2 => 
            array (
                'id_province_custom' => 3,
                'province_name' => 'Bangka Belitung ',
            ),
            3 => 
            array (
                'id_province_custom' => 4,
                'province_name' => 'Banten',
            ),
            4 => 
            array (
                'id_province_custom' => 5,
                'province_name' => 'Bengkulu',
            ),
            5 => 
            array (
                'id_province_custom' => 6,
                'province_name' => 'DI Yogyakarta',
            ),
            6 => 
            array (
                'id_province_custom' => 7,
                'province_name' => 'DKI Jakarta',
            ),
            7 => 
            array (
                'id_province_custom' => 8,
                'province_name' => 'Gorontalo',
            ),
            8 => 
            array (
                'id_province_custom' => 9,
                'province_name' => 'Jambi',
            ),
            9 => 
            array (
                'id_province_custom' => 10,
                'province_name' => 'Jawa barat',
            ),
            10 => 
            array (
                'id_province_custom' => 11,
                'province_name' => 'Jawa Tengah',
            ),
            11 => 
            array (
                'id_province_custom' => 12,
                'province_name' => 'Jawa timur',
            ),
            12 => 
            array (
                'id_province_custom' => 13,
                'province_name' => 'Kalimantan Barat',
            ),
            13 => 
            array (
                'id_province_custom' => 14,
                'province_name' => 'Kalimantan selatan',
            ),
            14 => 
            array (
                'id_province_custom' => 15,
                'province_name' => 'Kalimantan tengah',
            ),
            15 => 
            array (
                'id_province_custom' => 16,
                'province_name' => 'Kalimantan timur',
            ),
            16 => 
            array (
                'id_province_custom' => 17,
                'province_name' => 'Kalimantan utara',
            ),
            17 => 
            array (
                'id_province_custom' => 18,
                'province_name' => 'Lampung',
            ),
            18 => 
            array (
                'id_province_custom' => 19,
                'province_name' => 'Maluku',
            ),
            19 => 
            array (
                'id_province_custom' => 20,
                'province_name' => 'Maluku utara',
            ),
            20 => 
            array (
                'id_province_custom' => 21,
                'province_name' => 'Nusa Tenggara Barat',
            ),
            21 => 
            array (
                'id_province_custom' => 22,
                'province_name' => 'Nusa Tenggara Timur',
            ),
            22 => 
            array (
                'id_province_custom' => 23,
                'province_name' => 'Papua',
            ),
            23 => 
            array (
                'id_province_custom' => 24,
                'province_name' => 'Papua Barat',
            ),
            24 => 
            array (
                'id_province_custom' => 25,
                'province_name' => 'Riau',
            ),
            25 => 
            array (
                'id_province_custom' => 26,
                'province_name' => 'Sulawesi Barat',
            ),
            26 => 
            array (
                'id_province_custom' => 27,
                'province_name' => 'Sulawesi Selatan',
            ),
            27 => 
            array (
                'id_province_custom' => 28,
                'province_name' => 'Sulawesi tenggara',
            ),
            28 => 
            array (
                'id_province_custom' => 29,
                'province_name' => 'Sulawesi tenggara',
            ),
            29 => 
            array (
                'id_province_custom' => 30,
                'province_name' => 'Sulawesi utara',
            ),
            30 => 
            array (
                'id_province_custom' => 31,
                'province_name' => 'Sumatera Barat',
            ),
            31 => 
            array (
                'id_province_custom' => 32,
                'province_name' => 'Sumatera Selatan',
            ),
            32 => 
            array (
                'id_province_custom' => 33,
                'province_name' => 'Sumatera utara',
            ),
        ));
        
        
    }
}