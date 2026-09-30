<?php
/*
 * KAELHAX Pro Upgrades
 * Additive helpers only. Existing order/chat/auth flows remain untouched.
 */

function pro_amount_value($value) {
    $value = str_replace(',', '', (string)$value);
    $value = preg_replace('/[^0-9.\-]/', '', $value);
    return is_numeric($value) ? (float)$value : 0.0;
}

function pro_order_belongs_to_buyer($order, $username, $guestIds = []) {
    if (!is_array($order)) return false;

    $username = strtolower(trim((string)$username));
    $owner = strtolower(trim((string)($order['buyer_username'] ?? '')));

    /*
     * For an authenticated dashboard, explicit account ownership wins.
     * Guest session IDs are not accepted as a fallback because they can
     * otherwise surface orders that were not assigned to the current user.
     */
    if ($username !== '') {
        return $owner !== '' && hash_equals($username, $owner);
    }

    $id = (string)($order['id'] ?? '');
    return $id !== '' && in_array($id, $guestIds, true);
}

function pro_status_meta($status) {
    $status = strtolower(trim((string)$status));
    $map = [
        'pending' => ['label' => 'PENDING', 'icon' => '🕐', 'step' => 1],
        'accepted' => ['label' => 'ACCEPTED', 'icon' => '✓', 'step' => 2],
        'processing' => ['label' => 'PROCESSING', 'icon' => '⚙', 'step' => 3],
        'completed' => ['label' => 'COMPLETED', 'icon' => '✓', 'step' => 4],
        'ignored' => ['label' => 'IGNORED', 'icon' => '×', 'step' => 0],
    ];
    return $map[$status] ?? $map['pending'];
}

function pro_order_stats($orders, $buyers = [], $products = [], $conversations = []) {
    $stats = [
        'total' => 0, 'pending' => 0, 'accepted' => 0, 'processing' => 0,
        'completed' => 0, 'ignored' => 0, 'revenue' => 0.0,
        'unique_buyers' => 0, 'active_products' => count($products),
        'unread_messages' => 0, 'product_counts' => [], 'recent' => []
    ];

    $buyerKeys = [];
    foreach ((array)$orders as $order) {
        if (!is_array($order) || empty($order['id'])) continue;
        $stats['total']++;
        $status = strtolower((string)($order['status'] ?? 'pending'));
        if (!isset($stats[$status])) $status = 'pending';
        $stats[$status]++;
        if (in_array($status, ['accepted','processing','completed'], true)) {
            $stats['revenue'] += pro_amount_value($order['amount'] ?? 0);
        }

        $buyer = strtolower(trim((string)($order['buyer_username'] ?? $order['telegram_username'] ?? '')));
        if ($buyer !== '') $buyerKeys[$buyer] = true;

        $product = trim((string)($order['product'] ?? 'Unknown Product'));
        if (!isset($stats['product_counts'][$product])) {
            $stats['product_counts'][$product] = ['orders' => 0, 'revenue' => 0.0];
        }
        $stats['product_counts'][$product]['orders']++;
        $stats['product_counts'][$product]['revenue'] += pro_amount_value($order['amount'] ?? 0);

        $stats['recent'][] = $order;
    }

    foreach ((array)$conversations as $conversation) {
        if (function_exists('chat_unread_count')) {
            $stats['unread_messages'] += chat_unread_count($conversation, 'admin');
        }
    }

    $stats['unique_buyers'] = count($buyerKeys);
    usort($stats['recent'], function($a,$b) {
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });
    $stats['recent'] = array_slice($stats['recent'], 0, 8);

    uasort($stats['product_counts'], function($a,$b) {
        return ($b['orders'] <=> $a['orders']) ?: ($b['revenue'] <=> $a['revenue']);
    });
    $stats['product_counts'] = array_slice($stats['product_counts'], 0, 5, true);

    return $stats;
}

function pro_render_order_timeline($order) {
    $status = strtolower((string)($order['status'] ?? 'pending'));
    $step = (int)(pro_status_meta($status)['step'] ?? 1);
    $events = [
        ['key'=>'submitted','label'=>'Submitted','time'=>$order['created_at'] ?? ''],
        ['key'=>'accepted','label'=>'Accepted','time'=>$status !== 'pending' && $status !== 'ignored' ? ($order['reviewed_at'] ?? '') : ''],
        ['key'=>'processing','label'=>'Processing','time'=>$status === 'processing' || $status === 'completed' ? ($order['status_updated_at'] ?? '') : ''],
        ['key'=>'completed','label'=>'Completed','time'=>$status === 'completed' ? ($order['status_updated_at'] ?? '') : ''],
    ];
    if ($status === 'ignored') {
        $events[1] = ['key'=>'ignored','label'=>'Ignored','time'=>$order['reviewed_at'] ?? ''];
    }

    echo '<div class="pro-timeline">';
    foreach ($events as $index => $event) {
        $done = $status === 'ignored' ? $event['key'] === 'submitted' || $event['key'] === 'ignored' : $index < $step;
        $current = $status !== 'ignored' && $index + 1 === $step;
        echo '<div class="pro-timeline-item ' . ($done ? 'done ' : '') . ($current ? 'current' : '') . '">';
        echo '<span class="pro-timeline-dot">' . e($done ? '✓' : ($current ? '•' : '')) . '</span>';
        echo '<div><strong>' . e($event['label']) . '</strong>';
        if (!empty($event['time'])) echo '<span>' . e(date('M d, Y g:i A', strtotime($event['time']))) . '</span>';
        echo '</div></div>';
    }
    echo '</div>';
}

