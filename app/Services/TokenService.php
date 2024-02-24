<?php

namespace App\Services;

use App\Auth\JWTAuthentication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class TokenService
{
    private function getJWT($userId, $name)
    {
        $user = new JWTAuthentication($userId, $name);
        JWTAuth::factory()->setTTL(45);

        return JWTAuth::fromSubject($user);
    }

    private function getRefresh()
    {
        return bin2hex(random_bytes(64));
    }

    public function getTokensPair($id, $name)
    {
        $jwt = $this->getJWT($id, $name);

        $refresh = $this->getRefresh();

        return [
            'access_token' => $jwt,
            'refresh_token' => $refresh,
        ];
    }

    public static function refreshToken()
    {

    }
}
