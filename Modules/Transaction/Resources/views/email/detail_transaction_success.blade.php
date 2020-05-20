
<table style="margin-left: auto;margin-right: auto;max-width: 1000px;float: none;background:#fcfcfc;" width="500px" cellspacing="0" cellpadding="5" border="0" >
    <tbody>
    <tr>
        <td colspan="5" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)"></td>
    </tr>
    <tr>
        <td colspan="5" style="text-align: right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{date('d M Y H:i', strtotime($data['transaction_date']))}}</span>
        </td>
    </tr>

    <tr>
        <td colspan="5" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top"  align="center">
            <?php
            if(isset($setting['email_logo'])){
                if(stristr($setting['email_logo'], 'http')){
                    $email_logo = $setting['email_logo'];
                }else{
                    $email_logo = env('AWS_URL').$setting['email_logo'];
                }
            }else{
                $email_logo = 'http://localhost/GitWork/maxxcoffee-cust-view/public/images/logo_login.png';//env('S3_URL_API').('img/logo.jpg');
            }
            ?>
            <img class="img-responsive" style="display: block;max-width: 100%;height: 100px" src="{{$email_logo}}">
        </td>
    </tr>

    <tr>
        <td colspan="5"></td>
    </tr>

    <tr>
        <td colspan="5" style="border-bottom-style:none;text-align:center">
            <h2 style="color:#000000;font-family:\'Source Sans Pro\',sans-serif;font-size:16px;line-height:1.5;margin:0;padding:5px 0">Thank you for placing the order</h2>
        </td>
    </tr>
    <tr>
        <td colspan="5" style="border-bottom-style:none;text-align:center">
            <span style="color:#b3b3b3;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">#{{$data['transaction_receipt_number']}}</span>
        </td>
    </tr>
    <tr>
        <td colspan="5" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="center">
            <img class="img-responsive" style="display: block;max-width: 100%;height: 80px" src="{{ $data['qr'] }}"><br>
            <span style="color:#b3b3b3;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">Order ID : {{ $data['detail']['order_id'] }}</span>
        </td>
    </tr>
    <tr>
        <th colspan="5" style="border-bottom-style:none;padding-left:10px;padding-right:10px">
        </th>
    </tr>

    @foreach ($data['productTransaction'] as $key => $item)
        <tr style="text-align:right">
            <td style="background:#f5f5f5;border-collapse:collapse;border-spacing:0;color:#555;padding:10px 40px" valign="top" align="left">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{$item['product']['product_name']}} ({{$item['transaction_product_qty']}})
            </span><br>

                <?php
                $topping = '';
                foreach ($data['modifiers'] as $mf){
                    $topping .= $mf['text']. '('.$mf['qty'].'), ';
                }

                $variant = '';
                foreach ($data['products_variant'] as $vrt){
                    $variant .= $vrt['product_variant_name'].', ';
                }

                if($topping !== '') $topping = '( '.substr($topping, 0, -2).' )<br>';
                if($variant !== '') $variant = '( '.substr($variant, 0, -2).' )';
                echo '<span style="color:#999;font-family:\'Source Sans Pro\',sans-serif;font-size:12px;margin-left:10%;"><i>'.$topping.$variant.'</i></span>';
                ?>
            </td>
            <td  colspan="2" width="50%" style="background:#f5f5f5;border-collapse:collapse;border-spacing:0;color:#555;padding:10px 15px" valign="top" align="right">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(floatval ($item['transaction_product_price']), '_CURRENCY') }}</span>
            </td>
        </tr>
    @endforeach

    <tr style="text-align:right">
        <td rowspan="3" style="background:#f5f5f5;" align="center">
            <img class="img-responsive" style="display: block;max-width: 100%;height: 80px" src="{{env('S3_URL_API').('img/icon_email_1.png')}}">
        </td>
        <td style="background:#f5f5f5;" valign="top" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">Subtotal</span>
        </td>
        <td style="background:#f5f5f5;padding-right:15px" valign="top" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:15px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_subtotal']), '_CURRENCY') }}</span>
        </td>
    </tr>
    @if($data['transaction_discount'] != 0)
        <tr style="text-align:right">
            <td style="background:#f5f5f5;padding-top:10px" valign="top"  align="right">
                <span style="color:#8fd6bd;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0"><b>Discount</b></span>
            </td>
            <td style="background:#f5f5f5;padding:10px 15px" valign="top"  align="right">
                <span style="color:#8fd6bd;font-family:\'Source Sans Pro\',sans-serif;font-size:15px;line-height:1.5;margin:0;padding:0"><b>{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_discount']), '_CURRENCY') }}</b></span>
            </td>
        </tr>
    @endif
    <tr style="text-align:right">
        <td  style="background:#f5f5f5;padding-top:10px" valign="top"  align="right">
            <span style="color:#8fd6bd;font-size: 18px;">Grand Total</span>
        </td>
        <td style="background:#f5f5f5;padding:10px 15px" valign="top"  align="right">
            <span style="color:#8fd6bd;font-size: 18px;"><b>{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_grandtotal']), '_CURRENCY') }}</b></span>
        </td>
    </tr>

    </tbody>
</table>

<table style="margin-left: auto;margin-right: auto;max-width: 1000px;float: none;background:#fcfcfc;" width="500px" cellspacing="0" cellpadding="5" border="0" >
    <tbody>
    @if(isset($data['outlet']['outlet_name']))
        <tr>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td width="50%" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top"  align="center">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:20px;line-height:1.5;margin:0;padding:0"><b>Detail Outlet</b></span>
            </td>
            <td colspan="2" width="50%" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top"  align="center">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:20px;line-height:1.5;margin:0;padding:0"><b>Payment Detail</b></span>
            </td>
        </tr>
        <tr>
            <td  @if(!empty($data['data_payment']))rowspan="{{count($data['data_payment'])}}"@endif style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top"  align="center">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">
                    {{ $data['outlet']['outlet_name'] }}
                </span><br>
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">
                {{ $data['outlet']['outlet_address'] }}
                </span><br>
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">
                {{ $data['outlet']['outlet_phone'] }}
                </span>
            </td>
            @foreach($data['data_payment'] as $dp)
                <td style="background:#fcfcfc;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top"  align="right">
                    <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{strtoupper($dp['payment_method'])}}</span>
                </td>
                <td style="background:#fcfcfc;border-bottom-color:#cccccc;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding-right:15px" valign="top"  align="right">
                    <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0;">{{ \App\Lib\MyHelper::requestNumber(floatval ($dp['nominal']), '_CURRENCY') }}</span>
                </td>
            @endforeach
        </tr>
    @endif
    <tr>
        <td colspan="5"></td>
    </tr>
    <tr>
        <td colspan="5" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)"></td>
    </tr>
    </tbody>
</table>
