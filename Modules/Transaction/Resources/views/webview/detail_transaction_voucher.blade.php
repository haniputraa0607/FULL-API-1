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

        .space-bottom20 {
            padding-bottom: 20px;
        }

        .space-top {
            padding-top: 15px;
        }

        .space-text {
            padding-bottom: 10px;
        }

        .line-bottom {
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }

        .text-grey {
            color: rgb(182, 182, 182);
        }
        .text-grey2 {
            color: rgba(0, 0, 0, 0.6);
        }

        .text-much-grey {
            color: #bfbfbf;
        }

        .text-black {
            color: #3d3d3d;
        }

        .text-red {
            color: #b72126;
        }

        .text-medium-grey {
            color: #806e6e6e;
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

        .text-grey-red-cancel {
            color: rgba(154,4,4,1);
        }

        .text-grey-blue {
            color: rgba(0,140,203,1);
        }

        .text-grey-yellow {
            color: rgba(227,159,0,1);
        }

        .text-grey-green {
            color: rgba(4,154,74,1);
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
    </style>
  </head>
  <body>
    {{ csrf_field() }}
    @php
        // print_r($data);die();
    @endphp
    <div class="container">
        <div class="row">
            <div class="col-12 text-black text-16-7px Ubuntu-Medium" style="margin-top:10px">
                <span>Buy Voucher</span>
                <hr style="margin: 15px 0px;border-top: dashed 1px #D7D2CB;"/>
            </div>
            <div class="col-7 text-left text-black text-14px Ubuntu-Medium">#TRX-{{ $data['voucher_hash_code'] }}</div>
            <div style="color: #707070;" class="col-5 text-right text-13-3px Ubuntu">{{ date('d M Y H:i', strtotime($data['date'])) }}</div>
            <div class="col-12 text-black text-13-3px Ubuntu-Medium" style="margin-top: 15px;">Your Transaction</div>
        </div>
    </div>
    <div class="kotak">
        <div class="row">
            <div style="color: #8fd6bd;" class="col-1 text-12-7px Ubuntu-Medium">1x</div>
            <div class="col-8 text-12-7px text-black Ubuntu-Medium">Use {{$data['deal_voucher']['deal']['deals_title']}}</div>
            <div class="col-3 text-12-7px text-black text-right Ubuntu-Medium">@if ($data['voucher_price_point'] != null) {{number_format($data['voucher_price_point'], 0, ',', '.')}} @else {{number_format($data['voucher_price_cash'], 0, ',', '.')}} @endif</div>
        </div>
    </div>
    <div class="container">
        <div class="row">
            <div class="col-12 text-black text-13-3px Ubuntu-Medium" style="margin-top: 15px;">Payment Details</div>
        </div>
    </div>
    <div class="kotak" style="box-shadow: none;background-color: #f0f3f7;">
        <div class="row">
            <div class="col-6 text-13-3px text-black Ubuntu-Medium">Grand Total</div>
            <div class="col-6 text-13-3px text-black text-right Ubuntu-Medium">@if ($data['voucher_price_point'] != null) {{number_format($data['voucher_price_point'], 0, ',', '.')}} @else {{number_format($data['voucher_price_cash'], 0, ',', '.')}} @endif</div>
        </div>
    </div>
    <div class="container">
        <div class="row">
            <div class="col-12 text-black text-13-3px Ubuntu-Medium" style="margin-top: 15px;">Payment Method</div>
        </div>
    </div>
    <div class="kotak">
        <div class="row">
            <div class="col-6 text-13-3px text-black Ubuntu-Medium">@if ($data['payment_method'] == 'Midtrans')
                {{$data['payment']['payment_type']}}
            @elseif ($data['payment_method'] == 'Balance')
                Maxx Points
            @endif</div>
            <div class="col-6 text-13-3px text-black text-right Ubuntu-Medium">@if ($data['voucher_price_point'] != null) {{number_format($data['voucher_price_point'], 0, ',', '.')}} @else {{number_format($data['voucher_price_cash'], 0, ',', '.')}} @endif</div>
        </div>
    </div>



    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="{{ env('API_URL') }}js/jquery.js"></script>
    <script src="{{ env('API_URL') }}js/transaction.js"></script>
  </body>
</html>