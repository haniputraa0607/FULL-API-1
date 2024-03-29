<?php
use App\Lib\MyHelper;
$title = "Deals Detail";
?>
@extends('webview.main')

@section('css')
	<link rel="stylesheet" href="{{env('API_URL')}}css/voucher.css">
	<style type="text/css">
		p{
			margin-top: 0px !important;
			margin-bottom: 0px !important;
		}
		.deals-detail > div{
			padding-left: 0px;
			padding-right: 0px;
		}
		.deals-img{
			width: 100%;
		}
		.title-wrapper{
    		background-color: #ffffff;
    		position: relative;
    		display: flex;
    		align-items: center;
    	}
		.col-left{
			flex: 70%;
		}
		.col-right{
			flex: 30%;
		}
		.title-wrapper > div{
			padding: 10px 15px;
		}
		.title{
			font-size: 18px;
			color: rgba(32, 32, 32);
		}
		.bg-yellow{
			background-color: #d1af28;
		}
		.bg-red{
			background-color: #c02f2fcc;
		}
		.bg-black{
			background-color: #000c;
		}

		.bg-yellow{
			background-color: #d1af28;
		}
		.bg-red{
			background-color: #c02f2fcc;
		}
		.bg-black{
			background-color: rgba(0, 0, 0, 0.5);
		}
		.bg-grey{
			background-color: #cccccc;
		}
		.fee{
			margin-top: 30px;
			font-size: 18px;
			color: #000;
		}
		.description-wrapper{
			padding: 20px;
    		background-color: #ffffff;
		}
		.outlet-wrapper{
			padding: 0 20px;
		}
		.description{
			padding-top: 10px;
			font-size: 14px;
		}
		.subtitle{
			margin-bottom: 10px;
			color: #000;
			font-size: 15px;
		}
		.outlet{
			font-size: 13.5px;
		}
		.outlet-city:not(:first-child){
			margin-top: 10px;
		}

		.voucher{
			margin-top: 30px;
		}
		.font-red{
			color: #990003;
		}
		.subtitle2{
			margin-bottom: 20px;
			color: #aaaaaa;
			font-size: 15px;
		}
		.kode-text{
			margin: 20px 0 8px;
			color: #aaaaaa;
			font-size: 18px;
		}
		.voucher-code{
			font-size: 22px;
		}

		#invalidate {
			color:#fff;
			background-color: #990003;
			border: none;
			border-radius: 5px;
			margin-bottom: 70px;
			margin-top: 30px;
			width: 90%;
			height: 48px;
			font-size: 18px;
		}
		#qr-code-modal{
			position: fixed;
			top: 0;
			bottom: 0;
			left: 0;
			right: 0;
			background: rgba(0,0,0, 0.5);
			/*width: 100%;*/
			/*height: 100vh;*/
			display: none;
			z-index: 999;
			overflow-y: auto;
		}
		#qr-code-modal-content{
			position: absolute;
			left: 50%;
			top: 50%;
			margin-left: -155px;
			margin-top: -155px;
			padding: 30px;
			background: #fff;
			border-radius: 42.3px;
			border: 0;
		}

		.deals-qr {
			background: #fff;
			width: 135px;
			height: 135px;
			margin: 0 auto;
		}

		.card {
			background-color: rgb(248, 249, 251);
			transition: 0.3s;
			width: 100%;
			border-radius: 10px;
			background-repeat: no-repeat;
			background-size: 40% 100%;
			background-position: right;
		}

		#timer{
			right: 0px;
			bottom:0px;
			width: 100%;
		}
		#day{
			right: 0px;
			bottom:0px;
			width: 100%;
			padding: 5px;
		}
		.card:hover {
			box-shadow: 0 0 5px 0 rgba(0,0,0,0.1);
		}

		@media only screen and (min-width: 768px) {
			.deals-img{
				width: auto;
			}
		}
		.tab-head{
			padding-left: 0px !important;
			padding-right: 0px !important;
		}
		.nav-item a:focus{
			outline: unset;
		}
		.nav-item a:hover{
			border: 1px solid #fff !important;
		}
		.nav-item a{
			color: #d7d2cb !important;
		}
		.nav-item .active{
			color: #10704e !important;
			border:none !important;
			border-bottom: 3px solid #10704e !important;
			border-radius: 3px;
		}
		.nav-item .active:hover{
			border:none !important;
			border-bottom: 3px solid #10704e !important;
		}
		.nav-tabs{
			border-bottom: none;
			overflow-x: auto;
			overflow-y: hidden;
			display: -webkit-box;
			display: -moz-box;
		}
		.nav-tabs>li {
			float:none;
		}
		.nav>li>a:focus, .nav>li>a:hover {
			background-color: transparent;
		}
		::-webkit-scrollbar {
			width: 0px;
			background: transparent; /* make scrollbar transparent */
		}
	</style>
@stop

