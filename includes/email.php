<?php
/*
 * Transactional email helpers using the Resend REST API.
 * API credentials are server-side environment variables only.
 */

function kh_env_value($name, $fallback = '') {
    $value = getenv($name);
    if ($value === false) {
        return $fallback;
    }

    $value = trim((string)$value);
    return $value === '' ? $fallback : $value;
}

function kh_app_public_url() {
    $configured = kh_env_value('APP_PUBLIC_URL', '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $shopUrl = defined('SHOP_URL') ? trim((string)SHOP_URL) : '';
    if ($shopUrl !== '') {
        if (preg_match('/^https?:\/\//i', $shopUrl)) {
            return rtrim($shopUrl, '/');
        }
        return 'https://' . rtrim($shopUrl, '/');
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $https = $forwardedProto === 'https' || !empty($_SERVER['HTTPS']);
        return ($https ? 'https://' : 'http://') . $host;
    }

    return '';
}

function kh_html_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function kh_resend_configured() {
    return kh_env_value('RESEND_API_KEY', '') !== ''
        && kh_env_value('RESEND_FROM_EMAIL', '') !== '';
}

function kh_send_email($to, $subject, $html, $text = '') {
    $to = trim((string)$to);
    $subject = trim((string)$subject);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid recipient email address.'];
    }

    $apiKey = kh_env_value('RESEND_API_KEY', '');
    $fromEmail = kh_env_value('RESEND_FROM_EMAIL', '');
    $fromName = kh_env_value('RESEND_FROM_NAME', 'KAELHAX');

    if ($apiKey === '' || $fromEmail === '') {
        return [false, 'Email delivery is not configured yet.'];
    }

    $from = $fromName !== ''
        ? $fromName . ' <' . $fromEmail . '>'
        : $fromEmail;

    $payload = [
        'from' => $from,
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ];

    if ($text !== '') {
        $payload['text'] = $text;
    }

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return [false, 'Unable to prepare the email.'];
    }

    $ch = curl_init('https://api.resend.com/emails');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return [false, 'Email delivery connection failed.'];
    }

    $json = json_decode($response, true);

    if ($status < 200 || $status >= 300 || !is_array($json) || empty($json['id'])) {
        return [false, is_array($json) ? (string)($json['message'] ?? 'Email provider rejected the request.') : 'Email provider rejected the request.'];
    }

    return [true, 'OK'];
}

