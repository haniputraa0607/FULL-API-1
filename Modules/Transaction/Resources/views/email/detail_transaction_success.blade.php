
<table style="margin-left: auto;margin-right: auto;max-width: 1000px;float: none;background:#ffffff;" width="500px" cellspacing="0" cellpadding="5" border="0" bgcolor="#FFFFFF">
    <tbody>
    <tr>
        <td colspan="5" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)"></td>
    </tr>
    <tr>
        <td colspan="5"></td>
    </tr>
    <tr>
        <td colspan="5"></td>
    </tr>

    <tr>
        <td colspan="3" style="border-bottom-style:none;text-align:center">
            <h2 style="color:#000000;font-family:\'Source Sans Pro\',sans-serif;font-size:16px;line-height:1.5;margin:0;padding:5px 0">Thank you for placing the order</h2>
        </td>
    </tr>
    <tr>
        <td colspan="3" style="border-bottom-style:none;text-align:center">
            <span style="color:#b3b3b3;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">#{{$data['transaction_receipt_number']}}<br>{{date('d M Y H:i', strtotime($data['transaction_date']))}}</span>
        </td>
    </tr>
    <tr>
        <th colspan="3" style="border-bottom-style:none;padding-left:10px;padding-right:10px">
        </th>
    </tr>

    @foreach ($data['productTransaction'] as $key => $item)
        <tr style="text-align:right">
            <td style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="left">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{$item['product']['product_name']}} ({{$item['transaction_product_qty']}})
            </span><br>

                <?php
                $topping = '';
                foreach ($data['modifiers'] as $mf){
                    $topping .= $mf['text']. '('.$mf['qty'].'), ';
                }
                echo '<span style="color:#999;font-family:\'Source Sans Pro\',sans-serif;font-size:12px;margin-left:5%;line-height:1.5;padding:0">'.substr($topping, 0, -2).'</span>';
                ?>
            </td>
            <td  width="10%"style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="right">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(floatval ($item['transaction_product_price']), '_CURRENCY') }}</span>
            </td>
        </tr>
    @endforeach

    <tr style="text-align:right">
        <td style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">Subtotal</span>
        </td>
        <td style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:15px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_subtotal']), '_CURRENCY') }}</span>
        </td>
    </tr>
    @if($data['transaction_discount'] != 0)
        <tr style="text-align:right">
            <td style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
                <span style="color:#8fd6bd;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0"><b>Discount</b></span>
            </td>
            <td style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
                <span style="color:#8fd6bd;font-family:\'Source Sans Pro\',sans-serif;font-size:15px;line-height:1.5;margin:0;padding:0"><b>{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_discount']), '_CURRENCY') }}</b></span>
            </td>
        </tr>
    @endif
    <tr style="text-align:right">
        <td  style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#8c8c8c;font-family:\'Source Sans Pro\',sans-serif;font-size:20px;line-height:1.5;margin:0;padding:0"><b>Grand Total</b></span>
        </td>
        <td style="background:#f8f8f8;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#8c8c8c;font-family:\'Source Sans Pro\',sans-serif;font-size:20px;line-height:1.5;margin:0;padding:0"><b>{{ \App\Lib\MyHelper::requestNumber(floatval ($data['transaction_grandtotal']), '_CURRENCY') }}</b></span>
        </td>
    </tr>

    @if(!empty($data['data_payment']))
        <tr>
            <th colspan="5" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)">
                <h2 style="color:#ffffff;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:5px 0">Payment Detail</h2>
            </th>
        </tr>
        @foreach($data['data_payment'] as $dp)
            <tr>
                <td style="background:#ffffff;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="left">
                    <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{strtoupper($dp['payment_method'])}}</span>
                </td>
                <td colspan="2" style="background:#ffffff;border-bottom-color:#cccccc;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
                    <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0;">{{ \App\Lib\MyHelper::requestNumber(floatval ($dp['nominal']), '_CURRENCY') }}</span>
                </td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" style="background:#ffffff;border-top: 2px dashed #8fd6bd;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)">
            </td>
        </tr>
    @endif
    </tbody>
</table>

<table style="margin-left: auto;margin-right: auto;max-width: 1000px;float: none;background:#ffffff;" width="500px" cellspacing="0" cellpadding="5" border="0" bgcolor="#FFFFFF">
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
            <td width="50%" style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="center">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:18px;line-height:1.5;margin:0;padding:0">Detail Outlet</span>
            </td>
            <td width="50%" style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="center">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:18px;line-height:1.5;margin:0;padding:0">Your Pick Up Code</span>
            </td>
        </tr>
        <tr>
            <td  style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="center">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">
                {{ $data['outlet']['outlet_name'] }}
            </span><br>
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">
                {{ $data['outlet']['outlet_address'] }}
            </span>
            </td>
            <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="center">
                <img class="img-responsive" style="display: block;max-width: 100%;height: 100px" src="{{ $data['qr'] }}"><br>
                {{ $data['detail']['order_id'] }}
            </td>
        </tr>
    @endif
    <tr>
        <td colspan="5"></td>
    </tr>
    <tr>
        <td colspan="5"></td>
    </tr>
    <tr>
        <td colspan="5" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)"></td>
    </tr>
    </tbody>
</table>
