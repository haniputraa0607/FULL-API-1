<?php

namespace Modules\UserRating\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\UserRating\Entities\RatingOption;

use App\Lib\MyHelper;

class ApiRatingOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $ratings = array_map(function($var){
            $var['value'] = explode(',',$var['star']);
            $var['options'] = explode(',',$var['options']);
            return $var;
        },RatingOption::all()->toArray());
        return MyHelper::checkGet($ratings);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request)
    {
        $post = $request->json()->all();
        \DB::beginTransaction();
        RatingOption::truncate();
        foreach ($post['rule'] as $rule) {
            $insert['star'] = implode(',',$rule['value']);
            $insert['question'] = $rule['question'];
            $insert['options'] = implode(',',$rule['options']);
            $create = RatingOption::create($insert);
            if(!$create){
                \DB::rollback();
                return MyHelper::checkCreate($create);
            }
        }
        \DB::commit();
        return MyHelper::checkCreate($create??[]);
    }
}
