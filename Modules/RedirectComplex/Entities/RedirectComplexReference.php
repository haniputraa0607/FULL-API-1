<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 06 Aug 2020 15:39:29 +0700.
 */

namespace Modules\RedirectComplex\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class RedirectComplexReference
 * 
 * @property int $id_redirect_complex_reference
 * @property string $type
 * @property string $outlet_type
 * @property string $promo_type
 * @property string $promo_reference
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property \Illuminate\Database\Eloquent\Collection $redirect_complex_outlets
 * @property \Illuminate\Database\Eloquent\Collection $redirect_complex_products
 *
 * @package Modules\RedirectComplex\Entities
 */
class RedirectComplexReference extends Eloquent
{
	protected $primaryKey = 'id_redirect_complex_reference';

	protected $appends  = [
		// 'get_promo'
	];

	protected $fillable = [
		'type',
		'name',
		'outlet_type',
		'promo_type',
		'promo_reference'
	];

	public function redirect_complex_outlets()
	{
		return $this->hasMany(\Modules\RedirectComplex\Entities\RedirectComplexOutlet::class, 'id_redirect_complex_reference');
	}

	public function redirect_complex_products()
	{
		return $this->hasMany(\Modules\RedirectComplex\Entities\RedirectComplexProduct::class, 'id_redirect_complex_reference');
	}

	public function outlets()
	{
		return $this->belongsToMany(\App\Http\Models\Outlet::class, 'redirect_complex_outlets', 'id_redirect_complex_reference', 'id_outlet')
					->withPivot('id_outlet')
					->withTimestamps()->orderBy('id_outlet', 'DESC');
	}

	public function products()
	{
		return $this->belongsToMany(\App\Http\Models\Product::class, 'redirect_complex_products', 'id_redirect_complex_reference', 'id_product')
					->select('product_categories.*','products.*')
					->leftJoin('product_categories', 'product_categories.id_product_category', '=', 'products.id_product_category')
					->withPivot('id_redirect_complex_product', 'qty')
					->withTimestamps();
	}

	public function promo_campaign()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaign::class, 'promo_reference', 'id_promo_campaign');
	}

	public function getGetPromoAttribute() {

        if( $this->promo_type == 'promo_campaign')
        {	
			$this->load(['promo_campaign.promo_campaign_promo_codes' => function($q) {
							$q->first();
						}]);
        }
    }

    public function getProductAttribute(){
		$use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();
		$id_outlet = $this->id_outlet;
		$id_product = $this->id_product;
		$product_qty = $this->product_qty;

		if($use_product_variant){
			$product_group = ProductGroup::select(\DB::raw('product_groups.id_product_group,product_group_name,product_group_code,product_group_description,product_group_photo,product_prices.product_price as price'))
				->join('products','products.id_product_group','=','product_groups.id_product_group')
				->where('products.id_product',$id_product)
				->join('product_prices','products.id_product','=','product_prices.id_product')
				->where('product_prices.id_outlet',$id_outlet)
				->groupBy('products.id_product')->first()->toArray();
			$product_group['variants'] = ProductVariant::select('product_variants.id_product_variant','product_variants.product_variant_name','product_variants.product_variant_code')
				->join('product_product_variants','product_product_variants.id_product_variant','product_variants.id_product_variant')
				->join('product_variants as parent','parent.id_product_variant', '=', 'product_variants.parent')
				->where('product_product_variants.id_product',$id_product)
				->orderBy('parent.product_variant_position')
				->get()->toArray();
			unset($product_group['products']);
			return $product_group;
		}

		$product = Product::select('id_product','product_name','product_code','product_description')->where([
			'id_product'=>$id_product
		])->with([
			'photos' => function($query){
				$query->select('id_product','product_photo')->limit(1);
			}
		])->first();
		return [
			'product_name' => $product->product_name,
			'product_code' => $product->product_code,
			'product_description' => $product->product_description,
			'url_product_photo' => optional($product->photos[0]??null)->url_product_photo?:env('S3_URL_API').'img/product/item/default.png',
			'price' => $this->getProductPrice($id_outlet,$id_product)
		];
	}
}
