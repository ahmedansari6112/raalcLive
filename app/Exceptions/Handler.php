<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        // List of exceptions that should not be reported
    ];

    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function render($request, Throwable $exception)
    {
        if ($request->wantsJson()) {
            return $this->convertExceptionToResponse($exception);
        }

        return parent::render($request, $exception);
    }

    protected function convertExceptionToResponse(Throwable $e): JsonResponse
    {
        $status = $this->getStatusCode($e);

        return new JsonResponse([
            'status' => 'false', 
            'message' => $e->getMessage()
        ], $status);
    }

    protected function getStatusCode(Throwable $e): int
    {
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return SymfonyResponse::HTTP_UNAUTHORIZED;
        }

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY;
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return SymfonyResponse::HTTP_NOT_FOUND;
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return SymfonyResponse::HTTP_METHOD_NOT_ALLOWED;
        }

        return SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR;
    }

    protected function getErrorMessage(Throwable $e): string
    {
        // Customize error messages here if needed
        return 'An error occurred';
    }
}
