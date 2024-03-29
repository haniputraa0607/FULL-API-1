<!DOCTYPE html>
<html lang="en">
<!-- BEGIN HEAD -->
<head>
  <meta charset="utf-8" />
  <title>Verify Email</title>

  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans|Questrial" rel="stylesheet">
  <style>
    body{
      font-family: 'Open Sans', sans-serif;
    }

    .logo{
      margin: 30px auto 0;
      padding: 10px;
      text-align: center;
    }

    .copyright {
      text-align: center;
      margin: 0 auto 30px 0;
      padding: 10px;
      color: #7a8ca5;
      font-size: 13px;
    }

    .box-failed{
         width: 400px;
         margin: 0px auto 10px;
         padding: 10px 30px 0px;
         overflow: hidden;
         position: relative;
         background-color: #fbe1e3;
         border-radius: 5px;
         text-align: center;
    }

    .box-success{
        width: 400px;
        margin: 0px auto 10px;
        padding: 10px 30px 0px;
        overflow: hidden;
        position: relative;
        background-color: #d4edda;
        border-radius: 5px;
        text-align: center;
    }

    .box-already{
        width: 400px;
        margin: 0px auto 10px;
        padding: 10px 30px 0px;
        overflow: hidden;
        position: relative;
        background-color: #abe7ed;
        border-radius: 5px;
        text-align: center;
    }

    .header {
        overflow: hidden;
        background-color: #e6e6e6;
    }

    .header a {
        float: left;
        color: black;
        text-align: center;
        padding: 12px;
        text-decoration: none;
        font-size: 18px;
        line-height: 10px;
        border-radius: 4px;
    }
  </style>
</head>

<body style="background-color: #f2f2f2;text-align: center">
    <div class="header">
        <a href="#default" class="logo-header">
        <img src="{{ env('S3_URL_VIEW') }}{{ ('images/logo.png') }}" alt="logo" class="logo-default"  style="margin: 0 -10px;height: {{env('SIZE_LOGO_EMAIL_VERIFY')}}px;"/> </a>
        </a>
    </div>

    <h4 style="padding-bottom: 30px; margin-top: 6%;">User Verify Email</h4>
    @if($status_verify == 'success')
        <div class="box-success">
            <p style="font-size: 16px;color: #256434">{{$message}}</p>
        </div>
    @elseif($status_verify == 'fail')
        <div class="box-failed">
            <p style="font-size: 16px;color: #e73d4a;">{{$message}}</p>
        </div>
    @elseif($status_verify == 'expired')
        <div class="box-failed">
            <p style="font-size: 16px;color: #e73d4a;">{{$message}}</p>
        </div>
    @else
        <div class="box-already">
            <p style="font-size: 16px;color: #27a4b0;">{{$message}}</p>
        </div>
    @endif

    <div class="copyright" style="color: #000000">{{$settings['value']}}</div>
</body>

</html>