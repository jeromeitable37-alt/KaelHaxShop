<?php
/*
 * KAELHAX application security helpers.
 *
 * These controls are intentionally additive:
 * - security headers for every HTML response
 * - lightweight Upstash-backed rate limiting for abuse-prone actions
 * - no raw IP addresses are stored
 *
 * Volumetric DDoS still needs edge/network protection; this layer protects
 * the application from common brute-force and request-flood patterns.
 */

function security_client_identifier() {
    $candidates = [
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string)$candidate);
        if ($value === '') continue;

        if (strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            $value = trim((string)$parts[0]);
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    return 'unknown';
}

function security_rate_limit($bucket, $limit, $windowSeconds, $subject = '') {
    $bucket = preg_replace('/[^a-zA-Z0-9._:-]/', '_', (string)$bucket);
    $limit = max(1, (int)$limit);
    $windowSeconds = max(1, (int)$windowSeconds);
    $subject = trim((string)$subject);

    /*
     * Fail open when Redis is unavailable so a temporary storage outage
     * does not turn into a site-wide login/order outage.
     */
    if (!function_exists('upstash_configured') || !upstash_configured()) {
        return ['allowed' => true, 'remaining' => $limit, 'retry_after' => 0];
    }

    $identifier = security_client_identifier();
    $rateIdentity = $subject !== '' ? $subject : $identifier;
    $rawIdentity = $bucket . '|' . $rateIdentity;
    $identityHash = hash('sha256', $rawIdentity);
    $key = 'kaelhax:security:rl:v1:' . $bucket . ':' . $identityHash;

    [$ok, $result] = upstash_request(['INCR', $key]);

    if (!$ok || !is_numeric($result)) {
        return ['allowed' => true, 'remaining' => $limit, 'retry_after' => 0];
    }

    $count = (int)$result;

    if ($count === 1) {
        upstash_request(['EXPIRE', $key, $windowSeconds]);
    }

    if ($count > $limit) {
        return [
            'allowed' => false,
            'remaining' => 0,
            'retry_after' => $windowSeconds,
        ];
    }

    return [
        'allowed' => true,
        'remaining' => max(0, $limit - $count),
        'retry_after' => 0,
    ];
}

function security_rate_limit_response($message = 'Too many requests. Please wait and try again.', $retryAfter = 60, $ajax = false, $redirectPage = 'account') {
    if ($ajax && function_exists('chat_json_response')) {
        chat_json_response([
            'ok' => false,
            'error' => $message,
            'retry_after' => max(1, (int)$retryAfter),
        ], 429);
    }

    http_response_code(429);
    header('Retry-After: ' . max(1, (int)$retryAfter));
    if (function_exists('redirect_page')) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => $message];
        redirect_page($redirectPage);
    }

    exit;
}

function security_headers() {
    if (headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header("Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
    header('Cache-Control: private, no-store, max-age=0, must-revalidate');

    $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? ''));
    if ($proto === 'https' || !empty($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