function kh_create_password_reset($username) {
    $username = strtolower(trim((string)$username));

    if ($username === '') {
        return null;
    }

    $users = load_users();

    foreach ($users as $index => $user) {
        if (strcasecmp((string)($user['username'] ?? ''), $username) !== 0) {
            continue;
        }

        $email = trim((string)($user['recovery_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $users[$index]['password_reset'] = [
            'token_hash' => hash('sha256', $token),
            'expires_at' => time() + 1800,
            'requested_at' => date('c'),
        ];

        if (!save_users($users)) {
            return null;
        }

        return [
            'token' => $token,
            'email' => $email,
            'username' => (string)$user['username'],
            'display_name' => (string)($user['display_name'] ?? $user['username']),
        ];
    }

    return null;
}

function kh_find_password_reset($token) {
    $token = trim((string)$token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $users = load_users();

    foreach ($users as $index => $user) {
        $reset = is_array($user['password_reset'] ?? null) ? $user['password_reset'] : null;
        if (!$reset) {
            continue;
        }

        if (!hash_equals((string)($reset['token_hash'] ?? ''), $tokenHash)) {
            continue;
        }

        $expires = (int)($reset['expires_at'] ?? 0);
        if ($expires < time()) {
            unset($users[$index]['password_reset']);
            save_users(array_values($users));
            return null;
        }

        return [
            'index' => $index,
            'user' => $user,
            'expires_at' => $expires,
        ];
    }

    return null;
}

function kh_consume_password_reset($token, $newPassword) {
    $match = kh_find_password_reset($token);
    if (!$match) {
        return [false, 'This password reset link is invalid or has expired.'];
    }

    $users = load_users();
    $index = (int)$match['index'];

    if (!isset($users[$index]) || !is_array($users[$index])) {
        return [false, 'This password reset link is no longer valid.'];
    }

    $users[$index]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
    unset($users[$index]['password_reset']);

    if (!save_users($users)) {
        return [false, 'Unable to save the new password. Please try again.'];
    }

    return [true, (string)($users[$index]['username'] ?? '')];
}

function kh_send_password_reset_email($reset) {
    $baseUrl = kh_app_public_url();

    if ($baseUrl === '') {
        return [false, 'APP_PUBLIC_URL is not configured.'];
    }

    $token = rawurlencode((string)$reset['token']);
    $resetUrl = $baseUrl . '/index.php?page=reset-password&token=' . $token;
    $displayName = (string)$reset['display_name'];

    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#0b0f15;color:#edf2f8;padding:24px">' .
        '<div style="max-width:560px;margin:auto;background:#151b24;border:1px solid #293341;border-radius:16px;padding:24px">' .
        '<h2 style="margin:0 0 10px">Password Reset</h2>' .
        '<p style="color:#b4bfcd">Hi ' . kh_html_escape($displayName) . ', a password reset was requested for your KAELHAX account.</p>' .
        '<p style="color:#b4bfcd">This link expires in 30 minutes and can be used once.</p>' .
        '<p><a href="' . kh_html_escape($resetUrl) . '" style="display:inline-block;background:#70a6ff;color:#07101a;padding:12px 16px;border-radius:10px;text-decoration:none;font-weight:700">Reset Password</a></p>' .
        '<p style="font-size:12px;color:#758091;line-height:1.5">If you did not request this, you can safely ignore this email.</p>' .
        '</div></body></html>';

    $text = "Hi {$displayName},\n\nA password reset was requested for your KAELHAX account.\n\nReset your password here:\n{$resetUrl}\n\nThis link expires in 30 minutes and can be used once.\n\nIf you did not request this, you can ignore this email.";

    return kh_send_email(
        (string)$reset['email'],
        'KAELHAX Password Reset',
        $html,
        $text
    );
}


function kh_create_admin_password_reset($email) {
    $email = strtolower(trim((string)$email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $account = load_admin_account();

    if (!$account || empty($account['recovery_email'])) {
        return null;
    }

    if (strcasecmp((string)$account['recovery_email'], $email) !== 0) {
        return null;
    }

    $token = bin2hex(random_bytes(32));

    $account['password_reset'] = [
        'token_hash' => hash('sha256', $token),
        'expires_at' => time() + 1800,
        'requested_at' => date('c'),
    ];

    if (!save_admin_account($account)) {
        return null;
    }

    return [
        'token' => $token,
        'email' => (string)$account['recovery_email'],
        'username' => (string)$account['username'],
        'display_name' => 'Administrator',
    ];
}

function kh_find_admin_password_reset($token) {
    $token = trim((string)$token);

    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $account = load_admin_account();
    $reset = is_array($account['password_reset'] ?? null)
        ? $account['password_reset']
        : null;

    if (!$account || !$reset) {
        return null;
    }

    $tokenHash = hash('sha256', $token);

    if (!hash_equals((string)($reset['token_hash'] ?? ''), $tokenHash)) {
        return null;
    }

    $expires = (int)($reset['expires_at'] ?? 0);

    if ($expires < time()) {
        unset($account['password_reset']);
        save_admin_account($account);
        return null;
    }

    return [
        'account' => $account,
        'expires_at' => $expires,
    ];
}

function kh_consume_admin_password_reset($token, $newPassword) {
    $match = kh_find_admin_password_reset($token);

    if (!$match) {
        return [false, 'This administrator password reset link is invalid or has expired.'];
    }

    $account = $match['account'];

    if (password_verify($newPassword, (string)($account['password_hash'] ?? ''))) {
        return [false, 'New administrator password must be different from the current password.'];
    }

    $account['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $account['updated_at'] = date('c');
    unset($account['password_reset']);

    if (!save_admin_account($account)) {
        return [false, 'Unable to save the new administrator password. Please try again.'];
    }

    return [true, (string)($account['username'] ?? '')];
}

function kh_send_admin_password_reset_email($reset) {
    $baseUrl = kh_app_public_url();

    if ($baseUrl === '') {
        return [false, 'APP_PUBLIC_URL is not configured.'];
    }

    $resetUrl = $baseUrl . '/index.php?page=reset-password&type=admin&token=' . rawurlencode((string)$reset['token']);

    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#0b0f15;color:#edf2f8;padding:24px">' .
        '<div style="max-width:560px;margin:auto;background:#151b24;border:1px solid #293341;border-radius:16px;padding:24px">' .
        '<h2 style="margin:0 0 10px">Administrator Password Reset</h2>' .
        '<p style="color:#b4bfcd">A password reset was requested for the administrator account.</p>' .
        '<p style="color:#b4bfcd">This link expires in 30 minutes and can be used once.</p>' .
        '<p><a href="' . kh_html_escape($resetUrl) . '" style="display:inline-block;background:#70a6ff;color:#07101a;padding:12px 16px;border-radius:10px;text-decoration:none;font-weight:700">Reset Administrator Password</a></p>' .
        '<p style="font-size:12px;color:#758091;line-height:1.5">If you did not request this, you can safely ignore this email.</p>' .
        '</div></body></html>';

    $text = "A password reset was requested for the administrator account.\n\nReset your password here:\n{$resetUrl}\n\nThis link expires in 30 minutes and can be used once.\n\nIf you did not request this, you can ignore this email.";

    return kh_send_email(
        (string)$reset['email'],
        'KAELHAX Administrator Password Reset',
        $html,
        $text
    );
}
