<?php

namespace Modules\POS\Jobs;

use App\Http\Models\Outlet;
use App\Http\Models\ProductModifier;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use DB;
use Illuminate\Support\Facades\Schema;

class SyncAddOnPrice implements ShouldQueue
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
        $this->data = json_decode($data, true);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        $productModifier = ProductModifier::where('code', $this->data['menu_id'])->first();
        foreach ($this->data['price_detail'] as $price) {
            $outlet = Outlet::where('outlet_code', $price['store_code'])->first();
            if (!Schema::connection('mysql3')->hasTable('outlet_' . $price['store_code'] . '_modifier_' . $this->data['menu_id'])) {
                Schema::connection('mysql3')->create('outlet_' . $price['store_code'] . '_modifier_' . $this->data['menu_id'], function ($table) {
                    $table->bigIncrements('id_product_modifier_price_periode');
                    $table->unsignedInteger('id_product_modifier');
                    $table->unsignedInteger('id_outlet');
                    $table->float('price')->nullable();
                    $table->dateTime('date')->nullable();
                    $table->timestamps();

                    $table->index(['id_product_modifier', 'id_outlet', 'date']);
                });
            }

            $interval = date_diff(date_create($price['start_date']), date_create($price['end_date']));
            for ($i = 0; $i < $interval->format('%a') + 1; $i++) {
                DB::connection('mysql3')->table('outlet_' . $price['store_code'] . '_modifier_' . $this->data['menu_id'])->updateOrInsert([
                    'id_product_modifier'   => $productModifier->id_product_modifier,
                    'id_outlet'             => $outlet->id_outlet,
                    'date'                  => date('Y-m-d H:i:s', strtotime($price['start_date'] . ' +' . $i . ' day'))
                ], [
                    'id_product_modifier'   => $productModifier->id_product_modifier,
                    'id_outlet'             => $outlet->id_outlet,
                    'price'                 => $price['price'],
                    'date'                  => date('Y-m-d H:i:s', strtotime($price['start_date'] . ' +' . $i . ' day')),
                    'created_at'            => date('Y-m-d H:i:s'),
                    'updated_at'            => date('Y-m-d H:i:s')
                ]);
            }
        }
        DB::commit();
    }
}
