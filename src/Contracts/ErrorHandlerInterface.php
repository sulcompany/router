<?php

declare(strict_types=1);

namespace SulCompany\Router\Contracts;

use SulCompany\Router\Request;
use SulCompany\Router\Response;

interface ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response;
}