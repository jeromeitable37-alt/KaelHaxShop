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


/* -------------------- ADMIN CREDENTIAL STORAGE -------------------- */
/*
 * Administrator credentials live in the same Upstash store used by the app.
 * The legacy environment variables ADMIN_USERNAME / ADMIN_PASSWORD_HASH are
 * accepted only as a one-time migration source when no admin record exists.
 * Once the record exists, normal logins and password changes use Upstash only.
 */
function admin_account_key() {
    return 'kaelhax:admin:v1';
}

function admin_account_file() {
    return __DIR__ . '/../data/admin.json';
}

function admin_legacy_credentials() {
    $username = function_exists('kaelhax_env')
        ? kaelhax_env('ADMIN_USERNAME', 'admin')
        : trim((string)(getenv('ADMIN_USERNAME') ?: 'admin'));

    $hash = function_exists('kaelhax_env')
        ? kaelhax_env('ADMIN_PASSWORD_HASH', '')
        : trim((string)(getenv('ADMIN_PASSWORD_HASH') ?: ''));

    return [
        'username' => $username,
        'password_hash' => $hash,
    ];
}

function load_admin_account() {
    if (function_exists('upstash_configured') && upstash_configured()) {
        [$ok, $result] = upstash_request(['GET', admin_account_key()]);
        if ($ok && is_string($result) && $result !== '') {
            $account = json_decode($result, true);
            if (is_array($account) && !empty($account['username']) && !empty($account['password_hash'])) {
                return $account;
            }
        }
        return null;
    }

    $file = admin_account_file();
    if (!file_exists($file)) return null;

    $json = @file_get_contents($file);
    $account = json_decode($json ?: '', true);
    return is_array($account) && !empty($account['username']) && !empty($account['password_hash'])
        ? $account
        : null;
}

function save_admin_account($account) {
    if (!is_array($account) || empty($account['username']) || empty($account['password_hash'])) {
        return false;
    }

    $json = json_encode($account, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    if (function_exists('upstash_configured') && upstash_configured()) {
        [$ok] = upstash_request(['SET', admin_account_key(), $json]);
        return $ok;
    }

    $file = admin_account_file();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    return @file_put_contents(
        $file,
        json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

function admin_authenticate($username, $password) {
    $username = trim((string)$username);
    $password = (string)$password;

    $account = load_admin_account();

    if ($account) {
        $active = ($account['status'] ?? 'active') === 'active';
        $validUser = strtolower((string)$account['username']) === strtolower($username);
        $validPassword = $active && password_verify($password, (string)($account['password_hash'] ?? ''));

        if ($validUser && $validPassword) {
            if (password_needs_rehash((string)$account['password_hash'], PASSWORD_DEFAULT)) {
                $account['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $account['updated_at'] = date('c');
                save_admin_account($account);
            }
            return [
                'ok' => true,
                'username' => (string)$account['username'],
                'source' => 'upstash',
            ];
        }

        /*
         * Recovery compatibility: if the persistent admin record is stale
         * but the current Vercel legacy credentials are valid, refresh the
         * persistent record from those credentials. This prevents a previous
         * migrated password from locking the administrator out after an
         * environment-variable update.
         */
        $legacy = admin_legacy_credentials();
        $legacyUserOk = $legacy['username'] !== '' && strtolower($legacy['username']) === strtolower($username);
        $legacyPasswordOk = $legacy['password_hash'] !== '' && password_verify($password, $legacy['password_hash']);

        if ($legacyUserOk && $legacyPasswordOk) {
            $account['username'] = $legacy['username'];
            $account['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $account['updated_at'] = date('c');
            $account['status'] = 'active';
            $saved = save_admin_account($account);

            return [
                'ok' => true,
                'username' => $legacy['username'],
                'source' => $saved ? 'legacy-refresh' : 'legacy',
            ];
        }

        return ['ok' => false];
    }

    /* One-time compatibility migration from the old admin env variables. */
    $legacy = admin_legacy_credentials();
    $validUser = $legacy['username'] !== '' && strtolower($legacy['username']) === strtolower($username);
    $validPassword = $legacy['password_hash'] !== '' && password_verify($password, $legacy['password_hash']);

    if (!$validUser || !$validPassword) {
        return ['ok' => false];
    }

    $account = [
        'username' => $legacy['username'],
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'status' => 'active',
    ];

    $saved = save_admin_account($account);

    return [
        'ok' => true,
        'username' => $legacy['username'],
        'source' => $saved ? 'migrated' : 'legacy',
    ];
}

function admin_verify_current_password($username, $password) {
    $username = trim((string)$username);
    $password = (string)$password;

    $account = load_admin_account();

    if ($account) {
        return hash_equals((string)$account['username'], $username)
            && ($account['status'] ?? 'active') === 'active'
            && password_verify($password, (string)($account['password_hash'] ?? ''));
    }

    $legacy = admin_legacy_credentials();

    return $legacy['username'] !== ''
        && hash_equals($legacy['username'], $username)
        && $legacy['password_hash'] !== ''
        && password_verify($password, $legacy['password_hash']);
}

function admin_change_password($username, $currentPassword, $newPassword) {
    if (!admin_verify_current_password($username, $currentPassword)) {
        return [false, 'Current administrator password is incorrect.'];
    }

    $account = load_admin_account();

    if ($account && password_verify($newPassword, (string)($account['password_hash'] ?? ''))) {
        return [false, 'New administrator password must be different from the current password.'];
    }

    if (!$account) {
        $account = [
            'username' => trim((string)$username),
            'created_at' => date('c'),
            'status' => 'active',
        ];
    }

    $account['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $account['updated_at'] = date('c');

    if (!save_admin_account($account)) {
        return [false, 'Unable to save the new administrator password. Please try again.'];
    }

    return [true, 'Administrator password changed successfully.'];
}
