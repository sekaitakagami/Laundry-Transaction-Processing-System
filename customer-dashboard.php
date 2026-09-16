<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$ordersModel = new Orders($db);
$userId      = currentUserId();
$orders      = $ordersModel->findByUser($userId);

$totalOrders  = count($orders);
$activeOrders = 0;
$totalSpent   = 0.0;
$spentToday   = 0.0;
$nextPickup   = null;
$today        = date('Y-m-d');

foreach ($orders as $o) {
    if ($o['order_status'] !== 'finished') $activeOrders++;
    if ($o['payment_status'] === 'paid')   $totalSpent += (float)$o['amount'];

    if ($o['payment_status'] === 'paid'
        && date('Y-m-d', strtotime($o['created_at'])) === $today) {
        $spentToday += (float)$o['amount'];
    }

    if (!empty($o['schedule_date']) && $o['order_status'] !== 'finished') {
        $ts = strtotime($o['schedule_date']);
        if ($nextPickup === null || $ts < $nextPickup) $nextPickup = $ts;
    }
}

/* Chart data — this customer only */
$currentMonth = date('Y-m');
$daysInMonth  = (int)date('t');
$dailySpend   = array_fill(1, $daysInMonth, 0);

foreach ($orders as $o) {
    if (date('Y-m', strtotime($o['created_at'])) === $currentMonth) {
        $d = (int)date('j', strtotime($o['created_at']));
        $dailySpend[$d] += (float)$o['amount'];
    }
}

$revenueData = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $revenueData[] = ['day' => $d, 'total' => $dailySpend[$d]];
}

include __DIR__ . '/includes/customer-header.php';
?>

<main>

    <!-- Hero panel with Make Order -->
    <section class="customer-hero">
        <div class="hero-text">
            <h2>Hi, <?= h(explode('@', $_SESSION['user_email'])[0]) ?> 👋</h2>
            <p>Track your laundry, schedule a pickup, or place a new order.</p>
        </div>
        <a href="order.php" class="hero-cta">+ Make New Order</a>
    </section>

    <!-- STATS -->
    <section class="stats">
        <div class="stat-card">
            <p class="label">MY ORDERS</p>
            <h2><?= $totalOrders ?></h2>
            <p class="description">All time</p>
        </div>
        <div class="stat-card">
            <p class="label">ACTIVE</p>
            <h2><?= $activeOrders ?></h2>
            <p class="description">In progress</p>
        </div>
        <div class="stat-card">
            <p class="label">SPENT TODAY</p>
            <h2>&#8369; <?= number_format($spentToday, 2) ?></h2>
            <p class="description">Paid today</p>
        </div>
        <div class="stat-card">
            <p class="label">NEXT PICKUP</p>
            <h2><?= $nextPickup ? date('M j', $nextPickup) : '—' ?></h2>
            <p class="description">Scheduled</p>
        </div>
    </section>

    <!-- Orders table — no delete, no payment toggle -->
    <section class="orders-section">
        <div class="table-toolbar">
            <div class="filter-tabs">
                <span class="filter-label">MY ORDERS</span>
                <button type="button" class="filter-btn active">ALL</button>
                <button type="button" class="filter-btn">PENDING</button>
                <button type="button" class="filter-btn">WASHING</button>
                <button type="button" class="filter-btn">FINISHED</button>
            </div>
            <input type="text" class="search-orders" placeholder="Search orders">
        </div>

        <table class="orders-table">
            <thead>
                <tr>
                    <th>ORDER ID</th>
                    <th>SERVICE</th>
                    <th>WEIGHT</th>
                    <th>AMOUNT</th>
                    <th>DATE</th>
                    <th>SCHEDULE</th>
                    <th>ORDER STATUS</th>
                    <th>PAYMENT</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:48px 24px;color:#5a7280;">
                            You haven't placed any orders yet.<br>
                            <a href="order.php" style="color:#0d5c72;font-weight:600;text-decoration:underline;margin-top:8px;display:inline-block;">
                                Place your first order →
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= h($order['order_code']) ?></td>
                            <td><?= h($order['service_type'] ?? 'N/A') ?></td>
                            <td><?= h($order['weight_kg'] ?? '0') ?> kg</td>
                            <td>&#8369; <?= number_format((float)$order['amount'], 2) ?></td>
                            <td><?= date('M j', strtotime($order['created_at'])) ?></td>
                            <td><?= !empty($order['schedule_date']) ? date('M j', strtotime($order['schedule_date'])) : '—' ?></td>
                            <td>
                                <span class="status-text <?= strtolower($order['order_status']) ?>">
                                    <?= ucfirst($order['order_status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="payment-text" style="color: <?= $order['payment_status'] === 'paid' ? '#2e9d7a' : '#e06868' ?>; font-weight:600;">
                                    <?= ucfirst($order['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="update.php?id=<?= $order['id'] ?>" class="action-link">VIEW</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="table-footer">
            <p class="showing-text">SHOWING <?= count($orders) ?> OF <?= $totalOrders ?> ORDERS</p>
        </div>
    </section>

    <!-- Chart + Quick actions -->
    <section class="bottom-grid">
        <div class="panel revenue-panel">
            <p class="panel-title">MY SPENDING &mdash; THIS MONTH</p>
            <div class="revenue-chart">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <div class="panel quick-actions-panel">
            <p class="panel-title">QUICK ACTIONS</p>

            <button type="button" class="quick-action-btn">
                <a href="order.php">+ Make New Order</a>
            </button>

            <div class="update-panel">
                <p class="panel-title" style="margin:0 0 12px;">MY PROFILE</p>
                <p style="font-size:13px;color:#5a7280;margin-bottom:6px;">
                    <strong style="color:#0d5c72;">Email:</strong> <?= h($_SESSION['user_email']) ?>
                </p>
                <p style="font-size:13px;color:#5a7280;">
                    <strong style="color:#0d5c72;">Orders:</strong> <?= $totalOrders ?>
                </p>
            </div>
        </div>
    </section>
</main>

<script>
    window.revenueData = <?= json_encode($revenueData) ?>;
</script>
<script src="script/dashboard.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>