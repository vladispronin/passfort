<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

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
            $this->logger->error('Unhandled exception', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }

        $response = new JsonResponse([
            'error' => $message,
            'code' => $statusCode,
        ], $statusCode);

        $event->setResponse($response);
    }
}
