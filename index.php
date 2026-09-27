<?php
require_once __DIR__ . '/includes/config.php';

/*
 * Vercel-safe session handling.
 *
 * The application keeps using $_SESSION everywhere else, but the session
 * payload is stored in an encrypted HttpOnly cookie instead of PHP's
 * default filesystem session storage. This prevents the login/CSRF session
 * from disappearing when Vercel serves the next request from another
 * container instance.
 */
class AppCookieSessionHandler implements SessionHandlerInterface
{
    private string $cookieName;
    private string $encryptionKey;

    public function __construct(string $secret, string $cookieName = 'KAELHAX_SESSION')
    {
        $this->cookieName = $cookieName;
        $this->encryptionKey = hash('sha256', $secret, true);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $cookie = $_COOKIE[$this->cookieName] ?? '';
        if ($cookie === '') {
            return '';
        }

        $raw = self::base64UrlDecode($cookie);
        if ($raw === false || strlen($raw) < 28) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            return '';
        }

        $payload = json_decode($plaintext, true);
        if (!is_array($payload) || ($payload['version'] ?? 0) !== 1) {
            return '';
        }

        if ((int)($payload['expires'] ?? 0) < time()) {
            return '';
        }

        $sessionData = self::base64UrlDecode((string)($payload['data'] ?? ''));
        return $sessionData === false ? '' : $sessionData;
    }

    public function write(string $id, string $data): bool
    {
        $payload = json_encode([
            'version' => 1,
            'expires' => time() + (60 * 60 * 24 * 7),
            'data' => self::base64UrlEncode($data),
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return false;
        }

        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            return false;
        }

        $cookieValue = self::base64UrlEncode($iv . $tag . $ciphertext);
        $secure = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return setcookie($this->cookieName, $cookieValue, [
            'expires' => time() + (60 * 60 * 24 * 7),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function destroy(string $id): bool
    {
        $secure = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return setcookie($this->cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}

$appSessionSecret = getenv('APP_SESSION_SECRET') ?: '';
if ($appSessionSecret === '') {
    $appSessionSecret = 'local-development-session-secret-change-me';
}

ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.use_strict_mode', '0');

$appSessionHandler = new AppCookieSessionHandler($appSessionSecret);
session_set_save_handler($appSessionHandler, true);
session_start();

/*
 * Optional config.php constants supported:
 * TELEGRAM_BOT_TOKEN
 * TELEGRAM_CHAT_ID
 * TELEGRAM_CHANNEL_USERNAME
 * ADMIN_USERNAME
 * ADMIN_PASSWORD
 * SHOP_URL
 */
if (!defined('TELEGRAM_BOT_TOKEN')) define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
if (!defined('TELEGRAM_CHAT_ID')) define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: '');
if (!defined('TELEGRAM_CHANNEL_USERNAME')) define('TELEGRAM_CHANNEL_USERNAME', getenv('TELEGRAM_CHANNEL_USERNAME') ?: '');
if (!defined('ADMIN_USERNAME')) define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
if (!defined('ADMIN_PASSWORD')) define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');
if (!defined('SHOP_URL')) define('SHOP_URL', getenv('SHOP_URL') ?: 'kael-hax-shop.vercel.app');

$defaultProducts = [
    'injector' => [
        'slug' => 'injector',
        'category' => 'CODM',
        'name' => 'KAELHAX INJECTOR | CODMGR',
        'image' => 'assets/injector.jpg',
        'promo' => false,
        'details_title' => '👌 INFO DETAILS 👌',
        'details' => [
            '3-7 Days Ban Only',
            'Strong Bypass',
            'Compatible to Low-End',
            'Unlock All Skins',
            'Can Play Brutal'
        ],
        'price_title' => 'OFFICIAL PRICELIST',
        'tiers' => [
            ['7 Days Access', 100, null],
            ['15 Days Access', 150, null],
            ['30 Days Access', 200, null],
            ['Lifetime Access', 250, null],
        ],
    ],
    'codmgr-vip' => [
        'slug' => 'codmgr-vip',
        'category' => 'CODM',
        'name' => 'KAELHAX VIP ACCESS | CODMGR',
        'image' => 'assets/codmgr-mod.jpg',
        'promo' => true,
        'features' => ['LOADER', 'MOD'],
        'details_title' => '👌 FULL DETAILS 👌',
        'details' => [
            '3-7 Days Ban Only',
            'No Auto Ban',
            'Strong Bypass',
            'Unlock All Skins & Mythic Attachments',
            'Easy LB this season'
        ],
        'price_title' => 'PRICELIST PROMO',
        'tiers' => [
            ['7 Days Access', 100, '2/5'],
            ['15 Days Access', 150, '0/5'],
            ['30 Days Access', 200, '0/5'],
            ['Lifetime Access', 350, '1/5'],
        ],
    ],
];


/* -------------------- STOREFRONT CONTENT STORAGE -------------------- */
function content_file() { return __DIR__ . '/data/content.json'; }
function products_file() { return __DIR__ . '/data/products.json'; }
function settings_file() { return __DIR__ . '/data/settings.json'; }
function ensure_storefront_storage() {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    if (!file_exists(content_file())) {
        $defaults = [
            'site_name' => 'Project Market',
            'site_brand' => 'KAELHAX',
            'shop_title' => 'Shop',
            'shop_description' => 'Browse all administrator managed digital projects and packages.',
            'preview_title' => 'Preview',
            'preview_description' => 'Browse the current project previews before choosing a package.',
            'promos_title' => 'Promos',
            'promos_description' => 'Featured products with promotional pricing.',
            'concerns_title' => 'Concerns',
            'concerns_description' => 'Send an order concern to the administrator through Telegram.',
            'terms_title' => 'Terms',
            'terms_description' => 'General marketplace and order guidelines.',
            'account_title' => 'My Account',
            'account_description' => 'Manage your buyer session.',
            'orders_title' => 'My Orders',
            'orders_description' => 'Track your submitted orders and payment review status.',
            'footer_text' => 'Project Market Digital marketplace • Orders are manually reviewed',
            'payment_title' => 'PAYMENT QR',
            'payment_description' => 'Scan the QR below to complete your payment.',
            'payment_note' => 'After paying, upload your receipt below. Accepted: JPG, PNG, WEBP, PDF. Maximum 5 MB.'
        ];
        @file_put_contents(content_file(), json_encode($defaults, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    if (!file_exists(products_file())) {
        global $defaultProducts;
        @file_put_contents(products_file(), json_encode($defaultProducts, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    if (!file_exists(settings_file())) {
        $defaults = [
            'shop_enabled' => true,
            'registration_enabled' => true,
            'telegram_enabled' => true,
            'payment_qr' => 'assets/payment-qr.png',
            'currency' => 'PHP'
        ];
        @file_put_contents(settings_file(), json_encode($defaults, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
function read_json_file($file, $fallback) {
    $json = @file_get_contents($file);
    $data = json_decode($json ?: '', true);
    return is_array($data) ? $data : $fallback;
}
function load_content() {
    ensure_storefront_storage();
    $fallback = [
        'site_name'=>'Project Market','site_brand'=>'KAELHAX','shop_title'=>'Shop','shop_description'=>'Browse all administrator managed digital projects and packages.',
        'preview_title'=>'Preview','preview_description'=>'Browse the current project previews before choosing a package.',
        'promos_title'=>'Promos','promos_description'=>'Featured products with promotional pricing.',
        'concerns_title'=>'Concerns','concerns_description'=>'Send an order concern to the administrator through Telegram.',
        'terms_title'=>'Terms','terms_description'=>'General marketplace and order guidelines.',
        'account_title'=>'My Account','account_description'=>'Manage your buyer session.',
        'orders_title'=>'My Orders','orders_description'=>'Track your submitted orders and payment review status.',
        'footer_text'=>'Project Market Digital marketplace • Orders are manually reviewed',
        'payment_title'=>'PAYMENT QR','payment_description'=>'Scan the QR below to complete your payment.',
        'payment_note'=>'After paying, upload your receipt below. Accepted: JPG, PNG, WEBP, PDF. Maximum 5 MB.'
    ];
    return array_merge($fallback, read_json_file(content_file(), []));
}
function save_content($content) {
    ensure_storefront_storage();
    return @file_put_contents(content_file(), json_encode($content, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}
function load_products() {
    ensure_storefront_storage();
    global $defaultProducts;
    $data = read_json_file(products_file(), $defaultProducts);
    return $data ?: $defaultProducts;
}
function save_products($products) {
    ensure_storefront_storage();
    return @file_put_contents(products_file(), json_encode($products, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}
function load_settings() {
    ensure_storefront_storage();
    $defaults = ['shop_enabled'=>true,'registration_enabled'=>true,'telegram_enabled'=>true,'payment_qr'=>'assets/payment-qr.png','currency'=>'PHP'];
    return array_merge($defaults, read_json_file(settings_file(), []));
}
function save_settings($settings) {
    ensure_storefront_storage();
    return @file_put_contents(settings_file(), json_encode($settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

require_once __DIR__ . '/includes/pro_upgrades.php';

ensure_storefront_storage();
$products = load_products();
$siteContent = load_content();
$siteSettings = load_settings();

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v) { return 'PHP ' . number_format((float)$v, 2); }
function redirect_to($location) { header('Location: ' . $location); exit; }
function redirect_page($page = 'shop') { redirect_to('index.php?page=' . rawurlencode($page)); }
function redirect_product($slug) { redirect_to('index.php?page=product&slug=' . rawurlencode($slug)); }
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_ok() {
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}
function is_admin() { return !empty($_SESSION['admin_logged_in']); }
function admin_only() { if (!is_admin()) redirect_page('admin'); }
function is_user() { return !empty($_SESSION['buyer_logged_in']); }
function users_file() { return __DIR__ . '/data/users.json'; }
function ensure_users_file() {
    $dir = dirname(users_file());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!file_exists(users_file())) @file_put_contents(users_file(), "[]");
}
function users_redis_key() { return 'kaelhax:users:v1'; }
function load_users() {
    if (function_exists('upstash_configured') && upstash_configured()) {
        [$ok, $result] = upstash_request(['GET', users_redis_key()]);
        if ($ok && is_string($result) && $result !== '') {
            $users = json_decode($result, true);
            return is_array($users) ? $users : [];
        }

        /* One-time migration of legacy local users when Redis is empty. */
        ensure_users_file();
        $json = @file_get_contents(users_file());
        $legacy = json_decode($json ?: '[]', true);
        $legacy = is_array($legacy) ? array_values($legacy) : [];
        if ($legacy) save_users($legacy);
        return $legacy;
    }

    ensure_users_file();
    $json = @file_get_contents(users_file());
    $users = json_decode($json ?: '[]', true);
    return is_array($users) ? $users : [];
}
function save_users($users) {
    $users = array_values(array_filter($users, function ($user) {
        return is_array($user) && !empty($user['username']);
    }));

    if (function_exists('upstash_configured') && upstash_configured()) {
        $json = json_encode($users, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;
        [$ok] = upstash_request(['SET', users_redis_key(), $json]);
        return $ok;
    }

    ensure_users_file();
    return @file_put_contents(
        users_file(),
        json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

function orders_file() { return __DIR__ . '/data/orders.json'; }
function receipts_dir() { return __DIR__ . '/receipts'; }

/* -------------------- PERSISTENT ORDER STORAGE -------------------- */
function ensure_orders_storage() {
    /*
     * Keep the local folders for development and legacy compatibility.
     * Production orders are stored in Upstash Redis when configured.
     */
    $dataDir = dirname(orders_file());
    if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
    if (!is_dir(receipts_dir())) @mkdir(receipts_dir(), 0755, true);

    if (!file_exists(orders_file())) {
        @file_put_contents(orders_file(), "[]", LOCK_EX);
    }
}

function upstash_config() {
    $url = '';
    $token = '';

    /* Vercel + Upstash integration names. */
    if (function_exists('getenv')) {
        $url = (string)(getenv('KV_REST_API_URL') ?: '');
        $token = (string)(getenv('KV_REST_API_TOKEN') ?: '');
    }

    /* Standard Upstash names are also supported. */
    if ($url === '' && function_exists('getenv')) {
        $url = (string)(getenv('UPSTASH_REDIS_REST_URL') ?: '');
    }

    if ($token === '' && function_exists('getenv')) {
        $token = (string)(getenv('UPSTASH_REDIS_REST_TOKEN') ?: '');
    }

    /* Fallback to PHP environment arrays. */
    if ($url === '' && isset($_ENV['KV_REST_API_URL'])) {
        $url = (string)$_ENV['KV_REST_API_URL'];
    }

    if ($token === '' && isset($_ENV['KV_REST_API_TOKEN'])) {
        $token = (string)$_ENV['KV_REST_API_TOKEN'];
    }

    if ($url === '' && isset($_SERVER['KV_REST_API_URL'])) {
        $url = (string)$_SERVER['KV_REST_API_URL'];
    }

    if ($token === '' && isset($_SERVER['KV_REST_API_TOKEN'])) {
        $token = (string)$_SERVER['KV_REST_API_TOKEN'];
    }

    return [
        'url' => rtrim($url, '/'),
        'token' => $token,
    ];
}

function upstash_configured() {
    $config = upstash_config();
    return $config['url'] !== '' && $config['token'] !== '';
}

function upstash_request($command, $endpoint = '') {
    $config = upstash_config();

    if ($config['url'] === '' || $config['token'] === '') {
        return [false, 'Upstash Redis is not configured.'];
    }

    $url = $config['url'] . ($endpoint !== '' ? '/' . ltrim($endpoint, '/') : '');

    $body = json_encode(
        $command,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($body === false) {
        return [false, 'Unable to encode Redis request.'];
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $error !== '') {
        return [false, 'Upstash connection failed: ' . ($error ?: 'Unknown connection error.')];
    }

    $data = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [false, is_array($data) ? ($data['error'] ?? 'Upstash request failed.') : 'Upstash request failed.'];
    }

    if (is_array($data) && isset($data['error'])) {
        return [false, (string)$data['error']];
    }

    if (is_array($data) && array_key_exists('result', $data)) {
        return [true, $data['result']];
    }

    return [true, $data];
}

function load_legacy_orders() {
    ensure_orders_storage();

    $json = @file_get_contents(orders_file());
    $orders = json_decode($json ?: '[]', true);

    return is_array($orders) ? $orders : [];
}

function load_orders() {
    /*
     * Production path: persistent Redis-backed orders.
     */
    if (upstash_configured()) {
        [$ok, $result] = upstash_request([
            'HGETALL',
            'kaelhax:orders:v1'
        ]);

        if (!$ok) {
            /*
             * Do NOT fall back to local orders in production when Redis
             * was configured. That would make the application appear to
             * lose orders when the database is temporarily unavailable.
             */
            return [];
        }

        $orders = [];

        if (is_array($result)) {
            for ($i = 0, $count = count($result); $i + 1 < $count; $i += 2) {
                $orderJson = (string)$result[$i + 1];
                $order = json_decode($orderJson, true);

                if (is_array($order) && !empty($order['id'])) {
                    $orders[] = $order;
                }
            }
        }

        /*
         * One-time migration for legacy local orders created before
         * Upstash was configured.
         */
        if (!$orders) {
            $legacy = load_legacy_orders();

            if ($legacy) {
                save_orders($legacy);
                return $legacy;
            }
        }

        return $orders;
    }

    /* Local development fallback. */
    return load_legacy_orders();
}

function save_orders($orders) {
    $orders = array_values(array_filter($orders, function ($order) {
        return is_array($order) && !empty($order['id']);
    }));

    /*
     * Production: save every order as an individual Redis hash field.
     */
    if (upstash_configured()) {
        $commands = [];

        foreach ($orders as $order) {
            $orderJson = json_encode(
                $order,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if ($orderJson === false) {
                continue;
            }

            $commands[] = [
                'HSET',
                'kaelhax:orders:v1',
                (string)$order['id'],
                $orderJson
            ];
        }

        if (!$commands) {
            return true;
        }

        [$ok, $result] = upstash_request($commands, 'pipeline');
        return $ok;
    }

    /* Local development fallback. */
    ensure_orders_storage();

    return @file_put_contents(
        orders_file(),
        json_encode(
            $orders,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        ),
        LOCK_EX
    ) !== false;
}

/* -------------------- WEB CHAT / SUPPORT STORAGE -------------------- */
function chat_root_key() { return 'kaelhax:chats:v1'; }
function chat_identity($username) {
    return strtolower(trim((string)$username));
}
function chat_conversation_id($username) {
    return hash('sha256', chat_identity($username));
}
function normalize_external_link($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}
function chat_get_conversation($username) {
    $username = chat_identity($username);
    if ($username === '') return null;

    if (upstash_configured()) {
        [$ok, $result] = upstash_request([
            'HGET',
            chat_root_key(),
            chat_conversation_id($username)
        ]);

        if ($ok && is_string($result) && $result !== '') {
            $conversation = json_decode($result, true);
            return is_array($conversation) ? $conversation : null;
        }
        return null;
    }

    $file = __DIR__ . '/data/chats.json';
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
    if (!file_exists($file)) @file_put_contents($file, '{}', LOCK_EX);

    $all = json_decode(@file_get_contents($file) ?: '{}', true);
    if (!is_array($all)) $all = [];
    return isset($all[chat_identity($username)]) && is_array($all[chat_identity($username)])
        ? $all[chat_identity($username)]
        : null;
}
function chat_save_conversation($conversation) {
    if (!is_array($conversation)) return false;
    $username = chat_identity($conversation['username'] ?? '');
    if ($username === '') return false;

    $conversation['username'] = $username;
    $conversation['updated_at'] = date('c');

    $json = json_encode($conversation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    if (upstash_configured()) {
        [$ok] = upstash_request([
            'HSET',
            chat_root_key(),
            chat_conversation_id($username),
            $json
        ]);
        return $ok;
    }

    $file = __DIR__ . '/data/chats.json';
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
    if (!file_exists($file)) @file_put_contents($file, '{}', LOCK_EX);

    $all = json_decode(@file_get_contents($file) ?: '{}', true);
    if (!is_array($all)) $all = [];
    $all[$username] = $conversation;

    return @file_put_contents(
        $file,
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}
function chat_add_message($username, $senderType, $senderName, $message, $link = '') {
    $username = chat_identity($username);
    $message = trim((string)$message);
    $link = normalize_external_link($link);

    if ($username === '' || $message === '') return false;
    if (!in_array($senderType, ['buyer', 'admin'], true)) return false;

    $conversation = chat_get_conversation($username);
    if (!is_array($conversation)) {
        $conversation = [
            'username' => $username,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'messages' => [],
        ];
    }

    if (!isset($conversation['messages']) || !is_array($conversation['messages'])) {
        $conversation['messages'] = [];
    }

    $conversation['messages'][] = [
        'id' => bin2hex(random_bytes(8)),
        'sender_type' => $senderType,
        'sender_name' => trim((string)$senderName) ?: ($senderType === 'admin' ? 'Admin' : $username),
        'message' => $message,
        'link' => $link,
        'created_at' => date('c'),
        'read_by_admin' => $senderType === 'admin',
        'read_by_buyer' => $senderType === 'buyer',
    ];

    return chat_save_conversation($conversation);
}
function chat_mark_read($username, $readerType) {
    if (!in_array($readerType, ['buyer', 'admin'], true)) return false;
    $conversation = chat_get_conversation($username);
    if (!is_array($conversation) || empty($conversation['messages']) || !is_array($conversation['messages'])) return false;

    $changed = false;
    foreach ($conversation['messages'] as &$message) {
        $field = $readerType === 'admin' ? 'read_by_admin' : 'read_by_buyer';
        if (empty($message[$field])) {
            $message[$field] = true;
            $changed = true;
        }
    }
    unset($message);

    return $changed ? chat_save_conversation($conversation) : true;
}
function chat_list_conversations() {
    $rows = [];

    if (upstash_configured()) {
        [$ok, $result] = upstash_request([
            'HGETALL',
            chat_root_key()
        ]);

        if (!$ok || !is_array($result)) return [];

        for ($i = 0, $count = count($result); $i + 1 < $count; $i += 2) {
            $conversation = json_decode((string)$result[$i + 1], true);
            if (is_array($conversation) && !empty($conversation['username'])) {
                $rows[] = $conversation;
            }
        }
    } else {
        $file = __DIR__ . '/data/chats.json';
        if (file_exists($file)) {
            $all = json_decode(@file_get_contents($file) ?: '{}', true);
            if (is_array($all)) {
                foreach ($all as $conversation) {
                    if (is_array($conversation) && !empty($conversation['username'])) {
                        $rows[] = $conversation;
                    }
                }
            }
        }
    }

    usort($rows, function ($a, $b) {
        return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
    });

    return $rows;
}
function chat_unread_count($conversation, $readerType) {
    if (!is_array($conversation) || !is_array($conversation['messages'] ?? null)) return 0;
    $field = $readerType === 'admin' ? 'read_by_admin' : 'read_by_buyer';
    $count = 0;
    foreach ($conversation['messages'] as $message) {
        if (empty($message[$field])) $count++;
    }
    return $count;
}
function chat_render_message($message) {
    $safeText = e((string)($message['message'] ?? ''));
    $safeText = preg_replace(
        '~(https?://[^\s<]+)~i',
        '<a class="chat-inline-link" href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $safeText
    );
    return nl2br($safeText);
}

function chat_json_response($payload, $status = 200) {
    http_response_code((int)$status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chat_unread_total($readerType, $username = '') {
    if (!in_array($readerType, ['admin', 'buyer'], true)) return 0;

    if ($readerType === 'buyer') {
        $username = chat_identity($username);
        if ($username === '') return 0;
        $conversation = chat_get_conversation($username);
        return chat_unread_count($conversation, 'buyer');
    }

    $total = 0;
    foreach (chat_list_conversations() as $conversation) {
        $total += chat_unread_count($conversation, 'admin');
    }
    return $total;
}

function new_order_id() {
    return 'KM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
function buyer_can_see_order($order) {
    if (is_admin()) return true;
    $ids = $_SESSION['guest_order_ids'] ?? [];
    if (!empty($order['buyer_username']) && is_user() && strtolower((string)$order['buyer_username']) === strtolower((string)($_SESSION['buyer_username'] ?? ''))) return true;
    return in_array($order['id'] ?? '', $ids, true);
}
function receipt_upload_error_message($code) {
    $map = [
        UPLOAD_ERR_INI_SIZE => 'The receipt is larger than the server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'The receipt is larger than the allowed upload size.',
        UPLOAD_ERR_PARTIAL => 'The receipt upload was incomplete. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Please upload your payment receipt.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is unavailable.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the receipt.',
        UPLOAD_ERR_EXTENSION => 'The server blocked the receipt upload.',
    ];
    return $map[$code] ?? 'Unable to upload the receipt.';
}
function cloudinary_config() {
    /*
     * Preferred Vercel Production variables:
     *   CLOUDINARY_CLOUD_NAME
     *   CLOUDINARY_API_KEY
     *   CLOUDINARY_API_SECRET
     *
     * CLOUDINARY_URL remains a complete fallback. We never mix credentials
     * between the two sources.
     */
    $cloudName = trim((string)(getenv('CLOUDINARY_CLOUD_NAME') ?: ''));
    $apiKey = trim((string)(getenv('CLOUDINARY_API_KEY') ?: ''));
    $apiSecret = trim((string)(getenv('CLOUDINARY_API_SECRET') ?: ''));

    if ($cloudName !== '' && $apiKey !== '' && $apiSecret !== '') {
        return [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }

    $cloudinaryUrl = trim((string)(getenv('CLOUDINARY_URL') ?: ''));
    if ($cloudinaryUrl !== '') {
        $parts = parse_url($cloudinaryUrl);
        if (is_array($parts)) {
            $fallbackCloudName = trim((string)($parts['host'] ?? ''));
            $fallbackApiKey = isset($parts['user']) ? trim(urldecode((string)$parts['user'])) : '';
            $fallbackApiSecret = isset($parts['pass']) ? trim(urldecode((string)$parts['pass'])) : '';

            if ($fallbackCloudName !== '' && $fallbackApiKey !== '' && $fallbackApiSecret !== '') {
                return [
                    'cloud_name' => $fallbackCloudName,
                    'api_key' => $fallbackApiKey,
                    'api_secret' => $fallbackApiSecret,
                ];
            }
        }
    }

    return [
        'cloud_name' => '',
        'api_key' => '',
        'api_secret' => '',
    ];
}

function cloudinary_configured() {
    $config = cloudinary_config();

    return $config['cloud_name'] !== ''
        && $config['api_key'] !== ''
        && $config['api_secret'] !== '';
}

function cloudinary_upload_receipt($tmpPath, $originalName, $mime, $orderId) {
    if (!cloudinary_configured()) {
        return [
            false,
            'Cloudinary is not configured. Check CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET, or CLOUDINARY_URL.'
        ];
    }

    if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
        return [false, 'The uploaded receipt could not be read.'];
    }

    $config = cloudinary_config();

    $safeOrderId = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        (string)$orderId
    );

    $publicId =
        'payment-receipts/' .
        $safeOrderId . '_' .
        bin2hex(random_bytes(6));

    /* Backend authenticated Upload API: no manual signature calculation. */
    $endpoint =
        'https://api.cloudinary.com/v1_1/' .
        rawurlencode($config['cloud_name']) .
        '/auto/upload';

    $fields = [
        'file' => new CURLFile(
            $tmpPath,
            $mime,
            basename($originalName)
        ),
        'public_id' => $publicId,
        'type' => 'upload',
    ];

    $ch = curl_init($endpoint);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $config['api_key'] . ':' . $config['api_secret'],
        CURLOPT_POSTFIELDS => $fields,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return [
            false,
            'Cloudinary connection failed: ' .
            ($curlError ?: 'Unknown connection error.')
        ];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [false, 'Cloudinary returned an invalid response.'];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $data['error']['message'] ?? 'Cloudinary rejected the receipt upload.';
        return [false, 'Cloudinary upload failed: ' . $message];
    }

    if (empty($data['secure_url'])) {
        return [false, 'Cloudinary uploaded the receipt but did not return a secure URL.'];
    }

    return [true, [
        'url' => $data['secure_url'],
        'public_id' => $data['public_id'] ?? $publicId,
        'asset_id' => $data['asset_id'] ?? '',
        'resource_type' => $data['resource_type'] ?? 'image',
        'format' => $data['format'] ?? '',
        'mime' => $mime,
        'filename' => basename($originalName),
    ]];
}

function save_receipt_upload($file, $orderId) {
    if (!isset($file) || !is_array($file)) {
        return [false, 'Please upload your payment receipt.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, receipt_upload_error_message((int)$file['error'])];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        return [false, 'Receipt must be between 1 byte and 5 MB.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'Invalid receipt upload.'];
    }

    $allowed = [
        'image/jpeg' => true,
        'image/jpg' => true,
        'image/pjpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'application/pdf' => true,
        'application/x-pdf' => true,
    ];

    $mime = 'application/octet-stream';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: $mime;
    } elseif (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmp) ?: $mime;
    }

    /* Fallback validation for Windows/mobile uploads. */
    if (!isset($allowed[$mime])) {
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('getimagesize')) {
            $info = @getimagesize($tmp);
            if ($info && in_array($info['mime'] ?? '', ['image/jpeg', 'image/pjpeg'], true)) {
                $mime = 'image/jpeg';
            }
        } elseif ($ext === 'png' && function_exists('getimagesize')) {
            $info = @getimagesize($tmp);
            if ($info && ($info['mime'] ?? '') === 'image/png') {
                $mime = 'image/png';
            }
        } elseif ($ext === 'webp' && function_exists('getimagesize')) {
            $info = @getimagesize($tmp);
            if ($info && ($info['mime'] ?? '') === 'image/webp') {
                $mime = 'image/webp';
            }
        } elseif ($ext === 'pdf') {
            $header = @file_get_contents($tmp, false, null, 0, 5);
            if ($header === '%PDF-') {
                $mime = 'application/pdf';
            }
        }
    }

    if (!isset($allowed[$mime])) {
        return [false, 'Receipt must be a valid JPG, PNG, WEBP, or PDF file.'];
    }

    /*
     * Production storage: upload the receipt directly to Cloudinary.
     * The Vercel filesystem is not used as permanent receipt storage.
     */
    [$cloudinaryOk, $cloudinaryResult] = cloudinary_upload_receipt(
        $tmp,
        (string)($file['name'] ?? 'receipt'),
        $mime,
        $orderId
    );

    if (!$cloudinaryOk) {
        return [false, $cloudinaryResult];
    }

    return [true, [
        'path' => $cloudinaryResult['url'],
        'url' => $cloudinaryResult['url'],
        'filename' => $cloudinaryResult['filename'],
        'mime' => $cloudinaryResult['mime'],
        'public_id' => $cloudinaryResult['public_id'],
        'asset_id' => $cloudinaryResult['asset_id'],
        'resource_type' => $cloudinaryResult['resource_type'],
        'format' => $cloudinaryResult['format'],
    ]];
}

function telegram_configured() {
    global $siteSettings;
    return TELEGRAM_BOT_TOKEN !== '' && TELEGRAM_CHAT_ID !== '' && (($siteSettings['telegram_enabled'] ?? true) === true);
}

function telegram_request($method, $fields, $multipart = false) {
    if (!telegram_configured()) return [false, 'Telegram is not configured yet.'];
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    $opts = [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20];
    if ($multipart) {
        $opts[CURLOPT_POSTFIELDS] = $fields;
    } else {
        $opts[CURLOPT_POSTFIELDS] = json_encode($fields);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($error || $body === false) return [false, 'Telegram connection failed.'];
    $json = json_decode($body, true);
    if ($code < 200 || $code >= 300 || empty($json['ok'])) return [false, $json['description'] ?? 'Telegram rejected the request.'];
    return [true, $json];
}

function telegram_send_order($caption) {
    if (!telegram_configured()) {
        return [false, 'Telegram is not configured yet.'];
    }

    $banner = __DIR__ . '/assets/order-banner.jpg';

    if (!file_exists($banner)) {
        return [false, 'Order banner image not found: assets/order-banner.jpg'];
    }

    if (!is_readable($banner)) {
        return [false, 'Order banner exists but PHP cannot read it.'];
    }

    $mime = 'image/jpeg';
    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($banner);
        if ($detected) {
            $mime = $detected;
        }
    }

    $fields = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'photo' => new CURLFile($banner, $mime, basename($banner)),
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ];

    [$ok, $result] = telegram_request('sendPhoto', $fields, true);

    if (!$ok) {
        return [false, is_string($result) ? $result : 'Telegram rejected the banner upload.'];
    }

    return [true, 'OK'];
}

function telegram_send_receipt($order, $receiptAbsolutePath, $mime) {
    if (!telegram_configured()) return [false, 'Telegram is not configured yet.'];
    if (!file_exists($receiptAbsolutePath) || !is_readable($receiptAbsolutePath)) return [false, 'Receipt file is not readable.'];

    $caption = '<b>🧾 PAYMENT RECEIPT</b>' . "\n\n" .
        '<b>Order ID:</b> <code>' . e($order['id']) . '</code>' . "\n" .
        '<b>Buyer:</b> ' . e($order['buyer_name']) . "\n" .
        '<b>Product:</b> ' . e($order['product']) . "\n" .
        '<b>Amount:</b> ' . e($order['amount']) . "\n" .
        '<b>Payment:</b> ' . e($order['payment_method']) . "\n" .
        "\n" . '<b>Status:</b> PENDING ADMIN REVIEW';

    if (strpos($mime, 'image/') === 0) {
        $fields = [
            'chat_id' => TELEGRAM_CHAT_ID,
            'photo' => new CURLFile($receiptAbsolutePath, $mime, basename($receiptAbsolutePath)),
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];
        [$ok, $result] = telegram_request('sendPhoto', $fields, true);
    } else {
        $fields = [
            'chat_id' => TELEGRAM_CHAT_ID,
            'document' => new CURLFile($receiptAbsolutePath, $mime, basename($receiptAbsolutePath)),
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];
        [$ok, $result] = telegram_request('sendDocument', $fields, true);
    }
    return [$ok, $ok ? 'OK' : (is_string($result) ? $result : 'Telegram rejected the receipt.')];
}

// -------------------- ADMIN ORDER NOTIFICATION ENDPOINT --------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'admin_order_notifications') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!is_admin()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }

    $since = trim((string)($_GET['since'] ?? ''));
    $sinceTimestamp = $since !== '' ? strtotime($since) : false;
    $sinceTimestamp = $sinceTimestamp === false ? 0 : $sinceTimestamp;

    $orders = load_orders();
    $newOrders = [];
    $latestTimestamp = $sinceTimestamp;
    $latestCreatedAt = '';

    foreach ($orders as $order) {
        $createdAt = (string)($order['created_at'] ?? '');
        $timestamp = strtotime($createdAt);
        if ($timestamp === false) {
            continue;
        }

        if ($timestamp > $latestTimestamp) {
            $latestTimestamp = $timestamp;
            $latestCreatedAt = $createdAt;
        } elseif ($timestamp === $latestTimestamp && $createdAt !== '') {
            $latestCreatedAt = $createdAt;
        }

        if ($timestamp >= $sinceTimestamp && $timestamp > 0) {
            $newOrders[] = [
                'id' => (string)($order['id'] ?? ''),
                'buyer_name' => (string)($order['buyer_name'] ?? ''),
                'product' => (string)($order['product'] ?? ''),
                'amount' => (string)($order['amount'] ?? ''),
                'status' => (string)($order['status'] ?? 'pending'),
                'created_at' => $createdAt,
            ];
        }
    }

    usort($newOrders, function ($a, $b) {
        return strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? ''));
    });

    // Keep the response small. The browser also tracks IDs it has already seen.
    if (count($newOrders) > 20) {
        $newOrders = array_slice($newOrders, -20);
    }

    $pendingCount = 0;
    foreach ($orders as $order) {
        if (($order['status'] ?? 'pending') === 'pending') {
            $pendingCount++;
        }
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    echo json_encode([
        'ok' => true,
        'orders' => $newOrders,
        'latest_created_at' => $latestCreatedAt,
        'pending_count' => $pendingCount,
        'server_time' => date('c'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------- REAL-TIME CHAT POLLING ENDPOINT --------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'chat_poll') {
    $chatPollLimit = security_rate_limit('chat-poll', 120, 60);
    if (!$chatPollLimit['allowed']) {
        chat_json_response(['ok'=>false,'error'=>'Rate limit reached. Please wait a moment.','retry_after'=>60], 429);
    }
    $requestedRole = ($_GET['role'] ?? '') === 'admin' ? 'admin' : 'buyer';

    if ($requestedRole === 'admin') {
        if (!is_admin()) {
            chat_json_response(['ok' => false, 'error' => 'Unauthorized.'], 401);
        }
    } elseif (!is_user()) {
        chat_json_response(['ok' => false, 'error' => 'Unauthorized.'], 401);
    }

    $role = $requestedRole;
    $selectedUser = '';

    if ($role === 'admin') {
        $selectedUser = chat_identity($_GET['user'] ?? '');
    } else {
        $selectedUser = chat_identity($_SESSION['buyer_username'] ?? '');
    }

    $selectedConversation = $selectedUser !== '' ? chat_get_conversation($selectedUser) : null;

    $response = [
        'ok' => true,
        'role' => $role,
        'selected_user' => $selectedUser,
        'conversation' => $selectedConversation ?: [
            'username' => $selectedUser,
            'messages' => [],
            'updated_at' => '',
        ],
        'unread_total' => chat_unread_total($role, $selectedUser),
        'server_time' => date('c'),
    ];

    if ($role === 'admin') {
        $response['conversations'] = chat_list_conversations();
    }

    chat_json_response($response);
}

// -------------------- BUYER ORDER STATUS POLLING --------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'buyer_order_status_poll') {
    if (!is_user()) {
        chat_json_response(['ok' => false, 'error' => 'Unauthorized.'], 401);
    }

    $username = strtolower(trim((string)($_SESSION['buyer_username'] ?? '')));
    $guestIds = is_array($_SESSION['guest_order_ids'] ?? null) ? $_SESSION['guest_order_ids'] : [];
    $orders = load_orders();
    $visible = [];

    foreach ($orders as $order) {
        if (!pro_order_belongs_to_buyer($order, $username, $guestIds)) continue;

        $visible[] = [
            'id' => (string)($order['id'] ?? ''),
            'product' => (string)($order['product'] ?? 'Order'),
            'amount' => (string)($order['amount'] ?? ''),
            'status' => (string)($order['status'] ?? 'pending'),
            'created_at' => (string)($order['created_at'] ?? ''),
            'updated_at' => (string)($order['status_updated_at'] ?? $order['reviewed_at'] ?? $order['created_at'] ?? ''),
        ];
    }

    usort($visible, function ($a, $b) {
        return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
    });

    chat_json_response([
        'ok' => true,
        'orders' => array_slice($visible, 0, 25),
        'server_time' => date('c'),
    ]);
}

require_once __DIR__ . '/includes/security.php';

security_headers();

// -------------------- POST ACTIONS --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_ok()) {
    if (($_POST['ajax'] ?? '') === '1') {
        chat_json_response(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 419);
    }
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
    redirect_page('shop');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $messageLimit = security_rate_limit('send-message', 30, 60);
    if (!$messageLimit['allowed']) {
        security_rate_limit_response('Too many messages sent. Please wait a moment before sending again.', 60, ($_POST['ajax'] ?? '') === '1');
    }
    $isAjaxChat = ($_POST['ajax'] ?? '') === '1';
    $chatRole = ($_POST['chat_role'] ?? '') === 'admin' ? 'admin' : 'buyer';

    if ($chatRole === 'admin' && !is_admin()) {
        if ($isAjaxChat) chat_json_response(['ok' => false, 'error' => 'Administrator session required.'], 401);
        redirect_page('admin');
    }

    if ($chatRole === 'buyer' && !is_user()) {
        if ($isAjaxChat) chat_json_response(['ok' => false, 'error' => 'Please log in again.'], 401);
        redirect_page('account');
    }

    $message = trim((string)($_POST['message'] ?? ''));
    $link = trim((string)($_POST['link'] ?? ''));

    if ($message === '') {
        if ($isAjaxChat) chat_json_response(['ok' => false, 'error' => 'Please enter a message.'], 422);
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter a message.'];
        redirect_page(is_admin() ? 'admin' : 'messages');
    }

    if ($chatRole === 'admin') {
        $username = chat_identity($_POST['username'] ?? '');
        $back = 'index.php?page=admin&tab=messages&user=' . rawurlencode($username);
        if ($username === '') {
            if ($isAjaxChat) chat_json_response(['ok' => false, 'error' => 'Select a buyer conversation first.'], 422);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Select a buyer conversation first.'];
            redirect_to('index.php?page=admin&tab=messages');
        }
        $senderType = 'admin';
        $senderName = 'Administrator';
    } else {
        $username = chat_identity($_SESSION['buyer_username'] ?? '');
        $back = 'index.php?page=messages';
        $senderType = 'buyer';
        $senderName = $_SESSION['buyer_name'] ?? $username;
    }

    if (normalize_external_link($link) === '' && $link !== '') {
        if ($isAjaxChat) chat_json_response(['ok' => false, 'error' => 'Please enter a valid http:// or https:// link.'], 422);
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter a valid http:// or https:// link.'];
        redirect_to($back);
    }

    if (!chat_add_message($username, $senderType, $senderName, $message, $link)) {
        if ($isAjaxChat) chat_json_response(['ok' => false, 'error' => 'Unable to save your message. Please try again.'], 500);
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Unable to save your message. Please try again.'];
        redirect_to($back);
    }

    $conversation = chat_get_conversation($username);

    if ($isAjaxChat) {
        chat_json_response([
            'ok' => true,
            'conversation' => $conversation ?: [
                'username' => $username,
                'messages' => [],
                'updated_at' => '',
            ],
            'unread_total' => chat_unread_total($senderType === 'admin' ? 'admin' : 'buyer', $username),
        ]);
    }

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Message sent successfully.'];
    redirect_to($back);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'chat_mark_read') {
    $chatRole = ($_POST['chat_role'] ?? '') === 'admin' ? 'admin' : 'buyer';

    if ($chatRole === 'admin' && !is_admin()) {
        chat_json_response(['ok' => false, 'error' => 'Administrator session required.'], 401);
    }

    if ($chatRole === 'buyer' && !is_user()) {
        chat_json_response(['ok' => false, 'error' => 'Please log in again.'], 401);
    }

    $isAjaxChat = ($_POST['ajax'] ?? '') === '1';

    if ($chatRole === 'admin') {
        $username = chat_identity($_POST['username'] ?? '');
        if ($username === '') {
            if ($isAjaxChat) chat_json_response(['ok' => false, 'error' => 'Conversation not found.'], 422);
            redirect_page('admin');
        }
        $readerType = 'admin';
    } else {
        $username = chat_identity($_SESSION['buyer_username'] ?? '');
        $readerType = 'buyer';
    }

    $ok = $username !== '' && chat_mark_read($username, $readerType);

    if ($isAjaxChat) {
        chat_json_response([
            'ok' => $ok || $username === '',
            'username' => $username,
            'unread_total' => chat_unread_total($readerType, $username),
        ]);
    }

    redirect_to($chatRole === 'admin'
        ? 'index.php?page=admin&tab=messages&user=' . rawurlencode($username)
        : 'index.php?page=messages'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_order') {
    $orderLimit = security_rate_limit('send-order', 8, 600);
    if (!$orderLimit['allowed']) {
        security_rate_limit_response('Too many order submissions. Please wait before submitting another order.', 600);
    }
    $slug = trim($_POST['slug'] ?? '');
    if (!isset($products[$slug])) redirect_page('shop');
    $p = $products[$slug];
    $tierIndex = filter_var($_POST['tier'] ?? null, FILTER_VALIDATE_INT);
    if ($tierIndex === false || !isset($p['tiers'][$tierIndex])) redirect_product($slug);
    $tier = $p['tiers'][$tierIndex];

    $user = trim($_POST['telegram_username'] ?? '');
    $name = trim($_POST['buyer_name'] ?? '');
    $payment = trim($_POST['payment_method'] ?? '');
    $uid = trim($_POST['uid'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($user === '' || $name === '') {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please complete your name and Telegram username.'];
        redirect_product($slug);
    }

    $orderId = new_order_id();
    [$receiptOk, $receipt] = save_receipt_upload($_FILES['receipt'] ?? null, $orderId);
    if (!$receiptOk) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => $receipt];
        redirect_product($slug);
    }

    $order = [
        'id' => $orderId,
        'created_at' => date('c'),
        'buyer_username' => is_user() ? ($_SESSION['buyer_username'] ?? '') : '',
        'telegram_username' => $user,
        'buyer_name' => $name,
        'product' => $p['name'],
        'slug' => $slug,
        'duration' => $tier[0],
        'amount' => money($tier[1]),
        'payment_method' => strtoupper($payment ?: '—'),
        'uid' => $uid,
        'note' => $note,
        'receipt' => $receipt['url'],
        'receipt_url' => $receipt['url'],
        'receipt_filename' => $receipt['filename'],
        'receipt_mime' => $receipt['mime'],
        'receipt_cloudinary_public_id' => $receipt['public_id'],
        'receipt_cloudinary_asset_id' => $receipt['asset_id'],
        'receipt_cloudinary_resource_type' => $receipt['resource_type'],
        'receipt_cloudinary_format' => $receipt['format'],
        'status' => 'pending',
    ];

    $orders = load_orders();
    $orders[] = $order;
    if (!save_orders($orders)) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'The receipt was uploaded, but the order could not be saved. Please try again.'];
        redirect_page('my-orders');
    }

    if (!isset($_SESSION['guest_order_ids'])) $_SESSION['guest_order_ids'] = [];
    if (!in_array($orderId, $_SESSION['guest_order_ids'], true)) $_SESSION['guest_order_ids'][] = $orderId;

    $caption =
        '<b>🛒 NEW ORDER</b>' . "\n\n" .
        '<b>Order ID:</b> <code>' . e($orderId) . '</code>' . "\n" .
        '<b>User:</b> <code>' . e($user) . '</code>' . "\n" .
        '<b>Name:</b> ' . e($name) . "\n" .
        '<b>Product:</b> ' . e($p['name']) . "\n" .
        '<b>Duration:</b> ' . e($tier[0]) . "\n" .
        '<b>Amount:</b> ' . e(money($tier[1])) . "\n" .
        '<b>Payment Method:</b> ' . e(strtoupper($payment ?: '—')) . "\n" .
        ($uid !== '' ? '<b>UID:</b> ' . e($uid) . "\n" : '') .
        ($note !== '' ? "\n" . '<b>Note:</b> ' . e($note) . "\n" : '') .
        "\n" . '<b>Status:</b> PENDING PAYMENT REVIEW' . "\n" .
        '<b>SHOP:</b> ' . e(SHOP_URL);

    // Do not notify Telegram yet. The banner + receipt will only be sent
    // after an administrator accepts this order.
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Order ' . $orderId . ' submitted and is waiting for admin review.'];
    redirect_page('my-orders');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_concern') {
    $concernLimit = security_rate_limit('send-concern', 10, 300);
    if (!$concernLimit['allowed']) {
        security_rate_limit_response('Too many concern submissions. Please wait before sending another.', 300);
    }
    $user = trim($_POST['telegram_username'] ?? '');
    $order = trim($_POST['order_id'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($user === '' || $message === '') {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter your Telegram username and concern.'];
        redirect_page('concerns');
    }
    $text = '<b>💬 ORDER CONCERN</b>\n\n' .
        '<b>User:</b> ' . e($user) . '\n' .
        '<b>Order ID:</b> ' . e($order ?: '—') . '\n\n' .
        '<b>Concern:</b>\n' . e($message) . '\n\n' .
        '<b>SHOP:</b> ' . e(SHOP_URL);
    [$ok, $msg] = telegram_request('sendMessage', [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => strip_tags(str_replace(['<b>','</b>'], '', $text)),
        'disable_web_page_preview' => true,
    ]);
    $_SESSION['flash'] = ['type' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Concern sent to Telegram successfully.' : ($msg ?: 'Unable to send concern.')];
    redirect_page('concerns');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buyer_register') {
    $registerLimit = security_rate_limit('buyer-register', 5, 3600);
    if (!$registerLimit['allowed']) {
        security_rate_limit_response('Too many registration attempts. Please wait before creating another account.', 3600);
    }
    if (empty($siteSettings['registration_enabled'])) { $_SESSION['flash']=['type'=>'error','msg'=>'Buyer registration is currently disabled.']; redirect_page('account'); }
    $username = strtolower(trim($_POST['username'] ?? ''));
    $display = trim($_POST['display_name'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_\.]{3,32}$/', $username) || $display === '' || strlen($password) < 6) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Use a valid username, display name, and password (minimum 6 characters).'];
        redirect_page('account');
    }
    $users = load_users();
    foreach ($users as $u) {
        if (strcasecmp($u['username'] ?? '', $username) === 0) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'That username is already registered.'];
            redirect_page('account');
        }
    }
    $users[] = [
        'username' => $username,
        'display_name' => $display,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c'),
        'status' => 'active',
    ];
    save_users($users);
    if (password_needs_rehash((string)($matched['password'] ?? ''), PASSWORD_DEFAULT)) {
        $users = load_users();
        foreach ($users as &$rehashUser) {
            if (strcasecmp((string)($rehashUser['username'] ?? ''), $matched['username'] ?? '') === 0) {
                $rehashUser['password'] = password_hash($password, PASSWORD_DEFAULT);
                break;
            }
        }
        unset($rehashUser);
        save_users($users);
    }

    session_regenerate_id(true);
    $_SESSION['buyer_logged_in'] = true;
    $_SESSION['buyer_username'] = $username;
    $_SESSION['buyer_name'] = $display;
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Account created successfully.'];
    redirect_page('account');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buyer_login') {
    $loginUsername = strtolower(trim((string)($_POST['username'] ?? '')));
    $loginIpLimit = security_rate_limit('buyer-login-ip', 10, 600);
    $loginUserLimit = security_rate_limit('buyer-login-user', 10, 600, $loginUsername);
    if (!$loginIpLimit['allowed'] || !$loginUserLimit['allowed']) {
        security_rate_limit_response('Too many login attempts. Please wait a few minutes and try again.', 600);
    }
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $users = load_users();
    $matched = null;
    foreach ($users as $u) {
        if (strcasecmp($u['username'] ?? '', $username) === 0) { $matched = $u; break; }
    }
    if (!$matched || ($matched['status'] ?? 'active') !== 'active' || !password_verify($password, $matched['password'] ?? '')) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Incorrect username or password.'];
        redirect_page('account');
    }
    session_regenerate_id(true);
    $_SESSION['buyer_logged_in'] = true;
    $_SESSION['buyer_username'] = $matched['username'];
    $_SESSION['buyer_name'] = $matched['display_name'];
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Welcome back, ' . $matched['display_name'] . '.'];
    redirect_page('account');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_login') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $ipLimit = security_rate_limit('admin-login-ip', 6, 600);
    $userLimit = security_rate_limit('admin-login-user', 8, 900, strtolower($username));
    if (!$ipLimit['allowed'] || !$userLimit['allowed']) {
        security_rate_limit_response('Too many administrator login attempts. Please wait 10–15 minutes and try again.', 900);
    }

    $hash = defined('ADMIN_PASSWORD_HASH') ? (string)ADMIN_PASSWORD_HASH : '';
    $validUser = hash_equals((string)ADMIN_USERNAME, $username);
    $validPassword = $hash !== '' && password_verify($password, $hash);

    if ($validUser && $validPassword) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = ADMIN_USERNAME;
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Administrator login successful.'];
        redirect_page('admin');
    }

    $_SESSION['flash'] = ['type'=>'error','msg'=>'Incorrect administrator username or password.'];
    redirect_page('admin');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_content') {
    admin_only();
    $fields = [
        'site_name','site_brand','shop_title','shop_description','preview_title','preview_description',
        'promos_title','promos_description','concerns_title','concerns_description','terms_title','terms_description',
        'account_title','account_description','orders_title','orders_description','footer_text','payment_title',
        'payment_description','payment_note'
    ];
    $content = load_content();
    foreach ($fields as $field) $content[$field] = trim((string)($_POST[$field] ?? ''));
    save_content($content);
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Website content updated successfully.'];
    redirect_to('index.php?page=admin&tab=content');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
    admin_only();
    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = trim($slug, '-');
    $name = trim((string)($_POST['name'] ?? ''));
    if ($slug === '' || $name === '') {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Product slug and name are required.'];
        redirect_to('index.php?page=admin&tab=products');
    }
    $products = load_products();
    $details = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['details'] ?? ''))), function($v){ return $v !== ''; }));
    $features = array_values(array_filter(array_map('trim', preg_split('/[,\R]+/', (string)($_POST['features'] ?? ''))), function($v){ return $v !== ''; }));
    $tiers = [];
    for ($i=0; $i<4; $i++) {
        $tierName = trim((string)($_POST['tier_name'][$i] ?? ''));
        $tierPrice = (float)($_POST['tier_price'][$i] ?? 0);
        $tierStock = trim((string)($_POST['tier_stock'][$i] ?? ''));
        if ($tierName !== '') $tiers[] = [$tierName, $tierPrice, ($tierStock !== '' ? $tierStock : null)];
    }
    if (!$tiers) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Add at least one pricing tier.'];
        redirect_to('index.php?page=admin&tab=products');
    }
    $products[$slug] = [
        'slug'=>$slug,
        'category'=>trim((string)($_POST['category'] ?? 'General')),
        'name'=>$name,
        'image'=>trim((string)($_POST['image'] ?? 'assets/kaelhax-logo.png')),
        'promo'=>!empty($_POST['promo']),
        'features'=>$features,
        'details_title'=>trim((string)($_POST['details_title'] ?? 'DETAILS')),
        'details'=>$details,
        'price_title'=>trim((string)($_POST['price_title'] ?? 'PRICELIST')),
        'tiers'=>$tiers,
    ];
    save_products($products);
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Product saved successfully.'];
    redirect_to('index.php?page=admin&tab=products&edit=' . rawurlencode($slug));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    admin_only();
    $slug = trim((string)($_POST['slug'] ?? ''));
    $products = load_products();
    if (isset($products[$slug])) {
        unset($products[$slug]);
        save_products($products);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Product removed from the catalog.'];
    }
    redirect_to('index.php?page=admin&tab=products');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    admin_only();
    $settings = load_settings();
    $settings['shop_enabled'] = !empty($_POST['shop_enabled']);
    $settings['registration_enabled'] = !empty($_POST['registration_enabled']);
    $settings['telegram_enabled'] = !empty($_POST['telegram_enabled']);
    $settings['payment_qr'] = trim((string)($_POST['payment_qr'] ?? 'assets/payment-qr.png')) ?: 'assets/payment-qr.png';
    save_settings($settings);
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Website settings saved.'];
    redirect_to('index.php?page=admin&tab=settings');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_buyer_status') {
    admin_only();
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $status = ($_POST['status'] ?? '') === 'disabled' ? 'disabled' : 'active';
    $users = load_users(); $found=false;
    foreach ($users as &$u) {
        if (strtolower((string)($u['username'] ?? '')) === $username) {
            $u['status'] = $status; $found=true; break;
        }
    }
    unset($u);
    if ($found) {
        save_users($users);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Buyer account status updated.'];
    }
    redirect_to('index.php?page=admin&tab=buyers');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_telegram') {
    admin_only();
    [$ok,$msg] = telegram_request('sendMessage', ['chat_id'=>TELEGRAM_CHAT_ID,'text'=>'✅ Admin test message from Project Market.','disable_web_page_preview'=>true]);
    $_SESSION['flash'] = ['type'=>$ok?'success':'error','msg'=>$ok?'Telegram test message sent successfully.':$msg];
    redirect_to('index.php?page=admin&tab=telegram');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order_status') {
    if (!is_admin()) redirect_page('admin');
    $orderId = trim($_POST['order_id'] ?? '');
    $requestedStatus = strtolower(trim((string)($_POST['status'] ?? 'pending')));
    $allowedStatuses = ['pending', 'accepted', 'processing', 'completed', 'ignored'];
    $status = in_array($requestedStatus, $allowedStatuses, true) ? $requestedStatus : 'pending';
    $orders = load_orders();
    $found = false;
    $approvedOrder = null;
    $wasAlreadyTelegramSent = false;
    foreach ($orders as &$order) {
        if (($order['id'] ?? '') === $orderId) {
            $wasAlreadyTelegramSent = !empty($order['telegram_accepted_sent_at']);
            $order['status'] = $status;
            $order['reviewed_at'] = date('c');
            $order['status_updated_at'] = date('c');
            $order['reviewed_by'] = $_SESSION['admin_username'] ?? 'admin';
            if ($status === 'accepted') {
                $approvedOrder = $order;
            }
            $found = true;
            break;
        }
    }
    unset($order);
    if ($found) {
        $telegramResult = null;

        // Only after admin approval: send the order banner first, then the receipt.
        // Ignored/pending orders never send the banner or receipt to Telegram.
        if ($status === 'accepted' && is_array($approvedOrder) && !$wasAlreadyTelegramSent) {
            $approvedCaption =
                '<b>✅ APPROVED ORDER</b>' . "\n\n" .
                '<b>Order ID:</b> <code>' . e($approvedOrder['id'] ?? $orderId) . '</code>' . "\n" .
                '<b>User:</b> <code>' . e($approvedOrder['telegram_username'] ?? '') . '</code>' . "\n" .
                '<b>Name:</b> ' . e($approvedOrder['buyer_name'] ?? '') . "\n" .
                '<b>Product:</b> ' . e($approvedOrder['product'] ?? '') . "\n" .
                '<b>Duration:</b> ' . e($approvedOrder['duration'] ?? '') . "\n" .
                '<b>Amount:</b> ' . e($approvedOrder['amount'] ?? '') . "\n" .
                '<b>Payment Method:</b> ' . e($approvedOrder['payment_method'] ?? '—') . "\n" .
                (!empty($approvedOrder['uid']) ? '<b>UID:</b> ' . e($approvedOrder['uid']) . "\n" : '') .
                (!empty($approvedOrder['note']) ? "\n" . '<b>Note:</b> ' . e($approvedOrder['note']) . "\n" : '') .
                "\n" . '<b>Status:</b> ACCEPTED BY ADMIN' . "\n" .
                '<b>SHOP:</b> ' . e(SHOP_URL);

            [$bannerSent, $bannerMsg] = telegram_send_order($approvedCaption);

            /*
             * The payment receipt is kept in Cloudinary and remains
             * viewable in the Admin Orders page. It is NOT sent to Telegram.
             */
            $receiptSent = true;
            $receiptMsg = 'Receipt kept in Admin / Cloudinary storage.';

            if ($bannerSent && $receiptSent) {
                foreach ($orders as &$savedOrder) {
                    if (($savedOrder['id'] ?? '') === $orderId) {
                        $savedOrder['telegram_accepted_sent_at'] = date('c');
                        break;
                    }
                }
                unset($savedOrder);
                $telegramResult = [true, 'Approved order banner sent to Telegram. Payment receipt remains available in Admin.'];
            } else {
                $telegramResult = [false, 'Order accepted, but Telegram delivery failed. ' . (!$bannerSent ? $bannerMsg : $receiptMsg)];
            }
        }

        if (!save_orders($orders)) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'The order status could not be saved. Please try again.'];
        } elseif ($telegramResult && !$telegramResult[0]) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Order ' . $orderId . ' marked as ACCEPTED. ' . $telegramResult[1]];
        } elseif ($status === 'accepted' && $wasAlreadyTelegramSent) {
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Order ' . $orderId . ' is already accepted and has already been sent to Telegram.'];
        } elseif ($status === 'accepted') {
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Order ' . $orderId . ' accepted. Banner sent to Telegram; payment receipt remains available in Admin.'];
        } else {
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Order ' . $orderId . ' marked as ' . strtoupper($status) . '.'];
        }
    } else {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Order not found.'];
    }
    $backTab = preg_replace('/[^a-z-]/', '', (string)($_POST['tab'] ?? 'dashboard'));
    redirect_to('index.php?page=admin&tab=' . ($backTab ?: 'dashboard'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    $type = $_POST['type'] ?? 'buyer';
    if ($type === 'admin') {
        unset($_SESSION['admin_logged_in'], $_SESSION['admin_username']);
    } else {
        unset($_SESSION['buyer_logged_in'], $_SESSION['buyer_username'], $_SESSION['buyer_name']);
    }
    $_SESSION['flash'] = ['type'=>'success','msg'=>'You have been logged out.'];
    redirect_page($type === 'admin' ? 'admin' : 'account');
}

$page = $_GET['page'] ?? 'account';
$slug = $_GET['slug'] ?? '';

/* Login-first experience: buyers must sign in before entering the storefront. */
$protectedBuyerPages = ['shop', 'product', 'preview', 'promos', 'my-orders', 'messages'];
if (!is_user() && !is_admin() && in_array($page, $protectedBuyerPages, true)) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please log in first to access the storefront.'];
    $page = 'account';
}

$product = ($page === 'product' && isset($products[$slug])) ? $products[$slug] : null;
if (!$siteSettings['shop_enabled'] && in_array($page, ['shop','product','preview','promos'], true) && !is_admin()) { $page = 'account'; $product = null; }
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function menu_item($href, $label, $icon, $pageKey) {
    global $page;
    $active = ($page === $pageKey) ? ' active' : '';
    return '<a class="menu-link' . $active . '" href="' . e($href) . '"><span class="menu-icon">' . $icon . '</span><span>' . e($label) . '</span></a>';
}

/*
 * Persist the current session BEFORE any HTML is emitted.
 * This is critical for the custom cookie session handler because
 * setcookie() cannot modify response headers after output begins.
 */
csrf_token();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<base href="/">
<meta name="theme-color" content="#0b0f15">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="stylesheet" href="/assets/pro-upgrades.css">
<link rel="stylesheet" href="/assets/security.css">
<link rel="apple-touch-icon" href="/assets/kaelhax-logo.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= $page === 'product' && $product ? e($product['name']) . ' | ' . e($siteContent['site_name']) : e($siteContent['site_name']) . ' | ' . e($siteContent['site_brand']) ?></title>
<style>
:root{--bg:#0b0f15;--surface:#151b24;--surface2:#10161f;--line:#293341;--line2:#222c38;--text:#edf2f8;--muted:#9ca8b8;--blue:#72a6ff;--green:#51dc92;--danger:#ff7188;--shadow:0 22px 65px rgba(0,0,0,.34)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}body.lock{overflow:hidden}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}button{cursor:pointer}
.site-header{height:72px;position:sticky;top:0;z-index:50;background:rgba(12,16,22,.97);border-bottom:1px solid var(--line);backdrop-filter:blur(14px)}.header-inner{height:100%;width:min(1220px,100%);margin:auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between}.brand{display:flex;align-items:center;gap:11px}.brand-logo{width:42px;height:42px;border-radius:10px;object-fit:cover;border:1px solid #384351}.brand-title{font-size:17px;font-weight:900;line-height:1}.brand-title small{display:block;margin-top:4px;font-size:9px;color:#7e8998;letter-spacing:.15em;text-transform:uppercase}.menu{border:1px solid #303c4a;background:#141b24;color:var(--text);border-radius:13px;padding:10px 15px;font-weight:900;display:flex;gap:10px;align-items:center}.menu svg{width:19px;height:19px}
.menu-backdrop{position:fixed;z-index:60;inset:72px 0 0;background:rgba(0,0,0,.62);opacity:0;pointer-events:none;transition:.18s}.menu-backdrop.open{opacity:1;pointer-events:auto}.drawer{position:fixed;z-index:65;top:72px;right:0;bottom:0;width:420px;max-width:92vw;background:linear-gradient(180deg,#171e28,#10161e 72%);border-left:1px solid #2b3542;transform:translateX(102%);transition:.2s;box-shadow:-18px 0 60px rgba(0,0,0,.35);overflow:auto}.drawer.open{transform:none}.drawer-inner{padding:20px}.drawer-top{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;margin-bottom:19px}.drawer-kicker{font-size:12px;color:var(--blue);letter-spacing:.13em;text-transform:uppercase;font-weight:900}.drawer-title{font-size:23px;font-weight:900;margin-top:3px}.drawer-close{width:43px;height:43px;border:1px solid #354151;background:#18212b;color:#e8eef6;border-radius:12px;font-size:26px}.menu-group{border-top:1px solid #27323e;padding-top:13px;margin-top:13px}.group-title{font-size:12px;letter-spacing:.11em;text-transform:uppercase;color:#8c98a8;font-weight:900;margin:0 9px 7px}.menu-link{width:100%;display:flex;align-items:center;gap:13px;border:1px solid transparent;background:transparent;color:#bdc6d2;border-radius:13px;padding:12px 13px;font-weight:800}.menu-link:hover{background:#1c2530;border-color:#303b49;color:#fff}.menu-link.active{background:#213147;border-color:#31567f;color:#fff}.menu-icon{width:20px;display:grid;place-items:center;color:#a8b6c8}.menu-link.active .menu-icon{color:#77b2ff}.drawer-note{border-top:1px solid #27323e;margin-top:15px;padding-top:15px;color:#8793a3;font-size:12px;line-height:1.5}
main{width:min(1220px,100%);margin:auto;padding:30px 24px 62px}.content-head{margin-bottom:22px}.kicker{color:var(--blue);font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.content-head h1{font-size:37px;line-height:1;margin:9px 0 12px;letter-spacing:-.04em}.content-head p{margin:0;color:var(--muted);font-size:16px;max-width:780px}
.shop-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}.product-card{border:1px solid var(--line);background:var(--surface);border-radius:19px;overflow:hidden;box-shadow:0 12px 34px rgba(0,0,0,.16);transition:.18s transform,.18s border-color}.product-card:hover{transform:translateY(-3px);border-color:#3b4656}.product-image{aspect-ratio:16/9;background:#000;overflow:hidden}.product-image img{width:100%;height:100%;object-fit:cover;display:block}.product-info{padding:15px 16px 16px}.badges{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:6px}.badge{font-size:11px;font-weight:800;color:var(--green);border:1px solid #275f44;background:rgba(81,220,146,.04);border-radius:999px;padding:5px 8px}.badge.promo{color:var(--blue);border-color:#365078;background:rgba(114,166,255,.05)}.product-name{font-size:19px;font-weight:900;margin:0 0 10px}.product-price{font-size:16px;font-weight:900;margin:0 0 13px}.view-btn{width:100%;border:1px solid #2c3745;background:#1a222d;color:#eef2f7;border-radius:12px;padding:10px 12px;font-weight:900}
.product-layout{display:grid;grid-template-columns:1fr 480px;gap:36px;align-items:start}.product-meta{padding-top:4px}.product-meta h1{font-size:36px;line-height:1.05;margin:8px 0 10px;letter-spacing:-.035em}.available{display:inline-flex;border:1px solid #255e43;border-radius:999px;color:var(--green);padding:5px 9px;font-size:11px;font-weight:900}.feature-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.feature-pill{border:1px solid #275d45;color:var(--green);background:rgba(81,220,146,.05);border-radius:999px;padding:6px 10px;font-size:11px;font-weight:900}.product-art{margin-top:235px;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#000}.product-art img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover}.details-panel{margin-top:16px;background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:18px}.details-panel h2{font-size:18px;margin:0 0 13px}.detail-line{color:#d7dee7;padding:7px 0;font-size:13px}.price-panel{background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:18px}.price-panel h2{font-size:26px;margin:1px 0 18px}.price-list{display:grid;gap:9px}.price-item{border:1px solid var(--line);background:#101720;border-radius:14px;padding:11px 12px;display:flex;justify-content:space-between;align-items:center;gap:10px}.price-name{font-weight:900}.stock{font-size:10px;color:#8692a1;margin-top:2px}.price-cur{font-size:10px;color:#7f8a99;margin-top:2px}.price-right{display:flex;align-items:center;gap:10px}.amount{font-weight:900;white-space:nowrap}.buy{border:0;background:#70a6ff;color:#06101a;border-radius:10px;padding:9px 12px;font-weight:900}.back-link{border:0;background:none;color:#9fabb9;padding:0;margin-bottom:18px;font-weight:800}
.info-box{border:1px solid var(--line);background:var(--surface);border-radius:18px;padding:18px;margin-top:13px}.info-row{display:flex;justify-content:space-between;gap:18px;padding:11px 0;border-bottom:1px solid #202a36}.info-row:last-child{border-bottom:0}.info-row span{color:var(--muted)}.hero-actions{display:grid;gap:9px;margin-top:14px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field label{display:block;color:#aab5c3;font-size:11px;margin:0 0 5px}.field input,.field select,.field textarea{width:100%;background:#090e14;border:1px solid #2b3643;color:var(--text);border-radius:11px;padding:10px 11px;outline:0}.field textarea{min-height:110px;resize:vertical}.full{grid-column:1/-1}.primary{border:0;background:#70a6ff;color:#07101a;border-radius:12px;padding:11px 13px;font-weight:900;width:100%;margin-top:11px}.notice{font-size:10px;line-height:1.5;color:#758091;margin-top:9px}
.flash{border-radius:13px;padding:12px 14px;margin-bottom:16px;border:1px solid}.flash.success{border-color:#265e44;background:rgba(81,220,146,.07);color:#9be8bc}.flash.error{border-color:#6a3542;background:rgba(255,113,136,.07);color:#ffb1bd}
.auth-shell{min-height:calc(100vh - 72px);display:flex;align-items:center;justify-content:center;padding:28px 16px}.auth-card{width:min(460px,100%);background:linear-gradient(180deg,#151b24,#111720);border:1px solid var(--line);border-radius:20px;padding:22px;box-shadow:var(--shadow)}.auth-card .auth-logo{display:block;width:58px;height:58px;border-radius:15px;object-fit:cover;border:1px solid #354151;margin:0 auto 14px}.auth-card h1{text-align:center;font-size:28px;margin:0 0 7px}.auth-card>p{text-align:center;color:var(--muted);font-size:13px;margin:0 0 18px}.auth-tabs{display:grid;grid-template-columns:1fr 1fr;background:#0c1219;padding:4px;border:1px solid var(--line);border-radius:12px;gap:4px}.auth-tabs button{border:0;background:transparent;color:#8e9aaa;border-radius:9px;padding:9px;font-weight:800}.auth-tabs button.active{background:#1c2a3b;color:#fff}.auth-form{margin-top:15px}.auth-divider{height:1px;background:#242f3c;margin:18px 0}.logout-btn{border:1px solid var(--line);background:#101720;color:#e7edf4;border-radius:11px;padding:10px 12px;font-weight:900}
.admin-panel{display:grid;grid-template-columns:repeat(3,1fr);gap:11px;margin-top:15px}.stat{background:#101720;border:1px solid var(--line);border-radius:13px;padding:14px}.stat strong{display:block;font-size:21px}.stat span{display:block;color:var(--muted);font-size:11px;margin-top:3px}
.admin-nav{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.admin-nav a{border:1px solid var(--line);background:#101720;color:#aeb8c5;border-radius:11px;padding:9px 12px;font-weight:850;font-size:12px}.admin-nav a.active{background:#213147;border-color:#31567f;color:#fff}.admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.admin-card{border:1px solid var(--line);background:var(--surface);border-radius:18px;padding:18px}.admin-card h2{font-size:19px;margin:0 0 12px}.admin-card p{color:var(--muted);font-size:12px}.admin-card .field{margin-bottom:10px}.admin-card textarea{min-height:95px}.admin-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.small-btn{border:1px solid var(--line);background:#101720;color:#e7edf4;border-radius:10px;padding:8px 10px;font-weight:850;font-size:11px}.small-btn.primary{background:#70a6ff;color:#07101a;border:0}.small-btn.danger{color:#ffb1bd;border-color:#6a3542}.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:11px 0;border-bottom:1px solid #202a36}.toggle-row:last-child{border-bottom:0}.check{display:flex;align-items:center;gap:8px;color:#cbd3dd;font-size:12px}.product-admin-card{border:1px solid var(--line);background:#101720;border-radius:15px;padding:14px;margin-top:10px}.product-admin-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.product-admin-top h3{margin:0;font-size:16px}.tier-grid{display:grid;grid-template-columns:1.4fr .8fr .8fr;gap:8px}.receipt-view{display:block;max-width:260px;max-height:300px;object-fit:contain;background:#fff;border-radius:10px;margin-top:10px}.receipt-frame{width:100%;min-height:320px;border:1px solid var(--line);border-radius:12px;background:#0b1017;margin-top:10px}.muted-block{font-size:11px;color:var(--muted);line-height:1.5}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:15px}.admin-table{width:100%;border-collapse:collapse;min-width:700px}.admin-table th,.admin-table td{padding:11px 12px;border-bottom:1px solid #202a36;text-align:left;font-size:12px}.admin-table th{color:#8f9bac;font-size:10px;letter-spacing:.07em;text-transform:uppercase}.admin-table td{color:#d7dee7}.status-active{color:var(--green)}.status-disabled{color:var(--danger)}

footer{border-top:1px solid #1a222c;padding:30px 18px 44px;text-align:center;color:#7e8998;font-size:14px}
.payment-box{margin:16px 0;padding:14px;border:1px solid var(--line);background:#111821;border-radius:15px;text-align:center}.payment-box h3{margin:0 0 8px;font-size:15px}.payment-box p{margin:0 0 11px;color:var(--muted);font-size:11px}.payment-qr{display:block;width:min(300px,100%);aspect-ratio:1080/1045;object-fit:contain;background:#fff;border-radius:12px;padding:8px;margin:0 auto 10px}.payment-note{font-size:10px;color:#7e8998;line-height:1.5}.order-card{border:1px solid var(--line);background:var(--surface);border-radius:17px;padding:16px;margin-top:12px}.order-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}.order-id{font-weight:900}.order-meta{color:var(--muted);font-size:12px;line-height:1.6;margin-top:7px}.status-pill{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.status-pending{color:#f3d28a;border:1px solid #68552b;background:rgba(243,210,138,.06)}.status-accepted{color:var(--green);border:1px solid #275f44;background:rgba(81,220,146,.06)}.status-ignored{color:var(--danger);border:1px solid #6a3542;background:rgba(255,113,136,.06)}.admin-order-actions{display:flex;gap:8px;margin-top:13px}.admin-order-actions form{flex:1}.admin-order-actions button{width:100%;border-radius:11px;padding:10px 12px;font-weight:900;border:1px solid var(--line);background:#101720;color:#e7edf4}.admin-order-actions .accept{border-color:#275f44;color:#9be8bc;background:rgba(81,220,146,.04)}.admin-order-actions .ignore{border-color:#6a3542;color:#ffb1bd;background:rgba(255,113,136,.04)}.receipt-link{display:inline-flex;margin-top:10px;border:1px solid #31465d;background:#14202d;color:#a9c9ef;border-radius:10px;padding:8px 10px;font-weight:800;font-size:11px}
.receipt-section{margin-top:12px;padding:12px;border:1px solid var(--line);background:#0d141c;border-radius:13px}.receipt-title{color:#9ca8b8;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px}.receipt-view{display:block;width:min(100%,420px);max-height:420px;object-fit:contain;background:#fff;border:1px solid #33404f;border-radius:10px}.receipt-frame{display:block;width:100%;height:480px;border:1px solid #33404f;border-radius:10px;background:#fff}.receipt-link{display:inline-flex;margin-top:9px;border:1px solid #31465d;background:#14202d;color:#a9c9ef;border-radius:10px;padding:9px 11px;font-weight:800;font-size:11px}

.chat-shell{border:1px solid var(--line);background:var(--surface);border-radius:18px;overflow:hidden;max-width:860px}.chat-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 16px;border-bottom:1px solid var(--line);background:#101720}.chat-header strong{display:block;font-size:15px}.chat-header span{display:block;color:var(--muted);font-size:11px;margin-top:3px}.chat-status{display:inline-flex!important;border:1px solid #275f44;border-radius:999px;padding:5px 8px;color:var(--green)!important;font-size:9px!important;font-weight:900}.chat-thread{padding:16px;min-height:340px;max-height:540px;overflow:auto;background:#0b1017}.chat-row{display:flex;margin:8px 0}.chat-row.mine{justify-content:flex-end}.chat-row.theirs{justify-content:flex-start}.chat-bubble{max-width:min(78%,620px);border:1px solid #293341;background:#151d27;border-radius:15px;padding:10px 11px}.chat-row.mine .chat-bubble{background:#1a293a;border-color:#31506d}.chat-author{font-size:10px;color:var(--blue);font-weight:900;margin-bottom:5px}.chat-text{font-size:13px;line-height:1.5;color:#e4eaf1;word-break:break-word}.chat-inline-link{color:#89b8ff;text-decoration:underline}.chat-time{font-size:9px;color:#788496;margin-top:7px}.chat-link-btn{display:inline-flex;margin-top:8px;border:1px solid #31465d;background:#14202d;color:#a9c9ef;border-radius:9px;padding:7px 9px;font-size:10px;font-weight:900}.chat-empty{text-align:center;padding:70px 18px;color:#7f8b9a;font-size:12px;line-height:1.6}.chat-compose{padding:13px;border-top:1px solid var(--line);background:#101720}.chat-compose textarea{width:100%;min-height:90px;resize:vertical;background:#090e14;border:1px solid #2b3643;color:var(--text);border-radius:11px;padding:10px 11px;outline:0}.chat-compose-row{display:grid;grid-template-columns:1fr auto;gap:9px;margin-top:9px}.chat-compose-row input{width:100%;background:#090e14;border:1px solid #2b3643;color:var(--text);border-radius:11px;padding:10px 11px;outline:0}.chat-send{width:auto;min-width:150px;margin-top:0}.chat-admin-layout{display:grid;grid-template-columns:320px minmax(0,1fr);gap:14px}.chat-inbox-list{display:grid;gap:8px;max-height:620px;overflow:auto}.chat-inbox-item{display:block;border:1px solid var(--line);background:#101720;border-radius:12px;padding:11px;color:inherit}.chat-inbox-item:hover,.chat-inbox-item.active{border-color:#31567f;background:#182332}.chat-inbox-top{display:flex;justify-content:space-between;gap:10px;align-items:center}.chat-inbox-top strong{font-size:12px}.chat-inbox-preview{margin-top:5px;color:#b0bac7;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.chat-inbox-time{margin-top:5px;color:#758091;font-size:9px}.chat-unread{display:inline-flex;min-width:20px;height:20px;align-items:center;justify-content:center;border-radius:999px;background:#70a6ff;color:#07101a;font-size:9px;font-weight:900}.chat-admin-thread{min-width:0}.admin-thread-header{margin:-18px -18px 0}.admin-thread-scroll{min-height:360px;max-height:560px}.chat-admin-thread .chat-compose{margin:0 -18px -18px}.chat-admin-thread .chat-thread{margin:0 -18px}.chat-admin-thread .chat-header{border-radius:16px 16px 0 0}
.chat-page{max-width:980px;position:relative}.chat-page-header{padding:13px 15px}.chat-contact{display:flex;align-items:center;gap:11px}.chat-avatar{width:41px;height:41px;border-radius:13px;display:grid;place-items:center;background:linear-gradient(145deg,#243b55,#162334);border:1px solid #35516f;color:#dcecff;font-size:15px;font-weight:900;box-shadow:0 8px 25px rgba(0,0,0,.18)}.chat-status-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#51dc92;box-shadow:0 0 10px rgba(81,220,146,.55);vertical-align:1px;margin-right:5px}.chat-live-pill{border:1px solid #275f44;background:rgba(81,220,146,.06);color:#9be8bc;border-radius:999px;padding:6px 9px;font-size:9px;font-weight:900;letter-spacing:.08em}.chat-page-thread{height:min(62vh,620px);min-height:420px;max-height:none;padding:18px 17px 22px;background:radial-gradient(circle at 50% 0%,rgba(114,166,255,.035),transparent 33%),#0b1017;scroll-behavior:smooth}.chat-row{margin:6px 0}.chat-bubble{position:relative;box-shadow:0 6px 18px rgba(0,0,0,.12)}.chat-row.mine .chat-bubble{border-bottom-right-radius:6px}.chat-row.theirs .chat-bubble{border-bottom-left-radius:6px}.chat-meta-line{display:flex;justify-content:flex-end;align-items:center;gap:7px;margin-top:6px;color:#788496;font-size:9px}.chat-row.theirs .chat-meta-line{justify-content:flex-start}.chat-separator{display:flex;align-items:center;gap:9px;color:#687487;font-size:9px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;margin:16px 0}.chat-separator::before,.chat-separator::after{content:"";height:1px;background:#202a36;flex:1}.chat-new-message{position:absolute;right:18px;bottom:105px;z-index:5;border:1px solid #35567d;background:#17283a;color:#bcd9ff;border-radius:999px;padding:8px 11px;font-size:10px;font-weight:900;box-shadow:0 10px 30px rgba(0,0,0,.35);cursor:pointer}.chat-new-message[hidden]{display:none}.chat-modern-compose{padding:11px 13px 13px}.chat-compose-top{display:flex;align-items:center;gap:8px;margin-bottom:7px}.chat-compose-hint{color:#667487;font-size:9px;flex:1}.chat-char-count{color:#667487;font-size:9px;font-variant-numeric:tabular-nums}.chat-emoji-btn{border:1px solid #2d3948;background:#131c26;color:#dce4ee;border-radius:10px;width:31px;height:31px;padding:0;display:grid;place-items:center}.chat-input-wrap{position:relative}.chat-input-wrap textarea{padding:11px 46px 11px 12px;min-height:43px;max-height:150px;resize:none;overflow:auto}.chat-send-icon{position:absolute;right:7px;bottom:7px;width:34px;height:34px;border:0;border-radius:10px;background:#70a6ff;color:#07101a;font-weight:900}.chat-optional-row{margin-top:7px}.chat-optional-row input{width:100%;background:#090e14;border:1px solid #293441;color:var(--text);border-radius:10px;padding:8px 10px;outline:0;font-size:11px}.chat-compose-footer{display:flex;justify-content:space-between;align-items:center;gap:10px}.chat-compose-footer .notice{margin-top:6px}.chat-send-state{font-size:9px;color:#758091}.chat-send-state.sending{color:#f3d28a}.chat-send-state.sent{color:#9be8bc}.chat-send-state.error{color:#ffb1bd}.chat-inbox-item.unread-pulse{animation:chatInboxPulse 1s ease-in-out 2}.chat-inbox-item .chat-inbox-preview strong{color:#fff}.chat-inbox-unread-label{display:inline-flex;align-items:center;gap:5px;color:#9bc3ff;font-size:9px;font-weight:900;margin-top:5px}.chat-row.new-message .chat-bubble{animation:chatMessageIn .22s cubic-bezier(.2,.8,.2,1)}@keyframes chatInboxPulse{50%{transform:translateX(3px);border-color:#4c76a4}}@keyframes chatMessageIn{from{opacity:0;transform:translateY(5px) scale(.985)}to{opacity:1;transform:none}}@media(max-width:700px){.chat-page-thread{height:calc(100vh - 280px);min-height:330px;padding:13px 11px 18px}.chat-new-message{right:12px;bottom:112px}.chat-compose-hint{display:none}.chat-avatar{width:38px;height:38px}.chat-live-pill{padding:6px 8px}.chat-modern-compose{padding:9px}.chat-input-wrap textarea{font-size:16px}.chat-compose-footer .notice{font-size:9px}.admin-thread-scroll{height:calc(100vh - 450px);min-height:300px}}

.chat-message-toast{position:fixed;right:18px;top:88px;z-index:125;width:min(340px,calc(100vw - 28px));padding:11px 13px;border:1px solid #31567f;background:linear-gradient(135deg,#172536,#101820);border-radius:14px;box-shadow:0 16px 50px rgba(0,0,0,.42);opacity:0;transform:translateY(-8px) scale(.98);pointer-events:none;transition:.22s}.chat-message-toast.show{opacity:1;transform:none}.chat-message-toast strong{display:block;font-size:12px;color:#eef4fb}.chat-message-toast span{display:block;font-size:10px;color:#9ca8b8;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}@media(max-width:700px){.chat-message-toast{top:90px;right:10px;width:calc(100vw - 20px)}}
.pwa-tools{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:0 0 18px;padding:12px 13px;border:1px solid var(--line);background:#101720;border-radius:14px}.pwa-tools-left{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.pwa-tools button{border:1px solid var(--line);background:#18212b;color:#e7edf4;border-radius:10px;padding:9px 11px;font-size:11px;font-weight:900}.pwa-tools button.primary-tool{background:#70a6ff;color:#07101a;border-color:#70a6ff}.pwa-tools button:disabled{opacity:.55;cursor:not-allowed}.pwa-status{font-size:10px;color:#8e9aaa}.order-notification-toast{position:fixed;right:18px;bottom:18px;z-index:120;width:min(400px,calc(100vw - 28px));padding:0;overflow:hidden;border:1px solid #31567f;background:linear-gradient(135deg,#152131,#0f171f);color:#eef4fb;border-radius:18px;box-shadow:0 22px 70px rgba(0,0,0,.45);display:none;transform:translateY(14px) scale(.98);opacity:0}.order-notification-toast.show{display:block;animation:toastPop .28s cubic-bezier(.22,.8,.2,1) forwards}.order-notification-toast::before{content:"";display:block;height:3px;background:linear-gradient(90deg,#70a6ff,#51dc92)}.order-notification-inner{display:grid;grid-template-columns:42px 1fr auto;gap:11px;align-items:start;padding:13px}.order-notification-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:#1b2a3a;border:1px solid #33475e;font-size:20px;box-shadow:inset 0 0 18px rgba(114,166,255,.08)}.order-notification-copy strong{display:block;font-size:13px;letter-spacing:.01em}.order-notification-copy span{display:block;color:#aeb8c6;font-size:11px;margin-top:4px;line-height:1.45}.order-notification-copy a{display:inline-flex;margin-top:9px;color:#9bc3ff;font-size:10px;font-weight:900}.order-notification-close{width:28px;height:28px;border-radius:9px;border:1px solid #2e3c4c;background:#141d27;color:#aeb8c6;cursor:pointer;font-size:17px;line-height:1}.order-notification-close:hover{color:#fff;background:#1b2734}.order-notification-toast.pulse .order-notification-icon{animation:notifyPulse .8s ease-in-out infinite}.pwa-install-note{font-size:10px;color:#778394;line-height:1.45}@keyframes toastPop{0%{opacity:0;transform:translateY(14px) scale(.98)}100%{opacity:1;transform:translateY(0) scale(1)}}@keyframes notifyPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}@media(max-width:700px){.order-notification-toast{right:10px;bottom:12px;width:calc(100vw - 20px)}}
.modal-backdrop{position:fixed;z-index:90;inset:0;background:rgba(0,0,0,.72);display:none;align-items:flex-end;justify-content:center;padding:0}.modal-backdrop.open{display:flex}.modal{width:min(650px,100%);max-height:94vh;overflow:auto;background:#0d131b;border:1px solid var(--line);border-radius:21px 21px 0 0;padding:18px 15px 25px}.modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}.modal-head h2{margin:4px 0;font-size:24px}.modal-muted{color:var(--muted);margin:0;font-size:13px}.close{width:38px;height:38px;border:1px solid var(--line);background:var(--surface);color:#cbd4df;border-radius:10px;font-size:24px}
@media(max-width:980px){.shop-grid{grid-template-columns:repeat(2,1fr)}.product-layout{grid-template-columns:1fr;gap:18px}.product-art{margin-top:18px}.chat-admin-layout{grid-template-columns:1fr}.chat-inbox-list{max-height:260px}.chat-admin-thread .chat-compose{margin:0 -18px -18px}.chat-admin-thread .chat-thread{margin:0 -18px}}
@media(max-width:700px){.chat-shell{border-radius:16px}.chat-thread{min-height:300px;padding:12px}.chat-bubble{max-width:88%}.chat-compose-row{grid-template-columns:1fr}.chat-send{width:100%;min-width:0}.chat-admin-thread .chat-header{margin:-16px -15px 0}.chat-admin-thread .chat-thread{margin:0 -15px}.chat-admin-thread .chat-compose{margin:0 -15px -16px}.site-header{height:80px}.header-inner{padding:0 16px}.brand-logo{width:40px;height:40px}.brand-title{font-size:16px}.drawer{top:80px}.menu-backdrop{inset:80px 0 0}main{padding:23px 14px 49px}.content-head{margin-bottom:18px}.content-head h1{font-size:34px}.content-head p{font-size:14px}.shop-grid{grid-template-columns:1fr;gap:14px}.product-card{border-radius:19px}.product-info{padding:16px 14px 15px}.product-name{font-size:21px}.product-price{font-size:20px}.view-btn{padding:12px;font-size:17px}.product-meta h1{font-size:30px}.product-layout{gap:8px}.product-art{margin-top:17px;border-radius:15px}.price-panel{padding:16px;border-radius:17px}.price-panel h2{font-size:24px}.price-item{padding:12px 11px}.price-right{gap:8px}.amount{font-size:14px}.buy{padding:9px 10px}.details-panel{padding:16px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.auth-shell{padding:18px 13px;min-height:calc(100vh - 80px);align-items:center}.auth-card{padding:19px 15px;border-radius:18px}.auth-card h1{font-size:26px}.admin-panel{grid-template-columns:1fr}.drawer-inner{padding:18px 15px}}
@media(max-width:390px){.brand-title small{display:none}.menu{padding:9px 11px}.menu span{font-size:14px}.drawer-title{font-size:21px}.product-name{font-size:20px}.price-item .amount{font-size:13px}.auth-card{padding:17px 13px}}
</style>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php?page=shop"><img class="brand-logo" src="assets/kaelhax-logo.png" alt="<?= e($siteContent['site_brand']) ?>"><div class="brand-title"><?= e($siteContent['site_name']) ?><small><?= e($siteContent['site_brand']) ?></small></div></a>
    <button class="menu" onclick="setMenu(true)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M4 7h16M4 12h16M4 17h16"/></svg><span>Menu</span></button>
  </div>
</header>
<div id="menuBackdrop" class="menu-backdrop" onclick="setMenu(false)"></div>
<aside id="drawer" class="drawer"><div class="drawer-inner">
  <div class="drawer-top"><div><div class="drawer-kicker">Navigation</div><div class="drawer-title">Project Market</div></div><button class="drawer-close" onclick="setMenu(false)">×</button></div>
  <div class="menu-group"><div class="group-title">Explore</div><?= menu_item('index.php?page=shop','Shop','▣','shop') ?><?= menu_item('index.php?page=preview','Preview','▧','preview') ?><?= menu_item('index.php?page=promos','Promos','◇','promos') ?></div>
  <div class="menu-group"><div class="group-title">Support</div><?= menu_item('index.php?page=concerns','Concerns','◌','concerns') ?><?= menu_item('index.php?page=terms','Terms','▤','terms') ?></div>
  <div class="menu-group"><div class="group-title">Account</div><?= menu_item('index.php?page=account','Account','♙','account') ?><?= menu_item('index.php?page=my-orders','My Orders','◫','my-orders') ?><?= menu_item('index.php?page=messages','Messages','✉','messages') ?></div>
  <div class="menu-group"><div class="group-title">Administration</div><?= menu_item('index.php?page=admin','Administrator Dashboard','♢','admin') ?></div>
  <div class="menu-group"><div class="group-title">Display</div><a class="menu-link" href="#" onclick="toggleDesktopPreview();return false"><span class="menu-icon">▱</span><span id="displayLabel">Desktop Mode</span></a></div>
  <div class="drawer-note">KAELHAX • Project Market<br>Mobile-first buyer storefront</div>
</div></aside>

<main>
<?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

<?php if ($page === 'product' && $product): ?>
  <button class="back-link" onclick="location.href='index.php?page=shop'">← Back to Shop</button>
  <div class="product-layout">
    <section class="product-meta">
      <div class="kicker"><?= e($product['category']) ?></div><h1><?= e($product['name']) ?></h1>
      <span class="available">Available</span><?php if (!empty($product['promo'])): ?><span class="badge promo" style="margin-left:5px">Promo</span><?php endif; ?>
      <?php if (!empty($product['features'])): ?><div class="feature-pills"><?php foreach ($product['features'] as $feature): ?><span class="feature-pill">🟢 <?= e($feature) ?></span><?php endforeach; ?></div><?php endif; ?>
      <div class="product-art"><img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"></div>
      <div class="details-panel"><h2><?= e($product['details_title']) ?></h2><?php foreach ($product['details'] as $detail): ?><div class="detail-line">➡️ <?= e($detail) ?></div><?php endforeach; ?></div>
    </section>
    <aside class="price-panel"><h2><?= e($product['price_title']) ?></h2><div class="price-list">
      <?php foreach ($product['tiers'] as $i => $tier): ?><div class="price-item"><div><div class="price-name"><?= e($tier[0]) ?></div><?php if (!empty($tier[2])): ?><div class="stock">(<?= e($tier[2]) ?>)</div><?php endif; ?><div class="price-cur">PHP</div></div><div class="price-right"><div class="amount"><?= money($tier[1]) ?></div><button class="buy" type="button" onclick="openOrder('<?= e($product['slug']) ?>',<?= $i ?>)">Buy Now</button></div></div><?php endforeach; ?>
    </div></aside>
  </div>

<?php elseif ($page === 'preview'): ?>
  <section class="content-head"><div class="kicker">Showcase</div><h1><?= e($siteContent['preview_title']) ?></h1><p><?= e($siteContent['preview_description']) ?></p></section>
  <section class="shop-grid"><?php foreach ($products as $p): ?><article class="product-card"><div class="product-image"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div><div class="product-info"><div class="badges"><span class="badge">Available</span><?php if($p['promo']): ?><span class="badge promo">Promo</span><?php endif; ?></div><h3 class="product-name"><?= e($p['name']) ?></h3><p class="product-price">From <?= money($p['tiers'][0][1]) ?></p><button class="view-btn" onclick="location.href='index.php?page=product&slug=<?= e($p['slug']) ?>'">View Product</button></div></article><?php endforeach; ?></section>

<?php elseif ($page === 'promos'): ?>
  <section class="content-head"><div class="kicker">Explore</div><h1><?= e($siteContent['promos_title']) ?></h1><p><?= e($siteContent['promos_description']) ?></p></section>
  <section class="shop-grid"><?php foreach ($products as $p): if(!$p['promo']) continue; ?><article class="product-card"><div class="product-image"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div><div class="product-info"><div class="badges"><span class="badge">Available</span><span class="badge promo">Promo</span></div><h3 class="product-name"><?= e($p['name']) ?></h3><p class="product-price">From <?= money($p['tiers'][0][1]) ?></p><button class="view-btn" onclick="location.href='index.php?page=product&slug=<?= e($p['slug']) ?>'">View Product</button></div></article><?php endforeach; ?></section>

<?php elseif ($page === 'concerns'): ?>
  <section class="content-head"><div class="kicker">Support</div><h1><?= e($siteContent['concerns_title']) ?></h1><p><?= e($siteContent['concerns_description']) ?></p></section>
  <div class="info-box"><form method="post"><input type="hidden" name="action" value="send_concern"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="form-grid"><div class="field"><label>Telegram Username</label><input name="telegram_username" placeholder="@username" required></div><div class="field"><label>Order ID</label><input name="order_id" placeholder="Optional"></div><div class="field full"><label>Concern</label><textarea name="message" placeholder="Describe your concern..." required></textarea></div></div><button class="primary">Send Concern</button><div class="notice">Your Telegram bot token remains server-side.</div></form></div>

<?php elseif ($page === 'terms'): ?>
  <section class="content-head"><div class="kicker">Information</div><h1><?= e($siteContent['terms_title']) ?></h1><p><?= e($siteContent['terms_description']) ?></p></section>
  <div class="info-box"><div class="info-row"><span>Order details</span><strong>Provide accurate information</strong></div><div class="info-row"><span>Order channel</span><strong>Official Telegram channel</strong></div><div class="info-row"><span>Support</span><strong>Use Concerns</strong></div><div class="info-row"><span>Administration</span><strong>Orders are manually verified</strong></div></div>

<?php elseif ($page === 'my-orders'): ?>
  <?php $allOrders = load_orders(); $visibleOrders = array_values(array_filter($allOrders, 'buyer_can_see_order')); usort($visibleOrders, function($a,$b){ return strcmp((string)($b['created_at'] ?? ''),(string)($a['created_at'] ?? '')); }); ?>
  <section class="content-head"><div class="kicker">Account</div><h1><?= e($siteContent['orders_title']) ?></h1><p><?= e($siteContent['orders_description']) ?></p></section>
  <?php if (!$visibleOrders): ?>
    <div class="info-box"><strong>No orders yet.</strong><div class="notice">Choose a package from the Shop, scan the payment QR, upload your receipt, and submit your order.</div></div>
  <?php else: ?>
    <?php foreach ($visibleOrders as $o): ?>
      <article class="order-card">
        <div class="order-top"><div><div class="order-id"><?= e($o['id']) ?></div><div class="order-meta"><?= e($o['product']) ?><br><?= e($o['duration']) ?> • <?= e($o['amount']) ?><br>Submitted <?= e(date('M d, Y g:i A', strtotime($o['created_at'] ?? 'now'))) ?></div></div><span class="status-pill status-<?= e($o['status'] ?? 'pending') ?>"><?= e($o['status'] ?? 'pending') ?></span></div>
        <div class="order-meta">Payment: <?= e($o['payment_method'] ?? '—') ?><?php if (!empty($o['telegram_username'])): ?><br>Telegram: <?= e($o['telegram_username']) ?><?php endif; ?></div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

<?php elseif ($page === 'messages'): ?>
  <?php
    $chatUsername = chat_identity($_SESSION['buyer_username'] ?? '');
    $buyerConversation = $chatUsername !== '' ? chat_get_conversation($chatUsername) : null;
    chat_mark_read($chatUsername, 'buyer');
    $buyerConversation = $chatUsername !== '' ? chat_get_conversation($chatUsername) : $buyerConversation;
  ?>
  <section class="content-head">
    <div class="kicker">Support</div>
    <h1>Messages</h1>
    <p>Chat directly with the administrator about your account, orders, or general support.</p>
  </section>
  <section class="chat-shell chat-page">
    <div class="chat-header chat-page-header">
      <div class="chat-contact">
        <div class="chat-avatar">A</div>
        <div><strong>Administrator Support</strong><span><span class="chat-status-dot"></span> Support channel</span></div>
      </div>
      <span class="chat-live-pill" id="buyerChatLiveStatus">LIVE</span>
    </div>
    <div class="chat-thread chat-page-thread" id="buyerChatThread" data-chat-viewer="buyer">
      <?php if (!$buyerConversation || empty($buyerConversation['messages'])): ?>
        <div class="chat-empty">No messages yet.<br>Send a message below to start a conversation.</div>
      <?php else: ?>
        <?php foreach ($buyerConversation['messages'] as $message): ?>
          <div class="chat-row <?= ($message['sender_type'] ?? '') === 'buyer' ? 'mine' : 'theirs' ?>" data-message-id="<?= e($message['id'] ?? '') ?>">
            <div class="chat-bubble">
              <div class="chat-author"><?= e($message['sender_name'] ?? (($message['sender_type'] ?? '') === 'admin' ? 'Administrator' : 'You')) ?></div>
              <div class="chat-text"><?= chat_render_message($message) ?></div>
              <?php if (!empty($message['link'])): ?>
                <a class="chat-link-btn" href="<?= e($message['link']) ?>" target="_blank" rel="noopener noreferrer">🔗 Open Link</a>
              <?php endif; ?>
              <div class="chat-meta-line"><span><?= e(date('g:i A', strtotime($message['created_at'] ?? 'now'))) ?></span><?php if (($message['sender_type'] ?? '') === 'buyer'): ?><?php if (!empty($message['read_by_admin'])): ?><span>• Seen</span><?php else: ?><span>• Sent</span><?php endif; ?><?php elseif (($message['sender_type'] ?? '') === 'admin'): ?><?php if (!empty($message['read_by_buyer'])): ?><span>• Seen</span><?php else: ?><span>• Sent</span><?php endif; ?><?php endif; ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="chat-new-message" id="buyerChatNewMessage" hidden>↓ New message</div>
    <form method="post" class="chat-compose chat-modern-compose" id="buyerChatForm">
      <input type="hidden" name="action" value="send_message">
      <input type="hidden" name="chat_role" value="buyer">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="chat-compose-top">
        <button class="chat-emoji-btn" type="button" data-emoji-button="buyerChatForm" aria-label="Add emoji">😊</button>
        <span class="chat-compose-hint">Enter to send • Shift+Enter for a new line</span>
        <span class="chat-char-count" data-char-count="buyerChatForm">0 / 3000</span>
      </div>
      <div class="chat-input-wrap">
        <textarea name="message" rows="1" maxlength="3000" placeholder="Write a message..." autocomplete="off" required></textarea>
        <button class="chat-send-icon" type="submit" aria-label="Send message">➤</button>
      </div>
      <div class="chat-optional-row">
        <input name="link" type="url" maxlength="2000" placeholder="Optional link (https://...)" inputmode="url">
      </div>
      <div class="chat-compose-footer"><span class="notice">Your conversation is saved to the support inbox.</span><span class="chat-send-state" data-send-state="buyerChatForm">Ready</span></div>
    </form>
  </section>

<?php elseif ($page === 'account'): ?>
  <?php if (is_user()): ?>
    <section class="content-head"><div class="kicker">Account</div><h1><?= e($siteContent['account_title']) ?></h1><p><?= e($siteContent['account_description']) ?></p></section>
    <div class="info-box"><div class="info-row"><span>Username</span><strong>@<?= e($_SESSION['buyer_username']) ?></strong></div><div class="info-row"><span>Display Name</span><strong><?= e($_SESSION['buyer_name']) ?></strong></div><div class="info-row"><span>Status</span><strong>Signed in</strong></div><form method="post" class="hero-actions"><input type="hidden" name="action" value="logout"><input type="hidden" name="type" value="buyer"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="logout-btn">Log Out</button></form></div>
    <?php pro_render_buyer_dashboard(load_orders()); ?>
  <?php else: ?>
    <div class="auth-shell"><div class="auth-card">
      <img class="auth-logo" src="assets/kaelhax-logo.png" alt="KAELHAX"><h1>Buyer Account</h1><p>Login or create an account for your Project Market purchases.</p>
      <div class="auth-tabs"><button id="tabLogin" class="active" onclick="switchAuth('login')">Login</button><?php if (!empty($siteSettings['registration_enabled'])): ?><button id="tabRegister" onclick="switchAuth('register')">Register</button><?php endif; ?></div>
      <form id="loginForm" class="auth-form" method="post"><input type="hidden" name="action" value="buyer_login"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Username</label><input name="username" required autocomplete="username"></div><div class="field" style="margin-top:11px"><label>Password</label<div class="password-field-wrap"><input id="loginPassword" name="password" type="password" required autocomplete="current-password"><button type="button" class="password-toggle" data-password-toggle="loginPassword">Show</button></div></div><button class="primary">Login</button></form>
      <form id="registerForm" class="auth-form" method="post" style="display:<?= !empty($siteSettings['registration_enabled']) ? 'none' : 'none' ?>"><input type="hidden" name="action" value="buyer_register"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Username</label><input name="username" pattern="[A-Za-z0-9_\.]{3,32}" required></div><div class="field" style="margin-top:11px"><label>Display Name</label><input name="display_name" required></div><div class="field" style="margin-top:11px"><label>Password</label<div class="password-field-wrap"><input id="registerPassword" name="password" type="password" minlength="6" required><button type="button" class="password-toggle" data-password-toggle="registerPassword">Show</button></div></div><button class="primary">Create Account</button></form>
    </div></div>
  <?php endif; ?>

<?php elseif ($page === 'admin'): ?>
  <?php if (is_admin()): ?>
    <?php
      $adminOrders = load_orders();
      usort($adminOrders, function($a,$b){ return strcmp((string)($b['created_at'] ?? ''),(string)($a['created_at'] ?? '')); });
      $pendingOrders = array_values(array_filter($adminOrders, function($o){ return ($o['status'] ?? 'pending') === 'pending'; }));
      $acceptedOrders = array_values(array_filter($adminOrders, function($o){ return ($o['status'] ?? '') === 'accepted'; }));
      $ignoredOrders = array_values(array_filter($adminOrders, function($o){ return ($o['status'] ?? '') === 'ignored'; }));
      $buyers = load_users();
      $tab = $_GET['tab'] ?? 'dashboard';
      $editingSlug = $_GET['edit'] ?? '';
      $editingProduct = ($editingSlug !== '' && isset($products[$editingSlug])) ? $products[$editingSlug] : null;
    ?>
    <section class="content-head"><div class="kicker">Administration</div><h1>Administrator Dashboard</h1><p>Manage website content, products, pricing, buyers, promotions, orders, Telegram, and settings.</p></section>
    <nav class="admin-nav">
      <?php foreach ([['dashboard','Dashboard'],['content','Website Content'],['products','Products'],['orders','Orders'],['buyers','Buyers'],['messages','Messages'],['promos','Promotions'],['telegram','Telegram'],['settings','Settings']] as $nav): ?>
        <a class="<?= $tab === $nav[0] ? 'active' : '' ?>" href="index.php?page=admin&tab=<?= e($nav[0]) ?>"><?= e($nav[1]) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="pwa-tools">
      <div class="pwa-tools-left">
        <button type="button" id="enableAdminNotifications" class="primary-tool">🔔 Enable Sound & Vibration</button>
        <button type="button" id="installPwaButton" style="display:none">📲 Install App</button>
      </div>
      <span id="adminNotificationStatus" class="pwa-status">Tap Enable Sound & Vibration once to unlock order alerts.</span>
    </div>

    <?php if ($tab === 'dashboard'): ?>
      <div class="admin-panel">
        <div class="stat"><strong><?= count($products) ?></strong><span>Catalog Products</span></div>
        <div class="stat"><strong><?= count($buyers) ?></strong><span>Registered Buyers</span></div>
        <div class="stat"><strong><?= count($pendingOrders) ?></strong><span>Pending Orders</span></div>
        <div class="stat"><strong><?= count($acceptedOrders) ?></strong><span>Accepted Orders</span></div>
        <div class="stat"><strong><?= count($ignoredOrders) ?></strong><span>Ignored Orders</span></div>
        <div class="stat"><strong><?= telegram_configured() ? 'READY' : 'NOT SET' ?></strong><span>Telegram Status</span></div>
      </div>

      <div class="admin-grid" style="margin-top:14px">
        <div class="admin-card"><h2>Quick Actions</h2><p>Jump directly to the most-used administrator controls.</p><div class="admin-actions"><a class="small-btn primary" href="index.php?page=admin&tab=content">Edit Website Text</a><a class="small-btn" href="index.php?page=admin&tab=products">Manage Products</a><a class="small-btn" href="index.php?page=admin&tab=orders">Review Orders</a><a class="small-btn" href="index.php?page=admin&tab=buyers">Manage Buyers</a></div></div>
        <div class="admin-card"><h2>System Status</h2><div class="info-row"><span>Logged in as</span><strong><?= e($_SESSION['admin_username']) ?></strong></div><div class="info-row"><span>Shop</span><strong><?= !empty($siteSettings['shop_enabled']) ? 'Enabled' : 'Disabled' ?></strong></div><div class="info-row"><span>Registration</span><strong><?= !empty($siteSettings['registration_enabled']) ? 'Enabled' : 'Disabled' ?></strong></div><div class="info-row"><span>Payment QR</span><strong><?= e($siteSettings['payment_qr']) ?></strong></div></div>
      </div>

      <section class="content-head" style="margin-top:28px"><div class="kicker">Order Review</div><h2 style="font-size:25px;margin:8px 0 0">Pending Orders</h2></section>
      <?php if (!$pendingOrders): ?>
        <div class="info-box"><strong>No pending orders.</strong><div class="notice">New submitted orders will appear here until you Accept or Ignore them.</div></div>
      <?php else: ?>
        <?php foreach ($pendingOrders as $o): ?>
          <article class="order-card">
            <div class="order-top"><div><div class="order-id"><?= e($o['id']) ?></div><div class="order-meta"><?= e($o['product']) ?><br><?= e($o['duration']) ?> • <?= e($o['amount']) ?><br>Buyer: <?= e($o['buyer_name']) ?> • <?= e($o['telegram_username']) ?></div></div><span class="status-pill status-pending">PENDING</span></div>
            <?php if (!empty($o['uid'])): ?><div class="order-meta">UID / Account: <?= e($o['uid']) ?></div><?php endif; ?>
            <?php if (!empty($o['note'])): ?><div class="order-meta">Note: <?= e($o['note']) ?></div><?php endif; ?>
            <div class="order-meta">Payment: <?= e($o['payment_method']) ?><br>Receipt: <?= e($o['receipt_filename'] ?? 'Uploaded') ?></div>
            <?php
              $receiptUrl = trim((string)($o['receipt_url'] ?? ''));
              $legacyReceipt = trim((string)($o['receipt'] ?? ''));
              if ($receiptUrl === '' && preg_match('/^https?:\/\//i', $legacyReceipt)) {
                  $receiptUrl = $legacyReceipt;
              }
              $isCloudinaryReceipt = $receiptUrl !== '' && preg_match('/^https?:\/\//i', $receiptUrl);
            ?>
            <?php if ($isCloudinaryReceipt): ?>
              <div class="receipt-section">
                <div class="receipt-title">PAYMENT RECEIPT</div>
                <?php if (strpos((string)($o['receipt_mime'] ?? ''), 'image/') === 0): ?>
                  <img class="receipt-view" src="<?= e($receiptUrl) ?>" alt="Payment receipt for <?= e($o['id']) ?>" loading="lazy">
                <?php elseif (($o['receipt_mime'] ?? '') === 'application/pdf'): ?>
                  <iframe class="receipt-frame" src="<?= e($receiptUrl) ?>" title="Payment receipt PDF"></iframe>
                <?php else: ?>
                  <div class="notice">Receipt uploaded successfully. Use the button below to open it.</div>
                <?php endif; ?>
                <a class="receipt-link" href="<?= e($receiptUrl) ?>" target="_blank" rel="noopener">🔍 View Full Receipt</a>
              </div>
            <?php elseif ($legacyReceipt !== ''): ?>
              <div class="receipt-section">
                <div class="receipt-title">PAYMENT RECEIPT</div>
                <div class="notice">This order uses the old local receipt path. Please have the buyer submit the receipt again so it can be stored in Cloudinary.</div>
              </div>
            <?php else: ?>
              <div class="notice">No payment receipt was attached to this order.</div>
            <?php endif; ?>
            <div class="admin-order-actions">
              <form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="accepted"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="accept">✓ Accept</button></form>
              <form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="ignored"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="ignore">✕ Ignore</button></form>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>

          <?php pro_render_admin_dashboard($adminOrders, $buyers, $products); ?>

<?php elseif ($tab === 'content'): ?>
      <form method="post" class="admin-card"><input type="hidden" name="action" value="save_content"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><h2>Website Content Editor</h2><p>Edit the visible text of the storefront without opening the PHP code.</p>
        <div class="admin-grid">
          <?php foreach ([
            'site_name'=>'Site Name','site_brand'=>'Brand','shop_title'=>'Shop Title','shop_description'=>'Shop Description','preview_title'=>'Preview Title','preview_description'=>'Preview Description','promos_title'=>'Promos Title','promos_description'=>'Promos Description','concerns_title'=>'Concerns Title','concerns_description'=>'Concerns Description','terms_title'=>'Terms Title','terms_description'=>'Terms Description','account_title'=>'Account Title','account_description'=>'Account Description','orders_title'=>'My Orders Title','orders_description'=>'My Orders Description','footer_text'=>'Footer Text','payment_title'=>'Payment Box Title','payment_description'=>'Payment Description','payment_note'=>'Payment Note'
          ] as $field=>$label): ?>
            <div class="field"><label><?= e($label) ?></label><?php if (strpos($field,'description') !== false || strpos($field,'note') !== false || $field === 'footer_text'): ?><textarea name="<?= e($field) ?>"><?= e($siteContent[$field] ?? '') ?></textarea><?php else: ?><input name="<?= e($field) ?>" value="<?= e($siteContent[$field] ?? '') ?>"><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
        <button class="primary" type="submit">Save Website Content</button>
      </form>

    <?php elseif ($tab === 'products'): ?>
      <section class="admin-card">
        <h2><?= $editingProduct ? 'Edit Product' : 'Add Product' ?></h2><p>Create or edit catalog products and their pricing tiers. Existing buyer-side styling remains unchanged.</p>
        <?php $ep = $editingProduct ?: ['slug'=>'','category'=>'General','name'=>'','image'=>'assets/kaelhax-logo.png','promo'=>false,'features'=>[],'details_title'=>'DETAILS','details'=>[],'price_title'=>'PRICELIST','tiers'=>[['',0,null],['',0,null],['',0,null],['',0,null]]]; ?>
        <form method="post"><input type="hidden" name="action" value="save_product"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="admin-grid">
            <div class="field"><label>Slug</label><input name="slug" value="<?= e($ep['slug']) ?>" required></div>
            <div class="field"><label>Category</label><input name="category" value="<?= e($ep['category']) ?>"></div>
            <div class="field"><label>Product Name</label><input name="name" value="<?= e($ep['name']) ?>" required></div>
            <div class="field"><label>Image Path</label><input name="image" value="<?= e($ep['image']) ?>"></div>
            <div class="field"><label>Details Title</label><input name="details_title" value="<?= e($ep['details_title']) ?>"></div>
            <div class="field"><label>Price Title</label><input name="price_title" value="<?= e($ep['price_title']) ?>"></div>
            <div class="field full"><label>Features (comma-separated)</label><input name="features" value="<?= e(implode(', ', $ep['features'] ?? [])) ?>"></div>
            <div class="field full"><label>Details (one per line)</label><textarea name="details"><?= e(implode("\n", $ep['details'] ?? [])) ?></textarea></div>
            <div class="field full"><label class="check"><input type="checkbox" name="promo" value="1" <?= !empty($ep['promo'])?'checked':'' ?>> Mark as Promotion</label></div>
          </div>
          <div class="field"><label>Pricing Tiers</label>
            <?php for($i=0;$i<4;$i++): $tier=$ep['tiers'][$i] ?? ['','','']; ?>
              <div class="tier-grid" style="margin-top:8px"><input name="tier_name[<?= $i ?>]" placeholder="Tier name" value="<?= e($tier[0] ?? '') ?>"><input name="tier_price[<?= $i ?>]" type="number" step="0.01" min="0" placeholder="Price" value="<?= e($tier[1] ?? '') ?>"><input name="tier_stock[<?= $i ?>]" placeholder="Stock (optional)" value="<?= e($tier[2] ?? '') ?>"></div>
            <?php endfor; ?>
          </div>
          <div class="admin-actions"><button class="small-btn primary" type="submit">Save Product</button><?php if($editingProduct): ?><a class="small-btn" href="index.php?page=admin&tab=products">New Product</a><?php endif; ?></div>
        </form>
      </section>
      <section class="admin-card" style="margin-top:14px"><h2>Catalog Products</h2><?php foreach($products as $p): ?><div class="product-admin-card"><div class="product-admin-top"><div><h3><?= e($p['name']) ?></h3><div class="muted-block"><?= e($p['slug']) ?> • <?= e($p['category']) ?> • <?= !empty($p['promo'])?'Promo':'Regular' ?></div></div><div class="admin-actions"><a class="small-btn" href="index.php?page=admin&tab=products&edit=<?= e($p['slug']) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this product?');"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="slug" value="<?= e($p['slug']) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="small-btn danger" type="submit">Delete</button></form></div></div></div><?php endforeach; ?></section>

    <?php elseif ($tab === 'orders'): ?>
      <section class="content-head"><div class="kicker">Orders</div><h2 style="font-size:25px;margin:8px 0 0">Pending Orders</h2></section>
      <?php if(!$pendingOrders): ?><div class="info-box">No pending orders.</div><?php else: foreach($pendingOrders as $o): ?><article class="order-card"><div class="order-top"><div><div class="order-id"><?= e($o['id']) ?></div><div class="order-meta"><?= e($o['product']) ?><br><?= e($o['duration']) ?> • <?= e($o['amount']) ?><br>Buyer: <?= e($o['buyer_name']) ?> • <?= e($o['telegram_username']) ?></div></div><span class="status-pill status-pending">PENDING</span></div><div class="order-meta">Payment: <?= e($o['payment_method']) ?><?php if(!empty($o['uid'])):?><br>UID: <?= e($o['uid']) ?><?php endif;?></div>
      <?php
        $receiptUrl = trim((string)($o['receipt_url'] ?? ''));
        $legacyReceipt = trim((string)($o['receipt'] ?? ''));
        if ($receiptUrl === '' && preg_match('/^https?:\/\//i', $legacyReceipt)) {
            $receiptUrl = $legacyReceipt;
        }
        $isCloudinaryReceipt = $receiptUrl !== '' && preg_match('/^https?:\/\//i', $receiptUrl);
      ?>
      <?php if ($isCloudinaryReceipt): ?>
        <div class="receipt-section">
          <div class="receipt-title">PAYMENT RECEIPT</div>
          <?php if (strpos((string)($o['receipt_mime'] ?? ''), 'image/') === 0): ?>
            <img class="receipt-view" src="<?= e($receiptUrl) ?>" alt="Payment receipt for <?= e($o['id']) ?>" loading="lazy">
          <?php elseif (($o['receipt_mime'] ?? '') === 'application/pdf'): ?>
            <iframe class="receipt-frame" src="<?= e($receiptUrl) ?>" title="Payment receipt PDF"></iframe>
          <?php else: ?>
            <div class="notice">Receipt uploaded successfully. Use the button below to open it.</div>
          <?php endif; ?>
          <a class="receipt-link" href="<?= e($receiptUrl) ?>" target="_blank" rel="noopener">🔍 View Full Receipt</a>
        </div>
      <?php elseif ($legacyReceipt !== ''): ?>
        <div class="receipt-section">
          <div class="receipt-title">PAYMENT RECEIPT</div>
          <div class="notice">This order uses the old local receipt path. Please have the buyer submit the receipt again so it can be stored in Cloudinary.</div>
        </div>
      <?php else: ?>
        <div class="notice">No payment receipt was attached to this order.</div>
      <?php endif; ?>
      <div class="admin-order-actions"><form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="accepted"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="accept">✓ Accept</button></form><form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="ignored"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="ignore">✕ Ignore</button></form></div></article><?php endforeach; endif; ?>
      <section class="content-head" style="margin-top:28px"><div class="kicker">History</div><h2 style="font-size:25px;margin:8px 0 0">Order History</h2></section>
      <div class="pro-card" style="margin-top:14px">
        <div class="pro-card-head">
          <div><h3>Order History</h3><span>Search, filter, and move accepted orders through processing to completion.</span></div>
          <div class="pro-tools"><input type="search" class="pro-search" data-pro-history-search placeholder="Search order, buyer, product..." aria-label="Search order history"><select class="pro-filter" data-pro-history-filter aria-label="Filter order history"><option value="all">All statuses</option><option value="pending">Pending</option><option value="accepted">Accepted</option><option value="processing">Processing</option><option value="completed">Completed</option><option value="ignored">Ignored</option></select></div>
        </div>
        <?php if(!$adminOrders): ?>
          <div class="info-box">No orders have been submitted yet.</div>
        <?php else: ?>
          <div class="table-wrap"><table class="admin-table" data-pro-history-table><thead><tr><th>Order</th><th>Buyer</th><th>Product</th><th>Amount</th><th>Status</th><th>Date</th><th>Next Step</th></tr></thead><tbody>
          <?php foreach(array_slice($adminOrders,0,80) as $o): ?>
            <?php $historyStatus=strtolower((string)($o['status']??'pending')); ?>
            <tr data-pro-history-row data-status="<?= e($historyStatus) ?>" data-search="<?= e(strtolower(($o['id']??'').' '.($o['buyer_name']??'').' '.($o['product']??''))) ?>">
              <td><strong><?= e($o['id']) ?></strong></td>
              <td><?= e($o['buyer_name']??'—') ?><?php if(!empty($o['buyer_username'])): ?><small class="muted-block">@<?= e($o['buyer_username']) ?></small><?php endif; ?></td>
              <td><?= e($o['product']??'—') ?></td>
              <td><?= e($o['amount']??'—') ?></td>
              <td><span class="status-pill status-<?= e($historyStatus) ?>"><?= e(pro_status_meta($historyStatus)['label']) ?></span></td>
              <td><?= e(!empty($o['created_at']) ? date('M d, Y g:i A',strtotime($o['created_at'])) : '—') ?></td>
              <td>
                <?php if($historyStatus==='accepted'): ?>
                  <form method="post" class="pro-inline-form"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="processing"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="small-btn" type="submit">Mark Processing</button></form>
                <?php elseif($historyStatus==='processing'): ?>
                  <form method="post" class="pro-inline-form"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="completed"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="small-btn primary" type="submit">Mark Completed</button></form>
                <?php elseif($historyStatus==='completed'): ?><span class="notice">Done</span>
                <?php elseif($historyStatus==='ignored'): ?><span class="notice">Ignored</span>
                <?php else: ?><span class="notice">Awaiting review</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </div>


    <?php elseif ($tab === 'buyers'): ?>
      <section class="admin-card"><h2>Buyer Management</h2><p>Review registered buyer accounts and enable or disable access.</p><div class="table-wrap"><table class="admin-table"><thead><tr><th>Username</th><th>Display Name</th><th>Registered</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($buyers as $b): $bst=($b['status']??'active'); ?><tr><td>@<?= e($b['username']) ?></td><td><?= e($b['display_name']) ?></td><td><?= e(date('M d, Y',strtotime($b['created_at']??'now'))) ?></td><td><span class="<?= $bst==='active'?'status-active':'status-disabled' ?>"><?= e(strtoupper($bst)) ?></span></td><td><form method="post"><input type="hidden" name="action" value="update_buyer_status"><input type="hidden" name="username" value="<?= e($b['username']) ?>"><input type="hidden" name="status" value="<?= $bst==='active'?'disabled':'active' ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="small-btn" type="submit"><?= $bst==='active'?'Disable':'Enable' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></section>

    <?php elseif ($tab === 'messages'): ?>
      <?php
        $conversations = chat_list_conversations();
        $selectedChatUser = chat_identity($_GET['user'] ?? '');
        if ($selectedChatUser === '' && !empty($conversations[0]['username'])) {
            $selectedChatUser = chat_identity($conversations[0]['username']);
        }
        $selectedConversation = $selectedChatUser !== '' ? chat_get_conversation($selectedChatUser) : null;
        if ($selectedChatUser !== '') {
            chat_mark_read($selectedChatUser, 'admin');
            $selectedConversation = chat_get_conversation($selectedChatUser);
        }
      ?>
      <div class="chat-admin-layout">
        <section class="admin-card chat-inbox">
          <h2>Message Inbox</h2>
          <p>Buyer conversations are stored persistently in Redis when Upstash is configured.</p>
          <?php if (!$conversations): ?>
            <div class="info-box"><strong>No conversations yet.</strong><div class="notice">Buyer messages will appear here automatically.</div></div>
          <?php else: ?>
            <div class="chat-inbox-list" id="adminChatInbox">
              <?php foreach ($conversations as $conversation): ?>
                <?php
                  $cu = chat_identity($conversation['username'] ?? '');
                  $messages = is_array($conversation['messages'] ?? null) ? $conversation['messages'] : [];
                  $last = !empty($messages) ? $messages[count($messages)-1] : [];
                  $unread = chat_unread_count($conversation, 'admin');
                ?>
                <a class="chat-inbox-item <?= $cu === $selectedChatUser ? 'active' : '' ?>" data-chat-user="<?= e($cu) ?>" href="index.php?page=admin&tab=messages&user=<?= rawurlencode($cu) ?>">
                  <div class="chat-inbox-top"><strong>@<?= e($cu) ?></strong><?php if ($unread): ?><span class="chat-unread"><?= e($unread) ?></span><?php endif; ?></div>
                  <div class="chat-inbox-preview"><?= e((string)($last['message'] ?? 'No messages')) ?></div>
                  <div class="chat-inbox-time"><?= !empty($last['created_at']) ? e(date('M d, Y g:i A', strtotime($last['created_at']))) : '—' ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="admin-card chat-admin-thread">
          <?php if (!$selectedConversation): ?>
            <h2>Select a conversation</h2>
            <p>Choose a buyer from the inbox to view and reply to the conversation.</p>
          <?php else: ?>
            <div class="chat-header admin-thread-header">
              <div><strong>@<?= e($selectedChatUser) ?></strong><span><span class="chat-status-dot"></span> Buyer support conversation</span></div>
              <a class="small-btn" href="index.php?page=admin&tab=orders">View Orders</a>
            </div>
            <div class="chat-thread admin-thread-scroll" id="adminChatThread" data-chat-viewer="admin" data-chat-user="<?= e($selectedChatUser) ?>">
              <?php if (empty($selectedConversation['messages'])): ?>
                <div class="chat-empty">No messages in this conversation.</div>
              <?php else: ?>
                <?php foreach ($selectedConversation['messages'] as $message): ?>
                  <div class="chat-row <?= ($message['sender_type'] ?? '') === 'admin' ? 'mine' : 'theirs' ?>" data-message-id="<?= e($message['id'] ?? '') ?>">
                    <div class="chat-bubble">
                      <div class="chat-author"><?= e($message['sender_name'] ?? 'User') ?></div>
                      <div class="chat-text"><?= chat_render_message($message) ?></div>
                      <?php if (!empty($message['link'])): ?>
                        <a class="chat-link-btn" href="<?= e($message['link']) ?>" target="_blank" rel="noopener noreferrer">🔗 Open Link</a>
                      <?php endif; ?>
                      <div class="chat-meta-line"><span><?= e(date('g:i A', strtotime($message['created_at'] ?? 'now'))) ?></span><?php if (($message['sender_type'] ?? '') === 'admin'): ?><?php if (!empty($message['read_by_buyer'])): ?><span>• Seen</span><?php else: ?><span>• Sent</span><?php endif; ?><?php elseif (($message['sender_type'] ?? '') === 'buyer'): ?><?php if (!empty($message['read_by_admin'])): ?><span>• Seen</span><?php else: ?><span>• Sent</span><?php endif; ?><?php endif; ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="chat-new-message" id="adminChatNewMessage" hidden>↓ New message</div>
            <form method="post" class="chat-compose chat-modern-compose" id="adminChatForm">
              <input type="hidden" name="action" value="send_message">
              <input type="hidden" name="chat_role" value="admin">
              <input type="hidden" name="username" value="<?= e($selectedChatUser) ?>">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <div class="chat-compose-top">
                <button class="chat-emoji-btn" type="button" data-emoji-button="adminChatForm" aria-label="Add emoji">😊</button>
                <span class="chat-compose-hint">Enter to send • Shift+Enter for a new line</span>
                <span class="chat-char-count" data-char-count="adminChatForm">0 / 3000</span>
              </div>
              <div class="chat-input-wrap">
                <textarea name="message" rows="1" maxlength="3000" placeholder="Reply to @<?= e($selectedChatUser) ?>..." autocomplete="off" required></textarea>
                <button class="chat-send-icon" type="submit" aria-label="Send reply">➤</button>
              </div>
              <div class="chat-optional-row">
                <input name="link" type="url" maxlength="2000" placeholder="Optional link (https://...)" inputmode="url">
              </div>
              <div class="chat-compose-footer"><span class="notice">Reply will appear instantly in the buyer chat.</span><span class="chat-send-state" data-send-state="adminChatForm">Ready</span></div>
            </form>
          <?php endif; ?>
        </section>
      </div>

<?php elseif ($tab === 'promos'): ?>
      <section class="admin-card"><h2>Promotion Manager</h2><p>Products marked as Promotion appear on the public Promos page.</p><?php foreach($products as $p): ?><div class="product-admin-card"><div class="product-admin-top"><div><h3><?= e($p['name']) ?></h3><div class="muted-block">Current status: <?= !empty($p['promo'])?'PROMO':'REGULAR' ?></div></div><form method="post"><input type="hidden" name="action" value="save_product"><input type="hidden" name="slug" value="<?= e($p['slug']) ?>"><input type="hidden" name="category" value="<?= e($p['category']) ?>"><input type="hidden" name="name" value="<?= e($p['name']) ?>"><input type="hidden" name="image" value="<?= e($p['image']) ?>"><input type="hidden" name="details_title" value="<?= e($p['details_title']) ?>"><input type="hidden" name="price_title" value="<?= e($p['price_title']) ?>"><input type="hidden" name="features" value="<?= e(implode(', ',$p['features']??[])) ?>"><input type="hidden" name="details" value="<?= e(implode("\n",$p['details']??[])) ?>"><?php foreach(($p['tiers']??[]) as $i=>$t): ?><input type="hidden" name="tier_name[<?= $i ?>]" value="<?= e($t[0]) ?>"><input type="hidden" name="tier_price[<?= $i ?>]" value="<?= e($t[1]) ?>"><input type="hidden" name="tier_stock[<?= $i ?>]" value="<?= e($t[2]??'') ?>"><?php endforeach; ?><input type="hidden" name="promo" value="<?= empty($p['promo'])?'1':'' ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="small-btn <?= !empty($p['promo'])?'primary':'' ?>" type="submit"><?= !empty($p['promo'])?'Remove Promo':'Mark Promo' ?></button></form></div></div><?php endforeach; ?></section>

    <?php elseif ($tab === 'telegram'): ?>
      <section class="admin-card"><h2>Telegram Settings</h2><p>Connection status and a safe test message. Bot credentials remain in server/environment configuration.</p><div class="info-row"><span>Bot Status</span><strong><?= telegram_configured() ? 'CONNECTED / CONFIGURED' : 'NOT CONFIGURED' ?></strong></div><div class="info-row"><span>Target Chat</span><strong><?= e(TELEGRAM_CHAT_ID ?: 'Not set') ?></strong></div><div class="info-row"><span>Channel Username</span><strong><?= e(TELEGRAM_CHANNEL_USERNAME ?: 'Not set') ?></strong></div><form method="post" class="hero-actions"><input type="hidden" name="action" value="test_telegram"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="primary">Send Test Message</button></form></section>

    <?php elseif ($tab === 'settings'): ?>
      <form method="post" class="admin-card"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><h2>Website Settings</h2><p>Control key storefront switches and the payment QR asset path.</p>
        <div class="toggle-row"><label class="check"><input type="checkbox" name="shop_enabled" value="1" <?= !empty($siteSettings['shop_enabled'])?'checked':'' ?>> Shop enabled</label></div>
        <div class="toggle-row"><label class="check"><input type="checkbox" name="registration_enabled" value="1" <?= !empty($siteSettings['registration_enabled'])?'checked':'' ?>> Buyer registration enabled</label></div>
        <div class="toggle-row"><label class="check"><input type="checkbox" name="telegram_enabled" value="1" <?= !empty($siteSettings['telegram_enabled'])?'checked':'' ?>> Telegram order notifications enabled</label></div>
        <div class="field" style="margin-top:12px"><label>Payment QR Path</label><input name="payment_qr" value="<?= e($siteSettings['payment_qr']) ?>"><div class="notice">Current QR: <?= e($siteSettings['payment_qr']) ?></div><img class="payment-qr" src="<?= e($siteSettings['payment_qr']) ?>" alt="Current payment QR"></div>
        <button class="primary" type="submit">Save Settings</button>
      </form>
    <?php endif; ?>

    <form method="post" class="hero-actions" style="margin-top:18px"><input type="hidden" name="action" value="logout"><input type="hidden" name="type" value="admin"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="logout-btn">Log Out Administrator</button></form>
  <?php else: ?>
    <div class="auth-shell"><div class="auth-card"><img class="auth-logo" src="assets/kaelhax-logo.png" alt="KAELHAX"><h1>Administrator Login</h1><p>Secure access to the KAELHAX Project Market administration area.</p>
      <form class="auth-form" method="post"><input type="hidden" name="action" value="admin_login"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Administrator Username</label><input name="username" required autocomplete="username"></div><div class="field" style="margin-top:11px"><label>Password</label<div class="password-field-wrap"><input id="adminPassword" name="password" type="password" required autocomplete="current-password"><button type="button" class="password-toggle" data-password-toggle="adminPassword">Show</button></div></div><button class="primary">Administrator Login</button><div class="notice">Credentials come from config.php or environment variables. Defaults are admin / admin123 until changed.</div></form>
    </div></div>
  <?php endif; ?>
<?php else: ?>
  <section class="content-head"><div class="kicker">Catalog</div><h1><?= e($siteContent['shop_title']) ?></h1><p><?= e($siteContent['shop_description']) ?></p></section>
  <section class="shop-grid">
    <?php foreach ($products as $p): ?><article class="product-card"><div class="product-image"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div><div class="product-info"><div class="badges"><span class="badge">Available</span><?php if ($p['promo']): ?><span class="badge promo">Promo</span><?php endif; ?></div><h3 class="product-name"><?= e($p['name']) ?></h3><p class="product-price">From <?= money($p['tiers'][0][1]) ?></p><button class="view-btn" onclick="location.href='index.php?page=product&slug=<?= e($p['slug']) ?>'">View Product</button></div></article><?php endforeach; ?>
  </section>
<?php endif; ?>
</main>
<footer><?= e($siteContent['footer_text']) ?></footer>

<div id="modalBackdrop" class="modal-backdrop"><div id="modal" class="modal"></div></div>
<script src="/assets/pro-upgrades.js" defer></script>
<script src="/assets/security.js" defer></script>
<script>
const isAdminPortal = <?= is_admin() ? 'true' : 'false' ?>;
let deferredInstallPrompt = null;

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', {scope: '/'}).catch(() => {});
  });
}

function setNotificationStatus(message) {
  const el = document.getElementById('adminNotificationStatus');
  if (el) el.textContent = message;
}

/*
 * In-app order alert feedback.
 * This intentionally does NOT call the browser Notification API, so active
 * admins get the site's own bubble instead of a Google/Chrome notification.
 */
let adminAudioContext = null;
let adminAudioUnlocked = false;

function unlockAdminAlertFeedback() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return false;

    if (!adminAudioContext) {
      adminAudioContext = new AudioCtx();
    }

    if (adminAudioContext.state === 'suspended') {
      adminAudioContext.resume().catch(() => {});
    }

    const now = adminAudioContext.currentTime;
    const gain = adminAudioContext.createGain();
    const oscillator = adminAudioContext.createOscillator();

    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(880, now);
    oscillator.frequency.exponentialRampToValueAtTime(660, now + 0.12);

    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.075, now + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16);

    oscillator.connect(gain);
    gain.connect(adminAudioContext.destination);
    oscillator.start(now);
    oscillator.stop(now + 0.17);

    adminAudioUnlocked = true;
    return true;
  } catch (_) {
    return false;
  }
}

function playAdminOrderAlert() {
  try {
    if (!adminAudioContext) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      adminAudioContext = new AudioCtx();
    }

    if (adminAudioContext.state === 'suspended') {
      adminAudioContext.resume().catch(() => {});
    }

    const now = adminAudioContext.currentTime;
    const gain = adminAudioContext.createGain();
    const oscillator = adminAudioContext.createOscillator();

    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(880, now);
    oscillator.frequency.setValueAtTime(988, now + 0.11);
    oscillator.frequency.setValueAtTime(1175, now + 0.22);

    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.09, now + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.34);

    oscillator.connect(gain);
    gain.connect(adminAudioContext.destination);
    oscillator.start(now);
    oscillator.stop(now + 0.36);
  } catch (_) {}

  try {
    if ('vibrate' in navigator) {
      navigator.vibrate([120, 70, 120, 70, 180]);
    }
  } catch (_) {}
}

function enableAdminNotifications() {
  const button = document.getElementById('enableAdminNotifications');
  if (!button) return;

  const audioReady = unlockAdminAlertFeedback();

  try {
    if ('vibrate' in navigator) navigator.vibrate(70);
  } catch (_) {}

  adminAudioUnlocked = audioReady;
  button.textContent = '✅ Sound & Vibration Enabled';
  button.disabled = false;

  setNotificationStatus(
    audioReady
      ? 'Custom order bubble, sound and vibration are enabled.'
      : 'Custom order bubble and vibration are enabled; this browser blocked audio.'
  );
}

function closeOrderToast() {
  const toast = document.getElementById('orderNotificationToast');
  if (!toast) return;
  toast.classList.remove('show', 'pulse');
}

function showOrderToast(order) {
  let toast = document.getElementById('orderNotificationToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'orderNotificationToast';
    toast.className = 'order-notification-toast';
    document.body.appendChild(toast);
  }

  toast.innerHTML = `
    <div class="order-notification-inner">
      <div class="order-notification-icon" aria-hidden="true">🛒</div>
      <div class="order-notification-copy">
        <strong>New Order Received</strong>
        <span>${esc(order.buyer_name || 'Buyer')} • ${esc(order.product || 'Order')} • ${esc(order.amount || '')}</span>
        <a href="index.php?page=admin&tab=orders">Open Admin Orders →</a>
      </div>
      <button type="button" class="order-notification-close" aria-label="Close" onclick="closeOrderToast()">×</button>
    </div>
  `;

  toast.classList.remove('show', 'pulse');
  void toast.offsetWidth;
  toast.classList.add('show', 'pulse');

  clearTimeout(window.__orderToastTimer);
  window.__orderToastTimer = setTimeout(() => {
    toast.classList.remove('show', 'pulse');
  }, 9000);
}

function notifyNewOrder(order) {
  showOrderToast(order);
  playAdminOrderAlert();
}

function initAdminOrderNotifications() {
  if (!isAdminPortal) return;

  const button = document.getElementById('enableAdminNotifications');
  if (button) {
    button.addEventListener('click', enableAdminNotifications);
  }

  setNotificationStatus('Watching for new orders. Tap Enable Sound & Vibration once for audio + vibration feedback.');

  let initialized = false;
  let latestCreatedAt = '';
  const storageKey = 'kaelhax_admin_seen_orders_v1';
  let seenIds = [];

  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
    if (Array.isArray(saved)) seenIds = saved.filter(Boolean).slice(-100);
  } catch (_) {}

  async function pollOrders() {
    try {
      const url = new URL('index.php', window.location.href);
      url.searchParams.set('action', 'admin_order_notifications');
      if (latestCreatedAt) url.searchParams.set('since', latestCreatedAt);

      const response = await fetch(url.toString(), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {'Accept': 'application/json'},
      });

      if (response.status === 401) {
        setNotificationStatus('Admin session expired. Please log in again.');
        return;
      }

      if (!response.ok) return;

      const data = await response.json();
      if (!data || !data.ok) return;

      const orders = Array.isArray(data.orders) ? data.orders : [];

      if (!initialized) {
        for (const order of orders) {
          if (order.id && !seenIds.includes(order.id)) seenIds.push(order.id);
        }
        latestCreatedAt = data.latest_created_at || latestCreatedAt;
        seenIds = seenIds.slice(-100);
        localStorage.setItem(storageKey, JSON.stringify(seenIds));
        initialized = true;
        setNotificationStatus('Watching for new orders. Pending: ' + Number(data.pending_count || 0));
        return;
      }

      for (const order of orders) {
        if (!order.id || seenIds.includes(order.id)) continue;
        seenIds.push(order.id);
        notifyNewOrder(order);
      }

      latestCreatedAt = data.latest_created_at || latestCreatedAt;
      seenIds = seenIds.slice(-100);
      localStorage.setItem(storageKey, JSON.stringify(seenIds));
      setNotificationStatus('Watching for new orders. Pending: ' + Number(data.pending_count || 0));
    } catch (_) {
      // Keep polling quietly; transient network errors should not break the page.
    }
  }

  pollOrders();
  window.__kaelhaxOrderPoll = window.setInterval(pollOrders, 5000);
}

const installButton = document.getElementById('installPwaButton');
window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  deferredInstallPrompt = event;
  if (installButton) installButton.style.display = 'inline-block';
});

if (installButton) {
  installButton.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    try { await deferredInstallPrompt.userChoice; } catch (_) {}
    deferredInstallPrompt = null;
    installButton.style.display = 'none';
  });
}

window.addEventListener('appinstalled', () => {
  if (installButton) installButton.style.display = 'none';
});


/* -------------------- REAL-TIME CHAT SYSTEM -------------------- */
const chatViewer = isAdminPortal ? 'admin' : <?= is_user() ? "'buyer'" : "'none'" ?>;
let chatPollTimer = null;
let chatPollBusy = false;
let chatSoundContext = null;
let adminConversationCache = [];
let chatLastServerUpdate = '';

function chatParseResponse(text) {
  const raw = String(text ?? '').replace(/^\\uFEFF/, '').trim();

  try {
    return JSON.parse(raw);
  } catch (_) {
    /* Recover the first complete JSON object if PHP/Vercel added extra output. */
    const start = raw.indexOf('{');
    if (start < 0) throw new Error('Invalid server response.');

    let depth = 0;
    let inString = false;
    let escaped = false;

    for (let i = start; i < raw.length; i++) {
      const ch = raw[i];

      if (inString) {
        if (escaped) {
          escaped = false;
        } else if (ch === '\\') {
          escaped = true;
        } else if (ch === '"') {
          inString = false;
        }
        continue;
      }

      if (ch === '"') {
        inString = true;
      } else if (ch === '{') {
        depth++;
      } else if (ch === '}') {
        depth--;
        if (depth === 0) {
          const candidate = raw.slice(start, i + 1);
          try {
            return JSON.parse(candidate);
          } catch (_) {
            break;
          }
        }
      }
    }

    throw new Error('Invalid JSON response from chat server.');
  }
}

function chatEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[m]));
}

function chatLinkify(value) {
  const safe = chatEscape(value);
  return safe.replace(/(https?:\/\/[^\s<]+)/gi, '<a class="chat-inline-link" href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
}

function chatFormatTime(value) {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
}

function chatFormatDay(value) {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const day = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diff = Math.round((start - day) / 86400000);

  if (diff === 0) return 'Today';
  if (diff === 1) return 'Yesterday';

  return d.toLocaleDateString([], {
    month:'short',
    day:'numeric',
    year:d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
  });
}

function chatSetLiveStatus(state) {
  const el = document.getElementById('buyerChatLiveStatus');
  if (!el) return;

  if (state === 'offline') {
    el.textContent = 'OFFLINE';
    el.style.color = '#ffb1bd';
    el.style.borderColor = '#6a3542';
    el.style.background = 'rgba(255,113,136,.06)';
  } else if (state === 'syncing') {
    el.textContent = 'SYNCING';
    el.style.color = '#f3d28a';
    el.style.borderColor = '#68552b';
    el.style.background = 'rgba(243,210,138,.06)';
  } else {
    el.textContent = 'LIVE';
    el.style.color = '';
    el.style.borderColor = '';
    el.style.background = '';
  }
}

function chatPlayIncomingTone() {
  try {
    if (!chatSoundContext) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      chatSoundContext = new AudioCtx();
    }

    if (chatSoundContext.state === 'suspended') {
      chatSoundContext.resume().catch(() => {});
    }

    const now = chatSoundContext.currentTime;
    const gain = chatSoundContext.createGain();
    const osc = chatSoundContext.createOscillator();

    osc.type = 'sine';
    osc.frequency.setValueAtTime(760, now);
    osc.frequency.exponentialRampToValueAtTime(620, now + 0.12);

    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.045, now + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.15);

    osc.connect(gain);
    gain.connect(chatSoundContext.destination);
    osc.start(now);
    osc.stop(now + 0.16);
  } catch (_) {}
}

function chatUnlockSound() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    if (!chatSoundContext) chatSoundContext = new AudioCtx();
    if (chatSoundContext.state === 'suspended') {
      chatSoundContext.resume().catch(() => {});
    }
  } catch (_) {}
}

function chatVibrateIncoming() {
  try {
    if ('vibrate' in navigator) navigator.vibrate([60, 45, 60]);
  } catch (_) {}
}

function chatIsNearBottom(thread) {
  return !thread || (thread.scrollHeight - thread.scrollTop - thread.clientHeight < 90);
}

function chatScrollBottom(thread, smooth = true) {
  if (!thread) return;
  thread.scrollTo({
    top: thread.scrollHeight,
    behavior: smooth ? 'smooth' : 'auto'
  });
}

function chatShowToast(title, detail) {
  let toast = document.getElementById('chatMessageToast');

  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'chatMessageToast';
    toast.className = 'chat-message-toast';
    toast.innerHTML = '<strong></strong><span></span>';
    document.body.appendChild(toast);
  }

  toast.querySelector('strong').textContent = title;
  toast.querySelector('span').textContent = detail;
  toast.classList.remove('show');
  void toast.offsetWidth;
  toast.classList.add('show');

  clearTimeout(window.__chatToastTimer);
  window.__chatToastTimer = setTimeout(() => toast.classList.remove('show'), 4200);
}

function chatRenderMessages(thread, conversation, viewer, forceScroll = false) {
  if (!thread) return;

  const messages = Array.isArray(conversation?.messages)
    ? conversation.messages
    : [];

  const wasNearBottom = chatIsNearBottom(thread);
  const previousLastId = thread.dataset.lastMessageId || '';

  if (!messages.length) {
    thread.innerHTML = '<div class="chat-empty">No messages yet.<br>Send a message below to start a conversation.</div>';
    thread.dataset.lastMessageId = '';
    return;
  }

  let html = '';
  let previousDay = '';

  messages.forEach((msg) => {
    const senderType = msg.sender_type === 'admin' ? 'admin' : 'buyer';
    const mine = senderType === viewer;
    const id = String(msg.id || '');
    const senderName = msg.sender_name || (senderType === 'admin' ? 'Administrator' : 'Buyer');
    const day = chatFormatDay(msg.created_at);

    if (day !== previousDay) {
      html += '<div class="chat-separator">' + chatEscape(day) + '</div>';
      previousDay = day;
    }

    const counterpartRead = viewer === 'admin'
      ? !!msg.read_by_buyer
      : !!msg.read_by_admin;

    const delivery = mine
      ? (counterpartRead ? 'Seen' : 'Sent')
      : '';

    const isNew = id && previousLastId && id !== previousLastId && id === String(messages[messages.length - 1]?.id || '');

    html += '<div class="chat-row ' +
      (mine ? 'mine' : 'theirs') +
      (isNew ? ' new-message' : '') +
      '" data-message-id="' + chatEscape(id) + '">';

    html += '<div class="chat-bubble">';
    html += '<div class="chat-author">' + chatEscape(senderName) + '</div>';
    html += '<div class="chat-text">' + chatLinkify(msg.message || '') + '</div>';

    if (msg.link) {
      html += '<a class="chat-link-btn" href="' +
        chatEscape(msg.link) +
        '" target="_blank" rel="noopener noreferrer">🔗 Open Link</a>';
    }

    html += '<div class="chat-meta-line"><span>' +
      chatEscape(chatFormatTime(msg.created_at)) +
      '</span>';

    if (delivery) {
      html += '<span>• ' + chatEscape(delivery) + '</span>';
    }

    html += '</div></div></div>';
  });

  thread.innerHTML = html;
  thread.dataset.lastMessageId = String(messages[messages.length - 1].id || '');
  thread.dataset.lastUpdated = String(conversation.updated_at || '');

  if (forceScroll || wasNearBottom || previousLastId === '') {
    chatScrollBottom(thread, false);
  }
}

function chatRenderInbox(conversations, selectedUser) {
  const inbox = document.getElementById('adminChatInbox');
  if (!inbox || !Array.isArray(conversations)) return;

  const rows = conversations
    .slice()
    .sort((a,b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')));

  adminConversationCache = rows;

  let html = '';

  for (const conversation of rows) {
    const username = String(conversation.username || '').toLowerCase();
    if (!username) continue;

    const messages = Array.isArray(conversation.messages)
      ? conversation.messages
      : [];

    const last = messages.length ? messages[messages.length - 1] : null;
    let unread = 0;

    for (const msg of messages) {
      if (msg.sender_type === 'buyer' && !msg.read_by_admin) {
        unread++;
      }
    }

    const active = username === String(selectedUser || '').toLowerCase();
    const preview = last
      ? String(last.message || 'New message')
      : 'No messages';

    const time = last
      ? chatFormatDay(last.created_at) + ' · ' + chatFormatTime(last.created_at)
      : '—';

    html += '<a class="chat-inbox-item ' +
      (active ? 'active ' : '') +
      (unread ? 'unread-pulse' : '') +
      '" data-chat-user="' + chatEscape(username) +
      '" href="index.php?page=admin&tab=messages&user=' +
      encodeURIComponent(username) + '">';

    html += '<div class="chat-inbox-top"><strong>@' +
      chatEscape(username) + '</strong>';

    if (unread) {
      html += '<span class="chat-unread">' + unread + '</span>';
    }

    html += '</div>';
    html += '<div class="chat-inbox-preview">' +
      chatEscape(preview) + '</div>';
    html += '<div class="chat-inbox-time">' +
      chatEscape(time) + '</div>';

    if (unread) {
      html += '<div class="chat-inbox-unread-label">● Unread message' +
        (unread > 1 ? 's' : '') + '</div>';
    }

    html += '</a>';
  }

  inbox.innerHTML = html ||
    '<div class="chat-empty" style="padding:40px 14px">No conversations yet.</div>';
}

async function chatMarkRead(username) {
  const effectiveUsername = username || <?= json_encode(is_user() ? ($_SESSION['buyer_username'] ?? '') : '') ?>;
  const role = isAdminPortal ? 'admin' : 'buyer';

  if (role === 'admin' && !effectiveUsername) return;
  if (role === 'buyer' && !effectiveUsername) return;

  const formData = new FormData();
  formData.set('action', 'chat_mark_read');
  formData.set('chat_role', role);
  formData.set('username', effectiveUsername);
  formData.set('ajax', '1');
  formData.set('csrf', <?= json_encode(csrf_token()) ?>);

  try {
    await fetch('index.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'Accept':'application/json'}
    });
  } catch (_) {}
}

function chatSetSendState(form, state, label) {
  const el = form?.querySelector('.chat-send-state');
  if (!el) return;
  el.className = 'chat-send-state ' + state;
  el.textContent = label;
}

function chatAutoResize(textarea) {
  if (!textarea) return;
  textarea.style.height = 'auto';
  textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
}

function chatUpdateCharCount(form) {
  if (!form) return;
  const textarea = form.querySelector('textarea[name="message"]');
  const counter = form.querySelector('.chat-char-count');
  if (textarea && counter) {
    counter.textContent = textarea.value.length + ' / ' + (textarea.maxLength || 3000);
  }
}

function chatInsertEmoji(form, emoji) {
  const textarea = form?.querySelector('textarea[name="message"]');
  if (!textarea) return;

  const start = textarea.selectionStart ?? textarea.value.length;
  const end = textarea.selectionEnd ?? textarea.value.length;

  textarea.value =
    textarea.value.slice(0, start) +
    emoji +
    textarea.value.slice(end);

  textarea.focus();
  textarea.selectionStart = textarea.selectionEnd = start + emoji.length;

  chatAutoResize(textarea);
  chatUpdateCharCount(form);
}

async function chatSubmitForm(form) {
  if (!form || form.dataset.busy === '1') return;

  const textarea = form.querySelector('textarea[name="message"]');
  if (!textarea || !textarea.value.trim()) {
    textarea?.focus();
    return;
  }

  form.dataset.busy = '1';
  chatSetSendState(form, 'sending', 'Sending…');

  const button = form.querySelector('.chat-send-icon');
  if (button) button.disabled = true;

  const data = new FormData(form);
  data.set('ajax', '1');
  data.set('chat_role', form.id === 'adminChatForm' ? 'admin' : 'buyer');

  try {
    chatUnlockSound();

    const response = await fetch('/index.php', {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'Accept':'application/json'}
    });

    const responseText = await response.text();
    const payload = chatParseResponse(responseText);

    if (!response.ok || !payload?.ok) {
      throw new Error(
        payload?.error ||
        'The chat server did not return a valid response.'
      );
    }

    const chatContainer = form.closest('.chat-shell, .chat-admin-thread');
    const thread = chatContainer?.querySelector('.chat-thread');
    const viewer = form.id === 'adminChatForm' ? 'admin' : 'buyer';

    if (thread && payload.conversation) {
      chatRenderMessages(thread, payload.conversation, viewer, true);
    }

    textarea.value = '';

    const linkInput = form.querySelector('input[name="link"]');
    if (linkInput) linkInput.value = '';

    chatAutoResize(textarea);
    chatUpdateCharCount(form);
    chatSetSendState(form, 'sent', 'Sent');

  } catch (error) {
    chatSetSendState(
      form,
      'error',
      error?.message || 'Message failed'
    );
  } finally {
    form.dataset.busy = '0';
    if (button) button.disabled = false;
  }
}

async function chatPollOnce() {
  const buyerThread = document.getElementById('buyerChatThread');
  const adminThread = document.getElementById('adminChatThread');
  const adminInbox = document.getElementById('adminChatInbox');

  if (!buyerThread && !adminThread && !adminInbox) return;

  if (chatPollBusy) return;
  chatPollBusy = true;

  const isAdmin = !!adminInbox || !!adminThread;
  const selectedUser = adminThread
    ? (adminThread.dataset.chatUser || '')
    : '';

  const thread = adminThread || buyerThread || null;
  const previousLastId = thread?.dataset.lastMessageId || '';
  const previousUpdated = thread?.dataset.lastUpdated || '';

  if (document.visibilityState === 'visible') {
    chatSetLiveStatus('syncing');
  }

  try {
    const url = new URL('/index.php', window.location.origin);
    url.searchParams.set('action', 'chat_poll');
    url.searchParams.set('role', isAdmin ? 'admin' : 'buyer');

    if (selectedUser) {
      url.searchParams.set('user', selectedUser);
    }

    const response = await fetch(url.toString(), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'Accept':'application/json'}
    });

    if (response.status === 401) {
      chatSetLiveStatus('offline');
      chatShowToast('Chat session expired', 'Please sign in again.');
      return;
    }

    const responseText = await response.text();
    const payload = chatParseResponse(responseText);

    if (!response.ok || !payload?.ok) {
      throw new Error(payload?.error || 'Chat sync failed.');
    }

    const conversation = payload.conversation || {
      messages: [],
      updated_at: ''
    };

    const messages = Array.isArray(conversation.messages)
      ? conversation.messages
      : [];

    const newLastId = messages.length
      ? String(messages[messages.length - 1].id || '')
      : '';

    const changed = newLastId !== previousLastId ||
      String(conversation.updated_at || '') !== previousUpdated;

    if (changed && thread) {
      const newest = messages[messages.length - 1];
      const incoming =
        newest &&
        previousLastId &&
        String(newest.id || '') !== previousLastId &&
        newest.sender_type !== chatViewer;

      const nearBottom = chatIsNearBottom(thread);

      chatRenderMessages(
        thread,
        conversation,
        isAdmin ? 'admin' : 'buyer',
        nearBottom
      );

      if (incoming) {
        if (isAdmin) {
          chatShowToast(
            'New buyer message',
            '@' + String(selectedUser || conversation.username || 'buyer')
          );
        } else {
          chatShowToast(
            newest.sender_name || 'Administrator',
            newest.message || 'New message'
          );
        }

        chatPlayIncomingTone();
        chatVibrateIncoming();
      }

      if (
        document.visibilityState === 'visible' &&
        nearBottom &&
        String(newest?.sender_type || '') !== chatViewer
      ) {
        await chatMarkRead(
          isAdmin ? selectedUser : <?= json_encode(is_user() ? ($_SESSION['buyer_username'] ?? '') : '') ?>
        );
      }
    }

    if (isAdmin && adminInbox) {
      const previousConversationIds = new Set(
        Array.from(adminInbox.querySelectorAll('[data-chat-user]'))
          .map(el => el.getAttribute('data-chat-user'))
          .filter(Boolean)
      );

      chatRenderInbox(payload.conversations || [], selectedUser);

      const newUnreadConversation = (payload.conversations || []).find((conv) => {
        const msgs = Array.isArray(conv.messages) ? conv.messages : [];
        return msgs.some(m => m.sender_type === 'buyer' && !m.read_by_admin) &&
          !previousConversationIds.has(String(conv.username || '').toLowerCase());
      });

      if (newUnreadConversation) {
        chatShowToast(
          'New conversation',
          '@' + String(newUnreadConversation.username || 'buyer')
        );
        chatPlayIncomingTone();
        chatVibrateIncoming();
      }
    }

    chatSetLiveStatus('live');
    chatLastServerUpdate = String(payload.server_time || '');
  } catch (error) {
    chatSetLiveStatus('offline');
    console.debug('[chat] sync error:', error?.message || error);
  } finally {
    chatPollBusy = false;
  }
}

function scheduleChatPoll() {
  if (chatPollTimer) clearTimeout(chatPollTimer);

  chatPollTimer = window.setTimeout(async () => {
    await chatPollOnce();
    scheduleChatPoll();
  }, document.visibilityState === 'visible' ? 2200 : 6000);
}

function initEnhancedChat() {
  const forms = [
    document.getElementById('buyerChatForm'),
    document.getElementById('adminChatForm')
  ].filter(Boolean);

  for (const form of forms) {
    const textarea = form.querySelector('textarea[name="message"]');
    const emojiButton = form.querySelector('[data-emoji-button]');
    const chatContainer = form.closest('.chat-shell, .chat-admin-thread');
    const thread = chatContainer?.querySelector('.chat-thread');

    if (textarea) {
      textarea.addEventListener('focus', chatUnlockSound);

      textarea.addEventListener('input', () => {
        chatAutoResize(textarea);
        chatUpdateCharCount(form);
      });

      textarea.addEventListener('keydown', (event) => {
        if (
          event.key === 'Enter' &&
          !event.shiftKey &&
          !event.isComposing
        ) {
          event.preventDefault();
          chatSubmitForm(form);
        }
      });

      chatAutoResize(textarea);
      chatUpdateCharCount(form);
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      chatSubmitForm(form);
    });

    if (emojiButton) {
      emojiButton.addEventListener('click', () => {
        chatInsertEmoji(form, '😊');
      });
    }

    thread?.addEventListener('scroll', () => {
      const pill = form.closest('.chat-shell, .chat-admin-thread')
        ?.querySelector('.chat-new-message');

      if (pill && chatIsNearBottom(thread)) {
        pill.hidden = true;
      }
    });

    const pill = form.closest('.chat-shell, .chat-admin-thread')
      ?.querySelector('.chat-new-message');

    if (pill) {
      pill.addEventListener('click', () => {
        pill.hidden = true;
        chatScrollBottom(thread, true);
      });
    }
  }

  /* Keep existing server-rendered messages untouched until the first successful poll. */
  const threads = [
    document.getElementById('buyerChatThread'),
    document.getElementById('adminChatThread')
  ].filter(Boolean);

  for (const thread of threads) {
    const lastRow = thread.querySelector('.chat-row:last-child');
    thread.dataset.lastMessageId = lastRow?.getAttribute('data-message-id') || '';
    thread.dataset.lastUpdated = '';
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      chatUnlockSound();
      chatPollOnce();
    }
  });

  chatPollOnce();
  scheduleChatPoll();
}

const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
function setMenu(open){document.getElementById('drawer').classList.toggle('open',open);document.getElementById('menuBackdrop').classList.toggle('open',open);document.body.classList.toggle('lock',open)}
function toggleDesktopPreview(){document.body.classList.toggle('desktop-preview');document.getElementById('displayLabel').textContent=document.body.classList.contains('desktop-preview')?'Mobile Mode':'Desktop Mode'}
function money(n){return 'PHP '+Number(n).toFixed(2)}
function esc(v){return String(v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
function openModal(){document.getElementById('modalBackdrop').classList.add('open')}
function closeModal(){document.getElementById('modalBackdrop').classList.remove('open')}
function openOrder(slug,tierIndex){
  const p = products[slug];
  if (!p || !p.tiers || !p.tiers[tierIndex]) return;

  const t = p.tiers[tierIndex];
  const csrf = <?= json_encode(csrf_token()) ?>;

  document.getElementById('modal').innerHTML = `
    <div class="modal-head">
      <div>
        <div class="kicker">New Order</div>
        <h2>${esc(p.name)}</h2>
        <p class="modal-muted">${esc(t[0])} • ${money(t[1])}</p>
      </div>
      <button class="close" type="button" onclick="closeModal()" aria-label="Close">×</button>
    </div>

    <form method="post" enctype="multipart/form-data" class="order-form">
      <input type="hidden" name="action" value="send_order">
      <input type="hidden" name="slug" value="${esc(slug)}">
      <input type="hidden" name="tier" value="${tierIndex}">
      <input type="hidden" name="csrf" value="${esc(csrf)}">

      <div class="payment-box">
        <h3>${esc(<?= json_encode($siteContent['payment_title']) ?>)}</h3>
        <p>${esc(<?= json_encode($siteContent['payment_description']) ?>)}</p>
        <img class="payment-qr" src="<?= e($siteSettings['payment_qr']) ?>" alt="Payment QR">
        <div class="payment-note">${esc(<?= json_encode($siteContent['payment_note']) ?>)}</div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="oUser">Telegram Username</label>
          <input id="oUser" name="telegram_username" placeholder="@username" autocomplete="off" required>
        </div>

        <div class="field">
          <label for="oName">Buyer Name</label>
          <input id="oName" name="buyer_name" placeholder="Name" autocomplete="name" required>
        </div>

        <div class="field">
          <label for="oPay">Payment Method</label>
          <select id="oPay" name="payment_method">
            <option value="INSTAPAY">INSTAPAY</option>
            <option value="GCASH">GCASH</option>
            <option value="MAYA">MAYA</option>
            <option value="BANK TRANSFER">BANK TRANSFER</option>
          </select>
        </div>

        <div class="field">
          <label for="oUid">UID / Account (optional)</label>
          <input id="oUid" name="uid" placeholder="Optional">
        </div>

        <div class="field full">
          <label for="oReceipt">Payment Receipt</label>
          <input id="oReceipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
        </div>

        <div class="field full">
          <label for="oNote">Note</label>
          <textarea id="oNote" name="note" placeholder="Optional note"></textarea>
        </div>
      </div>

      <button class="primary" type="submit">Submit Order + Receipt</button>
      <div class="notice">Your order stays <b>Pending</b> until the administrator reviews the receipt.</div>
    </form>
  `;

  openModal();
}
function switchAuth(mode){const login=document.getElementById('loginForm'),reg=document.getElementById('registerForm'),tLogin=document.getElementById('tabLogin'),tReg=document.getElementById('tabRegister'); if(!login||!reg)return; if(tLogin)tLogin.classList.toggle('active',mode==='login'); if(tReg)tReg.classList.toggle('active',mode==='register'); login.style.display=mode==='login'?'block':'none'; reg.style.display=mode==='register'?'block':'none'}
document.getElementById('modalBackdrop').addEventListener('click',e=>{if(e.target.id==='modalBackdrop')closeModal()});
initEnhancedChat();
initAdminOrderNotifications();

if (isAdminPortal) {
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && typeof window.__kaelhaxOrderPoll === 'number') {
      // The regular interval continues; this just wakes the page sooner after returning.
    }
  });
}

</script>
</body>
</html>
