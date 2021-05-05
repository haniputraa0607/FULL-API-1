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
    public function retryRefund($id_transaction, &$errors = [], $manualRetry = false)
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
        $result = true;
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
                    if ($tvf->retry_count >= 3 && !$manualRetry) {
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
                    $errors = $refund['messages'] ?? [];
                    $result = false;
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
                    $errors = $errors2;
                    $result = false;
                } else {
                    $tvf->update(['retry_status' => 1]);
                }
                break;
            default:
                $errors[] = 'Unkown payment type '.$tvf->payment_type;
                return false;
        }
        return $result;
    }

    public function index(Request $request)
    {
        $result = Transaction::select('transactions.id_transaction', 'transaction_date', 'transaction_receipt_number', 'users.name', 'users.phone', 'transaction_multiple_payments.type as trasaction_payment_type', 'transaction_grandtotal', 'need_manual_void', 'order_id', 'outlets.outlet_name', 'outlet_code', 'trasaction_type', 'failed_void_reason', 'transaction_void_faileds.retry_count', 'transaction_void_faileds.retry_status', \DB::raw('transaction_grandtotal - coalesce(transaction_payment_balances.balance_nominal, 0) as refund_nominal'))
            ->join('users', 'users.id', 'transactions.id_user')
            ->join('transaction_void_faileds', 'transaction_void_faileds.id_transaction', 'transactions.id_transaction')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
            ->join('transaction_multiple_payments', function($query) {
                $query->on('transaction_multiple_payments.id_transaction', 'transactions.id_transaction')
                    ->where('type', '<>', 'Balance');
            })
            ->leftJoin('transaction_payment_balances', 'transaction_payment_balances.id_transaction', 'transactions.id_transaction')
            ->with('transaction_payment_midtrans', 'transaction_payment_shopee_pay');

        $countTotal = null;

        if ($request->rule) {
            $countTotal = $result->count();
            $this->filterList($result, $request->rule, $request->operator ?: 'and');
        }

        if (is_array($orders = $request->order)) {
            $columns = [
                'transaction_date', 
                'transaction_receipt_number', 
                'name', 
                'phone',
                'trasaction_payment_type',
                'transaction_grandtotal', 
                'refund_nominal', 
            ];

            foreach ($orders as $column) {
                if ($colname = ($columns[$column['column']] ?? false)) {
                    $result->orderBy($colname, $column['dir']);
                }
            }
        }
        $result->orderBy('transactions.id_transaction', $column['dir'] ?? 'DESC');

        if ($request->page) {
            $result = $result->paginate($request->length ?: 15);
            $result->each(function($item) {
                $item->images = array_map(function($item) {
                    return env('STORAGE_URL_API').$item;
                }, json_decode($item->images) ?? []);
            });
            $result = $result->toArray();
            if (is_null($countTotal)) {
                $countTotal = $result['total'];
            }
            // needed by datatables
            $result['recordsTotal'] = $countTotal;
            $result['recordsFiltered'] = $result['total'];
        } else {
            $result = $result->get();
        }
        return MyHelper::checkGet($result);
    }

    public function filterList($model, $rule, $operator = 'and')
    {
        $new_rule = [];
        $where    = $operator == 'and' ? 'where' : 'orWhere';
        foreach ($rule as $var) {
            $var1 = ['operator' => $var['operator'] ?? '=', 'parameter' => $var['parameter'] ?? null, 'hide' => $var['hide'] ?? false];
            if ($var1['operator'] == 'like') {
                $var1['parameter'] = '%' . $var1['parameter'] . '%';
            }
            $new_rule[$var['subject']][] = $var1;
        }
        $model->where(function($model2) use ($model, $where, $new_rule){
            $inner = ['transaction_receipt_number', 'order_id'];
            foreach ($inner as $col_name) {
                if ($rules = $new_rule[$col_name] ?? false) {
                    foreach ($rules as $rul) {
                        $model2->$where($col_name, $rul['operator'], $rul['parameter']);
                    }
                }
            }

            $inner = ['name', 'phone'];
            foreach ($inner as $col_name) {
                if ($rules = $new_rule[$col_name] ?? false) {
                    foreach ($rules as $rul) {
                        $model2->$where('users.'.$col_name, $rul['operator'], $rul['parameter']);
                    }
                }
            }

            $inner = ['id_outlet'];
            foreach ($inner as $col_name) {
                if ($rules = $new_rule[$col_name] ?? false) {
                    foreach ($rules as $rul) {
                        $model2->$where('transactions.'.$col_name, $rul['operator'], $rul['parameter']);
                    }
                }
            }
        });

        if ($rules = $new_rule['transaction_date'] ?? false) {
            foreach ($rules as $rul) {
                $model->where(\DB::raw('DATE(transaction_date)'), $rul['operator'], $rul['parameter']);
            }
        }
    }

    public function retry(Request $request)
    {
        $retry = $this->retryRefund($request->id_transaction, $errors);
        if ($retry) {
            return [
                'status' => 'success'
            ];
        } else {
            return [
                'status' => 'fail',
                'messages' => $errors ?? ['Something went wrong']
            ];
        }
    }
}
