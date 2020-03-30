<?php

namespace Modules\IPay88\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\IPay88\Entities\TransactionPaymentIpay88;
use App\Http\Models\Transaction;

use Modules\IPay88\Lib\IPay88;
use App\Lib\MyHelper;

use Modules\IPay88\Http\Requests\Ipay88Response;

class IPay88Controller extends Controller
{
    public function __construct(){
        $this->lib = IPay88::create();
    }

    public function requestView(Request $request) {
        $id_reference = $request->id_reference;
        $type = $request->type;
        $data =  $this->lib->generateData($id_reference,$type);
        return view('ipay88::payment',$data);
    }

    public function notifUser(Ipay88Response $request,$type) {
        $post = $request->post();
        $post['type'] = $type;
        $post['triggers'] = 'user';
        $trx_ipay88 = TransactionPaymentIpay88::join('transactions','transactions.id_transaction','=','transaction_payment_ipay88s.id_transaction')
            ->where('transaction_receipt_number',$post['RefNo'])->first();
        if(!$trx_ipay88){
            return MyHelper::checkGet($trx_ipay88,'Transaction Not Found');
        }

        $requery = $this->lib->reQuery($post, $post['Status']);

        if($requery['valid']){
            $post['from_user'] = 1;
            $post['requery_response'] = $requery['response'];
            $this->lib->update($trx_ipay88,$post);
            return [
                'status' => 'success'
            ];
        }else{
            return MyHelper::checkGet([],$requery['response']);
        }
    }
}
