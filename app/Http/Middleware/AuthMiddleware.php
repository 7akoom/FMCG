<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\FlareClient\Http\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controllers\Middleware;


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
        $response = Http::withOptions([
            'verify' => false,
        ])
            ->withHeaders([
                'Authorization' => 'basic TUVGQVBFWDpGWEh4VGV4NThWd0pwbXNaSC9sSHVybkQ1elAwWVo3Tm14M0xZaDF1SFVvPQ==',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])
            ->withBody('grant_type=password&username=REST&firmno=888&password=REST454545', 'text/plain')
            ->post('https://10.27.0.109:32002/api/v1/token');

        Log::debug('response', ['data' => $response->json()]);

        $access_token = $response['access_token'];
        $request->headers->add([
            'Authorization' => 'Bearer ' . $access_token,
        ]);
        return $next($request);
    }
}
