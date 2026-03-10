<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ApiBearerTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $storedHash = AppSetting::getValue('external_api_bearer_token_hash');

        if (blank($storedHash)) {
            return $this->unauthorized('API token is not configured.');
        }

        $token = $request->bearerToken();

        if (blank($token) || !Hash::check($token, $storedHash)) {
            return $this->unauthorized('Invalid bearer token.');
        }

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
