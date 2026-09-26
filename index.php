<?php
session_start();
require_once __DIR__ . '/includes/config.php';

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
if (!defined('SHOP_URL')) define('SHOP_URL', getenv('SHOP_URL') ?: 'kielhax.elementfx.com');

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
function load_users() {
    ensure_users_file();
    $json = @file_get_contents(users_file());
    $users = json_decode($json ?: '[]', true);
    return is_array($users) ? $users : [];
}
function save_users($users) {
    ensure_users_file();
    @file_put_contents(users_file(), json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function orders_file() { return __DIR__ . '/data/orders.json'; }
function receipts_dir() { return __DIR__ . '/receipts'; }
function ensure_orders_storage() {
    $dataDir = dirname(orders_file());
    if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
    if (!file_exists(orders_file())) @file_put_contents(orders_file(), "[]");
    if (!is_dir(receipts_dir())) @mkdir(receipts_dir(), 0755, true);
}
function load_orders() {
    ensure_orders_storage();
    $json = @file_get_contents(orders_file());
    $orders = json_decode($json ?: '[]', true);
    return is_array($orders) ? $orders : [];
}
function save_orders($orders) {
    ensure_orders_storage();
    @file_put_contents(orders_file(), json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
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
function save_receipt_upload($file, $orderId) {
    ensure_orders_storage();
    if (!isset($file) || !is_array($file)) return [false, 'Please upload your payment receipt.'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, receipt_upload_error_message((int)$file['error'])];
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) return [false, 'Receipt must be between 1 byte and 5 MB.'];
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) return [false, 'Invalid receipt upload.'];

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
    ];

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: $mime;
    } elseif (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmp) ?: $mime;
    }

    // Some Windows/mobile uploads report a generic MIME type even when the
    // file itself is a valid JPG/PNG/WEBP/PDF. Verify the file contents as a
    // fallback so legitimate receipts are not rejected.
    if (!isset($allowed[$mime])) {
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('getimagesize')) {
            $info = @getimagesize($tmp);
            if ($info && !empty($info['mime']) && in_array($info['mime'], ['image/jpeg', 'image/pjpeg'], true)) {
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

    $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $orderId) . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = receipts_dir() . '/' . $filename;
    if (!@move_uploaded_file($tmp, $target)) return [false, 'The server could not save the receipt.'];
    return [true, ['path' => 'receipts/' . $filename, 'filename' => $filename, 'mime' => $mime]];
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
    if (!file_exists($receiptAbsolutePath) || !is_readable($receiptAbsolutePath)) {
        return [false, 'Receipt file is not readable.'];
    }

    $caption = '<b>🧾 PAYMENT RECEIPT</b>' . "\n\n" .
        '<b>Order ID:</b> <code>' . e($order['id'] ?? '') . '</code>' . "\n" .
        '<b>Buyer:</b> ' . e($order['buyer_name'] ?? '') . "\n" .
        '<b>Product:</b> ' . e($order['product'] ?? '') . "\n" .
        '<b>Amount:</b> ' . e($order['amount'] ?? '') . "\n" .
        '<b>Payment:</b> ' . e($order['payment_method'] ?? '') . "\n" .
        "\n" . '<b>Status:</b> ACCEPTED BY ADMIN';

    /*
     * Send the receipt as a Telegram DOCUMENT instead of a PHOTO.
     * This keeps JPG/PNG/WEBP/PDF receipts together with the approved
     * order banner and avoids photo-format restrictions.
     */
    $safeMime = (is_string($mime) && $mime !== '') ? $mime : 'application/octet-stream';
    $fields = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'document' => new CURLFile(
            $receiptAbsolutePath,
            $safeMime,
            basename($receiptAbsolutePath)
        ),
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ];

    [$ok, $result] = telegram_request('sendDocument', $fields, true);

    if (!$ok) {
        return [false, is_string($result) ? $result : 'Telegram rejected the receipt upload.'];
    }

    return [true, 'OK'];
}

