<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
        ]);

        // Add UnwrapRequestData middleware to API routes
        $middleware->api(append: [
            \App\Http\Middleware\UnwrapRequestData::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tratamento de exceções customizadas
        $exceptions->render(function (\App\Exceptions\InsufficientBalanceException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (\App\Exceptions\DailyLimitExceededException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (\App\Exceptions\InvalidAccountException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (\App\Exceptions\InvalidTransferException $e, Request $request) {
            return $e->render();
        });

        // Tratamento de ValidationException
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'data' => [
                        'error' => 'Dados de entrada inválidos',
                        'code' => 'VALIDATION_ERROR',
                    ],
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Tratamento de AuthenticationException
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'data' => [
                        'error' => 'Não autenticado',
                        'code' => 'UNAUTHENTICATED',
                    ],
                ], 401);
            }
        });

        // Tratamento de ModelNotFoundException
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'data' => [
                        'error' => 'Recurso não encontrado',
                        'code' => 'RESOURCE_NOT_FOUND',
                    ],
                ], 404);
            }
        });

        // Tratamento de NotFoundHttpException (rotas inexistentes)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'data' => [
                        'error' => 'Endpoint não encontrado',
                        'code' => 'ENDPOINT_NOT_FOUND',
                    ],
                ], 404);
            }
        });

        // Tratamento de erros de banco de dados
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                // Log do erro para debug
                Log::error('Database Error', [
                    'message' => $e->getMessage(),
                    'sql' => $e->getSql() ?? 'N/A',
                    'bindings' => $e->getBindings() ?? [],
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'data' => [
                        'error' => 'Operação não pode ser realizada, tente novamente. Se o erro persistir, entre em contato com nosso suporte.',
                        'code' => 'DATABASE_ERROR',
                    ],
                ], 500);
            }
        });

        // Tratamento de PDOException
        $exceptions->render(function (\PDOException $e, Request $request) {
            if ($request->is('api/*')) {
                // Log do erro
                Log::error('PDO Error', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'data' => [
                        'error' => 'Operação não pode ser realizada, tente novamente. Se o erro persistir, entre em contato com nosso suporte.',
                        'code' => 'DATABASE_CONNECTION_ERROR',
                    ],
                ], 500);
            }
        });

        // Tratamento de HttpException genérico
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'data' => [
                        'error' => $e->getMessage() ?: 'Erro HTTP',
                        'code' => 'HTTP_ERROR',
                    ],
                ], $e->getStatusCode());
            }
        });

        // Tratamento global para exceções não capturadas
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // Log de exceções não tratadas
                Log::error('Unhandled Exception', [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                // Em produção, sempre retorna mensagem genérica para erros 500
                if ($statusCode >= 500) {
                    return response()->json([
                        'data' => [
                            'error' => 'Operação não pode ser realizada, tente novamente. Se o erro persistir, entre em contato com nosso suporte.',
                            'code' => 'INTERNAL_ERROR',
                        ],
                    ], $statusCode);
                }

                return response()->json([
                    'data' => [
                        'error' => app()->environment('production')
                            ? 'Erro interno do servidor'
                            : $e->getMessage(),
                        'code' => 'INTERNAL_ERROR',
                        'trace' => app()->environment('production') ? null : $e->getTraceAsString(),
                    ],
                ], $statusCode);
            }
        });
    })->create();