function pro_render_admin_dashboard($orders, $buyers, $products) {
    $conversations = function_exists('chat_list_conversations') ? chat_list_conversations() : [];
    $stats = pro_order_stats($orders, $buyers, $products, $conversations);
    ?>
    <section class="pro-dashboard">
      <div class="pro-dashboard-header">
        <div>
          <div class="kicker">System Overview</div>
          <h2>Pro Control Center</h2>
          <p>Live operational summary for orders, customers, products, messages, and confirmed revenue.</p>
        </div>
        <div class="pro-live-badge"><span></span> LIVE DATA</div>
      </div>

      <div class="pro-kpi-grid">
        <article class="pro-kpi"><span class="pro-kpi-icon">🛒</span><div><strong><?= e($stats['total']) ?></strong><span>Total Orders</span></div></article>
        <article class="pro-kpi"><span class="pro-kpi-icon">💳</span><div><strong><?= e(money($stats['revenue'])) ?></strong><span>Confirmed Revenue</span></div></article>
        <article class="pro-kpi"><span class="pro-kpi-icon">👥</span><div><strong><?= e($stats['unique_buyers']) ?></strong><span>Unique Buyers</span></div></article>
        <article class="pro-kpi"><span class="pro-kpi-icon">💬</span><div><strong><?= e($stats['unread_messages']) ?></strong><span>Unread Messages</span></div></article>
      </div>

      <div class="pro-two-col">
        <section class="pro-card">
          <div class="pro-card-head"><div><h3>Order Pipeline</h3><span>Current order distribution</span></div><a class="small-btn" href="index.php?page=admin&tab=orders">Manage Orders →</a></div>
          <?php foreach (['pending','accepted','processing','completed','ignored'] as $status): ?>
            <?php $count=(int)($stats[$status]??0); $pct=$stats['total']>0?round(($count/$stats['total'])*100):0; $meta=pro_status_meta($status); ?>
            <div class="pro-bar-row"><div class="pro-bar-label"><span><?= e($meta['icon'].' '.$meta['label']) ?></span><strong><?= e($count) ?></strong></div><div class="pro-bar"><span style="width:<?= e($pct) ?>%"></span></div></div>
          <?php endforeach; ?>
        </section>

        <section class="pro-card">
          <div class="pro-card-head"><div><h3>Catalog Snapshot</h3><span>Top products by order count</span></div><a class="small-btn" href="index.php?page=admin&tab=products">Manage Products →</a></div>
          <?php if (!$stats['product_counts']): ?>
            <div class="pro-empty">No order data yet.</div>
          <?php else: ?>
            <?php $rank=1; foreach($stats['product_counts'] as $name=>$item): ?>
              <div class="pro-product-row"><span class="pro-rank"><?= e($rank++) ?></span><div><strong><?= e($name) ?></strong><span><?= e($item['orders']) ?> order<?= $item['orders']===1?'':'s' ?></span></div><b><?= e(money($item['revenue'])) ?></b></div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </div>

      <section class="pro-card pro-recent">
        <div class="pro-card-head">
          <div><h3>Recent Orders</h3><span>Latest activity across the marketplace</span></div>
          <div class="pro-tools">
            <input type="search" class="pro-search" data-pro-order-search placeholder="Search order, buyer, product..." aria-label="Search orders">
            <select class="pro-filter" data-pro-order-filter aria-label="Filter orders">
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="accepted">Accepted</option>
              <option value="processing">Processing</option>
              <option value="completed">Completed</option>
              <option value="ignored">Ignored</option>
            </select>
          </div>
        </div>
        <div class="pro-order-list" data-pro-order-list>
          <?php if (!$stats['recent']): ?>
            <div class="pro-empty">No orders submitted yet.</div>
          <?php else: ?>
            <?php foreach($stats['recent'] as $order): $status=strtolower((string)($order['status']??'pending')); ?>
              <article class="pro-order-row" data-status="<?= e($status) ?>" data-search="<?= e(strtolower(($order['id']??'').' '.($order['buyer_name']??'').' '.($order['product']??''))) ?>">
                <div class="pro-order-main"><span class="pro-order-id"><?= e($order['id']) ?></span><strong><?= e($order['product']??'Order') ?></strong><span><?= e($order['buyer_name']??'Buyer') ?><?php if(!empty($order['buyer_username'])): ?> • @<?= e($order['buyer_username']) ?><?php endif; ?></span></div>
                <div class="pro-order-status"><span class="status-pill status-<?= e($status) ?>"><?= e(pro_status_meta($status)['label']) ?></span><b><?= e($order['amount']??'') ?></b><small><?= !empty($order['created_at']) ? e(date('M d, Y g:i A',strtotime($order['created_at']))) : '—' ?></small></div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="pro-quick-grid">
        <a href="index.php?page=admin&tab=orders" class="pro-quick"><span>🧾</span><div><strong>Review Orders</strong><small><?= e($stats['pending']) ?> pending review</small></div>→</a>
        <a href="index.php?page=admin&tab=messages" class="pro-quick"><span>💬</span><div><strong>Open Inbox</strong><small><?= e($stats['unread_messages']) ?> unread message<?= $stats['unread_messages']===1?'':'s' ?></small></div>→</a>
        <a href="index.php?page=admin&tab=buyers" class="pro-quick"><span>👤</span><div><strong>Buyer Manager</strong><small><?= e(count($buyers)) ?> registered account<?= count($buyers)===1?'':'s' ?></small></div>→</a>
        <a href="index.php?page=admin&tab=settings" class="pro-quick"><span>⚙</span><div><strong>System Settings</strong><small>Website and checkout controls</small></div>→</a>
      </section>
    </section>
    <?php
}

