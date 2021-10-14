<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncAddonPrice2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dataOutlet;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($dataOutlet)
    {
        $this->dataOutlet = $dataOutlet;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            // select different price
            $p3 = \DB::connection('mysql')->table('outlet_product_modifier_price_periodes')->select('outlet_product_modifier_price_periode_temps.id_product_modifier','outlet_product_modifier_price_periode_temps.price')->join('outlet_product_modifier_price_periode_temps', function($join) {
                $join->on('outlet_product_modifier_price_periode_temps.id_product_modifier', '=', 'outlet_product_modifier_price_periodes.id_product_modifier')
                    ->whereColumn('outlet_product_modifier_price_periode_temps.id_outlet', '=', 'outlet_product_modifier_price_periodes.id_outlet')
                    ->whereColumn('outlet_product_modifier_price_periode_temps.price', '<>', 'outlet_product_modifier_price_periodes.price');
            });

            if(count($this->dataOutlet) != 1) {
                $p3->whereIn('outlet_product_modifier_price_periodes.id_outlet',$this->dataOutlet);
            } else {
                $p3->where('outlet_product_modifier_price_periodes.id_outlet',$this->dataOutlet[0]);
            }

            $p3->update([
                'outlet_product_modifier_price_periodes.price' => \DB::raw('outlet_product_modifier_price_periode_temps.price'),
                'outlet_product_modifier_price_periodes.start_date' => \DB::raw('outlet_product_modifier_price_periode_temps.start_date'),
                'outlet_product_modifier_price_periodes.end_date' => \DB::raw('outlet_product_modifier_price_periode_temps.end_date'),
                'outlet_product_modifier_price_periodes.updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
            ]);

            // create not existing product
            $toCreate = [];
            foreach ($this->dataOutlet as $id_outlet) {
                $existing_product = \DB::connection('mysql')->table('outlet_product_modifier_price_periodes')->select('id_product_modifier')->where('id_outlet', $id_outlet)->pluck('id_product_modifier');
                $products = \DB::connection('mysql')->table('outlet_product_modifier_price_periode_temps')->select('id_product_modifier','id_outlet','price','start_date','end_date')->where('id_outlet', $id_outlet)->whereNotIn('id_product_modifier', $existing_product)->get();
                foreach ($products as $product) {
                    $kwd = $product->id_product_modifier.'.'.$id_outlet;
                    $toCreate[$kwd] = [
                        'id_product_modifier' => $product->id_product_modifier,
                        'id_outlet' => $id_outlet,
                        'price' => $product->price,
                        'start_date' => $product->start_date,
                        'end_date' => $product->end_date,
                        'created_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                        'updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                    ];
                }
            }

            if ($toCreate) {
                \DB::connection('mysql')->table('outlet_product_modifier_price_periodes')->insert(array_values($toCreate));
            }
        } catch (\Exception $e) {
            \Log::error( $e->getMessage());
        }
    }
}
