<?php

namespace Example\Middlewares;


class AuthMiddleware
{
    public function handle($router): bool
    {
        $auth = false;
        if (!$auth) {
            echo "faça login";
            return false;
        }

        echo"logado";
        return true;
    }
}