@section('content')
	<div class="deals-detail">
		@if(!empty($voucher))
			@php
				$voucher = $voucher['data'][0];
			@endphp
			<div class="col-md-4 col-md-offset-4" style="background-color: #eeeeee;">
				<!-- Modal QR Code -->
				@if(isset($voucher['redeemed_at']) && $voucher['redeemed_at'] != null || isset($voucher['used_at']) && $voucher['used_at'] == null)
					<img class="deals-img center-block" src="{{ env('S3_URL_API').$voucher['deal_voucher']['deal']['deals_image'] }}" alt="">
					<div class="title-wrapper clearfix Ubuntu-Bold">
						<div class="title" style="color: #3d3935;font-size: 20px;">
							{{ $voucher['deal_voucher']['deal']['deals_title'] }}
							@if($voucher['deal_voucher']['deal']['deals_second_title'] != null)
							<br>
							<p style="color: #3d3935;font-size: 15px;" class="Ubuntu-Regular">{{ $voucher['deal_voucher']['deal']['deals_second_title'] }}</p>
							@endif
						</div>
					</div>

					@if($voucher['deal_voucher']['deal']['deals_description'] != "")
					<div class="title-wrapper Ubuntu-Regular">
						<div class="description" style="font-size: 12.7px;color: #3d3935;">{!! $voucher['deal_voucher']['deal']['deals_description'] !!}</div>
					</div>
					@endif
					<div class="title-wrapper clearfix" style="padding-bottom: 15px;">
						<p class="Ubuntu" style="font-size: 10.7px;color: #3d3935;margin-left: 15px;padding: 5px 10px;background-color: #f0f3f7;border-radius: 100px;">Valid until {{date('d F Y', strtotime($voucher['deal_voucher']['deal']['deals_end']))}}</p>
					</div>

					{{-- <a id="qr-code-modal" href="#">
						<div id="qr-code-modal-content">
							<img class="img-responsive" src="{{ $voucher['voucher_hash'] }}">
						</div>
					</a> --}}
					<div style="height: 10px;margin: 0px;padding: 0px;"></div>

					<div class="description-wrapper Ubuntu">
						<div class="subtitle2 text-center Ubuntu-Medium" style="font-size: 12.7px;color: #b72126;">
							<span> QR Code below </span>
							<br>
							<span> must be scanned by our Cashier </span>
						</div>

						<div class="deals-qr" style="margin-top: 10px;">
							<img class="img-responsive" style="display: block; max-width: 100%;" src="{{ $voucher['voucher_hash'] }}">
						</div>

						<center class="kode-text Ubuntu" style="color: #666666;font-size: 12.7px;">Kode Voucher</center>
						<center class="voucher-code font-red Ubuntu-Medium" style="color: #202020;font-size: 17.3px;">{{ $voucher['deal_voucher']['voucher_code'] }}</center>
						<div class="line"></div>
					</div>
				@else
					<div style="background: url('{{env('API_URL')}}img/asset/bg_card_membership.png');background-size: contain;padding: 10px;height: 190px;" class="col-md-12 clearfix Ubuntu">
						<div style="position: relative;margin-top: 26.7px;">
							<div style="width: 56%;height: 100px;position: absolute;top: 10%;left: 40%;">
								<div class="cotainer">
									<div class="pull-left" style="margin-top: 10px;">
										<p class="Ubuntu-Medium" style="font-size: 14px;color: #333333;">{{$voucher['deal_voucher']['deal']['deals_title']}}</p>
										{{-- <p style="font-size: 13.3px;color: #333333;">{{$deals['deals_voucher']['deal']['deals_second_title']}}</p> --}}
										<div style="margin-top: 23px;"></div>
										<p class="Ubuntu" style="font-size: 10.7px;color: #3d3935;padding: 5px 10px;background-color: #f0f3f7;border-radius: 100px;">Valid until {{date('d F Y', strtotime($voucher['deal_voucher']['deal']['deals_end']))}}</p>
									</div>
								</div>
							</div>
							<img src="{{ env('S3_URL_API').$voucher['deal_voucher']['deal']['deals_image'] }}" alt="" style="width: 85px;position: absolute;border-radius: 50%;top: 14.5%;left: 6.5%;">
							<img style="width:100%" height="130px" src="{{ env('S3_URL_API')}}img/asset/bg_item_kupon_saya.png" alt="">
						</div>
					</div>

					<div class="container" style="background-color: #ffffff;">
						<div class="col-12" style="padding: 10px 15px;padding-bottom: 0px;">
							<ul class="nav nav-tabs Ubuntu-Bold" id="myTab" role="tablist" style="font-size: 14px;">
								<li class="nav-item">
									<a class="nav-link active" id="ketentuan-tab" data-toggle="tab" href="#ketentuan" role="tab" aria-controls="ketentuan" aria-selected="true">Terms</a>
								</li>
								<li class="nav-item">
									<a class="nav-link" id="howuse-tab" data-toggle="tab" href="#howuse" role="tab" aria-controls="howuse" aria-selected="false">How to Use</a>
								</li>
								<li class="nav-item">
									<a class="nav-link" id="outlet-tab" data-toggle="tab" href="#outlet" role="tab" aria-controls="outlet" aria-selected="false">Available at</a>
								</li>
							</ul>
						</div>
						<div class="tab-content mt-4 Ubuntu-Regular" id="myTabContent" style="padding: 0 15px;padding-bottom: 5px;font-size: 12.7px;color: #707070;">
							<div class="tab-pane fade show active" id="ketentuan" role="tabpanel" aria-labelledby="ketentuan-tab">
								@if($voucher['deal_voucher']['deal']['deals_tos'] != "")
									{!! $voucher['deal_voucher']['deal']['deals_tos'] !!}
								@endif
							</div>
							<div class="tab-pane fade" id="howuse" role="tabpanel" aria-labelledby="howuse-tab">
								<p>Comming Soon</p>
							</div>
							<div class="tab-pane fade" id="outlet" role="tabpanel" aria-labelledby="outlet-tab">
								@foreach($voucher['deal_voucher']['deal']['outlet_by_city'] as $key => $outlet_city)
									<div class="outlet-city">@if(isset($outlet_city['city_name'])){{ $outlet_city['city_name'] }}@else - @endif</div>
									<ul class="nav">
										@foreach($outlet_city['outlet'] as $key => $outlet)
											<li>- {{ $outlet['outlet_name'] }}</li>
										@endforeach
									</ul>
								@endforeach
							</div>
						</div>
					</div>
					<hr width="100%" style="margin-top: 10px;margin-bottom: 0px;">
					<div class="container" style="border-top: 3.3px solid #8fd6bd;background-color: #ffffff;">
						<div style="padding-top: 15px;">
							@if ($voucher['is_used'] == 1)
								@if ($voucher['is_online'] == 1)
									<p class="col-12 Ubuntu-Medium" style="font-size: 13.3px;color: #333333;">Online Transaction</p>
									<p class="col-12 Ubuntu-Regular" style="font-size: 11.3px;color: #707070;">Apply promo on this app</p>
									<center>
										<a href="{{url()->current()}}#use_later" style="outline:none; font-size:15px; margin-bottom: 15px; margin-top: 15px; background-color: #b72126; color: #ffffff;padding: 15px;" id="invalidate" class="btn Ubuntu-Bold">Use Later</a>
									</center>
								@endif
								@if ($voucher['is_offline'] == 1)
									<p class="col-12 Ubuntu-Medium" style="font-size: 13.3px;color: #333333;">Offline Transaction</p>
									<p class="col-12 Ubuntu-Regular" style="font-size: 11.3px;color: #707070;">Redeem directly at Cashier</p>
									<center>
										<button disabled style="outline:none; font-size:15px; margin-bottom: 15px; margin-top: 15px; background-color: #cccccc; color: #ffffff" type="button" id="invalidate" class="btn Ubuntu-Bold">Redeem to Cashier</button>
									</center>
								@endif
							@else
								@if ($voucher['is_online'] == 1)
									<p class="col-12 Ubuntu-Medium" style="font-size: 13.3px;color: #333333;">Online Transaction</p>
									<p class="col-12 Ubuntu-Regular" style="font-size: 11.3px;color: #707070;">Apply promo on this app</p>
									<center>
										<a href="{{url()->current()}}#use_voucher" style="outline:none; font-size:15px; margin-bottom: 15px; margin-top: 15px; background-color: #8fd6bd; color: #10704e;padding: 15px;" id="invalidate" class="btn Ubuntu-Bold">Use Voucher</a>
									</center>
								@endif
								@if ($voucher['is_offline'] == 1)
									<p class="col-12 Ubuntu-Medium" style="font-size: 13.3px;color: #333333;">Offline Transaction</p>
									<p class="col-12 Ubuntu-Regular" style="font-size: 11.3px;color: #707070;">Redeem directly at Cashier</p>
									<center>
										<a href="{{url()->current()}}#redeem_to_cashier" style="outline:none; font-size:15px; margin-bottom: 15px; margin-top: 15px; background-color: #333333; color: #ffffff;padding: 15px;" id="invalidate" class="btn Ubuntu-Bold">Redeem to Cashier</a>
									</center>
								@endif
							@endif
						</div>
					</div>
				@endif
			</div>
		@else
			<div class="col-md-4 col-md-offset-4">
				<h4 class="text-center" style="margin-top: 30px;">Voucher not found</h4>
			</div>
		@endif
	</div>
@stop

@section('page-script')
	<script src="{{env('API_URL')}}js/jquery.js"></script>
	<script src="{{env('API_URL')}}js/popper.js"></script>
	<script src="{{env('API_URL')}}js/voucher.js"></script>
@stop