function pro_render_buyer_dashboard($orders) {
    $username = strtolower(trim((string)($_SESSION['buyer_username'] ?? '')));
    $guestIds = is_array($_SESSION['guest_order_ids'] ?? null) ? $_SESSION['guest_order_ids'] : [];
    $mine = [];
    foreach ((array)$orders as $order) {
        if (pro_order_belongs_to_buyer($order, $username, $guestIds)) $mine[] = $order;
    }
    usort($mine, function($a,$b){ return strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')); });

    $counts = ['pending'=>0,'accepted'=>0,'processing'=>0,'completed'=>0,'ignored'=>0];
    $confirmed = 0.0;
    foreach ($mine as $order) {
        $status = strtolower((string)($order['status']??'pending'));
        if (!isset($counts[$status])) $status='pending';
        $counts[$status]++;
        if (in_array($status,['accepted','processing','completed'],true)) $confirmed += pro_amount_value($order['amount']??0);
    }
    ?>
    <section class="pro-dashboard pro-buyer-dashboard">
      <div class="pro-dashboard-header">
        <div><div class="kicker">Account Overview</div><h2>Your Activity</h2><p>A quick view of your orders and their current progress.</p></div>
        <a class="small-btn" href="index.php?page=my-orders">Open My Orders →</a>
      </div>
      <div class="pro-kpi-grid">
        <article class="pro-kpi"><span class="pro-kpi-icon">🧾</span><div><strong><?= e(count($mine)) ?></strong><span>Total Orders</span></div></article>
        <article class="pro-kpi"><span class="pro-kpi-icon">🕐</span><div><strong><?= e($counts['pending']) ?></strong><span>Pending Review</span></div></article>
        <article class="pro-kpi"><span class="pro-kpi-icon">⚙</span><div><strong><?= e($counts['processing']) ?></strong><span>Processing</span></div></article>
        <article class="pro-kpi"><span class="pro-kpi-icon">💳</span><div><strong><?= e(money($confirmed)) ?></strong><span>Confirmed Total</span></div></article>
      </div>
      <?php if ($mine): ?>
        <section class="pro-card pro-recent"><div class="pro-card-head"><div><h3>Latest Orders</h3><span>Tap an order from My Orders for the full record.</span></div></div>
          <?php foreach(array_slice($mine,0,4) as $order): $status=strtolower((string)($order['status']??'pending')); $meta=pro_status_meta($status); ?>
            <article class="pro-buyer-order">
              <div class="pro-order-main"><span class="pro-order-id"><?= e($order['id']) ?></span><strong><?= e($order['product']??'Order') ?></strong><span><?= e($order['duration']??'Package') ?> • <?= e($order['amount']??'') ?></span></div>
              <div class="pro-buyer-order-side"><span class="status-pill status-<?= e($status) ?>"><?= e($meta['label']) ?></span><small><?= !empty($order['created_at']) ? e(date('M d, Y',strtotime($order['created_at']))) : '—' ?></small></div>
              <?= pro_render_order_timeline($order) ?>
            </article>
          <?php endforeach; ?>
        </section>
      <?php else: ?>
        <div class="pro-card pro-empty-state"><div class="pro-empty-icon">🛍️</div><h3>No orders yet</h3><p>Once you submit a purchase, its progress will appear here.</p><a class="small-btn primary" href="index.php?page=shop">Browse Shop →</a></div>
      <?php endif; ?>
    </section>
    <?php
}
