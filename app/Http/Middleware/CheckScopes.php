<?php

namespace App\Http\Middleware;

use App\Http\Models\OauthAccessToken;
use App\Http\Models\Setting;
use Closure;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Parser;
use SMartins\PassportMultiauth\Http\Middleware\AddCustomProvider;

class CheckScopes extends AddCustomProvider
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $scope = null)
    {
        /*check status maintenance mode for apps*/
        if($scope == 'apps'){
            $getMaintenance = Setting::where('key', 'maintenance_mode')->first();
            if($getMaintenance && $getMaintenance['value'] == 1){
                return response()->json([
                    'status' => 'fail',
                    'messages' => ['maintenance'],
                    'url_maintenance' =>  env('API_URL') ."api/maintenance-mode"
                ], 200);
            }
        }

        if($request->user()){
            $dataToken = json_decode($request->user()->token());
            $scopeUser = $dataToken->scopes[0];
        }else{
            $bearerToken = $request->bearerToken();
            $tokenId = (new Parser())->parse($bearerToken)->getHeader('jti');
            $getOauth = OauthAccessToken::find($tokenId);
            $scopeUser = str_replace(str_split('[]""'),"",$getOauth['scopes']);
            $clientId = $getOauth['client_id'];
        }

        if($scope == 'pos' && $scopeUser == 'pos' && $clientId == 1){
            return $next($request);
        }else{
            if($scope == 'be' && $scopeUser == 'be'){
                return $next($request);
            }elseif($scope == 'apps' && $scopeUser == 'apps'){
                return $next($request);
            }
        }
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}