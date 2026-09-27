<?php
/*
 * Website announcement storage.
 * Production data is stored in Upstash; local JSON is used only as a
 * development fallback.
 */

function announcements_redis_key() {
    return 'kaelhax:announcements:v1';
}

function announcements_file() {
    return __DIR__ . '/../data/announcements.json';
}

function ensure_announcements_file() {
    $dir = dirname(announcements_file());

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    if (!file_exists(announcements_file())) {
        @file_put_contents(announcements_file(), '[]', LOCK_EX);
    }
}

function load_announcements() {
    if (function_exists('upstash_configured') && upstash_configured()) {
        [$ok, $result] = upstash_request(['GET', announcements_redis_key()]);

        if ($ok && is_string($result) && $result !== '') {
            $items = json_decode($result, true);
            return is_array($items) ? $items : [];
        }

        return [];
    }

    ensure_announcements_file();
    $json = @file_get_contents(announcements_file());
    $items = json_decode($json ?: '[]', true);

    return is_array($items) ? $items : [];
}

function save_announcements($items) {
    $items = array_values(array_filter($items, function ($item) {
        return is_array($item) && !empty($item['id']);
    }));

    $json = json_encode(
        $items,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        return false;
    }

    if (function_exists('upstash_configured') && upstash_configured()) {
        [$ok] = upstash_request(['SET', announcements_redis_key(), $json]);
        return $ok;
    }

    ensure_announcements_file();

    return @file_put_contents(
        announcements_file(),
        json_encode(
            $items,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ),
        LOCK_EX
    ) !== false;
}

function announcement_is_active($item) {
    if (!is_array($item) || empty($item['published'])) {
        return false;
    }

    $expires = trim((string)($item['expires_at'] ?? ''));

    if ($expires !== '') {
        $time = strtotime($expires);

        if ($time !== false && $time <= time()) {
            return false;
        }
    }

    return trim((string)($item['title'] ?? '')) !== ''
        && trim((string)($item['message'] ?? '')) !== '';
}

function load_active_announcements() {
    $items = load_announcements();

    $items = array_values(array_filter($items, 'announcement_is_active'));

    usort($items, function ($a, $b) {
        return strcmp(
            (string)($b['created_at'] ?? ''),
            (string)($a['created_at'] ?? '')
        );
    });

    return $items;
}

function announcement_find_index($items, $id) {
    foreach ($items as $index => $item) {
        if ((string)($item['id'] ?? '') === (string)$id) {
            return $index;
        }
    }

    return -1;
}

function announcement_telegram_message($item) {
    return '<b>📢 ' . e($item['title']) . '</b>' . "\n\n" .
        e($item['message']) . "\n\n" .
        '<b>SHOP:</b> ' . e(defined('SHOP_URL') ? SHOP_URL : '');
}

function telegram_send_announcement($item) {
    if (!telegram_configured()) {
        return [false, 'Telegram is not configured or is currently disabled.'];
    }

    [$ok, $result] = telegram_request('sendMessage', [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => announcement_telegram_message($item),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);

    return [
        $ok,
        $ok ? 'OK' : (is_string($result) ? $result : 'Telegram rejected the announcement.'),
    ];
}
