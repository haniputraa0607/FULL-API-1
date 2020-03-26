<!DOCTYPE html>
<html lang="en">
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
        <link rel="stylesheet" href="{{env('API_URL')}}css/membership.css">
        <link rel="stylesheet" href="{{env('API_URL')}}css/owl.carousel.css"/>
        <link rel="stylesheet" href="{{env('API_URL')}}css/owl.theme.default.css"/>
        <title>Maxx Membership</title>
        <style>
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
        body {
            background-color: rgba(0, 0, 0, 0.1);
            font-family: 'Ubuntu-Regular';
            font-size: 14px;
        }
        a:hover{
            text-decoration: none
        }
        .font-header {
            font-family: 'Ubuntu-Regular';
            font-size: 20px;
            color: #202020;
        }
        .font-title {
            font-family: 'Ubuntu-Regular';
            font-size: 14px;
            color: #000000;
        }
        .font-nav {
            font-family: 'Ubuntu-Regular';
            font-size: 14px;
            color: #545454;
        }
        .font-regular-gray{
            font-family: 'Ubuntu-Regular';
            font-size: 12px;
            color: #545454;
        }
        .font-regular-black {
            font-family: 'Ubuntu-Regular';
            font-size: 12px;
            color: #000000;
        }
        .font-regular-brown {
            font-family: 'Ubuntu-Regular';
            font-size: 12px;
            color: #837046;
        }
        .container {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-height: 100vh;
            min-height: calc(var(--vh, 1vh) * 100);
            margin: auto;
            padding-bottom: 70px;
            background-color: #ffffff;
            position: relative;
        }
        .content {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        /* header */
        .header {
            display: flex;
            flex-direction: row;
            height: 70px;
            padding: 0px 5px;
            align-items: center;
            justify-content: center;
        }
        .header-icon {
            position: absolute;
            left: 0;
            margin: 0px 16px;
        }
        .header-title {
            display: flex;
            flex: 1;
            justify-content: center
        }
        /* navtop */
        .navtop-container {
            display: flex;
            justify-content: space-between;
            flex-direction: row;
            background-image: linear-gradient(to bottom, #ffffff, #fafafa 40%, #ededed 82%, #e6e6e6);
        }
        .navtop-item {
            display: flex;
            flex: 1;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 5px 10px
        }
        .navtop-item img{
            height: 40px;
            width: 40px;
            margin-bottom: 5px
        }
        .navtop-item.active{
            background-color: #ffffff;
            border-bottom-style: solid;
            border-bottom-width: 2px;
            border-bottom-color: #800000
        }
        /* content */
        .tab-content {
            margin: 10px 0px;
        }
        .content-list {
            display: flex;
            flex-direction: column;
            padding: 8px 0px;
            margin-bottom: 16px;
        }
        .content-list-item {
            display: flex;
            flex: 1;
            flex-direction: row;
        }
        .content-list .content-list-item img{
            margin-right: 8px;
            height: 15px;
            width: 15px;
        }
        
        /* member level */
        .level-container {
            display: flex;
            flex-direction: row;
            align-items: center;
            margin: 10px 0px;
        }
        .level-container img{
            margin-left: 0px 8px 0px;
            height: 24px;
            width: 24px;
        }
        .level-wrapper {
            flex: 1;
        }
        .level-wrapper img{
            margin-right: 8px;
            height: 18px;
            width: 18px;
        }

        .current-level-info{
            position: relative;
            display: flex;
            flex-direction: row;
            left: -15px;
        }
        .level-info{
            display: flex;
            flex-direction: row;
            justify-content: space-between;

        }
        .level-progress-container {
            position: relative;
            height: 8px;
            width: 100%;
            border-radius: 8px;
            margin: 8px 0px;
            background-color: #d7d2cb;
        }
        .level-progress {
            position: absolute;
            left:0;
            top:0;
            z-index: 9;
            height: 8px;
            background-color: #8fd6bd;
            border-radius: 8px
        }
        .level-progress-blank {
            width: 50%;
        }
        .dotted {
            content: "";
            width: 15px;
            height: 15px;
            top: 24px;
            z-index: 10;
            border-radius: 50%;
            position: absolute;
            background: #333333;
            box-shadow: 0px 1.7px 5px 0 #8fd6bd;
        }
        .owl-next {
            position: absolute;
            top: 38%;
            right: 20%;
            width: 21px;
            height: 21px;
            background: #9ad5c3;
            border-radius: 100%;
        }
        .owl-prev {
            position: absolute;
            top: 38%;
            left: 20%;
            width: 21px;
            height: 21px;
            background: #9ad5c3;
            border-radius: 100%;
        }
        </style>
	</head>
	<body style="background: #f8f9fb;">
        <div style="background: url('{{env('API_URL')}}img/asset/bg_card_membership.png');background-size: contain;" >
            @foreach ($result['all_membership'] as $key => $member)
                @if ($member['membership_name'] == $result['user_membership']['membership_name'])
                <div data-id="desc{{$key}}" class="item @if($member['membership_name'] == $result['user_membership']['membership_name']) active @endif">
                    <div style="padding: 20px 0px 20px 0px;">
                        <div class="card" style="margin: auto;background: url('{{$member['membership_bg_image']}}');width: 245px;height: 135px;border: #aaaaaa;border-radius: 15px;background-size: cover;">
                            <div class="card-body" style="display: flex;flex-wrap: wrap;padding: 15px;">
                                <div class="col-7 text-left" style="padding-left: 0px;padding-top: 5px;margin-bottom: 35px;">
                                    <p class="Ubuntu-Bold" style="margin-bottom: 0px;font-size: 12px;color: #ffffff;">{{$result['user_membership']['user']['name']}}</p>
                                </div>
                                <div class="col-5" style="padding-right: 0px;">
                                    <img src="{{$member['membership_image']}}" style="margin-top: 5px;width: 60px;float: right;"/>
                                </div>
                                <div class="col-7 text-left" style="padding-left: 0px;">
                                    <p class="Ubuntu-Regular" style="font-size: 10.7px;color: #ffffff;margin-bottom: 0px;">Your Points</p>
                                    <p class="Ubuntu-Medium" style="font-size: 12.7px;font-size: 16.7pxIDR ;color: #ffffff;">{{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</p>
                                </div>
                                <div class="col-5 text-center" style="padding-right: 0px;right: -7px;">
                                    <p class="Ubuntu-Medium" style="font-size: 12.7px;font-size: 14px;color: #ffffff;margin-top: 5px;">{{$member['membership_name']}}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="position: relative;left: auto;right: auto;padding: 10px;background: #ffffff;top: 0px;margin-bottom: 0px;" class="carousel-caption">
                    <div class="col-12">
                        <p class="Ubuntu-Medium text-left" style="font-size: 14px;color: #3d3935;margin-bottom: 0px;">Transaction Progress</p>
                        @if (isset($result['all_membership'][$key+1]))
                            @php
                                $trx_total = $result['all_membership'][$key+1]['min_value'] - $result['user_membership']['user']['progress_now']
                            @endphp
                            <div class="level-container Ubuntu-Regular">
                                <div class="level-wrapper">
                                    @if ($trx_total <= 0)
                                        <div class="current-level-info">
                                            <div style="width:100%"></div>
                                            <div class="Ubuntu-Medium" style="font-size: 12.7px;color: #3d3935;">IDR {{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</div>
                                            <div class="dotted" style="left: 100%;"></div>
                                        </div>
                                        <div class="level-progress-container" style="margin-right: 10px; height: 9px;">
                                            <div class="level-progress" style="width:100%; height: 9px;"></div>
                                        </div>
                                    @else
                                        <div class="current-level-info">
                                                @if ((($result['user_membership']['user']['progress_now'] - $result['all_membership'][$key]['min_value']) / ($result['all_membership'][$key+1]['min_value'] - $result['all_membership'][$key]['min_value']) * 100) <= 0)
                                                    <div style="width:0%;">
                                                        <div class="dotted" style="left: 3%;"></div>
                                                    </div>
                                                @else
                                                    <div style="width:{{(($result['user_membership']['user']['progress_now'] - $result['all_membership'][$key]['min_value']) / ($result['all_membership'][$key+1]['min_value'] - $result['all_membership'][$key]['min_value']) * 100)}}%;">
                                                        <div class="dotted" style="left: {{(($result['user_membership']['user']['progress_now'] - $result['all_membership'][$key]['min_value']) / ($result['all_membership'][$key+1]['min_value'] - $result['all_membership'][$key]['min_value']) * 100) + 3}}%;"></div>
                                                    </div>
                                                @endif
                                            <div class="Ubuntu-Medium" style="font-size: 12.7px;color: #3d3935;">IDR {{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</div>
                                        </div>
                                        <div class="level-progress-container" style="margin-right: 10px; height: 9px;">
                                                @if ((($result['user_membership']['user']['progress_now'] - $result['all_membership'][$key]['min_value']) / ($result['all_membership'][$key+1]['min_value'] - $result['all_membership'][$key]['min_value']) * 100) <= 0)
                                                    <div style="width:0%;"></div>
                                                @else
                                                    <div class="level-progress" style="width:{{ (($result['user_membership']['user']['progress_now'] - $result['all_membership'][$key]['min_value']) / ($result['all_membership'][$key+1]['min_value'] - $result['all_membership'][$key]['min_value']) * 100) }}%; height: 9px;"></div>
                                                @endif
                                        </div>
                                    @endif
                                    <div class="level-info">
                                        <div class="font-regular-black">{{number_format($result['all_membership'][$key]['min_value'] , 0, ',', '.')}}</div>
                                        <div class="font-regular-black">IDR {{number_format($result['all_membership'][$key+1]['min_value'] , 0, ',', '.')}}</div>
                                    </div>
                                </div>
                            </div>
                        @else
                            @php
                                $trx_total = 15000000 - $result['user_membership']['user']['progress_now'];
                                $dataNext = end($result['all_membership'])['min_value'];
                            @endphp
                            <div class="level-container Ubuntu-Regular">
                                <div class="level-wrapper">
                                <div class="current-level-info">
                                        @if (($result['user_membership']['user']['progress_now'] - end($result['all_membership'])['min_value']) <= 0)
                                            <div style="width:0%;">
                                                <div class="dotted" style="left: 3%;"></div>
                                            </div>
                                        @else
                                            <div style="width:{{ ($result['user_membership']['user']['progress_now'] / 15000000) * 100 }}%;">
                                                <div class="dotted" style="left: {{ (($result['user_membership']['user']['progress_now'] / 15000000) * 100) + 3 }}%;"></div>
                                            </div>
                                        @endif
                                    <div class="Ubuntu-Medium" style="font-size: 12.7px;color: #3d3935;">IDR {{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</div>
                                </div>
                                <div class="level-progress-container" style="margin-right: 10px; height: 9px;">
                                        @if (($result['user_membership']['user']['progress_now'] - end($result['all_membership'])['min_value']) <= 0)
                                            <div class="level-progress" style="width:0%; height: 9px;"></div>
                                        @else
                                            <div class="level-progress" style="width:{{ ($result['user_membership']['user']['progress_now'] / 15000000) * 100 }}%; height: 9px;"></div>
                                        @endif
                                </div>
                                    <div class="level-info">
                                        <div class="font-regular-black">IDR {{number_format($result['all_membership'][$key]['min_value'] , 0, ',', '.')}}</div>
                                        <div class="font-regular-black">IDR {{number_format(15000000 , 0, ',', '.')}}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="font-regular-gray text-center" style="font-size: 11.7px;">Get more transaction!</div>
                        @if (isset($result['all_membership'][$key+1]))
                            <div class="font-title text-center" style="font-size: 11.7px;">IDR {{number_format($result['all_membership'][$key+1]['min_value'] - $result['user_membership']['user']['progress_now'] , 0, ',', '.')}} to go to become <span class="Ubuntu-Medium">{{$result['all_membership'][$key+1]['membership_name']}} Member</span></div>
                        @else
                            <div class="font-title text-center" style="font-size: 11.7px;">IDR {{number_format(15000000 - $result['user_membership']['user']['progress_now'] , 0, ',', '.')}} passed <span class="Ubuntu-Medium">{{$member['membership_name']}} Member</span></div>
                        @endif
                    </div>
                </div>
                @endif
            @endforeach
        </div>
        <div style="background-size: contain;" class="loop owl-carousel owl-theme carousel-fade">
            @foreach ($result['all_membership'] as $key => $member)
            <div data-id="desc{{$key}}" class="item @if($member['membership_name'] == $result['user_membership']['membership_name']) active @endif">
                <div class="text-center" style="width: 135px;margin: auto;margin-top: 30px;">
                    <p class="Ubuntu-Medium" style="box-shadow: 0px 3.3px 6.7px 0px #eeeeee; @if($member['membership_name'] == $result['user_membership']['membership_name']) background-color: #ffffff; @else background-color: #eeeeee; @endif border-radius: 100px;font-size: 13.3px;font-size: 14px;color: #333333;margin-top: 5px;padding: 1px;">{{$member['membership_name']}}</p>
                </div>
            </div>
            @endforeach
        </div>
        @foreach ($result['all_membership'] as $key => $member)
            <div id="desc{{$key}}" class="eksekusi">
                <div class="text-center" style="font-size: 12.7px;border-radius: 6.7px;box-shadow: 0px 3.3px 6.7px 0px #eeeeee;margin-left: 20px;margin-right: 20px;position: relative;left: auto;right: auto;padding: 20px;background: #ffffff;top: 10px;margin-bottom: 0px;" class="Ubuntu-Medium carousel-caption">
                    Benefit as {{$member['membership_name']}} Member
                </div>
            </div>
        @endforeach

        <script src="{{env('API_URL')}}js/jquery.js"></script>
        <script src="{{env('API_URL')}}js/membership.js"></script>
        <script src="{{env('API_URL')}}js/owl.carousel.js"></script>
        <script>
        $(function(){
            $('.loop').on('initialized.owl.carousel translate.owl.carousel', function(e){
                idx = e.item.index;
                var getID = e.relatedTarget.$stage.children()[e.item.index]
                var iddata = $(getID).children().data('id')
                $('.eksekusi').hide()
                $("#"+iddata).show()
            });
            $('.loop').owlCarousel({
                center:true,
                items:1,
                nav:true,
                dots:false,
                loop:true,
                mouseDrag:false,
                touchDrag:false,
                pullDrag:false,
                freeDrag:false
            })
            $(document).ready(function() {
                $('.ui-page').css('background', 'white')
                $('.owl-next').css('background', '#9ad5c3')
                $('.owl-next').css('border-radius', '100%')
                $('.owl-next').css('outline', 'none')
                $('.owl-prev').css('background', '#9ad5c3')
                $('.owl-prev').css('border-radius', '100%')
                $('.owl-prev').css('outline', 'none')
            })
        });  
        </script>
    </body>
</html>