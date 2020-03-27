<?php

namespace Modules\PointInjection\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\User;
use Modules\PointInjection\Entities\PointInjection;
use Modules\PointInjection\Entities\PointInjectionReport;
use Modules\PointInjection\Entities\PointInjectionUser;
use Modules\PointInjection\Jobs\UserPointInjection;
use App\Http\Models\Outlet;
use App\Http\Models\News;
use App\Http\Models\OauthAccessToken;
use App\Http\Models\LogBackendError;

use App\Lib\MyHelper;
use App\Lib\PushNotificationHelper;
use DB;
use Modules\PointInjection\Entities\PivotPointInjection;

class ApiPointInjectionReportController extends Controller
{
    function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $post = $request->json()->all();
        $take = 25;
        $data = PointInjectionReport::join('users', 'users.id', 'point_injection_reports.id_user')
                ->orderBy('point_injection_reports.created_at', 'desc')
                ->select('users.name', 'users.phone', 'users.email', 'users.balance', 'point_injection_reports.*',
                    DB::raw('(SELECT SUM(point) FROM point_injection_reports pir WHERE pir.id_point_injection_report <= point_injection_reports.id_point_injection_report) as "total_point"'));

        if(isset($post['id_point_injection'])){
            $data->where('point_injection_reports.id_point_injection', $post['id_point_injection']);
        }

        if(isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])){
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $data->whereDate('point_injection_reports.created_at', '>=', $start_date)
                ->whereDate('point_injection_reports.created_at', '<=', $end_date);
        }

        if(isset($post['conditions']) && !empty($post['conditions'])){
            $rule = 'and';
            if(isset($post['rule'])){
                $rule = $post['rule'];
            }

            if($rule == 'and'){
                foreach ($post['conditions'] as $row){
                    if($row['subject'] == 'status'){
                        $data->where('point_injection_reports.status', $row['parameter']);
                    }

                    if($row['subject'] == 'name' || $row['subject'] == 'email' || $row['subject'] == 'phone'){
                        if($row['operator'] == '='){
                            $data->where('users.'.$row['subject'], $row['parameter']);
                        }else{
                            $data->where('users.'.$row['subject'], 'like', '%'.$row['parameter'].'%');
                        }
                    }

                    if($row['subject'] == 'point_received'){
                        $data->where('point_injection_reports.point', $row['operator'], $row['parameter']);
                    }

                    if($row['subject'] == 'total_point_received'){
                        $data->whereRaw('(SELECT SUM(point) FROM point_injection_reports pir WHERE pir.id_point_injection_report <= point_injection_reports.id_point_injection_report) '.$row['operator'].' '.$row['parameter']);
                    }
                }
            }else{
                $data->where(function ($subquery) use ($post){
                    foreach ($post['conditions'] as $row){
                        if($row['subject'] == 'status'){
                            $subquery->orWhere('point_injection_reports.status', $row['parameter']);
                        }

                        if($row['subject'] == 'name' || $row['subject'] == 'email' || $row['subject'] == 'phone'){
                            if($row['operator'] == '='){
                                $subquery->orWhere('users.'.$row['subject'], $row['parameter']);
                            }else{
                                $subquery->orWhere('users.'.$row['subject'], 'like', '%'.$row['parameter'].'%');
                            }
                        }

                        if($row['subject'] == 'point_received'){
                            $subquery->orWhere('point_injection_reports.point', $row['operator'], $row['parameter']);
                        }

                        if($row['subject'] == 'total_point_received'){
                            $subquery->orWhereRaw('(SELECT SUM(point) FROM point_injection_reports pir WHERE pir.id_point_injection_report <= point_injection_reports.id_point_injection_report) '.$row['operator'].' '.$row['parameter']);
                        }
                    }
                });
            }
        }

        if(isset($post['export']) && $post['export'] == 1){
            $data = $data->get();
        }else{
            $data = $data->paginate($take);
        }
        return response()->json(MyHelper::checkGet($data));
    }

}
