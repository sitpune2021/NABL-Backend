<?php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function labContext(Request $request): array
    {
        $labId = (int) $request->header('X-Lab-Id', 0);

        return [
            'lab_id'     => $labId,
            'owner_type' => $labId === 0 ? 'super_admin' : 'lab',
            'owner_id'   => $labId,
        ];
    }

    protected function success($data = [], string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $status);
    }

    protected function error(string $message = 'Error', int $status = 400, $errors = []): JsonResponse
    {
        return response()->json([
            'status' => 'failed',
            'message' => $message,
            'errors' => $errors
        ], $status);
    }
}
