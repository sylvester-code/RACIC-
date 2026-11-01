<?php
// visitors/view.php - Visitor Profile & Follow-up History
$pageTitle = 'Visitor Profile & Follow-up';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();
$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT v.*, m.first_name as inviter_first, m.last_name as inviter_last, m.member_code as inviter_code,
           u.full_name as assigned_worker_name,
           cm.member_code as converted_member_code, cm.id as converted_member_id
    FROM visitors v
    LEFT JOIN members m ON m.id = v.invited_by_member_id
    LEFT JOIN users u ON u.id = v.assigned_to_user_id
    LEFT JOIN members cm ON cm.id = v.converted_to_member_id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$visitor = $stmt->fetch();

if (!$visitor) {
    flash('danger', 'Visitor not found.');
    header('Location: ' . BASE_URL . 'visitors/index.php');
    exit;
}

// Fetch all follow-up history records
$fuStmt = $db->prepare("
    SELECT vf.*, u.full_name as worker_name
    FROM visitor_followups vf
    INNER JOIN users u ON u.id = vf.conducted_by_user_id
    WHERE vf.visitor_id = ?
    ORDER BY vf.follow_up_date DESC, vf.id DESC
");
$fuStmt->execute([$id]);
$followups = $fuStmt->fetchAll();
?>

<!-- Header Bar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary font-monospace fs-6"><?= e($visitor['visitor_code']) ?></span>
            <h3 class="fw-bold text-dark mb-0"><?= e($visitor['first_name'] . ' ' . $visitor['last_name']) ?></h3>
            <?= getStatusBadge($visitor['follow_up_status']) ?>
        </div>
        <p class="text-muted mb-0 small">
            First visited RACIC on <strong><?= formatDate($visitor['visit_date'], 'l, F j, Y') ?></strong>
        </p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>visitors/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Visitors List
        </a>
        <a href="<?= BASE_URL ?>visitors/follow-up.php?id=<?= $visitor['id'] ?>" class="btn btn-success btn-sm">
            <i class="fas fa-phone-volume me-1"></i>Log Follow-up
        </a>
        <?php if ($visitor['follow_up_status'] !== 'Converted'): ?>
            <a href="<?= BASE_URL ?>visitors/convert.php?id=<?= $visitor['id'] ?>" class="btn btn-warning btn-sm text-dark">
                <i class="fas fa-user-check me-1"></i>Convert to Member
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>members/view.php?id=<?= $visitor['converted_member_id'] ?>" class="btn btn-info btn-sm text-dark">
                <i class="fas fa-id-badge me-1"></i>View Member Profile (<?= e($visitor['converted_member_code']) ?>)
            </a>
        <?php endif; ?>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Visitor Profile & Details -->
    <div class="col-lg-5">
        <div class="church-card mb-4">
            <div class="card-header">
                <span><i class="fas fa-user text-primary me-2"></i>Visitor Information</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Gender:</span>
                        <strong><?= e($visitor['gender']) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Phone:</span>
                        <a href="tel:<?= e($visitor['phone']) ?>" class="fw-bold"><?= e($visitor['phone']) ?></a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Email:</span>
                        <strong><?= e($visitor['email'] ?: '—') ?></strong>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1">Residential Area / Address:</span>
                        <span class="text-secondary"><?= e($visitor['address'] ?: '—') ?></span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1">Invited By:</span>
                        <?php if ($visitor['invited_by_member_id']): ?>
                            <span class="fw-bold text-dark"><i class="fas fa-user-check text-success me-1"></i><?= e($visitor['inviter_first'] . ' ' . $visitor['inviter_last']) ?> (<?= e($visitor['inviter_code']) ?>)</span>
                        <?php else: ?>
                            <span class="text-dark"><?= e($visitor['invited_by_name'] ?: 'None specified') ?></span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Assigned Care Worker:</span>
                        <strong><?= e($visitor['assigned_worker_name'] ?: 'Unassigned') ?></strong>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Prayer Requests Card -->
        <div class="church-card mb-4">
            <div class="card-header bg-warning bg-opacity-10 text-dark">
                <span><i class="fas fa-praying-hands text-warning me-2"></i>Prayer Requests & Spiritual Needs</span>
            </div>
            <div class="card-body">
                <div class="p-3 bg-light rounded border text-dark">
                    <?= nl2br(e($visitor['prayer_requests'] ?: 'No specific prayer requests noted during reception.')) ?>
                </div>
            </div>
        </div>

        <!-- General Notes -->
        <?php if (!empty($visitor['notes'])): ?>
            <div class="church-card">
                <div class="card-header">
                    <span><i class="fas fa-sticky-note text-secondary me-2"></i>Reception Notes</span>
                </div>
                <div class="card-body">
                    <p class="mb-0 text-secondary small"><?= nl2br(e($visitor['notes'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Follow-up Timeline & Logs -->
    <div class="col-lg-7">
        <div class="church-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history text-primary me-2"></i>Follow-up History & Pastoral Care</span>
                <a href="<?= BASE_URL ?>visitors/follow-up.php?id=<?= $visitor['id'] ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i>Log Contact
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($followups)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-phone-slash fs-1 mb-2 text-secondary"></i>
                        <h6>No Follow-up Interactions Logged Yet</h6>
                        <p class="small">Reach out to this visitor and document the phone conversation, home visit, or prayer interaction.</p>
                        <a href="<?= BASE_URL ?>visitors/follow-up.php?id=<?= $visitor['id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-phone-volume me-1"></i>Record First Follow-up
                        </a>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($followups as $fu): ?>
                            <div class="border-start border-3 border-primary ps-3 pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-light text-primary border fw-semibold">
                                        <i class="fas fa-bullseye me-1"></i><?= e($fu['contact_type']) ?>
                                    </span>
                                    <small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?= formatDate($fu['follow_up_date']) ?></small>
                                </div>
                                <div class="p-3 bg-light rounded border my-2 text-dark small">
                                    <?= nl2br(e($fu['notes'])) ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small text-secondary">
                                    <span><i class="fas fa-user-check me-1"></i>Conducted by: <strong><?= e($fu['worker_name']) ?></strong></span>
                                    <?php if ($fu['next_action_date']): ?>
                                        <span class="text-danger fw-semibold"><i class="far fa-clock me-1"></i>Next Contact: <?= formatDate($fu['next_action_date']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

