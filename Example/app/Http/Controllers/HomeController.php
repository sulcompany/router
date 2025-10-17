<?php

namespace Example\Http\Controllers;

class HomeController
{
    public function index($data): void
    {
        echo "Home";
        var_dump($data);
    }
}