<?php

declare(strict_types=1);

namespace App\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ApiResponseTrait
{
    protected function successResponse(mixed $data = null, array $meta = [], int $statusCode = 200): JsonResponse
    {
        $response = ['data' => $data];
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        return new JsonResponse($response, $statusCode);
    }

    protected function createdResponse(mixed $data = null): JsonResponse
    {
        return $this->successResponse($data, [], 201);
    }

    protected function noContentResponse(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    protected function errorResponse(string $message, int $statusCode = 400, ?string $code = null): JsonResponse
    {
        $response = ['error' => $message, 'code' => $statusCode];
        if ($code !== null) {
            $response['error_code'] = $code;
        }
        return new JsonResponse($response, $statusCode);
    }

    protected function validationErrorResponse(array $errors): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Validation failed',
            'code' => 422,
            'violations' => $errors,
        ], 422);
    }
}
