<?php

namespace Tantto\Fail2BanLaravel;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fail2Ban extends Model
{
    use SoftDeletes;

    protected $table = "fail2ban";

    protected $fillable = [
        Fail2Ban::ACCESS_IP,
        Fail2Ban::ACCESS_URL,
    ];

    protected $hidden = [
        Fail2Ban::ID,
        Fail2Ban::ACCESS_URL,
        Fail2Ban::BAN_LEVEL,
        Fail2Ban::CLEAN_DATE,
        Fail2Ban::CREATED_AT,
        Fail2Ban::UPDATED_AT,
        Fail2Ban::DELETED_AT,
    ];

    public const ID = "id";
    public const ACCESS_IP = "access_ip";
    public const ACCESS_URL = "access_url";
    public const BAN_LEVEL = "ban_level";
    public const UNBAN_DATE = "unban_date";
    public const CLEAN_DATE = "clean_date";
    public const CREATED_AT = "created_at";
    public const UPDATED_AT = "updated_at";
    public const DELETED_AT = "deleted_at";

    // Fail2Ban

    // Nível 0 => Menos que 5 tentativas => Sem banimento
    // Nível 1 => 5 tentativas dentro de 10 minuos -> banimento de 5 minutos;
    // Nível 2 => +5 tentativas dentro de 1 hora desde o ultimo banimento de 5 minutos-> banimento de 15 minutos;
    // Nível 3 => +5 tentativas dentro de 1 dia desde o ultimo banimento de 15 minutos-> banimento de 24 horas;
    // Nível 4 => +5 tentativas dentro de 1 semana desde o ultimo banimento de 24 horas -> banimento de 1 mês;
    // Nível 5 => +5 tentativas dentro de 1 mês desde o ultimo banimento de 1 mês -> banimento de 10 anos;

    // Qualquer tentativa que seja sucesso remove qualquer banimento relacionado ao e-mail que foi sucesso

    private const URI_CHECK = [
        '/oauth/token',
        '/fail2ban-test'
    ];
    private const unban_time_by_level = [
        '0' => '0',
        '1' => '5',
        '2' => '15',
        '3' => '1440',
        '4' => '43200',
        '5' => '5256000',
    ];
    private const clean_time_by_level = [
        '0' => '10',
        '1' => '60',
        '2' => '1440',
        '3' => '10080',
        '4' => '43200',
        '5' => '5256000',
    ];
    private const tries_to_next_level = [
        '0' => '5',
        '1' => '5',
        '2' => '5',
        '3' => '5',
        '4' => '5',
        '5' => '-1',
    ];
    private const WHITE_LIST_IP = [
        '187.8.9.123',
        '177.183.69.140',
        '187.8.9.122',
        '187.180.185.147',
    ];

    public static function ValidateIP($ip)
    {
        if (in_array($ip, static::WHITE_LIST_IP))
            return null;

        $fail2ban = static::where(static::ACCESS_IP, $ip)
            ->where(static::BAN_LEVEL, '>=', 1)
            ->where(static::UNBAN_DATE, '>=', Carbon::now())
            ->orderBy(static::BAN_LEVEL, 'desc')
            ->first(); // Pegar apenas o de maior nivel

        if (!$fail2ban)
            return null;
        return $fail2ban;
    }

    public static function BanIP($ip, $url)
    {
        if (in_array($ip, static::WHITE_LIST_IP))
            return;
        if (!in_array($url, static::URI_CHECK))
            return;

        $fails2ban = static::where(static::ACCESS_IP, $ip)
            ->where(static::CLEAN_DATE, '>=', Carbon::now())
            ->orderBy(static::BAN_LEVEL, 'desc')
            ->get();

        $level = 0;
        $count = 0;
        $unban_date = Carbon::now();
        foreach ($fails2ban as $f2b) {
            if ($f2b->{static::BAN_LEVEL} < $level) { // Mudou de level, parar loop
                break;
            }
            $level = $f2b->{static::BAN_LEVEL};
            $count++;
        }
        if ($count >= static::tries_to_next_level[$level]) {
            if (static::tries_to_next_level[$level] >= 0)
                $level++;
            $unban_date = $unban_date->addMinutes(static::unban_time_by_level[$level]);
        }

        $fail2ban = new Fail2Ban();
        $fail2ban->{static::ACCESS_IP} = $ip;
        $fail2ban->{static::ACCESS_URL} = $url;
        $fail2ban->{static::BAN_LEVEL} = $level;
        $fail2ban->{static::UNBAN_DATE} = $unban_date;
        $fail2ban->{static::CLEAN_DATE} = Carbon::now()->addMinutes(static::clean_time_by_level[$level]);
        $fail2ban->save();
    }

    public static function UnbanIP($ip)
    {
        static::where(static::ACCESS_IP, $ip)->delete();
    }

    public static function Clean(): int
    {
        $fails2ban = static::where(static::CLEAN_DATE, '<', Carbon::now())->orWhereIn(static::ACCESS_IP, static::WHITE_LIST_IP)->get();
        $count = count($fails2ban);
        foreach ($fails2ban as $f2b) {
            $f2b->delete();
        }
        return $count;
    }
}
