<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ApiTransactionCIMB extends Controller
{
    public function callback(Request $request)
    {
        return $request;
        //
    }
}
