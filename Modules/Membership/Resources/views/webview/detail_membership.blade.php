<!DOCTYPE html>
<html lang="en">
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge" />
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
        <link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">
        <title>Champ Membership</title>
        @php
            $result['user_membership']['user']['progress_now'] = 1000000;
        @endphp
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
            top: 23px;
            z-index: 10;
            border-radius: 50%;
            position: absolute;
            background: #10704e;
            box-shadow: 0px 1.7px 5px 0 #8fd6bd;
        }
        .medium {
            height: 105px;
            top: 10px;
            width: 230px;
            -webkit-transition-property: top height;
            -webkit-transition-duration: 0.4s;
            -webkit-transition-timing-function: linear;
            transition-property: top height;
            transition-duration: 0.4s;
            transition-timing-function: linear;
        }
        .big {
            height: 125px;
            top: 0px;
            width: 230px;
            -webkit-transition-property: top height;
            -webkit-transition-duration: 0.4s;
            -webkit-transition-timing-function: linear;
            transition-property: top height;
            transition-duration: 0.4s;
            transition-timing-function: linear;
        }
        </style>
	</head>
	<body>
        <div id="carouselExampleFade" style="background: #f8f9fb;" class="loop owl-carousel slide carousel-fade" data-ride="carousel" data-interval="false">
            @foreach ($result['all_membership'] as $key => $member)
                <div data-id="desc{{$key}}" class="item @if($member['membership_name'] == $result['user_membership']['membership_name']) active @endif">
                    <div style="padding: 20px 0px 20px 0px;">
                        <div class="card" style="margin: auto;background: #3d3935;border: #aaaaaa;border-radius: 20px;box-shadow: 2.7px 6.7px 5px 0 #d0d4dd;">
                            <img src="{{ asset('img/membership/maxx_coffee_card_bg.png') }}" style="position: absolute;width: 150px;height: 130px;"/>
                            <div class="card-body" style="display: flex;flex-wrap: wrap;padding: 10px;">
                                <div class="col-9 text-left" style="margin-top: 10px;margin-bottom: 20px;">
                                    <p class="Ubuntu-Bold" style="margin-bottom: 0px;font-size: 15px;color: #ffffff;">{{$result['user_membership']['user']['name']}}</p>
                                </div>
                                <div class="col-3">
                                    <img src="{{$member['membership_image']}}" style="margin-top: 5px;width: 40px;float: right;"/>
                                </div>
                                <div class="col-7 text-left">
                                    <p class="Ubuntu-Regular" style="font-size: 10.7px;color: #ffffff;margin-bottom: 10px;">Your Points</p>
                                    <p class="Ubuntu-Medium" style="font-size: 13.3px;color: #ffffff;margin-bottom: 5px;">{{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</p>
                                </div>
                                <div class="col-5 text-right">
                                    <p class="Ubuntu-Bold" style="font-size: 13.3px;color: #ffffff;margin-top: 10px;">{{$member['membership_name']}}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @foreach ($result['all_membership'] as $key => $member)
        <div id="desc{{$key}}" class="eksekusi">
            <div style="position: relative;left: auto;right: auto;padding: 20px;background: #ffffff;top: 0px;margin-bottom: 0px;" class="carousel-caption row">
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
                                        <div class="Ubuntu-Medium" style="color: #3d3935;">{{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</div>
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
                                                    <div class="dotted" style="left: {{(($result['user_membership']['user']['progress_now'] - $result['all_membership'][$key]['min_value']) / ($result['all_membership'][$key+1]['min_value'] - $result['all_membership'][$key]['min_value']) * 100)}}%;"></div>
                                                </div>
                                            @endif
                                        <div class="Ubuntu-Medium" style="color: #3d3935;">{{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</div>
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
                                    <div class="font-regular-black">{{number_format($result['all_membership'][$key+1]['min_value'] , 0, ',', '.')}}</div>
                                </div>
                            </div>
                            <img style="width: 30px;height: 30px;" src="{{$result['all_membership'][$key]['membership_next_image']?:$result['all_membership'][$key+1]['membership_image']}}"/>
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
                                            <div class="dotted" style="left: {{ ($result['user_membership']['user']['progress_now'] / 15000000) * 100 }}%;"></div>
                                        </div>
                                    @endif
                                <div class="Ubuntu-Medium" style="color: #3d3935;">{{number_format($result['user_membership']['user']['progress_now'] , 0, ',', '.')}}</div>
                            </div>
                            <div class="level-progress-container" style="margin-right: 10px; height: 9px;">
                                    @if (($result['user_membership']['user']['progress_now'] - end($result['all_membership'])['min_value']) <= 0)
                                        <div class="level-progress" style="width:0%; height: 9px;"></div>
                                    @else
                                        <div class="level-progress" style="width:{{ ($result['user_membership']['user']['progress_now'] / 15000000) * 100 }}%; height: 9px;"></div>
                                    @endif
                            </div>
                                <div class="level-info">
                                    <div class="font-regular-black">{{number_format($result['all_membership'][$key]['min_value'] , 0, ',', '.')}}</div>
                                    <div class="font-regular-black">{{number_format(15000000 , 0, ',', '.')}}</div>
                                </div>
                            </div>
                            <img style="width: 30px;height: 30px;" src="{{$member['membership_image']}}"/>
                        </div>
                    @endif
                </div>
            </div>
            <div style="position: relative;left: auto;right: auto;padding: 20px 0px 20px 0px;background: #ffffff;top: 0px;margin-bottom: 0px;" class="carousel-caption">
                <div class="card" style="height: 155px;width: 90%;margin: auto;background: #f8f9fb;border: #aaaaaa;border-radius: 20px;box-shadow: 0 2px 3.3px 0 #d0d4dd;">
                    <div class="card-body" style="display: flex;flex-wrap: wrap;">
                        <div class="font-title">Keuntungan {{$member['membership_name']}} member : </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
        <script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
        <script>
        $(function(){
            $('.loop').on('initialized.owl.carousel translate.owl.carousel', function(e){
                idx = e.item.index;
                $('.owl-item').eq(idx).children().children().children().addClass('big');
                $('.owl-item').eq(idx).children().children().children().removeClass('medium');

                $('.owl-item').eq(idx-1).children().children().children().addClass('medium');
                $('.owl-item').eq(idx-1).children().children().children().removeClass('big');

                $('.owl-item').eq(idx+1).children().children().children().addClass('medium');
                $('.owl-item').eq(idx+1).children().children().children().removeClass('big');

                var getID = e.relatedTarget.$stage.children()[e.item.index]
                var iddata = $(getID).children().data('id')
                $('.eksekusi').hide()
                $("#"+iddata).show()
                console.log($(getID).children().data('id'))
            });
            $('.loop').owlCarousel({
                center: true,
                items:3,
                autoWidth:true,
                margin:15,
            })
            $(document).ready(function() {
                $('.ui-page').css('background', 'white')
            })
        });  
        </script>
    </body>
</html>