<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTTokenExpired
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token has expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token is invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token is absent or error occurred', 'message' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}
