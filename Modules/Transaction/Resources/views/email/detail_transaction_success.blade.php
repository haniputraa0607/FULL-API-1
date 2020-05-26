
<table style="margin-left:auto;margin-right:auto;max-width: 1000px;float: none;background:#fcfcfc;" width="500px" cellspacing="0" cellpadding="5" border="0" >
    <tbody>
    <tr>
        <td colspan="3" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)"></td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: right">
            <span style="color:#555;;font-size:14px;line-height:1.5;margin:0;padding:0">{{date('d M Y H:i', strtotime($data['transaction_date']))}}</span>
        </td>
    </tr>

    <tr>
        <td colspan="3" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;;line-height:1.5;margin:0;padding:15px 10px" valign="top"  align="center">
            <?php
            if(isset($data['setting']['email_logo'])){
                if(stristr($data['setting']['email_logo'], 'http')){
                    $email_logo = $data['setting']['email_logo'];
                }else{
                    $email_logo = env('AWS_URL').$data['setting']['email_logo'];
                }
            }else{
                $email_logo = env('S3_URL_API').('img/logo.jpg');
            }
            ?>
            <img class="img-responsive" style="display: block;max-width: 100%;height: 100px" src="{{$email_logo}}">
        </td>
    </tr>

    <tr>
        <td colspan="3"></td>
    </tr>

    <tr>
        <td colspan="3" style="border-bottom-style:none;text-align:center">
            <p style="color:#000000;;font-size:25px;line-height:1.5;margin:0;padding:5px 0">Thank you for placing the order</p>
        </td>
    </tr>
    <tr>
        <td colspan="3" style="border-bottom-style:none;text-align:center">
            <span style="color:#b3b3b3;;font-size:14px;line-height:1.5;margin:0;padding:0">#{{$data['transaction_receipt_number']}}</span>
        </td>
    </tr>
    <tr>
        <td colspan="3" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="center">
            <img class="img-responsive" style="display: block;max-width: 100%;height: 80px" src="{{ $data['qr'] }}"><br>
            <span style="color:#b3b3b3;;font-size:14px;line-height:1.5;margin:0;padding:0">Order ID: {{ $data['detail']['order_id'] }}</span>
        </td>
    </tr>
    <tr>
        <th colspan="3" style="border-bottom-style:none;padding-left:10px;padding-right:10px">
        </th>
    </tr>

    @foreach ($data['product_group'] as $key => $item)
        <tr style="text-align:right">
            <td colspan="5" width="50%" style="background:#f5f5f5;border-collapse:collapse;border-spacing:0;color:#555;padding-left: 5%" valign="top" align="left">
                <div class="left"><span style="font-size: 16px;color:#8fd6bd;">{{$item['transaction_product_qty']}}x </span><span style="font-size: 16px">@if(empty($item['product_group_name'])){{$item['product_name']}} @else{{$item['product_group_name']}}@endif</span></div>
                <div class="right"><span style="font-size: 16px">{{ \App\Lib\MyHelper::requestNumber(floatval ($item['transaction_product_price']), '_CURRENCY') }}</span></div>
                <div class="dotted"></div>
            </td>
        </tr>
        <tr>
            <td colspan="5" style="background:#f5f5f5;border-collapse:collapse;border-spacing:0;color:#555;padding-left: 10%" valign="top" align="left">
                <?php
                $topping = '';
                foreach ($data['modifiers'] as $mf){
                    $topping .= $mf['text']. '('.$mf['qty'].'), ';
                }

                $variant = '';
                foreach ($data['products_variant'] as $vrt){
                    $variant .= $vrt['product_variant_name'].', ';
                }

                if($topping !== '') $topping = substr($topping, 0, -2).'<br>';
                if($variant !== '') $variant = substr($variant, 0, -2);
                echo '<span style="color:#999;;font-size:14px;"><i>'.$topping.$variant.'</i><br>'.$item['transaction_product_note'].'</span>';
                ?>
            </td>
        </tr>
    @endforeach

    <tr style="text-align:right;padding-top: 15px">
        <td rowspan="3" style="background:#f5f5f5;" align="center">
            <img class="img-responsive" style="display: block;max-width: 100%;height: 80px" src="{{env('S3_URL_API').('img/icon_email_1.png')}}">
        </td>
        <td style="background:#f5f5f5;" valign="top" align="right">
            <span style="color:#555;font-size:14px;line-height:1.5;margin:0;padding:0">Subtotal:</span>
        </td>
        <td width="5%" style="background:#f5f5f5;padding-right:15px" valign="top" align="right">
            <span style="color:#555;font-size:15px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_subtotal']), '_CURRENCY') }}</span>
        </td>
    </tr>
    @if($data['transaction_discount'] != 0)
        <tr style="text-align:right">
            <td style="background:#f5f5f5;" valign="top"  align="right">
                <span style="color:#555;font-size:14px;line-height:1.5;margin:0;padding:0">Discount:
                     @if(isset($data['promo_campaign_promo_code']['promo_code']))
                        <br>({{$data['promo_campaign_promo_code']['promo_code']}})
                    @elseif(isset($data['vouchers'][0]['voucher_code']))
                        <br>({{$data['vouchers'][0]['voucher_code']}})
                    @endif
                </span>
            </td>
            <td style="background:#f5f5f5;padding-right:15px" valign="top"  align="right">
                <span style="color:#fc0303;font-size:15px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_discount']), '_CURRENCY') }}</span>
            </td>
        </tr>
    @endif
    <tr style="text-align:right">
        <td  style="background:#f5f5f5;padding-top:10px" valign="top"  align="right">
            <span style="color:#8fd6bd;font-size: 18px;"><b>Grand Total:</b></span>
        </td>
        <td style="background:#f5f5f5;padding:10px 15px" valign="top"  align="right">
            <span style="color:#8fd6bd;font-size: 18px;"><b>{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_grandtotal']), '_CURRENCY') }}</b></span>
        </td>
    </tr>

    </tbody>
</table>

<table style="margin-left:auto;margin-right:auto;background:#fcfcfc;" width="500px" cellspacing="0" cellpadding="5" border="0" >
    <tbody>
    @if(isset($data['outlet']['outlet_name']))
        <tr>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td width="50%" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;;line-height:1.5;margin:0;padding:0px 10px" valign="top"  align="center">
                <span style="color:#555;;font-size:20px;line-height:1.5;margin:0;padding:0"><b>Outlet Detail</b></span>
            </td>
            <td colspan="2" width="50%" style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;;line-height:1.5;margin:0;padding:0px 10px" valign="top"  align="center">
                <span style="color:#555;;font-size:20px;line-height:1.5;margin:0;padding:0"><b>Payment Detail</b></span>
            </td>
        </tr>
        <tr>
            <td  @if(!empty($data['data_payment']))rowspan="{{count($data['data_payment'])}}"@endif style="background:#fcfcfc;border-collapse:collapse;border-spacing:0;color:#555;;line-height:1.5;margin:0;padding:3px 10px" valign="top"  align="center">
                <span style="color:#555;font-size:14px;line-height:1.5;margin:0;padding:0">
                    {{ $data['outlet']['outlet_name'] }}
                </span><br>
                <span style="color:#555;font-size:14px;line-height:1.5;margin:0;padding:0">
                {{ $data['outlet']['outlet_address'] }}
                </span><br>
                <span style="color:#555;font-size:14px;line-height:1.5;margin:0;padding:0">
                {{ $data['outlet']['outlet_phone'] }}
                </span>
            </td>
            <?php $i= 1?>
            @foreach($data['data_payment'] as $dp)
                @if($i==1)
                    <td style="background:#fcfcfc;border-spacing:0;color:#555;margin:0;padding-top:3px" valign="top"  align="center">
                        <span style="color:#555;font-size:14px;margin:0;padding-left:3%">{{strtoupper($dp['payment_method'])}}</span>
                    </td>
                    <td width="5%" style="background:#fcfcfc;border-spacing:0;color:#555;margin:0;padding-top:3px" valign="top"  align="right">
                        <span style="color:#555;font-size:14px;margin:0;padding-right:2px">{{ \App\Lib\MyHelper::requestNumber(floatval ($dp['nominal']), '_CURRENCY') }}</span>
                    </td>
        </tr>
    @else
        <tr>
            <td style="background:#fcfcfc;border-spacing:0;color:#555;margin:0;@if($i==count($data['data_payment']))padding-bottom: 10%;@endif" valign="top"  align="center">
                <span style="color:#555;font-size:14px;margin:0;padding-left:3%">{{strtoupper($dp['payment_method'])}}</span>
            </td>
            <td width="5%" style="background:#fcfcfc;border-spacing:0;color:#555;margin:0;@if($i==count($data['data_payment']))padding-bottom: 10%;@endif" valign="top"  align="right">
                <span style="color:#555;font-size:14px;margin:0;padding-right:2px">{{ \App\Lib\MyHelper::requestNumber(floatval ($dp['nominal']), '_CURRENCY') }}</span>
            </td>
        </tr>
    @endif
    <?php $i++?>
    @endforeach
    @endif
    <tr>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td colspan="3" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)"></td>
    </tr>
    </tbody>
</table>
