<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            require base_path('routes/health.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (
            Throwable $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof HttpResponseException) {
                return $exception->getResponse();
            }

            $locale = $request->input('locale') === 'en' ? 'en' : 'lv';

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'error' => [
                        'code' => 'validation_failed',
                        'message' => $locale === 'en'
                            ? 'The request data is invalid.'
                            : 'Pieprasījuma dati nav derīgi.',
                        'details' => $exception->errors(),
                    ],
                ], 422);
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $code = match ($status) {
                    404 => 'not_found',
                    405 => 'method_not_allowed',
                    429 => 'rate_limited',
                    default => 'http_error',
                };
                $message = match ($status) {
                    404 => $locale === 'en'
                        ? 'Resource not found.'
                        : 'Resurss nav atrasts.',
                    405 => $locale === 'en'
                        ? 'Method not allowed.'
                        : 'Metode nav atļauta.',
                    429 => $locale === 'en'
                        ? 'Too many requests.'
                        : 'Pārāk daudz pieprasījumu.',
                    default => $locale === 'en'
                        ? 'The request failed.'
                        : 'Pieprasījums neizdevās.',
                };

                return response()->json([
                    'error' => [
                        'code' => $code,
                        'message' => $message,
                    ],
                ], $status);
            }

            return response()->json([
                'error' => [
                    'code' => 'server_error',
                    'message' => $locale === 'en'
                        ? 'The server could not complete the request.'
                        : 'Serveris nevarēja izpildīt pieprasījumu.',
                ],
            ], 500);
        });
    })->create();
