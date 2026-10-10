<?php

declare(strict_types=1);

namespace SulCompany\Router;

use SulCompany\Router\Contracts\ErrorHandlerInterface;

class DefaultErrorHandler implements ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response
    {
        $message = $this->message($status);

        if ($request->expectsJson()) {
            return $response->json([
                'success' => false,
                'code'    => $status,
                'message' => $message,
            ], $status);
        }

        return $response->text("{$status} — {$message}", $status);
    }



    protected function message(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Session Expired',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            default => 'Error',
        };
    }
}