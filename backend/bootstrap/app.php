<?php

use App\Exceptions\BalanceException;
use App\Exceptions\OrderException;
use App\Exceptions\RetryException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (BalanceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'error_type' => 'balance_error',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (OrderException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'error_type' => 'order_error',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (RetryException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'error_type' => 'retry_error',
            ], Response::HTTP_BAD_REQUEST);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'message' => '无权访问此资源',
                'code' => 403,
                'error_type' => 'authorization_error',
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'message' => '数据验证失败',
                'code' => 422,
                'error_type' => 'validation_error',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
