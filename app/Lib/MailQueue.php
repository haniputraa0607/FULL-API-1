<?php

namespace App\Lib;

use App\Jobs\SendMail;
use App\Mail\GenericMail;

class MailQueue{
	public static function send($view, $data = [], $callback = null, $queue = 'email_default', $callback_data = null)
	{
		$mail = (new GenericMail())->view($view, $data);
		if($callback) {
			$callback($mail);
			$to = $mail->to[0]??false;
			if ($to) {
				$mail->to = [['address' => trim($to['address']), 'name' => trim($to['name'])]];
			}
		}

		SendMail::dispatch($mail, $callback_data)->onQueue($queue)->allOnConnection('email');
	}
}