<?php
// KAELHAX Project Market configuration.
// Secrets should come from Vercel/server environment variables.
// Never place bot tokens, passwords, API keys, or other secrets in the repository.

function kaelhax_env($name, $fallback = '') {
    $value = getenv($name);
    if ($value === false) {
        return $fallback;
    }

    $value = trim((string)$value);
    return $value === '' ? $fallback : $value;
}

define('TELEGRAM_BOT_TOKEN', kaelhax_env('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_CHAT_ID', kaelhax_env('TELEGRAM_CHAT_ID'));
define('TELEGRAM_PUBLIC_CHANNEL', kaelhax_env('TELEGRAM_PUBLIC_CHANNEL', 'KAELHAX_MARKET'));

define('ADMIN_USERNAME', kaelhax_env('ADMIN_USERNAME', 'admin'));

/*
 * Passwords are verified with password_verify().
 * ADMIN_PASSWORD_HASH may be overridden in Vercel Environment Variables.
 *
 * The fallback below is only a compatibility bridge for the existing admin
 * password. Replace it in Vercel with a new password hash before sharing the
 * repository further.
 */
define(
    'ADMIN_PASSWORD_HASH',
    kaelhax_env(
        'ADMIN_PASSWORD_HASH',
        '$2y$12$mL8VP2qBvzIYtRPmSrcicuGGe5PIG1qPa1LERXVJdUKzQAjxoy6e2'
    )
);
