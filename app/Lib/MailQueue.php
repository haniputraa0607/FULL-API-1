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
		}

		SendMail::dispatch($mail, $callback_data)->onQueue($queue)->allOnConnection('email');
	}
}