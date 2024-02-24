<?php

namespace App\Auth;

use Tymon\JWTAuth\Contracts\JWTSubject;

class JWTAuthentication implements JWTSubject
{
    protected $id;
    protected $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function getJWTIdentifier()
    {
        return $this->id;
    }

    public function getJWTCustomClaims()
    {
        return [
            'name' => $this->name,
        ];
    }
}
