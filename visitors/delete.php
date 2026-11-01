<?php
// visitors/delete.php - Remove Visitor Record
require_once __DIR__ . '/../config/app.php';
requireRole(['super_admin', 'head_pastor']);

if (!validateCSRF()) {
    flash('danger', 'Security token invalid or expired.');
    header('Location: ' . BASE_URL . 'visitors/index.php');
    exit;
}

$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

$stmt = $db->prepare("SELECT visitor_code, first_name, last_name FROM visitors WHERE id = ?");
$stmt->execute([$id]);
$v = $stmt->fetch();

if ($v) {
    $del = $db->prepare("DELETE FROM visitors WHERE id = ?");
    $del->execute([$id]);

    logAudit('DELETE_VISITOR', 'Visitors', $id, "Deleted visitor record {$v['first_name']} {$v['last_name']} ({$v['visitor_code']})");
    flash('success', "Visitor '{$v['first_name']} {$v['last_name']}' deleted successfully.");
} else {
    flash('warning', 'Visitor record not found.');
}

header('Location: ' . BASE_URL . 'visitors/index.php');
exit;

