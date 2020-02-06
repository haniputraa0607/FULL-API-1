<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ env('API_URL') }}css/transaction.css">
	<style type="text/css">
		@font-face {
            font-family: "Ubuntu-Bold";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_bold.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu-BoldItalic";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_bolditalic.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu-Italic";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_italic.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu-Light";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_light.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu-LightItalic";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_lightitalic.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu-Medium";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_medium.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu-MediumItalic";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_mediumitalic.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_regular.ttf') }}');
        }
        @font-face {
            font-family: "Ubuntu-Regular";
            font-style: normal;
            font-weight: 400;
            src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_regular.ttf') }}');
        }
        .Ubuntu-Bold{
            font-family: "Ubuntu-Bold";
        }
        .Ubuntu-BoldItalic{
            font-family: "Ubuntu-BoldItalic";
        }
        .Ubuntu-Italic{
            font-family: "Ubuntu-Italic";
        }
        .Ubuntu-Light{
            font-family: "Ubuntu-Light";
        }
        .Ubuntu-LightItalic{
            font-family: "Ubuntu-LightItalic";
        }
        .Ubuntu-Medium{
            font-family: "Ubuntu-Medium";
        }
        .Ubuntu-MediumItalic{
            font-family: "Ubuntu-MediumItalic";
        }
        .Ubuntu{
            font-family: "Ubuntu";
        }
        .Ubuntu-Regular{
            font-family: "Ubuntu-Regular";
        }
        .kotak {
    		margin : 10px;
    		padding: 16.7px 11.7px;
    		/*margin-right: 15px;*/
            -webkit-box-shadow: 0px 3.3px 10px 0px #eeeeee;
            -moz-box-shadow: 0px 3.3px 10px 0px #eeeeee;
            box-shadow: 0px 3.3px 10px 0px #eeeeee;
			/* border-radius: 3px; */
			background: #fff;
			border-radius: 10px;
    	}

    	body {
    		background: #ffffff;
    	}

    	.completed {
    		color: green;
    	}

    	.bold {
    		font-weight: bold;
    	}

    	.space-bottom {
    		padding-bottom: 15px;
    	}

    	.space-text {
    		padding-bottom: 10px;
    	}

    	.line-bottom {
    		border-bottom: 1px solid #eee;
    		margin-bottom: 15px;
    	}

    	.text-grey {
    		color: #707070;
    	}

    	.text-much-grey {
    		color: #bfbfbf;
    	}

    	.text-black {
    		color: #3d3935;
    	}

    	.text-medium-grey {
    		color: #806e6e6e;
    	}

		.text-dark-grey {
			color: rgba(0,0,0,0.7);
		}

		.text-grey-light {
            color: #b6b6b6;
        }

    	.text-grey-white {
    		color: #666;
    	}

    	.text-grey-black {
    		color: #4c4c4c;
    	}

    	.text-grey-red {
    		color: #9a0404;
    	}

    	.text-grey-green {
    		color: #049a4a;
    	}

    	.text-14-3px {
    		font-size: 14.3px;
    	}

    	.text-14px {
    		font-size: 14px;
    	}

    	.text-13-3px {
    		font-size: 13.3px;
    	}

    	.text-12-7px {
    		font-size: 12.7px;
    	}

    	.text-12px {
    		font-size: 12px;
    	}

    	.text-11-7px {
    		font-size: 11.7px;
    	}

		hr {
			/* background: rgba(149, 152, 154, 0.3); */
			margin-top: 10px;
			margin-bottom: 10px;
		}

		.margin-10px {
			margin-right: -10px;
			margin-left: -10px;
		}

		.margin-top5px{
			margin-top: 5px;
		}
    </style>
  </head>
  <body>
	{{ csrf_field() }}
	
	<div class="container">
		<div class="row">
            <div class="col-12 text-black text-16-7px Ubuntu-Medium" style="margin-top:10px">
                <span>{{ $data['detail']['outlet']['outlet_name'] }}</span>
                <hr style="margin: 15px 0px;border-top: dashed 1px #D7D2CB;"/>
            </div>
            <div class="col-7 text-left text-black text-14px Ubuntu-Medium">#TRX-{{ $data['detail']['transaction_receipt_number'] }}</div>
            <div style="color: #707070;" class="col-5 text-right text-13-3px Ubuntu">{{ date('d M Y H:i', strtotime($data['detail']['transaction_date'])) }}</div>
            <div class="col-12 text-black text-13-3px Ubuntu-Medium" style="margin-top: 15px;">Your Transaction</div>
        </div>
	</div>
	@if ($data['balance'] > 0)
	<div class="kotak">
		<div class="row">
			<div class="col-6 text-13-3px text-black Ubuntu-Medium">Total Payment</div>
			<div class="col-6 text-right text-13-3px text-black Ubuntu-Medium">{{ str_replace(',', '.', number_format($data['grand_total'])) }}</div>
		</div>
	</div>
	<div class="kotak" style="box-shadow: none;background-color: #f0f3f7;border-radius: 5px;">
		<div class="row">
			<div class="col-6 text-13-3px text-black Ubuntu-Medium">Point Earned</div>
			<div class="col-6 text-right text-13-3px text-black Ubuntu-Medium">@if($data['balance'] > 0) + {{ str_replace(',', '.', number_format($data['balance'])) }} @else {{ str_replace(',', '.', number_format($data['balance'])) }}  @endif points</div>
		</div>
	</div>
	@else
	<div class="kotak">
		<div class="row">
			<div class="col-6 text-13-3px text-black Ubuntu-Medium">Total Payment</div>
			<div class="col-6 text-right text-13-3px text-black Ubuntu-Medium">{{ str_replace(',', '.', number_format($data['grand_total'])) }}</div>
		</div>
	</div>
	<div class="kotak" style="box-shadow: none;background-color: #f0f3f7;border-radius: 5px;">
		<div class="row">
			<div class="col-6 text-13-3px text-black Ubuntu-Medium">Point Used</div>
			<div style="color: #b72126;" class="col-6 text-right text-13-3px Ubuntu-Medium">@if($data['balance'] > 0) + {{ str_replace(',', '.', number_format($data['balance'])) }} @else {{ str_replace(',', '.', number_format($data['balance'])) }}  @endif points</div>
		</div>
	</div>
	@endif
  	{{-- <div class="kotak">
		<div class="row">
			@foreach ($data['detail']['product_transaction'] as $key => $item)
				<div class="col-2 text-13-3px Ubuntu text-right" style="color: #ff9d6e;">{{$item['transaction_product_qty']}}x</div>
				<div class="col-8 text-14px Ubuntu-Medium text-black" style="margin-left: -20px;">{{$item['product']['product_name']}}</div>
				<div class="col-2 text-13-3px text-right Ubuntu text-black">{{ str_replace(',', '.', number_format(explode('.',$item['transaction_product_price'])[0])) }}</div>
				@if ($item['product']['product_discounts'] != [])
					<div class="col-2 text-13-3px Ubuntu text-black">{{$item['transaction_product_qty']}}x</div>
					<div class="col-8 text-13-3px Ubuntu text-black" style="margin-left: -20px;">{{$item['product']['product_name']}}</div>
					<div class="col-2 text-13-3px text-right Ubuntu text-black">{{ str_replace(',', '.', number_format($data['transaction_subtotal'])) }}</div>
				@endif
			@endforeach
		</div>
		
		<hr style="margin: 15px 10px;border-top: dashed 1px #D7D2CB;"/>
		@if($data['balance'] > 0)
		<div class="row">
			<div class="col-6 text-13-3px text-black Ubuntu-Medium">Total Payment</div>
			<div class="col-6 text-right text-13-3px text-black Ubuntu-Medium">{{ str_replace(',', '.', number_format($data['grand_total'])) }}</div>
		</div>
		<div class="row">
			<div class="col-12"><hr style="margin-bottom: 20px;margin-top: 16.7px;border-top: dashed 1px #D7D2CB;"></div>
			<br>
			<div class="col-6 text-13-3px text-black Ubuntu-Medium">Earn {{env('POINT_NAME', 'Points')}}</div>
			<div class="col-6 text-right text-13-3px text-dark-grey Ubuntu-Medium">@if($data['balance'] > 0) + {{ str_replace(',', '.', number_format($data['balance'])) }} @else {{ str_replace(',', '.', number_format($data['balance'])) }}  @endif points</div>
		</div>
		@else
		<div class="row space-text">
			@php $countItem = 0; @endphp
			@foreach($data['detail']['product_transaction'] as $productTransaction)
				@php $countItem += $productTransaction['transaction_product_qty']; @endphp
			@endforeach
			<div class="col-6 text-13-3px text-black Ubuntu">Subtotal ({{$countItem}} item) </div>
			<div class="col-6 text-right text-13-3px text-dark-grey Ubuntu">{{ str_replace(',', '.', number_format($data['detail']['transaction_grandtotal'])) }}</div>
		</div>
		<div class="row">
			<div class="col-6 text-13-3px text-black Ubuntu">{{env('POINT_NAME', 'Points')}}</div>
			<div class="col-6 text-right text-13-3px text-dark-grey Ubuntu">@if($data['balance'] > 0) + @endif {{ str_replace(',', '.', number_format($data['balance'])) }}</div>
			<div class="col-12"><hr></div>
		</div>
		<div class="row space-text">
			<div class="col-6 text-13-3px text-black Ubuntu ">Total Pembayaran</div>
			<div class="col-6 text-right text-13-3px text-dark-grey Ubuntu">Rp {{ str_replace(',', '.', number_format($data['grand_total'] + $data['balance'])) }}</div>
		</div>
		@endif
  	</div> --}}



    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="{{ env('API_URL') }}js/jquery.js"></script>
    <script src="{{ env('API_URL') }}js/transaction.js"></script>

  </body>
</html>