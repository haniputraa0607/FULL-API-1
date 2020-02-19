<?php
    use App\Lib\MyHelper;
    $title = "Deals Detail";
?>
@extends('webview.main')

@section('css')
    <link rel="stylesheet" href="{{ env('API_URL') }}css/referral.css">
    <link rel="stylesheet" href="{{ env('API_URL') }}css/fontawesome.css">
    <style>
        body {
            width: 100%;
        }
        .box {
            margin: 30px 22px;
            padding: 0px;
        }
        .list {
            padding-left: 15px;
        }
        .no-space-bottom {
            margin-bottom: 0px;
        }
        .text-black {
            color: #3d3935;
        }
        .text-apricot {
            color: #ff9d6e;
        }
        .text-13-3px {
            font-size: 13.3px;
        }
        .text-16-7px {
            font-size: 16.7px;
        }
        .text-21-7px {
            font-size: 21.7px;
        }
        .space-top-30 {
            margin-top: 30px;
        }
        .bg-img {
            width: 100%;
        }
        .code {
            top: 48%;
            font-size: 21.7px;
            width: 100%;
            text-align: center;
            position: absolute;
            left: 0;
        }
        .btn-custom {
            width: 100%;
            height: 43.3px;
            background-color: #8fd6bd;
            font-size: 16.7px;
            color: #10704E;
            box-shadow: 0px 0px 6.7px 0px #F5F5F5;
            border-radius: 6.7px;
        }
    </style>
@stop

@section('content')
    <div class="row box">
        <div class="col-12 text-black text-13-3px Ubuntu-Regular">
            <p class="text-left no-space-bottom">Refer us to your and earn some {{$referral['referred_promo_type']}}.</p>
            <ul class="list">
                <li>They'll each get {{$referral['referred_promo_value']}}@if ($referral['referred_promo_unit'] == 'Percent')% @else points @endif {{$referral['referred_promo_type']}}</li>
                <li>You'll get {{$referral['referrer_promo_value']}}@if ($referral['referrer_promo_unit'] == 'Percent')% @else points @endif {{$referral['referred_promo_type']}} for each friend that makes first any transaction through MAXX Coffee application</li>
            </ul>
        </div>
        <div class="col-12 text-apricot text-16-7px space-top-30 Ubuntu-Medium">
            <p class="text-center no-space-bottom">Here Your Referral Number</p>
        </div>
        <div class="col-12 text-black text-21-7px Ubuntu-Bold">
            <img class="bg-img" src="{{ env('S3_URL_API') }}{{ ('img/bg_referral.png') }}">
            <span class="code">{{$promo_code}}</span>
        </div>
        <div class="col-12 space-top-30 text-black text-21-7px Ubuntu-Bold">
            <button onclick="location.href='{{url()->current()}}#share_now'" class="btn btn-custom"><i class="fas fa-share-alt"></i> Share Now</button>
        </div>
    </div>
@stop

@section('page-script')
    <script src="{{ env('S3_URL_API') }}{{ ('js/jquery.js') }}"></script>
    <script src="{{ env('S3_URL_API') }}{{ ('js/referral.js') }}"></script>
@stop