// -------------------- POST ACTIONS --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_ok()) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
    redirect_page('shop');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_order') {
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
        'receipt' => $receipt['path'],
        'receipt_filename' => $receipt['filename'],
        'receipt_mime' => $receipt['mime'],
        'status' => 'pending',
    ];

    $orders = load_orders();
    $orders[] = $order;
    save_orders($orders);

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
    session_regenerate_id(true);
    $_SESSION['buyer_logged_in'] = true;
    $_SESSION['buyer_username'] = $username;
    $_SESSION['buyer_name'] = $display;
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Account created successfully.'];
    redirect_page('account');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buyer_login') {
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
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (hash_equals((string)ADMIN_USERNAME, $username) && hash_equals((string)ADMIN_PASSWORD, $password)) {
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
    $status = ($_POST['status'] ?? '') === 'accepted' ? 'accepted' : (($_POST['status'] ?? '') === 'ignored' ? 'ignored' : 'pending');
    $orders = load_orders();
    $found = false;
    $approvedOrder = null;
    $wasAlreadyTelegramSent = false;
    foreach ($orders as &$order) {
        if (($order['id'] ?? '') === $orderId) {
            $wasAlreadyTelegramSent = !empty($order['telegram_accepted_sent_at']);
            $order['status'] = $status;
            $order['reviewed_at'] = date('c');
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
            $receiptAbsolutePath = __DIR__ . '/' . ($approvedOrder['receipt'] ?? '');
            [$receiptSent, $receiptMsg] = telegram_send_receipt($approvedOrder, $receiptAbsolutePath, $approvedOrder['receipt_mime'] ?? '');

            if ($bannerSent && $receiptSent) {
                foreach ($orders as &$savedOrder) {
                    if (($savedOrder['id'] ?? '') === $orderId) {
                        $savedOrder['telegram_accepted_sent_at'] = date('c');
                        break;
                    }
                }
                unset($savedOrder);
                $telegramResult = [true, 'Approved order banner and receipt sent to Telegram.'];
            } else {
                $telegramResult = [false, 'Order accepted, but Telegram delivery failed. ' . (!$bannerSent ? $bannerMsg : $receiptMsg)];
            }
        }

        save_orders($orders);
        if ($telegramResult && !$telegramResult[0]) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Order ' . $orderId . ' marked as ACCEPTED. ' . $telegramResult[1]];
        } elseif ($status === 'accepted' && $wasAlreadyTelegramSent) {
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Order ' . $orderId . ' is already accepted and has already been sent to Telegram.'];
        } elseif ($status === 'accepted') {
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Order ' . $orderId . ' accepted. Banner and receipt sent to Telegram.'];
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

$page = $_GET['page'] ?? 'shop';
$slug = $_GET['slug'] ?? '';
$product = ($page === 'product' && isset($products[$slug])) ? $products[$slug] : null;
if (!$siteSettings['shop_enabled'] && in_array($page, ['shop','product','preview','promos'], true) && !is_admin()) { $page = 'account'; $product = null; }
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function menu_item($href, $label, $icon, $pageKey) {
    global $page;
    $active = ($page === $pageKey) ? ' active' : '';
    return '<a class="menu-link' . $active . '" href="' . e($href) . '"><span class="menu-icon">' . $icon . '</span><span>' . e($label) . '</span></a>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b0f15">
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
.modal-backdrop{position:fixed;z-index:90;inset:0;background:rgba(0,0,0,.72);display:none;align-items:flex-end;justify-content:center;padding:0}.modal-backdrop.open{display:flex}.modal{width:min(650px,100%);max-height:94vh;overflow:auto;background:#0d131b;border:1px solid var(--line);border-radius:21px 21px 0 0;padding:18px 15px 25px}.modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}.modal-head h2{margin:4px 0;font-size:24px}.modal-muted{color:var(--muted);margin:0;font-size:13px}.close{width:38px;height:38px;border:1px solid var(--line);background:var(--surface);color:#cbd4df;border-radius:10px;font-size:24px}
@media(max-width:980px){.shop-grid{grid-template-columns:repeat(2,1fr)}.product-layout{grid-template-columns:1fr;gap:18px}.product-art{margin-top:18px}}
@media(max-width:700px){.site-header{height:80px}.header-inner{padding:0 16px}.brand-logo{width:40px;height:40px}.brand-title{font-size:16px}.drawer{top:80px}.menu-backdrop{inset:80px 0 0}main{padding:23px 14px 49px}.content-head{margin-bottom:18px}.content-head h1{font-size:34px}.content-head p{font-size:14px}.shop-grid{grid-template-columns:1fr;gap:14px}.product-card{border-radius:19px}.product-info{padding:16px 14px 15px}.product-name{font-size:21px}.product-price{font-size:20px}.view-btn{padding:12px;font-size:17px}.product-meta h1{font-size:30px}.product-layout{gap:8px}.product-art{margin-top:17px;border-radius:15px}.price-panel{padding:16px;border-radius:17px}.price-panel h2{font-size:24px}.price-item{padding:12px 11px}.price-right{gap:8px}.amount{font-size:14px}.buy{padding:9px 10px}.details-panel{padding:16px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.auth-shell{padding:18px 13px;min-height:calc(100vh - 80px);align-items:center}.auth-card{padding:19px 15px;border-radius:18px}.auth-card h1{font-size:26px}.admin-panel{grid-template-columns:1fr}.drawer-inner{padding:18px 15px}}
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
  <div class="menu-group"><div class="group-title">Account</div><?= menu_item('index.php?page=account','Account','♙','account') ?><?= menu_item('index.php?page=my-orders','My Orders','◫','my-orders') ?></div>
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

<?php elseif ($page === 'account'): ?>
  <?php if (is_user()): ?>
    <section class="content-head"><div class="kicker">Account</div><h1><?= e($siteContent['account_title']) ?></h1><p><?= e($siteContent['account_description']) ?></p></section>
    <div class="info-box"><div class="info-row"><span>Username</span><strong>@<?= e($_SESSION['buyer_username']) ?></strong></div><div class="info-row"><span>Display Name</span><strong><?= e($_SESSION['buyer_name']) ?></strong></div><div class="info-row"><span>Status</span><strong>Signed in</strong></div><form method="post" class="hero-actions"><input type="hidden" name="action" value="logout"><input type="hidden" name="type" value="buyer"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="logout-btn">Log Out</button></form></div>
  <?php else: ?>
    <div class="auth-shell"><div class="auth-card">
      <img class="auth-logo" src="assets/kaelhax-logo.png" alt="KAELHAX"><h1>Buyer Account</h1><p>Login or create an account for your Project Market purchases.</p>
      <div class="auth-tabs"><button id="tabLogin" class="active" onclick="switchAuth('login')">Login</button><?php if (!empty($siteSettings['registration_enabled'])): ?><button id="tabRegister" onclick="switchAuth('register')">Register</button><?php endif; ?></div>
      <form id="loginForm" class="auth-form" method="post"><input type="hidden" name="action" value="buyer_login"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Username</label><input name="username" required autocomplete="username"></div><div class="field" style="margin-top:11px"><label>Password</label><input name="password" type="password" required autocomplete="current-password"></div><button class="primary">Login</button></form>
      <form id="registerForm" class="auth-form" method="post" style="display:<?= !empty($siteSettings['registration_enabled']) ? 'none' : 'none' ?>"><input type="hidden" name="action" value="buyer_register"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Username</label><input name="username" pattern="[A-Za-z0-9_\.]{3,32}" required></div><div class="field" style="margin-top:11px"><label>Display Name</label><input name="display_name" required></div><div class="field" style="margin-top:11px"><label>Password</label><input name="password" type="password" minlength="6" required></div><button class="primary">Create Account</button></form>
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
      <?php foreach ([['dashboard','Dashboard'],['content','Website Content'],['products','Products'],['orders','Orders'],['buyers','Buyers'],['promos','Promotions'],['telegram','Telegram'],['settings','Settings']] as $nav): ?>
        <a class="<?= $tab === $nav[0] ? 'active' : '' ?>" href="index.php?page=admin&tab=<?= e($nav[0]) ?>"><?= e($nav[1]) ?></a>
      <?php endforeach; ?>
    </nav>

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
            <?php if (!empty($o['receipt']) && strpos((string)($o['receipt_mime'] ?? ''), 'image/') === 0): ?><img class="receipt-view" src="<?= e($o['receipt']) ?>" alt="Payment receipt"><?php elseif (!empty($o['receipt'])): ?><a class="receipt-link" href="<?= e($o['receipt']) ?>" target="_blank" rel="noopener">Open Receipt</a><?php endif; ?>
            <div class="admin-order-actions">
              <form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="accepted"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="accept">✓ Accept</button></form>
              <form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="ignored"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="ignore">✕ Ignore</button></form>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>

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
      <?php if(!$pendingOrders): ?><div class="info-box">No pending orders.</div><?php else: foreach($pendingOrders as $o): ?><article class="order-card"><div class="order-top"><div><div class="order-id"><?= e($o['id']) ?></div><div class="order-meta"><?= e($o['product']) ?><br><?= e($o['duration']) ?> • <?= e($o['amount']) ?><br>Buyer: <?= e($o['buyer_name']) ?> • <?= e($o['telegram_username']) ?></div></div><span class="status-pill status-pending">PENDING</span></div><div class="order-meta">Payment: <?= e($o['payment_method']) ?><?php if(!empty($o['uid'])):?><br>UID: <?= e($o['uid']) ?><?php endif;?></div><?php if(!empty($o['receipt']) && strpos((string)($o['receipt_mime'] ?? ''),'image/')===0): ?><img class="receipt-view" src="<?= e($o['receipt']) ?>" alt="Payment receipt"><?php elseif(!empty($o['receipt'])): ?><a class="receipt-link" href="<?= e($o['receipt']) ?>" target="_blank" rel="noopener">Open Receipt</a><?php endif; ?><div class="admin-order-actions"><form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="accepted"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="accept">✓ Accept</button></form><form method="post"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="hidden" name="status" value="ignored"><input type="hidden" name="tab" value="orders"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="ignore">✕ Ignore</button></form></div></article><?php endforeach; endif; ?>
      <section class="content-head" style="margin-top:28px"><div class="kicker">History</div><h2 style="font-size:25px;margin:8px 0 0">Order History</h2></section>
      <?php if(!$adminOrders): ?><div class="info-box">No orders have been submitted yet.</div><?php else: ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Buyer</th><th>Product</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach(array_slice($adminOrders,0,50) as $o): ?><tr><td><?= e($o['id']) ?></td><td><?= e($o['buyer_name']) ?></td><td><?= e($o['product']) ?></td><td><?= e($o['amount']) ?></td><td><span class="status-pill status-<?= e($o['status']??'pending') ?>"><?= e($o['status']??'pending') ?></span></td><td><?= e(date('M d, Y g:i A',strtotime($o['created_at']??'now'))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

    <?php elseif ($tab === 'buyers'): ?>
      <section class="admin-card"><h2>Buyer Management</h2><p>Review registered buyer accounts and enable or disable access.</p><div class="table-wrap"><table class="admin-table"><thead><tr><th>Username</th><th>Display Name</th><th>Registered</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($buyers as $b): $bst=($b['status']??'active'); ?><tr><td>@<?= e($b['username']) ?></td><td><?= e($b['display_name']) ?></td><td><?= e(date('M d, Y',strtotime($b['created_at']??'now'))) ?></td><td><span class="<?= $bst==='active'?'status-active':'status-disabled' ?>"><?= e(strtoupper($bst)) ?></span></td><td><form method="post"><input type="hidden" name="action" value="update_buyer_status"><input type="hidden" name="username" value="<?= e($b['username']) ?>"><input type="hidden" name="status" value="<?= $bst==='active'?'disabled':'active' ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="small-btn" type="submit"><?= $bst==='active'?'Disable':'Enable' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></section>

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

    <form method="post" class="hero-actions" style="margin-top:18px"><input type="hidden" name="action" value="logout"><input type="hidden" name="type" value="admin"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="logout-btn">Log Out Administrator</button></form>  <?php else: ?>
    <div class="auth-shell"><div class="auth-card"><img class="auth-logo" src="assets/kaelhax-logo.png" alt="KAELHAX"><h1>Administrator Login</h1><p>Secure access to the KAELHAX Project Market administration area.</p>
      <form class="auth-form" method="post"><input type="hidden" name="action" value="admin_login"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Administrator Username</label><input name="username" required autocomplete="username"></div><div class="field" style="margin-top:11px"><label>Password</label><input name="password" type="password" required autocomplete="current-password"></div><button class="primary">Administrator Login</button><div class="notice">Credentials come from config.php or environment variables. Defaults are admin / admin123 until changed.</div></form>
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
<script>
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
</script>
</body>
</html>