<?php

namespace Modules\PointInjection\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\PointInjection\Entities\PivotPointInjection;
use Log;
use Modules\PointInjection\Entities\PointInjectionReport;

class UserPointInjection implements ShouldQueue
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
        $insertPivot = PivotPointInjection::insert($this->data);

        if($insertPivot){
            foreach ($this->data as $val){
                PointInjectionReport::where('id_point_injection', $val['id_point_injection'])->where('id_user', $val['id_user'])
                    ->update(['status' => 'Success']);
            }
        }
    }
}
