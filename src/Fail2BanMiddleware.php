<?php

namespace Tantto\Fail2BanLaravel;

use Carbon\Carbon;
use Closure;

class Fail2BanMiddleware
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
        $ip = $request->header('x-real-ip') ? $request->header('x-real-ip') : $request->ip();
        $fail2ban = Fail2Ban::ValidateIP($ip);
        if (!is_null($fail2ban)) {
            $unban_date = Carbon::parse($fail2ban->{Fail2Ban::UNBAN_DATE});
            $unban_date->setLocale('pt_BR');
            $unban_date = $unban_date->fromNow(null, null, 2);
            return response()->json([
                'error' => 'IP_BAN',
                'message' => 'Seu IP foi banido. Liberação ' . $unban_date,
                'data' => $fail2ban,
            ], 401);
        }
        return $next($request);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function terminate($request, $response)
    {
        $response_content =  json_decode($response->getContent());
        if (!is_null($response_content) && isset($response_content->error) && $response_content->error == 'IP_BAN') // Não banir IPs já banidos
            return;
        $url = $_SERVER['REQUEST_URI'];
        $ip = $request->header('x-real-ip') ? $request->header('x-real-ip') : $request->ip();
        switch ($response->getStatusCode()) {
            case 200:
                Fail2Ban::UnbanIP($ip);
                break;
            case 401:
                Fail2Ban::BanIP($ip, $url);
                break;
        }
    }
}
