<?php

namespace Modules\POS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Schema;

use App\Http\Models\Product;
use App\Http\Models\Outlet;

use DB;

class SyncProductPrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        if(is_string($this->data)){
            $this->data = (array)json_decode($this->data);
        }
        $product = Product::where('product_code', $this->data['sap_matnr'])->first();
        foreach ($this->data['price_detail'] as $price) {
            $price = (array)$price;
            $outlet = Outlet::where('outlet_code', $price['store_code'])->first();

            if (!Schema::connection('mysql3')->hasTable('outlet_' . $price['store_code'])) {
                Schema::connection('mysql3')->create('outlet_' . $price['store_code'], function ($table) {
                    $table->bigIncrements('id_product_price_periode');
                    $table->unsignedInteger('id_product');
                    $table->unsignedInteger('id_outlet');
                    $table->float('price', 10, 2)->nullable();
                    $table->dateTime('date')->nullable();
                    $table->timestamps();

                    $table->index(['id_product', 'id_outlet', 'date'], 'index_product_price');
                });
            }

            if($price['start_date'] < date('Y-m-d')){
                $price['start_date'] = date('Y-m-d');
            }
            $interval = date_diff(date_create($price['start_date']), date_create($price['end_date']));

            if($interval->format('%a') > 90){
                $end = 90;
            }else{
                $end = $interval->format('%a') + 1;
            }

            for ($i = 0; $i < $end; $i++) {
                DB::connection('mysql3')->table('outlet_' . $price['store_code'])->updateOrInsert([
                    'id_product'    => $product->id_product,
                    'id_outlet'     => $outlet->id_outlet,
                    'date'          => date('Y-m-d H:i:s', strtotime($price['start_date'] . ' +' . $i . ' day'))
                ], [
                    'id_product'    => $product->id_product,
                    'id_outlet'     => $outlet->id_outlet,
                    'price'         => $price['price'],
                    'date'          => date('Y-m-d H:i:s', strtotime($price['start_date'] . ' +' . $i . ' day')),
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);
            }
        }
        DB::commit();
    }
}
