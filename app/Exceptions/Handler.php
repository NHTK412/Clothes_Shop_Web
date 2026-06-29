<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // For API requests return standardized JSON error format
        if ($request->expectsJson() || $request->is('api/*')) {
            $status = 500;
            if ($exception instanceof AuthenticationException) {
                $status = 401;
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
            }

            $message = $exception->getMessage() ?: 'Thông tin lỗi';

            $payload = [
                'status' => $status,
                'success' => false,
                'message' => $message,
                'data' => null,
            ];

            return new JsonResponse($payload, $status);
        }

        return parent::render($request, $exception);
    }
}
