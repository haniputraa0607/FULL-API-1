<head>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans|Source+Sans+Pro" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <link href="{{ env('S3_URL_VIEW') }}{{('css/slide.css') }}" rel="stylesheet">
    <style type="text/css">
        .table-bordered {
            font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
            border-collapse: collapse;
        }
        .table-bordered td, .table-bordered th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        #table-fraud-list {
            font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
            border-collapse: collapse;
        }

        #table-fraud-list td, #table-fraud-list th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        #table-fraud-list th {
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: left;
            background-color: #BFBFBF;
            color: white;
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
            font-family: 'Open Sans', sans-serif;
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
            padding-bottom: 15px;
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

        .text-red {
            color: #990003;
        }

        .text-grey {
            color: #aaaaaa;
        }

        .text-much-grey {
            color: #bfbfbf;
        }

        .text-black {
            color: #000000;
        }

        .text-medium-grey {
            color: #806e6e6e;
        }

        .text-grey-white {
            color: #666;
        }

        .text-grey-light {
            color: #b6b6b6;
        }

        .text-grey-medium-light{
            color: #a9a9a9;
        }

        .text-black-grey-light{
            color: #5f5f5f;
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

        .text-greyish-brown{
            color: #6c5648;
        }

        .open-sans-font {
            font-family: 'Open Sans', sans-serif;
        }

        .questrial-font {
            font-family: 'Questrial', sans-serif;
        }

        .roboto-regular-font {
            font-family: 'Roboto Regular';
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

        .round-greyish-brown{
            border: 1px solid #6c5648;
            border-radius: 50%;
            width: 10px;
            height: 10px;
            display: inline-block;
            margin-right:3px;
        }

        .bg-greyish-brown{
            background: #6c5648;
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

        .text-center{
            text-align: center;
        }
        .row {
            display: -ms-flexbox;
            display: flex;
            -ms-flex-wrap: wrap;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        .col-6 {
            -ms-flex: 0 0 50%;
            flex: 0 0 50%;
            max-width: 50%;
        }
        .col-4 {
            -ms-flex: 0 0 33.333333%;
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
        .col-8 {
            -ms-flex: 0 0 66.666667%;
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }
        .col-12 {
            -ms-flex: 0 0 100%;
            flex: 0 0 100%;
            max-width: 100%;
        }
        .col-7 {
            -ms-flex: 0 0 58.333333%;
            flex: 0 0 58.333333%;
            max-width: 58.333333%;
        }
        .col-5 {
            -ms-flex: 0 0 41.666667%;
            flex: 0 0 41.666667%;
            max-width: 41.666667%;
        }

        .col, .col-1, .col-10, .col-11, .col-12, .col-2, .col-3, .col-4, .col-5, .col-6, .col-7, .col-8, .col-9, .col-auto, .col-lg, .col-lg-1, .col-lg-10, .col-lg-11, .col-lg-12, .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6, .col-lg-7, .col-lg-8, .col-lg-9, .col-lg-auto, .col-md, .col-md-1, .col-md-10, .col-md-11, .col-md-12, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .col-md-9, .col-md-auto, .col-sm, .col-sm-1, .col-sm-10, .col-sm-11, .col-sm-12, .col-sm-2, .col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6, .col-sm-7, .col-sm-8, .col-sm-9, .col-sm-auto, .col-xl, .col-xl-1, .col-xl-10, .col-xl-11, .col-xl-12, .col-xl-2, .col-xl-3, .col-xl-4, .col-xl-5, .col-xl-6, .col-xl-7, .col-xl-8, .col-xl-9, .col-xl-auto {
            position: relative;
            width: 100%;
            min-height: 1px;
            /*padding-right: 15px;*/
            /*padding-left: 15px;*/
        }

        .container {
            /*width: 100%;*/
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
            padding-top: 15px;
            padding-bottom: 15px;
            margin-bottom:10px;
            border: 1px solid rgb(225, 221, 221);
        }

        @media (min-width: 576) {
            .container {
                max-width: 540px;
            }
        }
        @media (min-width: 768px) {
            .container {
                max-width: 720px;
            }
        }
        @media (min-width: 992px) {
            .container {
                max-width: 960px;
            }
        }
        @media (min-width: 1200px) {
            .container {
                max-width: 1140px;
            }
        }

        .text-right {
            text-align: right !important;
        }

        hr {
            margin-top: 1rem;
            margin-bottom: 1rem;
            border: 0;
            border-top-color: currentcolor;
            border-top-style: none;
            border-top-width: 0px;
            border-top: 1px solid rgba(0,0,0,.1);
            box-sizing: content-box;
            height: 0;
            overflow: visible;
        }

        #table-content {
            border-collapse: collapse;
        }

        #table-content td, #table-content th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        #table-contentt th {
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: left;
            background-color: #BFBFBF;
            color: white;
        }
    </style>
</head>

<table style="border-collapse:collapse;border-spacing:0;margin:0 auto;padding:0;background-color:#ffffff" width="800" cellspacing="0" cellpadding="0" border="0" align="center" >
    <tbody>
    <tr>
        <td style="border-collapse:collapse;border-spacing:0;color:#999;font-family:'Source Sans Pro',sans-serif;line-height:1.5;margin:0;padding:0" align="left">
            <table style="border-collapse:collapse;border-spacing:0;margin:0;padding:0" width="100%">
                <tbody>
                </tbody>
            </table>
        </td>
    </tr>
    <tr>
        <td style="border-collapse:collapse;border-spacing:0;color:#999;font-family:'Source Sans Pro',sans-serif;line-height:1.5;margin:0;padding:0" bgcolor="#ffffff">
            <table style="border-collapse:collapse;border-spacing:0;margin:0;padding:0" width="100%" align="left">
                <tbody>
                <tr>
                    <td style="border-collapse:collapse;border-spacing:0;color:#999;font-family:'Source Sans Pro',sans-serif;line-height:1.5;margin:0;padding:0" width="15" height="100"></td>
                    <td id="logoheader_center" style="border-collapse:collapse;border-spacing:0;color:#000;font-family:'Arial',sans-serif;line-height:1.5;margin:0;padding:0;text-align:center" height="100">
                        <?php
                            if(isset($setting['email_logo'])){
                                if(stristr($setting['email_logo'], 'http')){
                                    $email_logo = $setting['email_logo'];
                                }else{
                                    $email_logo = env('S3_URL_API').$setting['email_logo'];
                                }
                            }else{
                                $email_logo = env('S3_URL_API').('img/logo.jpg');
                            }
                        ?>
                        <img class="CToWUd" id="detail_logo_center" src="{{$email_logo}}" width="150" style="width:100%;max-width:150px;">
                    </td>
                    <td style="border-collapse:collapse;border-spacing:0;color:#999;font-family:'Source Sans Pro',sans-serif;line-height:1.5;margin:0;padding:0" width="15" height="100"></td>
                </tr>
                <tr>
                    <td style="border-collapse:collapse;border-spacing:0;color:#999;font-family:'Source Sans Pro',sans-serif;line-height:1.5;margin:0;padding:0" width="15"></td>
                    <td style="border-collapse:collapse;border-spacing:0;color:#999;font-family:'Source Sans Pro',sans-serif;line-height:1.5;margin:0;padding:0" width="550" align="left"><table style="border-collapse:collapse;border-spacing:0;margin:0;padding:0" width="100%" align="left">
                            <tbody>
                            <tr>
                                <td style="border-collapse:collapse;border-spacing:0;color:#999;font-family:'Source Sans Pro',sans-serif;line-height:1.5;margin:0;padding:0">
                                    <div style="margin:5px 2px">
