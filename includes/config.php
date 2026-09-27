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

