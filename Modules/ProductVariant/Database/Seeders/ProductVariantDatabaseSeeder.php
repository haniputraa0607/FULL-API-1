<?php

namespace Modules\ProductVariant\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class ProductVariantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('product_variants')->delete();
        
        \DB::table('product_variants')->insert(array (
            0 => 
            array (
                'id_product_variant' => 1,
                'product_variant_code' => 'size',
                'product_variant_name' => 'Size',
                'product_variant_title' => 'Your',
                'product_variant_subtitle' => 'Only pick one',
                'product_variant_position' => 2,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            1 => 
            array (
                'id_product_variant' => 2,
                'product_variant_code' => 'type',
                'product_variant_name' => 'Type',
                'product_variant_title' => 'Your Size',
                'product_variant_subtitle' => 'Only pick one',
                'product_variant_position' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ),
        ));
    }
}
