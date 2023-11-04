<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;


class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $type = $request->header('sourcetype');

        if(!$type) {
            return abort(401, 'unauthenticated');
        }

        $token = $type == 'finance' ? $this->getFinanceToken() : $this->getSalesManToken();

        $request->headers->add([
            'Authorization' => 'Bearer ' . $token,
        ]);

        $this->logRequest();

        return $next($request);
    }

    private function getFinanceToken()
    {

          $cityCode = request()->header('citycode');
          $username = request()->header('username');
          $password = request()->header('password');


          Log::debug("login credentials", [
            'citycode' => $cityCode,
            'username' => $username,
            'password' => $password,
          ]);

          if($username == "REST") {
            return abort(403);
          }

        return Cache::remember("token:$username:$cityCode", 60 * 20, function () use ($username, $password, $cityCode) {
            $response = Http::withOptions(['verify' => false])
                ->timeout(1000)
                ->withHeaders([
                    'Authorization' => 'basic TUVGQVBFWDpGWEh4VGV4NThWd0pwbXNaSC9sSHVybkQ1elAwWVo3Tm14M0xZaDF1SFVvPQ==',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->withBody("grant_type=password&username=$username&firmno=$cityCode&password=$password", 'text/plain')
                ->post('https://10.27.0.109:32002/api/v1/token');
        
            Log::debug('response', ['data' => $response->json()]);
        
            return $response['access_token'] ?? abort(403);
        });
    }

    private function getSalesManToken()
    {

        $cityCode = request()->header('citycode');

        return Cache::remember("token:$cityCode", 60 * 20, function () use ($cityCode) {
                    $response = Http::withOptions(['verify' => false])
                     ->timeout(1000)
                        ->withHeaders([
                            'Authorization' => 'basic TUVGQVBFWDpGWEh4VGV4NThWd0pwbXNaSC9sSHVybkQ1elAwWVo3Tm14M0xZaDF1SFVvPQ==',
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json'
                        ])
                        ->withBody("grant_type=password&username=REST&firmno=$cityCode&password=REST454545", 'text/plain')
                        ->post('https://10.27.0.109:32002/api/v1/token');
            
                Log::debug('response', ['data' => $response->json()]);
            
                return $response['access_token'] ?? abort(403);
        });
    }

    private function logRequest()
    {
        Log::debug('logging request', [
            'data' => request()->all(),
            'url' => request()->url()
        ]);
    }
}
