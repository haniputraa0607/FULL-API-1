<table style="background:#ffffff;" width="100%" cellspacing="0" cellpadding="5" border="0" bgcolor="#FFFFFF">
    <tbody>
    <tr>
        <th colspan="4" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(40, 141, 73)">
            <h2 style="color:#000000;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:5px 0"><a style="color:#000000!important;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0;text-decoration:none" target="_blank" data-saferedirecturl="https://www.google.com/url?q=http://vourest.com/history/transaction/hakaikykdm/1011&amp;source=gmail&amp;ust=1539830594941000&amp;usg=AFQjCNG9sneH2MymFvLJsuVjeOY2XvH7QA">#{{$data['transaction_receipt_number']}}</a></h2>
        </th>
    </tr>
    <tr>
        <td colspan="4" style="background:#ffffff;border-bottom-color:#cccccc;border-bottom-style:solid;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{date('d M Y H:i', strtotime($data['transaction_date']))}}</span>
        </td>
    </tr>
    <tr>
        <th colspan="4" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(40, 141, 73)">
        </th>
    </tr>
    <tr>
        <td style="background:#f0f0f0;border-bottom-color:#cccccc;border-bottom-style:solid;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:11px;line-height:1.5;margin:0;padding:15px 10px" width="25%" valign="top" bgcolor="#F0F0F0" align="center">
            <strong style="color:#555;font-size:14px">Product Name</strong>
        </td>
        <td style="background:#f0f0f0;border-bottom-color:#cccccc;border-bottom-style:solid;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:11px;line-height:1.5;margin:0;padding:15px 10px" width="25%" valign="top" bgcolor="#F0F0F0" align="right">
            <strong style="color:#555;font-size:14px">Price</strong>
        </td>
        <td style="background:#f0f0f0;border-bottom-color:#cccccc;border-bottom-style:solid;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:11px;line-height:1.5;margin:0;padding:15px 10px" width="10%" valign="top" bgcolor="#F0F0F0" align="center">
            <strong style="color:#555;font-size:14px">Qty</strong>
        </td>
        <td style="background:#f0f0f0;border-bottom-color:#cccccc;border-bottom-style:solid;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:11px;line-height:1.5;margin:0;padding:15px 10px" width="10%" valign="top" bgcolor="#F0F0F0" align="center">
            <strong style="color:#555;font-size:14px">Subtotal</strong>
        </td>
    </tr>

    @foreach ($data['productTransaction'] as $key => $item)
        <tr style="text-align:right">
            <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="center">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{$item['product']['product_name']}}</span><br>
                <?php
                $topping = '';
                foreach ($data['modifiers'] as $mf){
                    $topping .= $mf['text']. '('.$mf['qty'].'), ';
                }
                echo '<span style="color:#999;font-family:\'Source Sans Pro\',sans-serif;font-size:11px;line-height:1.5;margin:0;padding:0">'.substr($topping, 0, -2).'</span>';
                ?>
            </td>
            <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="right">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(explode('.',$item['transaction_product_price_base'])[0], '_CURRENCY') }}</span>
            </td>
            <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="center">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{$item['transaction_product_qty']}}</span>
            </td>
            <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" align="right">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber(explode('.',$item['transaction_product_price'])[0], '_CURRENCY') }}</span>
            </td>
        </tr>
    @endforeach


    <tr style="text-align:right">
        <td colspan="3" style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">Subtotal</span>
        </td>
        <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:15px;line-height:1.5;margin:0;padding:0">{{ \App\Lib\MyHelper::requestNumber($data['transaction_subtotal'], '_CURRENCY') }}</span>
        </td>
    </tr>
    <tr style="text-align:right">
        <td colspan="3" style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#8fd6bd;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0"><b>Discount</b></span>
        </td>
        <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#8fd6bd;font-family:\'Source Sans Pro\',sans-serif;font-size:15px;line-height:1.5;margin:0;padding:0"><b>-{{ \App\Lib\MyHelper::requestNumber($data['transaction_discount'], '_CURRENCY') }}</b></span>
        </td>
    </tr>
    <tr style="text-align:right">
        <td colspan="3" style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0"><b>Grand Total</b></span>
        </td>
        <td style="background:#ffffff;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
            <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:15px;line-height:1.5;margin:0;padding:0"><b>{{ \App\Lib\MyHelper::requestNumber($data['transaction_grandtotal'], '_CURRENCY') }}</b></span>
        </td>
    </tr>
    <tr>
        <th colspan="4" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(40, 141, 73)">
        </th>
    </tr>

    <tr>
        <th colspan="4" style="background:#8fd6bd;border-bottom-style:none;color:#ffffff;padding-left:10px;padding-right:10px" bgcolor="background: rgb(143, 214, 189)">
            <h2 style="color:#000000;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:5px 0">Payment Details</h2>
        </th>
    </tr>
    @foreach($data['data_payment'] as $dp)
        <tr>
            <td style="background:#ffffff;border-bottom-color:#cccccc;border-bottom-style:solid;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="left">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0">{{strtoupper($dp['payment_method'])}}</span>
            </td>
            <td colspan="3" style="background:#ffffff;border-bottom-color:#cccccc;border-bottom-style:solid;border-bottom-width:1px;border-collapse:collapse;border-spacing:0;color:#555;font-family:\'Source Sans Pro\',sans-serif;line-height:1.5;margin:0;padding:15px 10px" valign="top" bgcolor="#FFFFFF" align="right">
                <span style="color:#555;font-family:\'Source Sans Pro\',sans-serif;font-size:14px;line-height:1.5;margin:0;padding:0;">{{ \App\Lib\MyHelper::requestNumber($dp['nominal'], '_CURRENCY') }}</span>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>