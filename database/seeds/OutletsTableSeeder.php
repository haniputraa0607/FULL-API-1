<?php

use Illuminate\Database\Seeder;

class OutletsTableSeeder extends Seeder
{
    public function run()
    {
        

        \DB::table('outlets')->delete();
        
        \DB::table('outlets')->insert(array (
            0 => 
            array (
                'id_outlet' => 1,
                'outlet_code' => 'M11',
                'outlet_name' => 'Gunung Kidul',
                'outlet_address' => 'jalan wonosari',
                'id_city' => 135,
                'outlet_postal_code' => '68151',
                'outlet_phone' => '02734-889880',
                'outlet_email' => 'natasha@gmail.com',
                'outlet_latitude' => '-7.813832700000002',
                'outlet_longitude' => '110.36860209999998',
                'created_at' => '2018-04-11 16:41:11',
                'updated_at' => '2018-06-08 16:01:28',
            ),
            1 => 
            array (
                'id_outlet' => 2,
                'outlet_code' => 'M12',
                'outlet_name' => 'Kulon Progo',
                'outlet_address' => 'jalan bantul',
                'id_city' => 210,
                'outlet_postal_code' => '68151',
                'outlet_phone' => '02734-889880',
                'outlet_email' => 'natasha@gmail.com',
                'outlet_latitude' => '-7.787683799999999',
                'outlet_longitude' => '110.43176130000006',
                'created_at' => '2018-04-11 16:41:11',
                'updated_at' => '2018-06-21 20:41:27',
            ),
            2 => 
            array (
                'id_outlet' => 3,
                'outlet_code' => 'M13',
                'outlet_name' => 'Klaten',
                'outlet_address' => 'Jl. Rajawali, Bareng, Klaten Tengah, Kabupaten Klaten, Jawa Tengah 57414',
                'id_city' => 196,
                'outlet_postal_code' => '57411',
            'outlet_phone' => '(0272) 322288',
                'outlet_email' => 'natasha@gmail.com',
                'outlet_latitude' => '-7.739966999999999',
                'outlet_longitude' => '110.66456829999993',
                'created_at' => '2018-04-20 17:04:19',
                'updated_at' => '2018-06-07 22:23:43',
            ),
            3 => 
            array (
                'id_outlet' => 4,
                'outlet_code' => 'M14',
                'outlet_name' => 'Taman Anggrek',
                'outlet_address' => 'Mall Taman Anggrek Lt. 1 T10-T11, Jl. Letjend. S. Parman, RT.12/RW.1, Tj. Duren Sel., Grogol petamburan, Kota Jakarta Barat, Daerah Khusus Ibukota Jakarta',
                'id_city' => 151,
                'outlet_postal_code' => '11220',
            'outlet_phone' => '(021) 5609760',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-07 16:05:53',
                'updated_at' => '2018-06-07 16:54:53',
            ),
            4 => 
            array (
                'id_outlet' => 5,
                'outlet_code' => 'M15',
                'outlet_name' => 'Surabaya Mustajab',
                'outlet_address' => 'Jl. Walikota Mustajab No.58, Ketabang, Genteng, Kota SBY, Jawa Timur',
                'id_city' => 444,
                'outlet_postal_code' => '60119',
            'outlet_phone' => '(031) 5341315',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-08 20:52:35',
                'updated_at' => '2018-06-08 20:52:35',
            ),
            5 => 
            array (
                'id_outlet' => 6,
                'outlet_code' => 'M16',
                'outlet_name' => 'Surabaya Supermall Pakuwon Indah',
                'outlet_address' => 'Supermal Pakuwon Indah, Jalan Pakuwon Indah No.G095 - 096, Babatan, Wiyung, Surabaya City, East Java',
                'id_city' => 444,
                'outlet_postal_code' => '60119',
            'outlet_phone' => '(031) 7390048',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-08 21:15:20',
                'updated_at' => '2018-06-08 21:15:20',
            ),
            6 => 
            array (
                'id_outlet' => 7,
                'outlet_code' => 'M17',
                'outlet_name' => 'Bandung Festival Citylink',
                'outlet_address' => 'Jl. Peta No.241, Suka Asih, Bojongloa Kaler, Kota Bandung, Jawa Barat 40232',
                'id_city' => 22,
                'outlet_postal_code' => '40311',
            'outlet_phone' => '(022) 6128755',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-08 21:58:09',
                'updated_at' => '2018-06-21 20:41:30',
            ),
            7 => 
            array (
                'id_outlet' => 8,
                'outlet_code' => 'M18',
                'outlet_name' => 'Cirebon',
                'outlet_address' => 'Jl. Dr. Wahidin Sudirohusodo No. 17B, Sukapura, Kejaksan, Cirebon City, Indonesia',
                'id_city' => 108,
                'outlet_postal_code' => '45611',
            'outlet_phone' => '(0231) 233513',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-25 19:30:47',
                'updated_at' => '2018-06-25 19:30:47',
            ),
            8 => 
            array (
                'id_outlet' => 9,
                'outlet_code' => 'M19',
            'outlet_name' => 'Semarang Paragon',
                'outlet_address' => 'Paragon Mall Semarang, No. 19B, No. 118, Jl. Pemuda, Sekayu, Semarang Tengah, Kota Semarang, Jawa Tengah',
                'id_city' => 398,
                'outlet_postal_code' => '50511',
            'outlet_phone' => '(024) 86579161',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-25 19:32:02',
                'updated_at' => '2018-06-25 19:32:02',
            ),
            9 => 
            array (
                'id_outlet' => 10,
                'outlet_code' => 'M20',
            'outlet_name' => 'Jakarta MOI',
                'outlet_address' => 'Mall Of Indonesia Lantai 1 Unit G2 , G3, dan G5, Jl. Boulevard Barat Raya, Kelapa Gading, RT.18/RW.8, Klp. Gading Bar., Klp. Gading, Kota Jkt Utara, Daerah Khusus Ibukota Jakarta',
                'id_city' => 155,
                'outlet_postal_code' => '14140',
            'outlet_phone' => '(021) 45867971',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-25 19:34:55',
                'updated_at' => '2018-06-25 19:34:55',
            ),
            10 => 
            array (
                'id_outlet' => 11,
                'outlet_code' => 'M21',
                'outlet_name' => 'Natasha Skin Care Salatiga',
                'outlet_address' => 'Ruko Kaloka, Jl. Patimura 015-016, Salatiga, Sidorejo, Salatiga City, Central Java',
                'id_city' => 386,
                'outlet_postal_code' => '50711',
            'outlet_phone' => '(0298) 316220',
                'outlet_email' => NULL,
                'outlet_latitude' => '-7.7972',
                'outlet_longitude' => '110.36879999999996',
                'created_at' => '2018-06-25 19:36:19',
                'updated_at' => '2018-06-25 19:36:19',
            ),
        ));
        
        
    }
}