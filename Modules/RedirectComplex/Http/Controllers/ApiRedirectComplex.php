<?php

namespace Modules\RedirectComplex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\RedirectComplex\Entities\RedirectComplexReference;
use Modules\RedirectComplex\Entities\RedirectComplexProduct;
use Modules\RedirectComplex\Entities\RedirectComplexOutlet;

use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;

use Modules\RedirectComplex\Http\Requests\CreateRequest;
use Modules\RedirectComplex\Http\Requests\EditRequest;
use Modules\RedirectComplex\Http\Requests\UpdateRequest;
use Modules\RedirectComplex\Http\Requests\DeleteRequest;
use Modules\RedirectComplex\Http\Requests\DetailRequest;
use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\ProductVariant\Entities\ProductGroup;
use Modules\ProductVariant\Entities\ProductVariant;
use App\Http\Models\Outlet;
use App\Http\Models\Setting;
use App\Http\Models\Product;
use Modules\Brand\Entities\Brand;
use App\Lib\MyHelper;
use DB;

class ApiRedirectComplex extends Controller
{

	function __construct()
	{
        date_default_timezone_set('Asia/Jakarta');
		$this->outlet 				= "Modules\Outlet\Http\Controllers\ApiOutletController";
		$this->online_transaction   = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
		$this->api_promo   			= "Modules\PromoCampaign\Http\Controllers\ApiPromo";
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
    		'id_outlet' => null
    	];

    	$reference 	= RedirectComplexReference::where('id_redirect_complex_reference', $request->id_reference)
					->with([
	    				'outlets' => function($q) {
	    					$q->select('outlets.id_outlet', 'outlet_code', 'outlet_name');
	    				},
	    				'products' => function($q) {
	    					$q->with(['brands', 'product_variants']);
	    					// $q->select('products.id_product', 'product_code', 'product_name');
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
			$data['id_outlet'] = $outlet['id_outlet'];
		}

		// get product
		$data['item'] = $this->getProduct($reference->products);

		// get promo
		$data['promo_code'] = $this->getPromo($reference->promo_reference);
		
		$custom_request = new \Modules\Transaction\Http\Requests\CheckTransaction;
		$custom_request = $custom_request
						->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($data))
						->merge($data)
						->setUserResolver(function () use ($request) {
							return $request->user();
						});

		return app($this->online_transaction)->checkTransaction($custom_request);
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
	    				$id_outlet = $value['id_outlet'];
	    				break;
	    			}
	    		}
	    	}
	    	else {
	    		$id_outlet = $outlet[0]['id_outlet'];
	    	}
        }

        return ['status' => 'success', 'id_outlet' => $id_outlet];
    }

    function getProduct($products)
    {
    	$data =[];
    	$brand = Brand::select('id_brand')->first();
    	foreach ($products as $value) {
    		$temp = [
				"id_product_group" => $value['id_product_group'],
		    	"bonus"		=> 0,
				"id_brand"	=> $value['brands'][0]['id_brand']??$brand['id_brand'],
				"modifiers"	=> [],
				"note"		=> "",
				"qty"		=> $value['pivot']['qty'],
				"variants"	=> []
			];

			foreach ($value['product_variants'] as $value2) {
				$temp['variants'][] = $value2['id_product_variant'];
			}
			$data[] = $temp;
    	}
    	return $data;

    }

    function getPromo($promo_reference)
    {
    	$data = null;
    	if ($promo_reference) {
    		$promo = PromoCampaignPromoCode::where('id_promo_campaign', $promo_reference)->first();
    		$data = $promo['promo_code'];
    	}

    	return $data;
    }

    public function getData(Request $request)
    {
        $post = $request->json()->all();
        $data = [];
        switch ($post['get']) {
        	case 'Outlet':
            	$data = Outlet::select('id_outlet', DB::raw('CONCAT(outlet_code, " - ", outlet_name) AS outlet'));

            	if (!empty($post['brand'])) {
	                foreach ($post['brand'] as $value) {
		                $data = $data->whereHas('brands',function($query) use ($value){
				                    $query->where('brands.id_brand',$value);
				                });
		            }
	            }

            	$data = $data->get()->toArray();
        		break;
        	
        	case 'Product':
        		if ($post['type'] == 'group')
		        {
		            $data = ProductGroup::select('id_product_group as id_product', DB::raw('CONCAT(product_group_code, " - ", product_group_name) AS product'))->whereNotNull('id_product_category')->get()->toArray();
		        }
		        else
		        {
		            $data = Product::select('products.id_product', DB::raw('CONCAT(name_brand, " - ", product_code, " - ", product_name) AS product'))
		            		->whereHas('product_group', function($q) {
		            			$q->whereNotNull('id_product_category');
		            		})
		            		->leftJoin('brand_product', 'products.id_product', '=', 'brand_product.id_product')
		            		->leftJoin('brands', 'brands.id_brand', '=', 'brand_product.id_brand')
		            		->groupBy('brand_product.id_brand_product')
		            		->orderBy('brands.id_brand');

			        if (!empty($post['brand'])) {
		            	$data = $data->whereHas('brands',function($query) use ($post){
		                    $query->whereIn('brands.id_brand',$post['brand']);
		                });
		            }

		            $data = $data->get()->toArray();
		        }

        		break;

        	case 'ProductGroup':
        		$data = ProductGroup::select('id_product_group', DB::raw('CONCAT(product_group_code, " - ", product_group_name) AS product_group'))->whereNotNull('id_product_category')->get()->toArray();
        		break;

        	case 'promo':
        		$now = date('Y-m-d H:i:s');
        		// return $post;
        		switch ($post['type']) {
        			case 'promo_campaign':
        				$data = PromoCampaign::select('promo_campaigns.id_promo_campaign as id_promo', DB::raw('CONCAT(promo_code, " - ", campaign_name) AS promo'))
        						->where('code_type','Single')
        						->join('promo_campaign_promo_codes', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
        						->where('date_start', '<', $now)
        						->where('date_end', '>', $now)
        						->where('step_complete','=',1)
					            ->where( function($q){
					            	$q->whereColumn('usage','<','limitation_usage')
					            		->orWhere('code_type','Single')
					            		->orWhere('limitation_usage',0);
					            });
					    // check outlet
					    if (isset($post['outlet'])) {
					    	$data = $data->where(function($q) use ($post) {
			            	$q->where('is_all_outlet',1);
			            		$q->orWhere(function($q2) use ($post) {
			            			foreach ($post['outlet'] as $value) {
			            				$q2->whereHas('outlets',function($q3) use ($value){
						                    $q3->where('outlets.id_outlet',$value);
						                });
			            			}
			            		});
			            	});
					    }
					   	// check brand
					   	/* commented because promo campaign doesnt have brand column
					   	if (isset($post['brand'])) {
						    $data = $data->whereIn('id_brand', $post['brand']);
					   	}
					   	*/
					   	$data = $data->get()->toArray();
        				break;
        			
        			default:
        				$data = [];
        				break;
        		}
        		break;

        	default:
        		$data = [];
        		break;
        }


        return response()->json($data);
    }
}
	