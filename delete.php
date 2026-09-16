<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    header('Location: ' . (isAdmin() ? 'dashboard.php' : 'customer-dashboard.php'));
    exit;
}

$id = (int)$_POST['id'];
$orders = new Orders($db);
$order = $orders->findById($id);
$back = isAdmin() ? 'dashboard.php' : 'customer-dashboard.php';

if (!$order || (!isAdmin() && (int)$order['user_id'] !== currentUserId())) {
    header("Location: {$back}?msg=error");
    exit;
}

if ($orders->delete($id)) {
    header("Location: {$back}?msg=deleted");
    exit;
} else {
    header("Location: {$back}?msg=error");
    exit;
}