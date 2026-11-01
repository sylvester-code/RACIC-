<?php
// members/delete.php - Remove Member Record
require_once __DIR__ . '/../config/app.php';
requireRole(['super_admin', 'head_pastor']);

if (!validateCSRF()) {
    flash('danger', 'Security token invalid or expired.');
    header('Location: ' . BASE_URL . 'members/index.php');
    exit;
}

$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

$stmt = $db->prepare("SELECT member_code, first_name, last_name, photo FROM members WHERE id = ?");
$stmt->execute([$id]);
$mem = $stmt->fetch();

if ($mem) {
    if (!empty($mem['photo']) && file_exists(UPLOAD_PATH . $mem['photo'])) {
        @unlink(UPLOAD_PATH . $mem['photo']);
    }

    $del = $db->prepare("DELETE FROM members WHERE id = ?");
    $del->execute([$id]);

    logAudit('DELETE_MEMBER', 'Members', $id, "Deleted member record {$mem['first_name']} {$mem['last_name']} ({$mem['member_code']})");
    flash('success', "Member '{$mem['first_name']} {$mem['last_name']}' deleted successfully.");
} else {
    flash('warning', 'Member record not found.');
}

header('Location: ' . BASE_URL . 'members/index.php');
exit;

