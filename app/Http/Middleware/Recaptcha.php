<?php

namespace App\Http\Middleware;

use Closure;
use TimeHunter\LaravelGoogleReCaptchaV3\Facades\GoogleReCaptchaV3;

class Recaptcha
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (stristr($_SERVER['HTTP_USER_AGENT'], 'iOS') && $request->{env('CAPTCHA_PASS_KEY', 'M1d3H')} == env('CAPTCHA_PASS_VALUE','CZbCIT4fpgF7OfQ8Q3mB')) {
            return $next($request);
        }

        $captcha = GoogleReCaptchaV3::verifyResponse($request->g_recaptcha)->isSuccess();
        if (!$captcha) {
            return response()->json(['status' => 'fail', 'messages' => ['Invalid captcha. Try again.']]);
        }
        return $next($request);
    }
}
