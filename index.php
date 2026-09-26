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

$products = [
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
function telegram_configured() {
    return TELEGRAM_BOT_TOKEN !== '' && TELEGRAM_CHAT_ID !== '';
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

    $caption =
        '<b>🛒 NEW ORDER</b>' . "\n\n" .
        '<b>User:</b> <code>' . e($user) . '</code>' . "\n" .
        '<b>Name:</b> ' . e($name) . "\n" .
        '<b>Product:</b> ' . e($p['name']) . "\n" .
        '<b>Duration:</b> ' . e($tier[0]) . "\n" .
        '<b>Amount Paid:</b> ' . e(money($tier[1])) . "\n" .
        '<b>Payment Method:</b> ' . e(strtoupper($payment ?: '—')) . "\n" .
        ($uid !== '' ? '<b>UID:</b> ' . e($uid) . "\n" : '') .
        ($note !== '' ? "\n" . '<b>Note:</b> ' . e($note) . "\n" : '') .
        "\n" .
        '<b>SHOP:</b> https://t.me/' . e(TELEGRAM_PUBLIC_CHANNEL);

    [$ok, $msg] = telegram_send_order($caption);
    $_SESSION['flash'] = ['type' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Order sent to Telegram successfully.' : $msg];
    redirect_product($slug);
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
    if (!$matched || !password_verify($password, $matched['password'] ?? '')) {
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
<title><?= $page === 'product' && $product ? e($product['name']) . ' | Project Market' : 'Project Market | KAELHAX' ?></title>
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
footer{border-top:1px solid #1a222c;padding:30px 18px 44px;text-align:center;color:#7e8998;font-size:14px}
.modal-backdrop{position:fixed;z-index:90;inset:0;background:rgba(0,0,0,.72);display:none;align-items:flex-end;justify-content:center;padding:0}.modal-backdrop.open{display:flex}.modal{width:min(650px,100%);max-height:94vh;overflow:auto;background:#0d131b;border:1px solid var(--line);border-radius:21px 21px 0 0;padding:18px 15px 25px}.modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}.modal-head h2{margin:4px 0;font-size:24px}.modal-muted{color:var(--muted);margin:0;font-size:13px}.close{width:38px;height:38px;border:1px solid var(--line);background:var(--surface);color:#cbd4df;border-radius:10px;font-size:24px}
@media(max-width:980px){.shop-grid{grid-template-columns:repeat(2,1fr)}.product-layout{grid-template-columns:1fr;gap:18px}.product-art{margin-top:18px}}
@media(max-width:700px){.site-header{height:80px}.header-inner{padding:0 16px}.brand-logo{width:40px;height:40px}.brand-title{font-size:16px}.drawer{top:80px}.menu-backdrop{inset:80px 0 0}main{padding:23px 14px 49px}.content-head{margin-bottom:18px}.content-head h1{font-size:34px}.content-head p{font-size:14px}.shop-grid{grid-template-columns:1fr;gap:14px}.product-card{border-radius:19px}.product-info{padding:16px 14px 15px}.product-name{font-size:21px}.product-price{font-size:20px}.view-btn{padding:12px;font-size:17px}.product-meta h1{font-size:30px}.product-layout{gap:8px}.product-art{margin-top:17px;border-radius:15px}.price-panel{padding:16px;border-radius:17px}.price-panel h2{font-size:24px}.price-item{padding:12px 11px}.price-right{gap:8px}.amount{font-size:14px}.buy{padding:9px 10px}.details-panel{padding:16px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.auth-shell{padding:18px 13px;min-height:calc(100vh - 80px);align-items:center}.auth-card{padding:19px 15px;border-radius:18px}.auth-card h1{font-size:26px}.admin-panel{grid-template-columns:1fr}.drawer-inner{padding:18px 15px}}
@media(max-width:390px){.brand-title small{display:none}.menu{padding:9px 11px}.menu span{font-size:14px}.drawer-title{font-size:21px}.product-name{font-size:20px}.price-item .amount{font-size:13px}.auth-card{padding:17px 13px}}
</style>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php?page=shop"><img class="brand-logo" src="assets/kaelhax-logo.png" alt="KAELHAX"><div class="brand-title">Project Market<small>KAELHAX</small></div></a>
    <button class="menu" onclick="setMenu(true)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M4 7h16M4 12h16M4 17h16"/></svg><span>Menu</span></button>
  </div>
</header>
<div id="menuBackdrop" class="menu-backdrop" onclick="setMenu(false)"></div>
<aside id="drawer" class="drawer"><div class="drawer-inner">
  <div class="drawer-top"><div><div class="drawer-kicker">Navigation</div><div class="drawer-title">Project Market</div></div><button class="drawer-close" onclick="setMenu(false)">×</button></div>
  <div class="menu-group"><div class="group-title">Explore</div><?= menu_item('index.php?page=shop','Shop','▣','shop') ?><?= menu_item('index.php?page=preview','Preview','▧','preview') ?><?= menu_item('index.php?page=promos','Promos','◇','promos') ?></div>
  <div class="menu-group"><div class="group-title">Support</div><?= menu_item('index.php?page=concerns','Concerns','◌','concerns') ?><?= menu_item('index.php?page=terms','Terms','▤','terms') ?></div>
  <div class="menu-group"><div class="group-title">Account</div><?= menu_item('index.php?page=account','Account','♙','account') ?></div>
  <div class="menu-group"><div class="group-title">Administration</div><?= menu_item('index.php?page=admin','Administrator Login','♢','admin') ?></div>
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
  <section class="content-head"><div class="kicker">Showcase</div><h1>Preview</h1><p>Browse the current KAELHAX project previews before choosing a package.</p></section>
  <section class="shop-grid"><?php foreach ($products as $p): ?><article class="product-card"><div class="product-image"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div><div class="product-info"><div class="badges"><span class="badge">Available</span><?php if($p['promo']): ?><span class="badge promo">Promo</span><?php endif; ?></div><h3 class="product-name"><?= e($p['name']) ?></h3><p class="product-price">From <?= money($p['tiers'][0][1]) ?></p><button class="view-btn" onclick="location.href='index.php?page=product&slug=<?= e($p['slug']) ?>'">View Product</button></div></article><?php endforeach; ?></section>

<?php elseif ($page === 'promos'): ?>
  <section class="content-head"><div class="kicker">Explore</div><h1>Promos</h1><p>Featured products with promotional pricing.</p></section>
  <section class="shop-grid"><?php foreach ($products as $p): if(!$p['promo']) continue; ?><article class="product-card"><div class="product-image"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div><div class="product-info"><div class="badges"><span class="badge">Available</span><span class="badge promo">Promo</span></div><h3 class="product-name"><?= e($p['name']) ?></h3><p class="product-price">From <?= money($p['tiers'][0][1]) ?></p><button class="view-btn" onclick="location.href='index.php?page=product&slug=<?= e($p['slug']) ?>'">View Product</button></div></article><?php endforeach; ?></section>

<?php elseif ($page === 'concerns'): ?>
  <section class="content-head"><div class="kicker">Support</div><h1>Concerns</h1><p>Send an order concern to the administrator through Telegram.</p></section>
  <div class="info-box"><form method="post"><input type="hidden" name="action" value="send_concern"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="form-grid"><div class="field"><label>Telegram Username</label><input name="telegram_username" placeholder="@username" required></div><div class="field"><label>Order ID</label><input name="order_id" placeholder="Optional"></div><div class="field full"><label>Concern</label><textarea name="message" placeholder="Describe your concern..." required></textarea></div></div><button class="primary">Send Concern</button><div class="notice">Your Telegram bot token remains server-side.</div></form></div>

<?php elseif ($page === 'terms'): ?>
  <section class="content-head"><div class="kicker">Information</div><h1>Terms</h1><p>General marketplace and order guidelines.</p></section>
  <div class="info-box"><div class="info-row"><span>Order details</span><strong>Provide accurate information</strong></div><div class="info-row"><span>Order channel</span><strong>Official Telegram channel</strong></div><div class="info-row"><span>Support</span><strong>Use Concerns</strong></div><div class="info-row"><span>Administration</span><strong>Orders are manually verified</strong></div></div>

<?php elseif ($page === 'account'): ?>
  <?php if (is_user()): ?>
    <section class="content-head"><div class="kicker">Account</div><h1>My Account</h1><p>Manage your buyer session.</p></section>
    <div class="info-box"><div class="info-row"><span>Username</span><strong>@<?= e($_SESSION['buyer_username']) ?></strong></div><div class="info-row"><span>Display Name</span><strong><?= e($_SESSION['buyer_name']) ?></strong></div><div class="info-row"><span>Status</span><strong>Signed in</strong></div><form method="post" class="hero-actions"><input type="hidden" name="action" value="logout"><input type="hidden" name="type" value="buyer"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="logout-btn">Log Out</button></form></div>
  <?php else: ?>
    <div class="auth-shell"><div class="auth-card">
      <img class="auth-logo" src="assets/kaelhax-logo.png" alt="KAELHAX"><h1>Buyer Account</h1><p>Login or create an account for your Project Market purchases.</p>
      <div class="auth-tabs"><button id="tabLogin" class="active" onclick="switchAuth('login')">Login</button><button id="tabRegister" onclick="switchAuth('register')">Register</button></div>
      <form id="loginForm" class="auth-form" method="post"><input type="hidden" name="action" value="buyer_login"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Username</label><input name="username" required autocomplete="username"></div><div class="field" style="margin-top:11px"><label>Password</label><input name="password" type="password" required autocomplete="current-password"></div><button class="primary">Login</button></form>
      <form id="registerForm" class="auth-form" method="post" style="display:none"><input type="hidden" name="action" value="buyer_register"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Username</label><input name="username" pattern="[A-Za-z0-9_\.]{3,32}" required></div><div class="field" style="margin-top:11px"><label>Display Name</label><input name="display_name" required></div><div class="field" style="margin-top:11px"><label>Password</label><input name="password" type="password" minlength="6" required></div><button class="primary">Create Account</button></form>
    </div></div>
  <?php endif; ?>

<?php elseif ($page === 'admin'): ?>
  <?php if (is_admin()): ?>
    <section class="content-head"><div class="kicker">Administration</div><h1>Administrator Dashboard</h1><p>Manage the storefront from the secured administrator session.</p></section>
    <div class="info-box"><div class="info-row"><span>Logged in as</span><strong><?= e($_SESSION['admin_username']) ?></strong></div><div class="info-row"><span>Products</span><strong><?= count($products) ?></strong></div><div class="admin-panel"><div class="stat"><strong><?= count($products) ?></strong><span>Catalog Products</span></div><div class="stat"><strong><?= count(load_users()) ?></strong><span>Registered Buyers</span></div><div class="stat"><strong><?= telegram_configured() ? 'READY' : 'NOT SET' ?></strong><span>Telegram Status</span></div></div><form method="post" class="hero-actions"><input type="hidden" name="action" value="logout"><input type="hidden" name="type" value="admin"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="logout-btn">Log Out Administrator</button></form></div>
  <?php else: ?>
    <div class="auth-shell"><div class="auth-card"><img class="auth-logo" src="assets/kaelhax-logo.png" alt="KAELHAX"><h1>Administrator Login</h1><p>Secure access to the KAELHAX Project Market administration area.</p>
      <form class="auth-form" method="post"><input type="hidden" name="action" value="admin_login"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Administrator Username</label><input name="username" required autocomplete="username"></div><div class="field" style="margin-top:11px"><label>Password</label><input name="password" type="password" required autocomplete="current-password"></div><button class="primary">Administrator Login</button><div class="notice">Credentials come from config.php or environment variables. Defaults are admin / admin123 until changed.</div></form>
    </div></div>
  <?php endif; ?>

<?php else: ?>
  <section class="content-head"><div class="kicker">Catalog</div><h1>Shop</h1><p>Browse all administrator managed digital projects and packages.</p></section>
  <section class="shop-grid">
    <?php foreach ($products as $p): ?><article class="product-card"><div class="product-image"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div><div class="product-info"><div class="badges"><span class="badge">Available</span><?php if ($p['promo']): ?><span class="badge promo">Promo</span><?php endif; ?></div><h3 class="product-name"><?= e($p['name']) ?></h3><p class="product-price">From <?= money($p['tiers'][0][1]) ?></p><button class="view-btn" onclick="location.href='index.php?page=product&slug=<?= e($p['slug']) ?>'">View Product</button></div></article><?php endforeach; ?>
  </section>
<?php endif; ?>
</main>
<footer>Project Market Digital project marketplace</footer>

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

    <form method="post" class="order-form">
      <input type="hidden" name="action" value="send_order">
      <input type="hidden" name="slug" value="${esc(slug)}">
      <input type="hidden" name="tier" value="${tierIndex}">
      <input type="hidden" name="csrf" value="${esc(csrf)}">

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
          <label for="oNote">Note</label>
          <textarea id="oNote" name="note" placeholder="Optional note"></textarea>
        </div>
      </div>

      <button class="primary" type="submit">Send Order to Telegram</button>
      <div class="notice">Your order details will be sent to the configured Telegram channel with <b>assets/order-banner.jpg</b> as the banner.</div>
    </form>
  `;

  openModal();
}
function switchAuth(mode){const login=document.getElementById('loginForm'),reg=document.getElementById('registerForm');document.getElementById('tabLogin').classList.toggle('active',mode==='login');document.getElementById('tabRegister').classList.toggle('active',mode==='register');login.style.display=mode==='login'?'block':'none';reg.style.display=mode==='register'?'block':'none'}
document.getElementById('modalBackdrop').addEventListener('click',e=>{if(e.target.id==='modalBackdrop')closeModal()});
</script>
</body>
</html>
