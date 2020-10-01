<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta http-equiv="cache-control" content="no-cache" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="-1" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <style type="text/css">
		<?php
			if($useragent == 'Android'){
		?>
        @font-face {
                font-family: "Ubuntu-Medium";
                font-style: normal;
                font-weight: bold;
                src: url('file:///android_asset/font/ubuntu_regular.ttf');
        }
        @font-face {
                font-family: "Ubuntu";
                font-style: normal;
                font-weight: 400;
                src: url('file:///android_asset/font/ubuntu_regular.ttf');
        }
        @font-face {
                font-family: "Ubuntu-Regular";
                font-style: normal;
                font-weight: 400;
                src: url('file:///android_asset/font/ubuntu_regular.ttf');
        }
	<?php } else { ?>

		@font-face {
                font-family: "Ubuntu-Medium";
                font-style: normal;
                font-weight: 400;
                src: url('{{ env('S3_URL_API') }}{{ ('fonts/ubuntu_medium.ttf') }}');
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
	<?php } ?>
        .Ubuntu-Medium{
            font-family: "Ubuntu-Medium";
        }
        .Ubuntu{
            font-family: "Ubuntu";
        }
        .Ubuntu-Regular{
            font-family: "Ubuntu-Regular";
        }

    	.kotak1 {
    		padding-top: 0px;
    		padding-bottom: 10px;
    		padding-left: 26.3px;
    		padding-right: 26.3px;
			background: #fff;
			font-family: 'Ubuntu', sans-serif;
    	}

    	.kotak2 {
    		padding-top: 10px;
    		padding-bottom: 10px;
    		padding-left: 26.3px;
    		padding-right: 26.3px;
			background: #fff;
			font-family: 'Ubuntu', sans-serif;
			height: 100%
    	}

    	.brown div {
    		color: #6c5648;
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
    	}

    	.min-left {
    		margin-left: -15px;
    		margin-right: 10px;
    	}

    	.line-bottom {
    		border-bottom: 0.3px solid #dbdbdb;
    		margin-bottom: 5px;
		    padding-bottom: 10px;
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

    	.text-black-grey {
    		color: #333333;
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

		.text-grey-2{
			color: #a9a9a9;
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
			color: #b72126;
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

		.cf_videoshare_referral {
			display: none !important;
		}

		.kelas-hr {
			margin-top: -5px;
		}

		.kelas-input {
			width: 100%;
			overflow: hidden;
			border-radius: 7px;
			/* margin: 10px; */
			-webkit-box-shadow: 0px 1px 2px 0px rgba(168,168,168,1);
			-moz-box-shadow: 0px 1px 2px 0px rgba(168,168,168,1);
			box-shadow: 0px 0px 2px 0px rgba(168,168,168,1);
			/* outline: 0; */

		}

		.linkTidakAda:hover {
			text-decoration: none;
		}

		.kelas-input .input-group-text {
			background: #df151500;
			border: none;
			border-left: 0;
			padding: 6px;
		}

		.kelas-input .form-control {
			border: none;
			outline: 0;
			padding: 6px 18px;
		}

		.kelas-input .form-control:focus {
			box-shadow: none;
			outline: 0;
		}

    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css?">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css?">
  </head>
  <body>
    {{ csrf_field() }}
  	<div class="kotak1">
   		<div class="row" style="margin-top: 0px;">
   			<div class="col-12 mb-4 mt-4">
	   			<div class="input-group kelas-input">
				  <input type="text" id="id-input" class="form-control text-12-7px Ubuntu-Regular" placeholder="Search">
				  <div class="input-group-append">
				    <span class="input-group-text"><img src="{{ env('S3_URL_API') }}{{ ('images/search.png') }}" style="width: 20px;"></span>
				  </div>
				</div>
			</div>
			   <div class="row" id="row" style="margin-left:0; margin-right:0; width:100%; @if(count($outlet) > 0) display: none @endif">
					<div class="space-bottom" style="margin: auto;">
						<img class="img-responsive" style="width: 34px;" src="{{ env('S3_URL_API') }}{{ ('images/empty.png') }}">
					</div>
					<div class="col-12 text-16-7px text-black Ubuntu-Medium space-sch" style="margin: auto; text-align:center">
						Oops!
					</div>
					<div class="col-12 text-12-7px text-grey Ubuntu-Regular" style="margin: auto; text-align:center">
						@if(isset($msg))
							{{$msg}}
						@else
							Outlet not found
						@endif
					</div>
				</div>
			   <div id="result" style="width: 100%">
					@foreach ($outlet as $out)
					<div class="col-12">
						
						<a @if($out['today']['status'] != 'closed') href="@if(isset($out['deep_link_gojek'])){{ $out['deep_link_gojek'] }} @elseif(isset($out['deep_link_grab'])) {{$out['deep_link_grab']}} @endif" @endif class="row linkTidakAda">
							<div class="col-8 @if($out['today']['status'] == 'closed') text-grey @else text-black-grey @endif text-14px Ubuntu-Medium"><span> {{ $out['outlet_name'] }} </span></div>
							<div class="col-4 text-11-7px Ubuntu-Regular text-right text-grey"><span> {{ $out['distance'] }} </span></div>
							<div class="col-10 text-11-7px Ubuntu-Regular text-grey" style="padding-top:8px; padding-bottom:8px"><span> {{ $out['outlet_address'] }} </span></div>
							<div class="col-2 text-red text-11-7px Ubuntu-Medium text-right"><span>@if($out['today']['status'] == 'closed') {{strtoupper($out['today']['status'] )}} @endif</span></div>
							<div class="col-12 kelas-hr"><hr style="border-top: #aaa dashed 1px;"></div>
						</a>
					</div>
					@endforeach
				</div>
   		</div>
   		<input type="hidden" class="data" value="{{ json_encode($outlet) }}">
  	</div>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js?" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js?" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
  </body>

  <script>
  	$(document).on('keyup', '#id-input', function() {
  		var data = JSON.parse($('.data').val());
	  	var input = $(this).val();
	  	var apa = data.filter(function(item) {
	  		return item.outlet_name.toLowerCase().search(input.toLowerCase()) !== -1;
	  	});

	  	$('#result').html('');
		if(apa.length == 0){
			$('#row').show();
		}else{
			$('#row').hide();
			apa.map(function(value, key) {
				var text_color = 'text-black-grey';
				var status = '';
				if(value.today.status == 'closed'){
					text_color = 'text-grey';
					status = value.today.status.toUpperCase();
				}
				$('#result').append('<div class="col-12"><a href="'+value.deep_link+'" class="row linkTidakAda">\
						<div class="col-9 '+text_color+' text-14px Ubuntu-Medium"><span> '+value.outlet_name+' </span></div>\
						<div class="col-3 text-11-7px Ubuntu-Regular text-right text-grey"><span> '+value.distance+'</span></div>\
						<div class="col-9 text-11-7px Ubuntu-Regular text-grey" style="padding-top:10px; padding-bottom:10px"><span> '+value.outlet_address+' </span></div>\
						<div class="col-3 text-red text-11-7px Ubuntu-Medium text-right"><span>'+status+'</span></div>\
						<div class="col-12 kelas-hr"><hr style="border-top: #aaa dashed 1px;"></div>\
					</a></div>')
			});
		}

  	});

	$(document).ready(function(){
		var height = $('#row').height();
		$('#row').css('margin-top', 'calc((100vh - '+height+'px)/2 - 60px)')
	})

  </script>
</html>