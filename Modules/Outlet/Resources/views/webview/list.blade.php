<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<!-- Bootstrap CSS -->
    <link href="{{ env('API_URL') }}css/general.css" rel="stylesheet">
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
        body {
            cursor: pointer;
        }
    	.kotak1 {
    		padding-top: 10px;
    		padding-bottom: 0;
    		padding-left: 7px;
    		padding-right: 7px;
			background: #fff;
    	}

    	.kotak2 {
    		padding-top: 10px;
    		padding-bottom: 10px;
    		padding-left: 26.3px;
    		padding-right: 26.3px;
			background: #fff;
      height: 100%
    	}

    	.red div {
    		color: #990003;
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

        .space-top {
            padding-top: 15px;
        }

    	.space-text {
    		padding-bottom: 10px;
    	}

    	.space-sch {
    		padding-bottom: 5px;
    		margin-left: 0 !important;
    	}

    	.min-left {
    		margin-left: -15px;
    		margin-right: 10px;
    	}

    	.line-bottom {
    		border-bottom: 0.3px solid #dbdbdb;
    		margin-bottom: 5px;
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
    		color: #666666;
    	}

    	.text-grey-black {
    		color: #4c4c4c;
    	}

		.text-grey-2{
			color: #979797;
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

      	.text-15px {
			font-size: 15px;
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

    	.logo-img {
    		width: 16.7px;
    		height: 16.7px;
        margin-top: -7px;
        margin-right: 5px;
    	}

      .text-bot {
        margin-left: -15px;
      }

      .owl-dots {
        margin-top: -37px !important;
        position: absolute;
        width: 100%;
        margin-left: 1px;
        height: 37px;
        opacity: 0.5;
      }

      .image-caption-outlet {

      }

      .owl-carousel {
        overflow: hidden;
      }

      .owl-theme .owl-dots .owl-dot span {
        width: 5px !important;
        height: 4px !important;
        margin: 5px 1px !important;
        margin-top: 28px !important;
      }

      .image-caption-all {
            position: absolute;
            z-index: 99999;
            bottom: 0;
            color: white;
            width: 100%;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px;
      }

      .image-caption-you {
            position: absolute;
            z-index: 99999;
            top: 0;
            color: white;
            width: 100%;
            padding: 8%;
      }

      .cf_videoshare_referral {
		display: none !important;
	}

	.day-alphabet{
		margin: 0 10px;
		border-radius: 50%;
		width: 20px;
		height: 20px;
		text-align: center;
		background: #d9d6d6;
		color: white !important;
		padding-top: 1px;
	}

	.day-alphabet-today{
		background: #6c5648;
	}

	.fa-angle-down{
		transform: rotate(0deg);
		transition: transform 0.25s linear;
	}

	.fa-angle-down.open{
		transform: rotate(180deg);
		transition: transform 0.25s linear;
	}

    </style>
  </head>
  <body>

	<div class="kotak1">
  		<div class="container">
			<div class="Ubuntu-Medium space-text" style="color: #3d3935; font-size: 15px; padding-bottom: 0;"><i style="font-size: 17px;" class="fa fa-phone"></i> {{$data[0]['outlet_phone']}}</div>
	   	</div>
  	</div>

	<div class="kotak1" style="padding-top: 10px;">
  		<div class="container">
  		    <div class="Ubuntu-Medium space-text" style="color: #3d3935; font-size: 13.3px; padding-bottom: 5px;">Location</div>
			<div class="Ubuntu space-text" style="color: #979797; font-size: 12.7px; padding-bottom: 0;">
				<?php
					echo nl2br ($data[0]['outlet_address']);
				?>
			</div>
			<hr style="margin-bottom: 5px;border-top: dashed 1px #979797;">
	   	</div>
	</div>
    <div class="kotak1" @if($data[0]['big_order'] == 0) style='margin-bottom: 20px;' @endif>
  		<div class="container">
  		    <div class="Ubuntu-Medium space-text" id="testClick" style="color: #3d3935; font-size: 13.3px; padding-bottom: 5px;">Open Hours</div>
  			@php
  				$hari = date ("D");
			@endphp
			<div class="row Ubuntu">
				<div class="col-8">
				    @if (!empty($data[0]['outlet_schedules']))
						@foreach ($data[0]['outlet_schedules'] as $key => $val)
						@php
							switch($val['day']){
								case 'Sunday':
									$val['day'] = "Sun";
								break;

								case 'Monday':
									$val['day'] = "Mon";
								break;

								case 'Tuesday':
									$val['day'] = "Tue";
								break;

								case 'Wednesday':
									$val['day'] = "Wed";
								break;

								case 'Thursday':
									$val['day'] = "Thu";
								break;

								case 'Friday':
									$val['day'] = "Fri";
								break;

								default:
									$val['day'] = "Sat";
								break;
							}
						@endphp
						<div class="pull-left row space-sch">
							<div style="@if ($val['day'] == $hari) color: #3d3935; @else color: #979797; @endif font-size: 12.7px; padding-bottom: 0;" class="@if ($val['day'] == $hari) Ubuntu-Medium @endif col-3 min-left">{{ $val['day'] }}</div>
							<div style="@if ($val['day'] == $hari) color: #3d3935; @else color: #979797; @endif font-size: 12.7px; padding-bottom: 0;" class="@if ($val['day'] == $hari) Ubuntu-Medium @endif col-9">
								@if($val['is_closed'] == '1')
									Close
								@else
									{{date('H.i', strtotime($val['open']))}} - {{date('H.i', strtotime($val['close']))}}
								@endif
							</div>
						</div>
						@endforeach
					@else
						<div class="Ubuntu space-text" style="color: rgb(0, 0, 0); font-size: 12.7px; padding-bottom: 0;">Belum Tersedia</div>
					@endif
				</div>
			</div>
	   	</div>
  	</div>

	@if($data[0]['big_order'] == 1)
	<div class="kotak1" style='margin-bottom: 20px'>
  		<div class="container">
  		    <div class="Ubuntu text-center space-text" style="color: rgb(0, 0, 0); font-size: 15px; padding-bottom: 5px;">Big Order Delivery Service</div>
		  <div class="Ubuntu space-text" style="color: rgb(102, 102, 102); font-size: 12.7px; padding-bottom: 0;">Khusus pemesanan diatas 50 pax, silahkan menghubungi <a style="color: rgb(128, 0, 0); text-decoration: underline;" href="#delivery_service">Call Center</a> kami untuk mendapatkan penawaran special</div>
	   	</div>
	</div>
	@endif

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
	<script src="{{ env('API_URL') }}js/jquery.js"></script>
	<script src="{{ env('API_URL') }}js/general.js"></script>
	<script>
	$(document).ready(function() {
    	$("#testClick").click(function() {
    		if($("#today").is(':visible')){
    			$(".icon").addClass('open');
    			$("#today").hide()
    			$(".anotherDay").show(500)
    		} else{
    			$(".icon").removeClass('open');
    			$("#today").show()
    			$(".anotherDay").hide(500)
    		}
    	});
	});
	</script>
  </body>
</html>