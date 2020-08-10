<?php

namespace Modules\RedirectComplex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\RedirectComplex\Entities\RedirectComplexReference;
use Modules\RedirectComplex\Entities\RedirectComplexProduct;
use Modules\RedirectComplex\Entities\RedirectComplexOutlet;

use Modules\RedirectComplex\Http\Requests\CreateRequest;
use Modules\RedirectComplex\Http\Requests\EditRequest;
use Modules\RedirectComplex\Http\Requests\UpdateRequest;
use Modules\RedirectComplex\Http\Requests\DeleteRequest;
use Modules\RedirectComplex\Http\Requests\DetailRequest;

use Modules\ProductVariant\Entities\ProductGroup;
use Modules\ProductVariant\Entities\ProductVariant;
use App\Http\Models\Outlet;
use App\Http\Models\Setting;
use App\Lib\MyHelper;
use DB;

class ApiRedirectComplex extends Controller
{

	function __construct()
	{
        date_default_timezone_set('Asia/Jakarta');
		$this->outlet = "Modules\Outlet\Http\Controllers\ApiOutletController";
	}

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
    	$data = RedirectComplexReference::orderBy('updated_at','Desc')->paginate(10);

    	return MyHelper::checkGet($data);
        
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create(CreateRequest $request)
    {
    	$post 		= $request->json()->all();
    	$status		= false;
    	$reference 	= [
    		'type'				=> 'push',
			'name'				=> $request->name,
			'outlet_type'		=> $request->outlet_type,
			'promo_type'		=> !empty($request->promo) ? 'promo_campaign' : null,
			'promo_reference'	=> !empty($request->promo) ? $request->promo : null
    	];

    	DB::beginTransaction();

    	do {

	    	$save_reference = RedirectComplexReference::create($reference);
	    	if(!$save_reference) break;

	    	if ($request->outlet  && $request->outlet_type == 'specific') {
				$save_outlet 	= $this->saveOutlet($request->outlet, $save_reference->id_redirect_complex_reference);
	    		if(!$save_outlet) break;

	    	}
	    	if ($request->product) {
				$save_product 	= $this->saveProduct($request->product, $save_reference->id_redirect_complex_reference);
	    		if(!$save_product) break;
	    	}
    		
    		$status = $save_reference;
    		DB::commit();
    	} while (0);

        return MyHelper::checkCreate($status);
    }

    function saveOutlet($outlet=[], $id)
    {
    	$now = date("Y-m-d H:i:s");
    	$data = [];
    	foreach ($outlet as $key => $value) {
    		$data[] = [
    			'id_redirect_complex_reference' => $id,
				'id_outlet' 	=> $value,
				'created_at' 	=> $now,
				'updated_at'	=> $now
    		];
    	}

    	$save = RedirectComplexOutlet::insert($data);

    	return $save;
    }

    function saveProduct($product=[], $id)
    {
    	$now = date("Y-m-d H:i:s");
    	$data = [];
    	foreach ($product as $key => $value) {
    		$data[] = [
    			'id_redirect_complex_reference' => $id,
				'id_product' 	=> $value['id'],
				'qty' 			=> $value['qty'],
				'created_at' 	=> $now,
				'updated_at'	=> $now
    		];
    	}

    	$save = RedirectComplexProduct::insert($data);

    	return $save;
    }

    public function edit(EditRequest $request)
    {
    	$data = RedirectComplexReference::where('id_redirect_complex_reference', $request->id_redirect_complex_reference)
    			->with([
    				'outlets' => function($q) {
    					$q->select('outlets.id_outlet', 'outlet_code', 'outlet_name');
    				},
    				'products' => function($q) {
    					$q->select('products.id_product', 'product_code', 'product_name');
    				}
    			])
    			->first();
		$data = $data->append('get_promo');

    	return MyHelper::checkGet($data);
    }

    public function update(UpdateRequest $request)
    {
    	$post 		= $request->json()->all();
    	$status		= true;
    	$reference 	= [
			'name'				=> $request->name,
			'outlet_type'		=> $request->outlet_type,
			'promo_type'		=> !empty($request->promo) ? 'promo_campaign' : null,
			'promo_reference'	=> !empty($request->promo) ? $request->promo : null
    	];

    	DB::beginTransaction();

    	try {
    		do {
	    		$delete = $this->deleteRule($request->id_redirect_complex_reference);
	    		if (!$delete) {
	    			return $delete;
	    			$status = false; 
	    			break;
	    		}

	    		$save_reference = RedirectComplexReference::where('id_redirect_complex_reference', $request->id_redirect_complex_reference)->update($reference);

		    	if ($request->outlet && $request->outlet_type == 'specific') {
					$save_outlet 	= $this->saveOutlet($request->outlet, $request->id_redirect_complex_reference);

		    	}
		    	if ($request->product) {
					$save_product 	= $this->saveProduct($request->product, $request->id_redirect_complex_reference);
		    	}

	    		DB::commit();
    		} while (0);
    	} 
    	catch (Exception $e) {
    		DB::rollback();
    		$status = false;
    	}

        return MyHelper::checkUpdate($status);
    }

    function deleteRule($id)
    {
    	try {
	    	$del = RedirectComplexProduct::where('id_redirect_complex_reference', $id)->delete();
	    	$del = RedirectComplexOutlet::where('id_redirect_complex_reference', $id)->delete();
    		
    		return true;
    	} 
    	catch (Exception $e) {
    		return false;
    	}
    }

    function delete(DeleteRequest $request)
    {
        $post=$request->json()->all();
        $delete=RedirectComplexReference::where('id_redirect_complex_reference',$post['id_redirect_complex_reference'])->delete();

        return MyHelper::checkDelete($delete);
    }

