<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ env('API_URL') }}css/transaction.css">
    {{-- <link href="{{ env('S3_URL_VIEW') }}{{('css/slide.css') }}" rel="stylesheet"> --}}
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
            padding: 10px;
            /*margin-right: 15px;*/
            -webkit-box-shadow: 0px 1px 3.3px 0px rgba(168,168,168,1);
            -moz-box-shadow: 0px 1px 3.3px 0px rgba(168,168,168,1);
            box-shadow: 0px 1px 3.3px 0px rgba(168,168,168,1);
            /* border-radius: 3px; */
            background: #fff;
            font-family: 'Ubuntu';
        }

        .kotak-qr {
            -webkit-box-shadow: 0px 0px 5px 0px rgba(214,214,214,1);
            -moz-box-shadow: 0px 0px 5px 0px rgba(214,214,214,1);
            box-shadow: 0px 0px 5px 0px rgba(214,214,214,1);
            background: #fff;
            width: 130px;
            height: 130px;
            margin: 0 auto;
            border-radius: 20px;
            padding: 10px;
        }

        .kotak-full {
            margin-bottom : 15px;
            padding: 10px;
            background: #fff;
            font-family: 'Open Sans', sans-serif;
        }

        .kotak-inside {
            padding-left: 25px;
            padding-right: 25px
        }

        body {
            background: #fafafa;
        }

        .completed {
            color: green;
        }

        .bold {
            font-weight: bold;
        }

        .space-bottom {
            padding-bottom: 5px;
        }

        .space-top-all {
            padding-top: 15px;
        }

        .space-text {
            padding-bottom: 10px;
        }

        .space-nice {
            padding-bottom: 20px;
        }

        .space-bottom-big {
            padding-bottom: 25px;
        }

        .space-top {
            padding-top: 5px;
        }

        .line-bottom {
            border-bottom: 1px solid rgba(0,0,0,.1);
            margin-bottom: 15px;
        }

        .text-grey {
            color: #aaaaaa;
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

        .text-grey-white {
            color: #666666;
        }

        .text-grey-light {
            color: #b6b6b6;
        }

        .text-grey-medium-light{
            color: #a9a9a9;
        }

        .text-black-grey-light{
            color: #333333;
        }


        .text-medium-grey-black{
            color: #424242;
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

        .text-red{
            color: #990003;
        }

        .text-20px {
            font-size: 20px;
        }
        .text-21-7px {
            font-size: 21.7px;
        }

        .text-16-7px {
            font-size: 16.7px;
        }

        .text-15px {
            font-size: 15px;
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

        .round-black{
            border: 1px solid #3d3935;
            border-radius: 50%;
            width: 10px;
            height: 10px;
            display: inline-block;
            margin-right:3px;
        }

        .round-grey{
            border: 1px solid #aaaaaa;
            border-radius: 50%;
            width: 10px;
            height: 10px;
            display: inline-block;
            margin-right:3px;
        }

        .bg-black{
            background: #3d3935;
        }

        .bg-grey{
            background: #aaaaaa;
        }

        .round-white{
            width: 10px;
            height: 10px;
            display: inline-block;
            margin-right:3px;
        }

        .line-vertical{
            font-size: 5px;
            width:10px;
            margin-right: 3px;
        }

        .inline{
            display: inline-block;
        }

        .vertical-top{
            vertical-align: top;
            padding-top: 5px;
        }

        .top-5px{
            top: -5px;
        }
        .top-10px{
            top: -10px;
        }
        .top-15px{
            top: -15px;
        }
        .top-20px{
            top: -20px;
        }
        .top-25px{
            top: -25px;
        }
        .top-30px{
            top: -30px;
        }
        .top-35px{
            top: -35px;
        }

        #map{
            border-radius: 10px;
            width: 100%;
            height: 150px;
        }

        .label-free{
            background: #6c5648;
            padding: 3px 15px;
            border-radius: 6.7px;
            float: right;
        }

        .text-strikethrough{
            text-decoration:line-through
        }

        #modal-usaha {
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(0,0,0, 0.5);
            width: 100%;
            display: none;
            height: 100vh;
            z-index: 999;
        }

        .modal-usaha-content {
            position: absolute;
            left: 50%;
            top: 50%;
            margin-left: -125px;
            margin-top: -125px;
        }

        .modal.fade .modal-dialog {
            transform: translate3d(0, 0, 0);
        }
        .modal.in .modal-dialog {
            transform: translate3d(0, 0, 0);
        }

        .body-admin{
            max-width: 480px;
            margin: auto;
            background-color: #fafafa;
            border: 1px solid #7070701c;
        }

    </style>
</head>
<?php
    use App\Lib\MyHelper;
 ?>
<body style="background:#fff">
<div class="@if(isset($data['admin'])) body-admin @endif">
    @if(isset($data['detail']['pickup_by']) && $data['detail']['pickup_by'] == 'GO-SEND')
        <div class="kotak-biasa">
            <div class="container">
                <div class="text-center">
                    <div class="col-12 Ubuntu text-15px space-nice text-grey">Detail Pengiriman</div>
                    <div class="col-12 text-red text-21-7px space-bottom Ubuntu-Medium">GO-SEND</div>
                    <div class="col-12 text-16-7px text-black space-bottom Ubuntu">
                        {{ $data['detail']['transaction_pickup_go_send']['destination_name'] }}
                        <br>
                        {{ $data['detail']['transaction_pickup_go_send']['destination_phone'] }}
                    </div>
                    <div class="kotak-inside col-12">
                        <div class="col-12 text-13-3px text-grey-white space-nice text-center Ubuntu">{{ $data['detail']['transaction_pickup_go_send']['destination_address'] }}</div>
                    </div>
                    <div class="col-12 text-15px space-bottom text-black Ubuntu">Map</div>
                    <div class="col-12 space-bottom-big">
                        <div class="container">
                            <div id="map"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="kotak-biasa" style="background-color: #FFFFFF;padding: 10px 0px;margin-top: 10px;">
            <div class="container">
                <div class="text-center" style="background-color: #FFFFFF;padding: 5px;padding-top: 0px;margin-top: 20px;">
                    <div class="col-12 text-black space-text Ubuntu-Bold" style="font-size: 16.7px;">{{ $data['outlet']['outlet_name'] }}</div>
                    <div class="kotak-inside col-12">
                        <div class="col-12 text-11-7px text-grey-white space-nice text-center Ubuntu">{{ $data['outlet']['outlet_address'] }}</div>
                    </div>
                    @if(isset($data['transaction_payment_status']) && $data['transaction_payment_status'] != 'Cancelled' && $data['trasaction_type'] != 'Offline')
                        <div class="col-12 Ubuntu-Medium space-text text-black" style="font-size: 15px;">Your Pick Up Code</div>
                        <div style="width: 135px;height: 135px;margin: 0 auto;" data-toggle="modal" data-target="#exampleModal">
                            <div class="col-12 text-14-3px space-top"><img class="img-responsive" style="display: block;max-width: 100%;padding-top: 10px" src="{{ $data['qr'] }}"></div>
                        </div>
                        <div class="col-12 text-black Ubuntu-Medium" style="color: #333333;font-size: 21.7px;padding-bottom: 5px;padding-top: 18px">{{ $data['detail']['order_id'] }}</div>
                    @endif
                </div>
            </div>
        </div>
        @if($data['trasaction_type'] != 'Offline')
            <div class="kotak-biasa" style="background-color: #FFFFFF;padding: 10px 0px;margin-top: 10px;">
                <div class="container">
                    <div class="text-center">
                        @if(isset($data['admin']))
                            <div class="col-12 text-16-7px text-black space-text Ubuntu">{{ strtoupper($data['user']['name']) }}</div>
                            <div class="col-12 text-16-7px text-black Ubuntu space-nice">{{ $data['user']['phone'] }}</div>
                        @endif
                        @if (isset($data['transaction_payment_status']) && $data['transaction_payment_status'] == 'Cancelled')
                            <div class="col-12 space-nice text-black Ubuntu" style="padding-bottom: 10px;">
                                Your order cancelled on
                            </div>
                            <div class="col-12 text-14px space-text text-black Ubuntu-Medium">{{ date('d F Y', strtotime($data['transaction_date'])) }}</div>
                        @else
                            <div class="col-12 space-nice text-black Ubuntu" style="padding-bottom: 10px;">
                                Your order will be ready on
                            </div>
                            <div class="col-12 text-14px space-text text-black Ubuntu-Medium">{{ date('d F Y', strtotime($data['transaction_date'])) }}</div>
                            <div class="col-12 text-21-7px Ubuntu-Medium" style="color: #8fd6bd;">
                                @if ($data['detail']['pickup_type'] == 'set time')
                                    {{ date('H:i', strtotime($data['detail']['pickup_at'])) }}
                                @elseif($data['detail']['pickup_type'] == 'at arrival')
                                    ON ARRIVAL
                                @else
                                    RIGHT NOW
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif

    <div class="kotak-biasa" style="background-color: #FFFFFF;padding: 15px;margin-top: 10px;">
        <div class="row space-bottom">
            <div class="col-7 text-left text-medium-grey-black text-13-3px Ubuntu">#{{ $data['transaction_receipt_number'] }}</div>
            <div class="col-5 text-right text-medium-grey text-11-7px Ubuntu">{{ date('d M Y H:i', strtotime($data['transaction_date'])) }}</div>
        </div>
        <div class="row space-text" style="margin-top: 10px;">
            <div class="col-12 text-14px Ubuntu-Medium">Your Transaction</div>
        </div>
            @foreach ($data['productTransaction'] as $key => $item)
                <div class="row space-text col-12">
                    <div class="col-2 Ubuntu text-left" style="color: #8fd6bd;">{{$item['transaction_product_qty']}}x</div>
                    <div class="col-8 Ubuntu-Medium text-black" style="margin-left: -20px;">{{$item['product']['product_name']}}</div>
                    <div class="col-3 text-right Ubuntu-Medium text-black" style="padding-right: 0px;">{{ MyHelper::requestNumber(explode('.',$item['transaction_product_price'])[0], '_CURRENCY') }}</div>
                </div>
                <div class="row space-text col-12">
                    @if (!empty($item['product']['product_discounts']))
                        <div class="col-2 Ubuntu text-black">{{$item['transaction_product_qty']}}x</div>
                        <div class="col-8 Ubuntu text-black" style="margin-left: -20px;">{{$item['product']['product_name']}}</div>
                        <div class="col-3 text-right Ubuntu-Medium text-black" style="padding-right: 0px;">{{ MyHelper::requestNumber($data['transaction_subtotal'], '_CURRENCY') }}</div>
                    @endif
                </div>
                @if ($item != end($data['productTransaction']))
                    <div class="col-12">
                        <hr style="margin: 10px 0px;border-top: dashed 1px #aaaaaa;"/>
                    </div>
                @endif
            @endforeach
    </div>

    <div class="kotak-biasa" style="background-color: #FFFFFF;padding: 15px;margin-top: 10px;">
        <div class="row space-bottom">
            <div class="col-12 text-14px Ubuntu-Bold text-black">Payment Details</div>
        </div>
        <div class="row space-bottom">
            <div class="col-6 text-13-3px Ubuntu-Medium text-black ">Subtotal</div>
            <div class="col-6 text-13-3px text-right Ubuntu text-black">{{ MyHelper::requestNumber($data['transaction_subtotal'], '_CURRENCY') }}</div>
        </div>
        <div class="row" style="padding-left: -7px;padding-right: -7px;background-color: #f0f3f7;border-radius: 5px;">
            <div class="col-6 text-13-3px Ubuntu-Medium text-black" style="padding: 7px;">Grand Total</div>
            MyHelper::requestNumber($value['transaction_grandtotal'], '_CURRENCY');
            <div class="col-6 text-13-3px text-right Ubuntu-Bold text-black" style="padding: 7px;">{{ MyHelper::requestNumber($data['transaction_grandtotal'], '_CURRENCY') }}</div>
        </div>
    </div>

    <div class="kotak-biasa" style="background-color: #FFFFFF;padding: 15px;margin-top: 10px;">
        <div class="row space-bottom">
            <div class="col-12 text-14px Ubuntu-Bold text-black">Payment Method</div>
        </div>
        <div class="row">
            @php if(strtoupper($data['trasaction_payment_type']) == 'BALANCE') $data['trasaction_payment_type'] = 'points' @endphp
            <div class="col-6 text-13-3px Ubuntu text-black ">{{strtoupper($data['trasaction_payment_type'])}}</div>
            @if(isset($data['balance']))
                <div class="col-6 text-13-3px text-right Ubuntu-Medium text-black">{{ MyHelper::requestNumber($data['transaction_grandtotal'] - $data['balance'], '_CURRENCY') }}</div>
            @else
                <div class="col-6 text-13-3px text-right Ubuntu-Medium text-black">{{ MyHelper::requestNumber($data['transaction_grandtotal'], '_CURRENCY') }}</div>
            @endif
        </div>
    </div>

    @if ($data['trasaction_type'] != 'Offline')
        <div class="kotak-biasa" style="background-color: #FFFFFF;padding: 15px;margin-top: 10px;">
            <div class="row space-bottom">
                <div class="col-12 text-14px Ubuntu-Medium text-black">Order Status</div>
            </div>
            <div style="margin-top: 10px;">
                @php $top = 5; $bg = true; @endphp
                @if(isset($data['transaction_payment_status']) && $data['transaction_payment_status'] == 'Cancelled')
                    <div class="col-12 text-13-3px Ubuntu-Medium text-black top-{{$top}}px">
                        <div class="@if($bg) bg-black @endif" style="border: 1px solid #3d3935;border-radius: 50%;width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                        Your order has been canceled
                    </div>
                    @php $top += 5; $bg = false; @endphp
                    <div class="col-12 top-{{$top}}px">
                        <div class="inline text-center">
                            <div class="line-vertical text-grey-medium-light">|</div>
                            <div class="line-vertical text-grey-medium-light">|</div>
                            <div class="line-vertical text-grey-medium-light">|</div>
                            <div class="line-vertical text-grey-medium-light">|</div>
                            <div class="line-vertical text-grey-medium-light">|</div>
                        </div>
                        <div class="inline vertical-top">
                            <div class="text-11-7px Ubuntu text-black space-bottom">
                                {{ date('d F Y H:i', strtotime($data['void_date'])) }}
                            </div>
                        </div>
                    </div>
                    @php $top += 5; @endphp
                @else
                    @if($data['detail']['reject_at'] != null)
                        <div class="col-12 text-13-3px Ubuntu-Medium text-black">
                            <div class="round-black bg-black" style="border: 1px solid #3d3935;border-radius: 50%;width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                            Order rejected
                        </div>
                        <div class="col-12 top-5px">
                            <div class="inline text-center">
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                            </div>
                            <div class="inline vertical-top">
                                <div class="text-11-7px Ubuntu text-black space-bottom">
                                    {{ date('d F Y H:i', strtotime($data['detail']['reject_at'])) }}
                                </div>
                            </div>
                        </div>
                        @php $top += 5; $bg = false; @endphp
                    @endif
                    @if($data['detail']['taken_by_system_at'] != null)
                        <div class="col-12 text-13-3px Ubuntu-Medium text-black top-{{$top}}px">
                            <div class="round-black @if($bg) bg-black @endif" style="border: 1px solid #3d3935;border-radius: 50%;width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                            Your order has been done by system
                        </div>
                        @php $top += 5; $bg = false; @endphp
                        <div class="col-12 top-{{$top}}px">
                            <div class="inline text-center">
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                            </div>
                            <div class="inline vertical-top">
                                <div class="text-11-7px Ubuntu text-black space-bottom">
                                    {{date('d F Y H:i', strtotime($data['detail']['taken_by_system_at']))}}
                                </div>
                            </div>
                        </div>
                        @php $top += 5; @endphp
                    @endif
                    @if($data['detail']['taken_at'] != null)
                        <div class="col-12 text-13-3px Ubuntu-Medium text-black top-{{$top}}px">
                            <div class="round-black @if($bg) bg-black @endif" style="border: 1px solid #3d3935;border-radius: 50%;width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                            Your order has been taken
                        </div>
                        @php $top += 5; $bg = false; @endphp
                        <div class="col-12 top-{{$top}}px">
                            <div class="inline text-center">
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                            </div>
                            <div class="inline vertical-top">
                                <div class="text-11-7px Ubuntu text-black space-bottom">
                                    {{date('d F Y H:i', strtotime($data['detail']['taken_at']))}}
                                </div>
                            </div>
                        </div>
                        @php $top += 5; @endphp
                    @endif
                    @if($data['detail']['ready_at'] != null)
                        <div class="col-12 text-13-3px Ubuntu-Medium text-black top-{{$top}}px">
                            <div class="round-black @if($bg) bg-black @endif" style="border: 1px solid #3d3935;border-radius: 50%;width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                            Your order is ready
                        </div>
                        @php $top += 5; $bg = false; @endphp
                        <div class="col-12 top-{{$top}}px">
                            <div class="inline text-center">
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                            </div>
                            <div class="inline vertical-top">
                                <div class="text-11-7px Ubuntu text-black space-bottom">
                                    {{ date('d F Y H:i', strtotime($data['detail']['ready_at'])) }}
                                </div>
                            </div>
                        </div>
                        @php $top += 5; @endphp
                    @endif
                    @if($data['detail']['receive_at'] != null)
                        <div class="col-12 text-13-3px Ubuntu-Medium text-black top-{{$top}}px">
                            <div class="round-black @if($bg) bg-black @endif" style="border: 1px solid #3d3935;border-radius: 50%;width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                            Your order has been received
                        </div>
                        @php $top += 5; $bg = false; @endphp
                        <div class="col-12 top-{{$top}}px">
                            <div class="inline text-center">
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                                <div class="line-vertical text-grey-medium-light">|</div>
                            </div>
                            <div class="inline vertical-top">
                                <div class="text-11-7px Ubuntu text-black space-bottom">
                                    {{ date('d F Y H:i', strtotime($data['detail']['receive_at'])) }}
                                </div>
                            </div>
                        </div>
                        @php $top += 5; @endphp
                    @endif
                    <div class="col-12 text-13-3px Ubuntu-Medium text-black top-{{$top}}px">
                        <div class="round-black @if($bg) bg-black @endif" style="border: 1px solid #3d3935;border-radius: 50%;width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                        Your order awaits confirmation
                    </div>
                    <div class="col-12 text-11-7px Ubuntu text-black space-bottom top-{{$top}}px">
                        <div class="round-white" style="width: 10px;height: 10px;display: inline-block;margin-right:3px;"></div>
                        {{ date('d F Y H:i', strtotime($data['transaction_date'])) }}
                    </div>
                @endif
            </div>
        </div>
    @endif

<!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="{{ env('API_URL') }}js/jquery.js"></script>
    <script src="{{ env('API_URL') }}js/transaction.js"></script>
</div>
</body>
</html>