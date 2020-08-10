<?php

namespace Modules\RedirectComplex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\RedirectComplex\Entities\RedirectComplexReference;
use Modules\RedirectComplex\Entities\RedirectComplexProduct;
use Modules\RedirectComplex\Entities\RedirectComplexOutlet;

use Modules\RedirectComplex\Http\Requests\CreateRequest;
use Modules\RedirectComplex\Http\Requests\DetailRequest;
use Modules\RedirectComplex\Http\Requests\UpdateRequest;
use Modules\RedirectComplex\Http\Requests\DeleteRequest;

use App\Lib\MyHelper;
use DB;

class ApiRedirectComplex extends Controller
{
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

    public function detail(DetailRequest $request)
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

    public function delete(DeleteRequest $request)
    {
        $post=$request->json()->all();
        $delete=RedirectComplexReference::where('id_redirect_complex_reference',$post['id_redirect_complex_reference'])->delete();

        return MyHelper::checkDelete($delete);
    }
}
