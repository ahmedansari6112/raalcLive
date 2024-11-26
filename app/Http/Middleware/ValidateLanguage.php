<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->route('lang');

        // Set default language to 'en' if not provided
        if (!$lang) {
            $lang = 'en';
            $request->route()->setParameter('lang', $lang);
        }

        // Validate the language parameter
        $allowedLanguages = ['en', 'ar', 'ru', 'ch'];

        if (!in_array($lang, $allowedLanguages)) {
            return response()->json(['status' => 'false', 'message' => 'Invalid language'], 400);
        }

        return $next($request);
    }
}
