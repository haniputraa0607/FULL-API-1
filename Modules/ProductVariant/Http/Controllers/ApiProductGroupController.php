<?php

namespace Modules\ProductVariant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\ProductVariant\Entities\ProductGroup;
use Modules\ProductVariant\Entities\ProductProductVariant;
use Modules\ProductVariant\Entities\ProductVariant;
use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductModifier;
use App\Http\Models\ProductCategory;

use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;

use App\Lib\MyHelper;

class ApiProductGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $pg = ProductGroup::select(\DB::raw('product_groups.*,count(id_product) as products_count'))
            ->leftJoin('products','products.id_product_group','=','product_groups.id_product_group')
            ->groupBy('product_groups.id_product_group')
            ->with('product_category');
        if($request->json('rule')){
            $this->filterList($pg,$request->post('rule'),$request->post('operator'));
        }
        if($request->page){
            $pg = $pg->paginate(10);
        }else{
            $pg = $pg->get();
        }
        return MyHelper::checkGet($pg->toArray());
    }

    public function filterList($model,$rule,$operator='and')
    {
        $where = $operator=='and'?'where':'orWhere';
        $newRule=[];
        $where=$operator=='and'?'where':'orWhere';
        foreach ($rule as $var) {
            $var1=['operator'=>$var['operator']??'=','parameter'=>$var['parameter']??null];
            if($var1['operator']=='like'){
                $var1['parameter']='%'.$var1['parameter'].'%';
            }
            $newRule[$var['subject']][]=$var1;
        }

        $inner=['product_group_name'];
        foreach ($inner as $col_name) {
            if($rules=$newRule[$col_name]??false){
                foreach ($rules as $rul) {
                    $model->$where('outlets.'.$col_name,$rul['operator'],$rul['parameter']);
                }
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $post = $request->json()->all();
        if (isset($post['product_group_photo'])) {
            $upload = MyHelper::uploadPhotoStrict($post['product_group_photo'], $path = 'img/product-group/photo/',200,200);
            if ($upload['status'] == "success") {
                $post['product_group_photo'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        if (isset($post['product_group_image_detail'])) {
            $upload = MyHelper::uploadPhotoStrict($post['product_group_image_detail'], $path = 'img/product-group/image/',720,360);
            if ($upload['status'] == "success") {
                $post['product_group_image_detail'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        $data = [
            'id_product_category' => $request->json('id_product_category'),
            'product_group_code' => $request->json('product_group_code'),
            'product_group_name' => $request->json('product_group_name'),
            'product_group_description' => $request->json('product_group_description'),
            'product_group_photo' => $post['product_group_photo'],
            'product_group_image_detail' => $post['product_group_image_detail']
        ];
        $create = ProductGroup::create($data);
        return MyHelper::checkCreate($create);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $id_product_group = $request->json('id_product_group');
        $data = ProductGroup::find($id_product_group)->toArray();
        $data['variants'] = Product::select(\DB::raw('products.id_product,products.product_name,GROUP_CONCAT(id_product_variant) as variants'))
            ->where('id_product_group',$id_product_group)
            ->leftJoin('product_product_variants','product_product_variants.id_product','products.id_product')
            ->groupBy('products.id_product')
            ->get()->toArray();

        return MyHelper::checkGet($data);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request)
    {
        $pg = ProductGroup::find($request->json('id_product_group'));
        if(!$pg){
            return MyHelper::checkGet($pg);
        }
        $post = $request->json()->all();
        if (isset($post['product_group_photo'])) {
            $pg_old['product_group_photo'] = $pg['product_group_photo'];
            $upload = MyHelper::uploadPhotoStrict($post['product_group_photo'], $path = 'img/product-group/photo/',200,200);
            if ($upload['status'] == "success") {
                $post['product_group_photo'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        if (isset($post['product_group_image_detail'])) {
            $pg_old['product_group_image_detail'] = $pg['product_group_image_detail'];
            $upload = MyHelper::uploadPhotoStrict($post['product_group_image_detail'], $path = 'img/product-group/image/',720,360);
            if ($upload['status'] == "success") {
                $post['product_group_image_detail'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        $data = [
            'id_product_category' => $request->json('id_product_category'),
            'product_group_code' => $request->json('product_group_code'),
            'product_group_name' => $request->json('product_group_name'),
            'product_group_description' => $request->json('product_group_description'),
        ];
        if($post['product_group_photo']??false){
            $data['product_group_photo'] = $post['product_group_photo'];
        }
        if($post['product_group_photo']??false){
            $data['product_group_image_detail'] = $post['product_group_image_detail'];
        }
        $update = $pg->update($data);
        if($update){
            if($pg_old['product_group_photo']??false){
                MyHelper::deletePhoto($pg_old['product_group_photo']);
            }
            if($pg_old['product_group_image_detail']??false){
                MyHelper::deletePhoto($pg_old['product_group_image_detail']);
            }
        }
        return MyHelper::checkUpdate($update);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $pg = ProductGroup::find($request->json('id_product_group'));
        if(!$pg){
            return MyHelper::checkDelete(false);
        }
        $delete = $pg->delete();
        if($delete){
            if($pg['product_group_photo']){
                MyHelper::deletePhoto($pg['product_group_photo']);
            }
            if($pg['product_group_image_detail']){
                MyHelper::deletePhoto($pg['product_group_image_detail']);
            }
        }
        return MyHelper::checkDelete($delete);
    }

    /**
     * Assign products to product group.
     * @return Response
     */
    public function assign(Request $request) {
        $products = $request->json('products');
        $id_product_group = $request->json('id_product_group');
        \DB::beginTransaction();
        $id_products = [];
        $updatex = [];
        foreach ($products as $id_variant1 => $variants2) {
            foreach($variants2 as $id_variant2 => $id_product){
                $id_products[] = $id_product;
                ProductProductVariant::where('id_product',$id_product)->delete();
                $insertData = [];
                $insertData[] = [
                    'id_product'=>$id_product,
                    'id_product_variant'=>$id_variant1
                ];
                $insertData[] = [
                    'id_product'=>$id_product,
                    'id_product_variant'=>$id_variant2
                ];
                $insert = ProductProductVariant::insert($insertData);
                $updatex[] = $insertData;
                if(!$insert){
                    \DB::rollback();
                    return MyHelper::checkCreate($insert);
                }
            }
        }
        $update = Product::where('id_product_group',$id_product_group)->update(['id_product_group'=>null]);
        $update = Product::whereIn('id_product',$id_products)->update(['id_product_group'=>$id_product_group]);
        if(!$update){
            \DB::rollback();
            return MyHelper::checkUpdate($update);
        }
        \DB::commit();
        return MyHelper::checkUpdate($update)+$updatex;
    }
    protected function checkAvailable($availables) {
        $avarr = explode(',', $availables);
        foreach ($avarr as $avail) {
            if($avail == 'Available'){
                return 'Available';
            }
        }
        return 'Sold Out';
    }
    // list product group yang ada di suatu outlet dengan nama, gambar, harga, order berdasarkan kategori
    public function tree(Request $request) {
        $post = $request->json()->all();
        $data = ProductGroup::select(\DB::raw('product_groups.id_product_group,product_groups.product_group_code,product_groups.product_group_name,product_groups.product_group_description,product_groups.product_group_photo,min(product_price) as product_price,product_groups.id_product_category,GROUP_CONCAT(product_stock_status) as product_stock_status'))
                    ->join('products','products.id_product_group','=','product_groups.id_product_group')
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    ->join('product_product_variants','products.id_product','=','product_product_variants.id_product')
                    ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
                    ->join('product_variants as parents','product_variants.parent','=','parents.id_product_variant')
                    ->join('brand_product','brand_product.id_product','=','product_product_variants.id_product')
                    // brand produk ada di outlet
                    ->where('brand_outlet.id_outlet','=',$post['id_outlet'])
                    ->join('brand_outlet','brand_outlet.id_brand','=','brand_product.id_brand')
                    // where active
                    ->where(function($query){
                        $query->where('product_prices.product_visibility','=','Visible')
                                ->orWhere(function($q){
                                    $q->whereNull('product_prices.product_visibility')
                                    ->where('products.product_visibility', 'Visible');
                                });
                    })
                    ->where('product_prices.product_status','=','Active')
                    ->whereNotNull('product_prices.product_price')
                    ->whereNotNull('product_groups.id_product_category')
                    // order by position
                    ->orderBy('products.position')
                    // group by product_groups
                    ->groupBy('product_groups.id_product_group');
                    // ->get();
        if (isset($post['promo_code'])) {
        	$data = $data->with('products');
        }

        $data = $data->get()->toArray();

        if(!$data){
            return MyHelper::checkGet($data);
        }

        // promo code
		foreach ($data as $key => $value) {
			$data[$key]['is_promo'] = 0;
		}
        if (isset($post['promo_code'])) {
        	$code=PromoCampaignPromoCode::where('promo_code',$request->promo_code)
	                ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
	                ->where('step_complete', '=', 1)
	                ->where( function($q){
	                	$q->whereColumn('usage','<','limitation_usage')
	                		->orWhere('code_type','Single');
	                } )
	                ->with([
						'promo_campaign.promo_campaign_product_discount.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign.promo_campaign_buyxgety_product_requirement.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign.promo_campaign_tier_discount_product.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign.promo_campaign_product_discount_rules',
						'promo_campaign.promo_campaign_tier_discount_rules',
						'promo_campaign.promo_campaign_buyxgety_rules'
					])
	                ->first();
	        if(!$code){
	            return [
	                'status'=>'fail',
	                'messages'=>['Promo code not valid']
	            ];
	        }else{

	        	if ($code['promo_campaign']['date_end'] < date('Y-m-d H:i:s')) {
	        		return [
		                'status'=>'fail',
		                'messages'=>['Promo campaign is ended']
		            ];
	        	}
	        	$code = $code->toArray();

		        if ( ($code['promo_campaign']['promo_campaign_product_discount_rules']['is_all_product']??false) == 1)
		        {
		        	$applied_product = '*';
		        }
		        elseif ( !empty($code['promo_campaign']['promo_campaign_product_discount']) )
		        {
		        	$applied_product = $code['promo_campaign']['promo_campaign_product_discount'];
		        }
		        elseif ( !empty($code['promo_campaign']['promo_campaign_tier_discount_product']) )
		        {
		        	$applied_product = $code['promo_campaign']['promo_campaign_tier_discount_product'];
		        }
		        elseif ( !empty($code['promo_campaign']['promo_campaign_buyxgety_product_requirement']) )
		        {
		        	// if buy x get y promo, applied product only for product x
		        	$applied_product = $code['promo_campaign']['promo_campaign_buyxgety_product_requirement'];

		        }
		        else
		        {
		        	$applied_product = [];
		        }

        		if ($applied_product == '*') {
        			foreach ($data as $key => $value) {
	        			$data[$key]['is_promo'] = 1;
						unset($data[$key]['products']);
    				}
        		}else{
        			if (isset($applied_product[0])) {
        				// loop available product
			        	foreach ($applied_product as $key => $value) {
			        		// loop product group
		        			foreach ($data as $key2 => $value2) {
		        				// loop product
		        				if (isset($value2['products'])) {
				        			foreach ($value2['products'] as $key3 => $value3) {
				        				if ( $value3['id_product'] == $value['id_product'] ) {
				    						$data[$key2]['is_promo'] = 1;
				    						break;
				    					}
				        			}
		        				}
		        			}
			        	}
        			}elseif(isset($applied_product['id_product'])){
        				foreach ($data as $key2 => $value2) {
	        				foreach ($value2['products'] as $key3 => $value3) {
								unset($data[$key2]['products']);
		        				if ( $value3['id_product'] == $applied_product['id_product'] ) {
		    						$data[$key2]['is_promo'] = 1;
		    						break;
		    					}
	        				}
	        			}
        			}
        		}
	        }
        }
        // end promo code

        $result = [];
        foreach ($data as $product) {
            $product['product_stock_status'] = $this->checkAvailable($product['product_stock_status']);
            $product['product_price'] = MyHelper::requestNumber($product['product_price'],$request->json('request_number'));
            $id_product_category = $product['id_product_category'];
            if(!isset($result[$id_product_category]['product_category_name'])){
                $category = ProductCategory::select('product_category_name','id_product_category')->find($id_product_category)->toArray();
                unset($category['url_product_category_photo']);
                $result[$id_product_category] = $category;
            }
            unset($product['id_product_category']);
            unset($product['products']);
            $result[$id_product_category]['products'][] = $product;
        }
        return MyHelper::checkGet(array_values($result));
    }
    public function product(Request $request) {
        $post = $request->json()->all();
        $query = Product::join('product_groups','products.id_product_group','=','product_groups.id_product_group')
                    ->where('product_groups.id_product_group',$post['id_product_group'])
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    ->join('brand_product','brand_product.id_product','=','products.id_product')
                    // brand produk ada di outlet
                    ->where('brand_outlet.id_outlet','=',$post['id_outlet'])
                    ->join('brand_outlet','brand_outlet.id_brand','=','brand_product.id_brand')
                    // where active
                    ->where(function($query){
                        $query->where('product_prices.product_visibility','=','Visible')
                                ->orWhere(function($q){
                                    $q->whereNull('product_prices.product_visibility')
                                    ->where('products.product_visibility', 'Visible');
                                });
                    })
                    ->where('product_prices.product_status','=','Active')
                    ->whereNotNull('product_prices.product_price');
        $query2 = clone $query;
        // get all product on this group
        $products = $query
            ->join('product_product_variants','products.id_product','=','product_product_variants.id_product')
            ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
            ->join('product_variants as parents','product_variants.parent','=','parents.id_product_variant')
            ->select(\DB::raw('products.id_product,product_prices.product_stock_status,GROUP_CONCAT(product_variants.product_variant_code order by parents.product_variant_position) as product_variant_code,product_prices.product_price'))->groupBy('products.id_product')->get('id_product')->toArray();
        $id_products = array_column($products, 'id_product');
        //get variant stock
        $variant_stock = [];
        foreach ($products as $product) {
            if($product['product_variant_code']){
                $varcode = explode(',',$product['product_variant_code']);
                if(count($varcode) !== 2) continue;
                $variant_stock[$varcode[0]][$varcode[1]] = [
                    'product_variant_code' => $varcode[1],
                    'product_stock_status' => $product['product_stock_status'],
                    'product_price' => $product['product_price']
                ];
            }
        }
        // product exists?
        if(!$id_products || !$variant_stock){
            return MyHelper::checkGet([]);
        }
        // get product lowest price and default variant
        $default = $query2
            ->select(\DB::raw('product_price,GROUP_CONCAT(CONCAT_WS(",",product_variants.parent,product_variants.id_product_variant) separator ";") as defaults'))
            ->leftJoin('product_product_variants','product_product_variants.id_product','=','products.id_product')
            ->leftJoin('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
            ->having('defaults','<>','')
            ->orderBy('product_price')
            ->groupBy('product_price')
            ->first();
        // get product group detail
        $data = ProductGroup::select('id_product_group','product_group_name','product_group_image_detail','product_group_code','product_group_description')->find($post['id_product_group'])->toArray();
        // get list product variant
        $variants = ProductProductVariant::select(
            'product_variants.id_product_variant',
            'product_variants.product_variant_name',
            'product_variants.product_variant_code',
            'product_product_variants.product_variant_price',
            't2.product_variant_title as type_title',
            't2.product_variant_position as type_position',
            't2.product_variant_subtitle as type_subtitle',
            't2.id_product_variant as parent_id')
            ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
            ->join('product_variants as t2','product_variants.parent','=','t2.id_product_variant')
            ->whereIn('product_product_variants.id_product',$id_products)->orderBy('product_variants.product_variant_position')->groupBy('product_variant_code')->get()->toArray();
        // set price to response
        $data['product_price'] = MyHelper::requestNumber($default['product_price'],$request->json('request_number'));
        // arrange default variant
        if($default['defaults']??false){
            $default['defaults'] = explode(';',$default['defaults']);
            $defaults = [];
            foreach ($default['defaults'] as $default) {
                if($default){
                    $exp = explode(',', $default);
                    $defaults[$exp[0]??''] = $exp[1]??'';
                }
            }
        }
        $arranged_variant = [];
        foreach ($variants as $key => $variant) {
            if(!isset($arranged_variant[$variant['parent_id']]['type_name'])){
                $arranged_variant[$variant['parent_id']]['type_title'] = $variant['type_title'];
                $arranged_variant[$variant['parent_id']]['type_position'] = $variant['type_position'];
                $arranged_variant[$variant['parent_id']]['type_subtitle'] = $variant['type_subtitle'];
            }
            $child = [
                'id_product_variant'=>$variant['id_product_variant'],
                'product_variant_code'=>$variant['product_variant_code'],
                'product_variant_name'=>$variant['product_variant_name'],
                'product_variant_price'=>MyHelper::requestNumber($variant['product_variant_price'],$request->json('request_number'))
            ];
            $child['default'] = ($defaults[$variant['parent_id']]??false) == $child['id_product_variant']?1:0;
            $arranged_variant[$variant['parent_id']]['childs'][$variant['product_variant_code']] = $child;
        }
        $arranged_variant = array_values($arranged_variant);
        // sorting by type position
        usort($arranged_variant, function($a,$b){
            return $a['type_position']<=>$b['type_position'];
        });
        $data['variants'] = $arranged_variant[0];
        unset($data['variants']['childs']);
        foreach ($variant_stock as $key => $vstock) {
            if($arranged_variant[0]['childs'][$key]??false){
                $stock = $arranged_variant[0]['childs'][$key];
                $child = $arranged_variant[1];
                unset($child['childs']);
                foreach ($arranged_variant[1]['childs'] as $vrn) {
                    if($variant_stock[$key][$vrn['product_variant_code']]??false){
                        $child['childs'][] = array_merge($vrn,$variant_stock[$key][$vrn['product_variant_code']]);
                    }
                }
                $stock['childs'] = $child;
                $data['variants']['childs'][]=$stock;
            }
        }
        // get available modifiers
        $posta = [
            'id_product' => $id_products,
            'id_outlet' => $post['id_outlet'],
            'id_product_category' => $data['id_product_category']??'',
            'id_brand' => $data['id_brand']??''
        ];
        $modifiers = ProductModifier::select('product_modifiers.id_product_modifier','code','type','text','product_modifier_stock_status','product_modifier_price as price')
            ->where(function($query) use($posta){
                $query->where('modifier_type','Global')
                ->orWhere(function($query) use ($posta){
                    $query->whereHas('products',function($query) use ($posta){
                        $query->whereIn('products.id_product',$posta['id_product']);
                    });
                    $query->orWhereHas('product_categories',function($query) use ($posta){
                        $query->where('product_categories.id_product_category',$posta['id_product_category']);
                    });
                    $query->orWhereHas('brands',function($query) use ($posta){
                        $query->where('brands.id_brand',$posta['id_brand']);
                    });
                });
            })
            ->join('product_modifier_prices',function($join) use ($posta){
                $join->on('product_modifier_prices.id_product_modifier','=','product_modifiers.id_product_modifier');
                $join->where('product_modifier_prices.id_outlet',$posta['id_outlet']);
            })->where('product_modifier_status','Active')
            ->where(function($query){
                $query->where('product_modifier_prices.product_modifier_visibility','=','Visible')
                        ->orWhere(function($q){
                            $q->whereNull('product_modifier_prices.product_modifier_visibility')
                            ->where('product_modifiers.product_modifier_visibility', 'Visible');
                        });
            })
            ->get()->toArray();
        $data['modifiers'] = array_values(MyHelper::groupIt($modifiers,'type',function($key,&$val) use ($request){
            $val['price'] = MyHelper::requestNumber($val['price'],$request->json('request_number'));
            return $key;
        },function($key,&$val){
            $newval['type'] = $key;
            $newval['modifiers'] = $val;
            $val = $newval;
            return $key;
        }));
        return MyHelper::checkGet($data);
    }
    public function search(Request $request) {
        $post = $request->json()->all();
        $data = ProductGroup::select(\DB::raw('product_groups.id_product_group,product_groups.product_group_code,product_groups.product_group_name,product_groups.product_group_description,product_groups.product_group_photo,min(product_price) as product_price,product_groups.id_product_category,GROUP_CONCAT(product_stock_status) as product_stock_status'))
                    ->join('products','products.id_product_group','=','product_groups.id_product_group')
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    ->join('product_product_variants','products.id_product','=','product_product_variants.id_product')
                    ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
                    ->join('product_variants as parents','product_variants.parent','=','parents.id_product_variant')
                    // where name like key_free
                    ->where('product_groups.product_group_name','like','%'.$post['key_free'].'%')
                    // where active
                    ->where(function($query){
                        $query->where('product_prices.product_visibility','=','Visible')
                                ->orWhere(function($q){
                                    $q->whereNull('product_prices.product_visibility')
                                    ->where('products.product_visibility', 'Visible');
                                });
                    })
                    ->where('product_prices.product_status','=','Active')
                    ->whereNotNull('product_prices.product_price')
                    ->whereNotNull('product_groups.id_product_category')
                    // order by position
                    ->orderBy('products.position')
                    // group by product_groups
                    ->groupBy('product_groups.id_product_group');

        if (isset($post['promo_code'])) {
        	$data = $data->with('products');
        }

        $data = $data->get()->toArray();

        if(!$data){
            return MyHelper::checkGet($data);
        }

        // promo code
		foreach ($data as $key => $value) {
			$data[$key]['is_promo'] = 0;
		}
        if (isset($post['promo_code'])) {
        	$code=PromoCampaignPromoCode::where('promo_code',$request->promo_code)
	                ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
	                ->where('step_complete', '=', 1)
	                ->where( function($q){
	                	$q->whereColumn('usage','<','limitation_usage')
	                		->orWhere('code_type','Single');
	                } )
	                ->with([
						'promo_campaign.promo_campaign_product_discount.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign.promo_campaign_buyxgety_product_requirement.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign.promo_campaign_tier_discount_product.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign.promo_campaign_product_discount_rules',
						'promo_campaign.promo_campaign_tier_discount_rules',
						'promo_campaign.promo_campaign_buyxgety_rules'
					])
	                ->first();
	        if(!$code){
	            return [
	                'status'=>'fail',
	                'messages'=>['Promo code not valid']
	            ];
	        }else{

	        	if ($code['promo_campaign']['date_end'] < date('Y-m-d H:i:s')) {
	        		return [
		                'status'=>'fail',
		                'messages'=>['Promo campaign is ended']
		            ];
	        	}
	        	$code = $code->toArray();

		        if ( ($code['promo_campaign']['promo_campaign_product_discount_rules']['is_all_product']??false) == 1)
		        {
		        	$applied_product = '*';
		        }
		        elseif ( !empty($code['promo_campaign']['promo_campaign_product_discount']) )
		        {
		        	$applied_product = $code['promo_campaign']['promo_campaign_product_discount'];
		        }
		        elseif ( !empty($code['promo_campaign']['promo_campaign_tier_discount_product']) )
		        {
		        	$applied_product = $code['promo_campaign']['promo_campaign_tier_discount_product'];
		        }
		        elseif ( !empty($code['promo_campaign']['promo_campaign_buyxgety_product_requirement']) )
		        {
		        	// if buy x get y promo, applied product only for product x
		        	$applied_product = $code['promo_campaign']['promo_campaign_buyxgety_product_requirement'];

		        }
		        else
		        {
		        	$applied_product = [];
		        }

        		if ($applied_product == '*') {
        			foreach ($data as $key => $value) {
	        			$data[$key]['is_promo'] = 1;
						unset($data[$key]['products']);
    				}
        		}else{
        			if (isset($applied_product[0])) {
        				// loop available product
			        	foreach ($applied_product as $key => $value) {
			        		// loop product group
		        			foreach ($data as $key2 => $value2) {
		        				// loop product
		        				if (isset($value2['products'])) {
				        			foreach ($value2['products'] as $key3 => $value3) {
				        				if ( $value3['id_product'] == $value['id_product'] ) {
				    						$data[$key2]['is_promo'] = 1;
				    						break;
				    					}
				        			}
		        				}
		        			}
			        	}
			        	// unset products
			        	foreach ($data as $key => $value) {
							unset($data[$key]['products']);
			        	}
        			}elseif(isset($applied_product['id_product'])){
        				foreach ($data as $key2 => $value2) {
	        				foreach ($value2['products'] as $key3 => $value3) {
								unset($data[$key2]['products']);
		        				if ( $value3['id_product'] == $applied_product['id_product'] ) {
		    						$data[$key2]['is_promo'] = 1;
		    						break;
		    					}
	        				}
	        			}
        			}
        		}
	        }
        }
        // end promo code

        $result = [];
        foreach ($data as $product) {
            $product['product_stock_status'] = $this->checkAvailable($product['product_stock_status']);
            $product['product_price'] = MyHelper::requestNumber($product['product_price'],$request->json('request_number'));
            unset($product['products']);
            $result[] = $product;
        }
        
        return MyHelper::checkGet(array_values($result));
    }
}
