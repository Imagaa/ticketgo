<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $input = $request->all();

        array_walk_recursive($input, function (&$value) {
            if (! is_string($value)) {
                return;
            }

            // Trim whitespace
            $value = trim($value);

            // Basic XSS cleanup: hapus <script> dan event handler (on*)
            // 1) Buang semua <script>...</script>
            $value = preg_replace('#<\s*script[^>]*>.*?<\s*/\s*script\s*>#is', '', $value);

            // 2) Hapus atribut onXXX= (onclick, onload, dll)
            $value = preg_replace('/on\w+\s*=\s*("|\').*?("|\')/i', '', $value);

            // 3) Hapus javascript: di href/src
            $value = preg_replace('/(javascript:)/i', '', $value);

            // 4) (opsional) strip semua tag HTML kecuali yang aman
            // Jika mau full text only:
            // $value = strip_tags($value);
        });

        $request->merge($input);

        return $next($request);
    }
}