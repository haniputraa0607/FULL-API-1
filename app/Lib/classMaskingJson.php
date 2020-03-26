<?php

namespace App\Lib;

class classMaskingJson {
	protected $data;
	protected $smsserverip;
	public function setData($data) {
		$this->data = $data;
	}
	public function send() {
		$dt=json_encode($this->data);
		$curlHandle = curl_init(env('SMS_URL')."/sms/api_sms_masking_send_json.php");
		curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $dt);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($dt))
		);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
		curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5);

		$hasil = curl_exec($curlHandle);
		$curl_response = $hasil;
		$curl_errno = curl_errno($curlHandle);
		$curl_error = curl_error($curlHandle);
		$http_code  = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		$curl_info=json_encode(curl_getinfo($curlHandle));
		curl_close($curlHandle);
		if ($curl_errno > 0) {
			$senddata = array(
			'sending_respon'=>array(
				'globalstatus' => 90,
				'globalstatustext' => $curl_errno."|".$http_code)
			);
			$hasil=json_encode($senddata);
		} else {
			if ($http_code<>"200") {
			$senddata = array(
			'sending_respon'=>array(
				'globalstatus' => 90,
				'globalstatustext' => $curl_errno."|".$http_code)
			);
			$hasil= json_encode($senddata);
			}
		}

		$log=[
			'request_header'=>$header??null,
			'request_body'=>$this->data,
			'request_url'=>env('SMS_URL'),
			'request_method'=>'POST',
			'response'=>$curl_response,
			'more_info'=>$curl_info,
			'phone'=>isset($this->data['number'])??null,
			'user_agent'=>'Mozilla/5.0 (Windows NT 5.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
		];
		MyHelper::logApiSMS($log);

		return $hasil;
	}
	public function balance() {
		$dt=json_encode($this->data);
		$curlHandle = curl_init(env('SMS_URL')."/sms/api_sms_masking_balance_json.php");
		curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $dt);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($dt))
		);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
		curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5);
		$hasil = curl_exec($curlHandle);
		$curl_errno = curl_errno($curlHandle);
		$curl_error = curl_error($curlHandle);
		$http_code  = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		curl_close($curlHandle);
		if ($curl_errno > 0) {
			$senddata = array(
			'sending_respon'=>array(
				'globalstatus' => 90,
				'globalstatustext' => $curl_errno."|".$http_code)
			);
			$hasil=json_encode($senddata);
		} else {
			if ($http_code<>"200") {
			$senddata = array(
			'sending_respon'=>array(
				'globalstatus' => 90,
				'globalstatustext' => $curl_errno."|".$http_code)
			);
			$hasil= json_encode($senddata);
			}
		}
		return $hasil;
	}
}