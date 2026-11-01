<?php
// activities/delete.php - Remove an Activity Record
require_once __DIR__ . '/../config/app.php';
requireRole(['super_admin', 'head_pastor']);

if (!validateCSRF()) {
    flash('danger', 'Security token invalid or expired.');
    header('Location: ' . BASE_URL . 'activities/index.php');
    exit;
}

$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

$stmt = $db->prepare("SELECT activity_code, title FROM activities WHERE id = ?");
$stmt->execute([$id]);
$act = $stmt->fetch();

if ($act) {
    $del = $db->prepare("DELETE FROM activities WHERE id = ?");
    $del->execute([$id]);

    logAudit('DELETE_ACTIVITY', 'Activities', $id, "Deleted activity '{$act['title']}' ({$act['activity_code']})");
    flash('success', "Activity '{$act['title']}' has been permanently deleted.");
} else {
    flash('warning', 'Activity record not found.');
}

header('Location: ' . BASE_URL . 'activities/index.php');
exit;

