<?php

namespace App\Http\Controllers;

abstract class Controller
{

    protected function success($data = null, $message = 'Success', $code = 200)
    {
        return response()->json([
            'status' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error($message = 'Error', $code = 400, $data = null)
    {
        return response()->json([
            'status' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function validationError($errors, $message = 'Validation Error', $code = 422)
    {
        return response()->json([
            'status' => $code,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function notFound($message = 'Resource not found')
    {
        return $this->error($message, 404);
    }

    protected function unauthorized($message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }
}
