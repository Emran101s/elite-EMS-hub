<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response.
 *
 * CSP allowlist (audited against layouts, PDF blades, Vite, Livewire/Alpine):
 *   - scripts/styles from self; unsafe-inline + unsafe-eval for Livewire/Alpine
 *     and the many inline style="…" / @script blocks the hub still uses
 *   - fonts.bunny.net for Playfair / Spectral / Amiri on app + PDF templates
 *   - data:/blob: images for badge QR data-URIs and html-to-image / html2canvas
 *   - local Vite HMR origins only when APP_ENV=local
 *
 * Browsershot PDFs load temp file:// HTML; this HTTP header does not constrain
 * that render. Bunny fonts in those templates still need to stay on the
 * style-src / font-src lists for any HTML preview route that hits the browser.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Only supply the app-wide policy when the response has not set its own.
        // A route that streams user-uploaded bytes needs a *stricter* policy
        // than the hub does — see EventDocumentController::view(), which
        // sandboxes them — and unconditionally setting the header here silently
        // replaced it, undoing the hardening it looked like it had.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $script = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];
        $style = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];
        $font = ["'self'", 'https://fonts.bunny.net', 'data:'];
        $img = ["'self'", 'data:', 'blob:'];
        $connect = ["'self'"];
        $worker = ["'self'", 'blob:'];

        if (app()->environment('local')) {
            foreach ($this->localViteOrigins($request) as $origin) {
                $script[] = $origin;
                $style[] = $origin;
                $connect[] = $origin;
                $connect[] = preg_replace('#^http:#', 'ws:', $origin);
            }
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            'script-src '.implode(' ', array_unique($script)),
            'style-src '.implode(' ', array_unique($style)),
            'font-src '.implode(' ', array_unique($font)),
            'img-src '.implode(' ', array_unique($img)),
            'connect-src '.implode(' ', array_unique($connect)),
            'worker-src '.implode(' ', array_unique($worker)),
            "media-src 'self'",
        ]);
    }

    /** @return list<string> */
    private function localViteOrigins(Request $request): array
    {
        $origins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://[::1]:5173',
        ];

        $host = $request->getHost();

        if ($host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            $origins[] = 'http://'.$host.':5173';
        }

        return $origins;
    }
}
