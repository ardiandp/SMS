<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk menambahkan security headers pada setiap response.
 *
 * Header yang ditambahkan membantu mencegah clickjacking, MIME sniffing,
 * XSS, dan kebocoran informasi via referer.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Mencegah browser MIME sniffing.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Mencegah clickjacking dengan melarang halaman di dalam iframe dari origin lain.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Aktifkan XSS protection di browser lama (modern CSP lebih disarankan).
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Batasi informasi yang dikirim ke situs lain melalui header Referer.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Jangan cache halaman yang mengandung data sensitif (default untuk semua response).
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
