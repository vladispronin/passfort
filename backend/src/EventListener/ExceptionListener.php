<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Только для API запросов
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() ?: 'HTTP Error';
        } else {
            $statusCode = 500;
            $message = 'Internal Server Error';
        }

        $response = new JsonResponse([
            'error' => $message,
            'code' => $statusCode,
        ], $statusCode);

        $event->setResponse($response);
    }
}
