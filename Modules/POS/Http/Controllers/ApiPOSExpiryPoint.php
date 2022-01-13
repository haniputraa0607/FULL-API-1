<?php

namespace Modules\POS\Http\Controllers;

use App\Lib\MyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\Outlet;

use DB;
use Modules\Balance\Entities\AdjustmentPointUser;
use Modules\Balance\Entities\NotificationExpiryPoint;

class ApiPOSExpiryPoint extends Controller
{
    function __construct() {
        ini_set('max_execution_time', 0);
        date_default_timezone_set('Asia/Jakarta');
        $this->setting_trx   = "Modules\Transaction\Http\Controllers\ApiSettingTransactionV2";
    }

    public function saveDataExpiryPoint(Request $request){
        $datas = $request->all();
        $currentDate = date('Y-m-d');
        $nextMonth = date('Y-m-d', strtotime($currentDate. ' + 1 month'));

        foreach ($datas as $data){
            $insert = [
                'id_user' => $data['cust_id'],
                'total_point' => $data['expiry_point'],
                'expired_date' => $nextMonth,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            NotificationExpiryPoint::updateOrCreate(['id_user' => $data['cust_id'],
                'total_point' => $data['expiry_point'],
                'expired_date' => $nextMonth], $insert);
        }

        return response()->json(['status' => 'success']);
    }

    public function saveDataAdjustmentPoint(Request $request){
        $datas = $request->all();

        foreach ($datas as $data){
            $insert = [
                'id_user' => $data['cust_id'],
                'point_adjust' => $data['point_adjust'],
                'reason' => $data['reason'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            AdjustmentPointUser::updateOrCreate(['id_user' => $data['cust_id'],
                'point_adjust' => $data['point_adjust'],
                'reason' => $data['reason']], $insert);
        }

        return response()->json(['status' => 'success']);
    }
}