    function listActive(Request $request){
        $post = $request->json()->all();

        $data = RedirectComplexReference::whereNotNull('name')
        		->whereNotNull('type')
        		->whereNotNull('outlet_type')
        		->whereHas('redirect_complex_products');

        if(isset($post['select'])){
            $data = $data->select($post['select']);
        }
        $data = $data->get();
        return response()->json(MyHelper::checkGet($data));
    }

    function detail(DetailRequest $request)
    {
    	$post = $request->all();
    	$data = [
    		'id_outlet' => null,
    		'data' 		=> [],
    		'promo' 	=> []
    	];
    	$reference 	= RedirectComplexReference::where('id_redirect_complex_reference', $request->id_reference)
					->with([
	    				'outlets' => function($q) {
	    					$q->select('outlets.id_outlet', 'outlet_code', 'outlet_name');
	    				},
	    				'products' => function($q) {
	    					$q->select('products.id_product', 'product_code', 'product_name');
	    				}
	    			])
					->first();
		if (!$reference) {
			return ['status' => 'fail', 'messages' => ['Reference not found']];
		}
    	$reference = $reference->append('get_promo');

    	// get outlet
    	$outlet 	= $this->getOutlet($request->latitude, $request->longitude, $reference->outlets, $reference->outlet_type);
    	if ($outlet['status'] == 'fail') {
			return ['status' => 'fail', 'messages' => ['Outlet not found']];
		}else{
			$data['id_outlet'] = $outlet['data'];
		}

		// get product data
		$product = $this->getProduct($reference, $data['id_outlet']);
		if ($product['status'] == 'fail') {
			return ['status' => 'fail', 'messages' => ['Product not found']];
		}else{
			$data['data'] = $product['data'];
		}

		// get promo
    	return $data;
    }

    function getOutlet($latitude, $longitude, $outlet_list=[], $outlet_type=null) {

        // outlet
        $outlet = Outlet::with(['today'])->select('outlets.id_outlet','outlets.outlet_name','outlets.outlet_phone','outlets.outlet_code','outlets.outlet_status','outlets.outlet_address','outlets.id_city','outlet_latitude','outlet_longitude')->where('outlet_status', 'Active')->whereNotNull('id_city')->orderBy('outlet_name','asc');

        $outlet->whereHas('brands',function($query){
            $query->where('brand_active','1');
        });

        $outlet = $outlet->get()->toArray();

        if (!empty($outlet)) {
            $processing = '0';
            $settingTime = Setting::where('key', 'processing_time')->first();
            if($settingTime && $settingTime->value){
                $processing = $settingTime->value;
            }
            foreach ($outlet as $key => $value) {
                $jaraknya =   number_format((float)app($this->outlet)->distance($latitude, $longitude, $value['outlet_latitude'], $value['outlet_longitude'], "K"), 2, '.', '');
                settype($jaraknya, "float");

                $outlet[$key]['distance'] = number_format($jaraknya, 2, '.', ',')." km";
                $outlet[$key]['dist']     = (float) $jaraknya;

                $outlet[$key] = app($this->outlet)->setAvailableOutlet($outlet[$key], $processing);

                if(isset($outlet[$key]['today']['time_zone'])){
                    $outlet[$key]['today'] = app($this->outlet)->setTimezone($outlet[$key]['today']);
                }
            }
            usort($outlet, function($a, $b) {
                return $a['dist'] <=> $b['dist'];
            });

        }else{
            return ['status' => 'fail', 'messages' => ['There is no open store','at this moment']];
        }

        if(!$outlet){
            return ['status' => 'fail', 'messages' => ['There is no open store','at this moment']];
        }

        if ($outlet_type) {
        	if ($outlet_type == 'specific') {
	    		$outlet_list = array_column($outlet_list->toArray(), 'id_outlet');
	    		foreach ($outlet as $key => $value) {
	    			if (in_array($value['id_outlet'], $outlet_list)) {
	    				$outlet = $value['id_outlet'];
	    				break;
	    			}
	    		}
	    	}
	    	else {
	    		$outlet = $outlet[0]['id_outlet'];
	    	}
        }

        return ['status' => 'success', 'data' => $outlet];
    }

    function getProduct($reference, $id_outlet){
    	$use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();
        $nf 		= 'float';

        $data = $reference->products;
        $product_group = [];
        if($use_product_variant){
        	foreach ($data as $key => $value) {
				$product_group[$key] = ProductGroup::select(\DB::raw('product_groups.id_product_group,product_group_name,product_group_code,product_group_description,product_group_photo,product_prices.product_price as price'))
					->join('products','products.id_product_group','=','product_groups.id_product_group')
					->where('products.id_product',$value->id_product)
					->join('product_prices','products.id_product','=','product_prices.id_product')
					->where('product_prices.id_outlet',$id_outlet)
					->groupBy('products.id_product')->first()->toArray();
				$product_group[$key]['variants'] = ProductVariant::select('product_variants.id_product_variant','product_variants.product_variant_name','product_variants.product_variant_code')
					->join('product_product_variants','product_product_variants.id_product_variant','product_variants.id_product_variant')
					->join('product_variants as parent','parent.id_product_variant', '=', 'product_variants.parent')
					->where('product_product_variants.id_product',$value->id_product)
					->orderBy('parent.product_variant_position')
					->get()->toArray();
				unset($product_group['products']);
        	}
		}

		if (empty($product_group)) {
			return ['status' => 'fail', 'messages' => ['Product not found']];			
		}
		else {
			return ['status' => 'success', 'data' => $product_group];
		}
    }
}
