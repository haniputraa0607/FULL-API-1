<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Transaction\Entities\TransactionVoidFailed;
use App\Lib\MyHelper;
use App\Lib\Midtrans;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\Transaction;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;

class TransactionVoidFailedController extends Controller
{
    public function __construct()
    {
        $this->autocrm = 'Modules\Autocrm\Http\Controllers\ApiAutoCrm';
        $this->shopeepay      = 'Modules\ShopeePay\Http\Controllers\ShopeePayController';
    }
    public function retryRefund($id_transaction, &$errors = [])
    {
        $refund_failed_process_balance = MyHelper::setting('refund_failed_process_balance');
        $shared = \App\Lib\TemporaryDataManager::create('retry_refund');
        $tvf = TransactionVoidFailed::where('id_transaction', $id_transaction)->first();
        if (!$tvf) {
            $errors[] = 'Model TransactionVoidFailed not found';
            return false;
        }
        $tvf->update(['retry_count' => $tvf->retry_count+1]);
        $rejectBalance = false;
        switch ($tvf->payment_type) {
            case 'Midtrans':
                $payMidtrans = TransactionPaymentMidtran::where('id_transaction', $id_transaction)->first();
                if (!$payMidtrans) {
                    $errors[] = 'Model TransactionPaymentMidtran not found';
                    return false;
                }
                $refund = Midtrans::refund($payMidtrans['vt_transaction_id'],['reason' => $tvf->refund_reason]);
                if ($refund['status'] != 'success') {
                    Transaction::where('id_transaction', $id_transaction)->update(['failed_void_reason' => implode(', ', $refund['messages'] ?? [])]);
                    if ($tvf->retry_count >= 3) {
                        if ($refund_failed_process_balance) {
                            $rejectBalance = true;
                        } else {
                            $trx = Transaction::where('id_transaction', $id_transaction)->with('user')->first();
                            $trx->update(['need_manual_void' => 1]);
                            $trx->payment_method = 'Midtrans';
                            $trx->payment_detail = $payMidtrans->payment_type;
                            $trx->manual_refund = $payMidtrans['gross_amount'];
                            $trx->payment_reference_number = $payMidtrans['vt_transaction_id'];
                            if ($shared['reject_batch'] ?? false) {
                                $shared['void_failed'][] = $trx;
                            } else {
                                $variables = [
                                    'detail' => view('emails.failed_refund', ['transaction' => $trx])->render()
                                ];
                                app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('Payment Void Failed', optional($trx->user)->phone, $variables, null, true);
                            }
                        }
                    }
                } else {
                    $tvf->update(['retry_status' => 1]);
                }
                break;
            case 'Shopeepay':
                $payShopeepay = TransactionPaymentShopeePay::where('id_transaction', $id_transaction)->first();
                if (!$payShopeepay) {
                    $errors[] = 'Model TransactionPaymentShopeePay not found';
                    return false;
                }
                $refund = app($this->shopeepay)->refund($id_transaction, 'trx', $errors2);
                if (!$refund) {
                    Transaction::where('id_transaction', $id_transaction)->update(['failed_void_reason' => implode(', ', $errors2 ?: [])]);
                    if ($tvf->retry_count >= 3) {
                        if ($refund_failed_process_balance) {
                            $rejectBalance = true;
                        } else {
                            $trx = Transaction::where('id_transaction', $id_transaction)->with('user')->first();
                            $trx->update(['need_manual_void' => 1]);
                            $trx->manual_refund = $payShopeepay['amount']/100;
                            $trx->payment_method = 'ShopeePay';
                            $trx->payment_reference_number = $payShopeepay['transaction_sn'];
                            if ($shared['reject_batch'] ?? false) {
                                $shared['void_failed'][] = $trx;
                            } else {
                                $variables = [
                                    'detail' => view('emails.failed_refund', ['transaction' => $trx])->render()
                                ];
                                $send = app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('Payment Void Failed', optional($trx->user)->phone, $variables, null, true);
                            }
                        }
                    }
                } else {
                    $tvf->update(['retry_status' => 1]);
                }
                break;
            default:
                $errors[] = 'Unkown payment type '.$tvf->payment_type;
                return false;
        }
        return true;
    }
}
