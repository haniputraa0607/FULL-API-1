<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\LogBalance;
use App\Lib\MyHelper;

class RecalculateLogWelcomePoint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recalculate:log_welcome_point';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate welcome point\'s Log Balance';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $logs = LogBalance::where('source', 'Welcome Point')->where('balance_before', 0)->with('user')->get();
        $result = [
            'all_logs' => $logs->count(),
            'count_updated' => 0,
            'updated_id' => [],
        ];
        foreach ($logs as $log) {
            $balance_nominal = $log->balance;
            $balance_before = LogBalance::where('id_user', $log->id_user)->where('id_log_balance', '<', $log->id_log_balance)->sum('balance');
            $balance_after = $balance_before + $balance_nominal;

            if ($balance_before == 0) {
                // sudah benar tidak perlu update
                continue;
            }

            $result['count_updated']++;
            $result['updated_id'][] = $log->id_log_balance;

            // hash the inserted data
            $dataHashBalance = [
                'id_log_balance'                 => $log->id_log_balance,
                'id_user'                        => $log->id_user,
                'balance'                        => $log->balance,
                'balance_before'                 => $balance_before,
                'balance_after'                  => $balance_after,
                'id_reference'                   => $log->id_reference,
                'source'                         => $log->source,
                'grand_total'                    => $log->grand_total,
                'ccashback_conversion'           => $log->ccashback_conversion,
                'membership_level'               => $log->membership_level,
                'membership_cashback_percentage' => $log->membership_cashback_percentage
            ];

            $enc = MyHelper::encrypt2019(json_encode(($dataHashBalance)));
            // update enc column
            $log->update([
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'enc' => $enc,
            ]);
        }
        \Log::info('Recalculate Log Welcome Point Result', $result);
        $this->info(json_encode($result));
    }
}
