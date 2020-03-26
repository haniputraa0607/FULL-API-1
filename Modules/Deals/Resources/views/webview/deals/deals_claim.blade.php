<?php
    use App\Lib\MyHelper;
    $title = "Deals Detail";
?>
@extends('webview.main')

@section('css')
	<link rel="stylesheet" href="{{env('API_URL')}}css/deals.css">
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
    		height: auto;
    	}
    	.title-wrapper{
    		background-color: #f8f8f8;
    		position: relative;
    		display: flex;
    	}
    	.col-left{
    		flex: 70%;
    	}
    	.col-right{
    		flex: 30%;
    	}
    	.title-wrapper > div{
    		padding: 10px 5px;
    	}
    	.title{
    		font-size: 18px;
    		color: rgba(32, 32, 32);
    	}
    	#timer{
    		position: absolute;
    		right: 0px;
			bottom:0px;
			width: 100%;
    		padding: 10px;
    		/*border-bottom-left-radius: 7px !important;*/
    		color: #fff;
            display: none;
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
		.bg-pale-teal {
			background-color: #8fd6bd;
		}
		.pale-teal{
			color: #8fd6bd;
		}
		.dark-sea-green {
			color: #10704e;
		}
    	.fee{
			margin-top: 30px;
			font-size: 18px;
			color: #000;
    	}
    	.description-wrapper{
    		padding: 20px;
    	}
		.outlet-wrapper{
		    padding: 0 20px;
		}
    	.description{
    	    padding-top: 10px;
    	    font-size: 14px;
			height: 20px;
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

        @media only screen and (min-width: 768px) {
            /* For mobile phones: */
            .deals-img{
	    		width: auto;
	    		height: auto;
	    	}
        }
		.card {
			box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
			transition: 0.3s;
			width: 100%;
			border-radius: 10px;
			background-repeat: no-repeat;
			background-size: 40% 100%;
			background-position: right;
		}

		.card:hover {
			box-shadow: 0 0 5px 0 rgba(0,0,0,0.1);
		}

		 .image-4 {
         clip-path: polygon(30% 0, 100% 0, 100% 100%, 0 100%);
		}
        body {
            background-color: #ffffff;
        }
    </style>
@stop

@section('content')
	<div class="deals-detail">
		@if(!empty($deals))
			<div class="col-md-4 col-md-offset-4" style="background-color: #ffffff;">
				<div style="background: url('{{env('API_URL')}}img/asset/bg_card_membership.png');background-size: contain;padding: 10px;box-shadow: 0 0.7px 3.3px #eeeeee;" class="col-md-12 clearfix Ubuntu">
					<div class="text-center title Ubuntu-Medium pale-teal" style="font-size: 16.7px;">
						Horayy!
					</div>
					<div class="text-center Ubuntu-Medium" style="font-size: 14px;color: #333333;margin-top: 10px;">
						Thankyou for buying
					</div>
					<div style="position: relative;margin-top: 20px;">
						<div style="width: 56%;height: 100px;position: absolute;top: 10%;left: 40%;">
							<div class="cotainer">
								<div style="margin-top: 5px;">
									<p class="Ubuntu-Medium" style="font-size: 14px;color: #333333;">{{$deals['deals_voucher']['deal']['deals_title']}}</p>
									{{-- <p style="font-size: 13.3px;color: #333333;">{{$deals['deals_voucher']['deal']['deals_second_title']}}</p> --}}
									<div style="margin-top: 23px;"></div>
									<p class="Ubuntu" style="font-size: 11.7px;color: #707070;padding: 5px 10px;background-color: #f8f9fb;border-radius: 100px;">Valid until <span style="color: #333333;">{{date('d F Y', strtotime($deals['deals_voucher']['deal']['deals_end']))}}</span></p>
								</div>
							</div>
						</div>
						<img src="{{ env('S3_URL_API').$deals['deals_voucher']['deal']['deals_image'] }}" alt="" style="width: 85px;position: absolute;border-radius: 50%;top: 14.5%;left: 6.5%;">
						<img style="width:100%" height="130px" src="{{ env('S3_URL_API')}}img/asset/bg_item_kupon_saya.png" alt="">
					</div>
				</div>

				<div style="background-color: #ffffff;" class="title-wrapper col-md-12 clearfix Ubuntu-Bold">
					<div class="title" style="font-size: 14px; color: #333333;">Transaction</div>
				</div>

				<div style="background-color: #ffffff;padding-top: 0px;padding-bottom: 16.7px;color: #aaaaaa;font-size: 12.7px;" class="description-wrapper Ubuntu">
					<div class="row">
						<div class="description col-6 Ubuntu-SemiBold">Date</div>
						<div style="color: #3d3935;" class="description col-6 text-right">{{date('d M Y H:i', strtotime($deals['claimed_at']))}}</div>
					</div>
					<div class="row" style="margin-top: 16.7px;">
						<div class="description col-6 Ubuntu-SemiBold">Transaction ID</div>
						<div style="color: #3d3935;" class="description col-6 text-right">{{strtotime($deals['claimed_at'])}}</div>
					</div>
				</div>

				<div style="background-color: #ffffff;padding-top: 0px;padding-bottom: 0px;color: #aaaaaa;font-size: 12.7px;" class="description-wrapper Ubuntu">
					<hr style="padding-top: 16.7px;margin-bottom: 0px;border-top-style: dashed;">
				</div>

				@php
					if ($deals['voucher_price_point'] != null) {
						$payment = number_format($deals['voucher_price_point'],0,",",".").' points';
					} elseif ($deals['voucher_price_cash'] != null) {
						$payment = number_format($deals['voucher_price_cash'],0,",",".");
					} else {
						$payment = 'Free';
					}
				@endphp
				<div style="background-color: #ffffff;padding-top: 0px;padding-bottom: 0px;color: #aaaaaa;font-size: 12.7px;" class="description-wrapper Ubuntu">
					<div class="row">
						<div style="color: #333333;" class="description col-6 Ubuntu-Medium">Total Payment</div>
						<div style="color: #333333;" class="description col-6 text-right Ubuntu-Medium">{{$payment}}</div>
					</div>
				</div>

				<div style="background-color: #ffffff;padding-top: 0px;color: rgb(0, 0, 0);height: 80px;position: fixed;bottom: 10px;width: 100%;" class="description-wrapper Ubuntu">
					<a style="width:100%;background-color: #8fd6bd;color: #10704e;font-size: 15px;" class="btn btn-lg Ubuntu-Bold" href="#yes">View Voucher</a>
				</div>
			</div>
		@else
			<div class="col-md-4 col-md-offset-4">
				<h4 class="text-center" style="margin-top: 30px;">Deals is not found</h4>
			</div>
		@endif
	</div>
@stop
