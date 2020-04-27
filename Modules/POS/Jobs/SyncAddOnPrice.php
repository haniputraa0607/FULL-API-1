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
        if (is_string($this->data)) {
            $this->data = (array) json_decode($this->data, true);
        }

        foreach ($this->data['price_detail'] as $price) {
            $outlet = Outlet::where('id_outlet', $price['id_outlet'])->first();
            if (!Schema::connection('mysql3')->hasTable('outlet_' . $outlet->outlet_code . '_modifier')) {
                Schema::connection('mysql3')->create('outlet_' . $outlet->outlet_code . '_modifier', function ($table) {
                    $table->bigIncrements('id_product_modifier_price_periode');
                    $table->unsignedInteger('id_product_modifier');
                    $table->unsignedInteger('id_outlet');
                    $table->float('price', 10, 2)->nullable();
                    $table->dateTime('start_date')->nullable();
                    $table->dateTime('end_date')->nullable();
                    $table->timestamps();
                    $table->index(['id_product_modifier', 'id_outlet', 'start_date', 'end_date'], 'index_modifier_price');
                });
            }

            $inBetween = DB::connection('mysql3')->table('outlet_' . $outlet->outlet_code . '_modifier')
                ->where('id_product_modifier', $price['id_product_modifier'])
                ->where('id_outlet', $price['id_outlet'])
                ->whereIn('id_product_modifier_price_periode', function ($q) use ($price, $outlet) {
                    $q->from('outlet_' . $outlet->outlet_code . '_modifier')
                        ->selectRaw('id_product_modifier_price_periode')
                        ->where('start_date', '>=', $price['start_date'])->where('end_date', '<=', $price['end_date']);
                })
                ->orWhere('end_date', '<=', date('Y-m-d'))
                ->orderBy('start_date')->get()->toArray();

            if (!empty($inBetween)) {
                foreach ($inBetween as $between) {
                    DB::connection('mysql3')->table('outlet_' . $outlet->outlet_code . '_modifier')->where('id_product_modifier_price_periode', $between->id_product_modifier_price_periode)->delete();
                }
            }

            DB::connection('mysql3')->table('outlet_' . $outlet->outlet_code . '_modifier')->insert($price);
        }
        DB::commit();
    }
}
