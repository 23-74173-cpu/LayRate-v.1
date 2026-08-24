<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Validator;

/**
 * Shared JSON helpers implementing the LayRate API contract (docs/api-contract.md).
 *
 * Success:  { "success": true, "data": <payload> }          (HTTP 200/201)
 * Error:    { "success": false, "error": { "message": "...",
 *                                            "errors": { field: [...] } } }
 * Only the JSON branches of web/device controllers should use these — HTML
 * flows (redirects, back(), session flash) stay untouched.
 */
trait HandlesApiResponses
{
    public function success(mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $code);
    }

    public function created(mixed $data = null): JsonResponse
    {
        return $this->success($data, 201);
    }

    public function error(string $message, int $code = 422, array $fieldErrors = []): JsonResponse
    {
        $error = ['message' => $message];
        if ($fieldErrors !== []) {
            $error['errors'] = $fieldErrors;
        }

        return response()->json(['success' => false, 'error' => $error], $code);
    }

    public function errorFromValidator(Validator $validator, string $message = 'Validation failed.'): JsonResponse
    {
        return $this->error($message, 422, $validator->errors()->toArray());
    }
}