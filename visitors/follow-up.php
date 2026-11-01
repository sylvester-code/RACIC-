<?php
// visitors/follow-up.php - Record a Visitor Care Follow-up Interaction
$pageTitle = 'Record Follow-up Interaction';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader', 'church_worker']);

$db = getDB();
$user = currentUser();
$visitorId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$stmt = $db->prepare("SELECT * FROM visitors WHERE id = ?");
$stmt->execute([$visitorId]);
$visitor = $stmt->fetch();

if (!$visitor) {
    flash('danger', 'Visitor record not found.');
    header('Location: ' . BASE_URL . 'visitors/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $contactType = $_POST['contact_type'] ?? 'Phone Call';
        $followUpDate = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        $nextActionDate = !empty($_POST['next_action_date']) ? $_POST['next_action_date'] : null;
        $newStatus = $_POST['new_status'] ?? $visitor['follow_up_status'];

        if (empty($notes)) $errors[] = "Follow-up conversation notes and feedback are required.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Insert into visitor_followups
                $ins = $db->prepare("
                    INSERT INTO visitor_followups (
                        visitor_id, follow_up_date, contact_type, notes,
                        next_action_date, conducted_by_user_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $ins->execute([
                    $visitorId, $followUpDate, $contactType, $notes,
                    $nextActionDate, $user['id']
                ]);

                // Update visitor current status
                $upd = $db->prepare("
                    UPDATE visitors 
                    SET follow_up_status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $upd->execute([$newStatus, $visitorId]);

                $db->commit();
                logAudit('FOLLOWUP_VISITOR', 'Visitors', $visitorId, "Logged {$contactType} follow-up for visitor {$visitor['visitor_code']}");
                flash('success', "Follow-up interaction for '{$visitor['first_name']} {$visitor['last_name']}' recorded successfully!");
                header("Location: " . BASE_URL . "visitors/view.php?id={$visitorId}");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Error saving follow-up: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-phone-volume text-success me-2"></i>Log Visitor Follow-up</h4>
                <small class="text-muted">Document outreach calls, pastoral visits, and prayer support.</small>
            </div>
            <a href="<?= BASE_URL ?>visitors/view.php?id=<?= $visitor['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Profile
            </a>
        </div>

        <!-- Visitor Banner -->
        <div class="alert alert-light border shadow-sm mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-weight: bold;">
                    <?= strtoupper(substr($visitor['first_name'], 0, 1)) ?>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0"><?= e($visitor['first_name'] . ' ' . $visitor['last_name']) ?> (<?= e($visitor['visitor_code']) ?>)</h6>
                    <div class="small text-muted">
                        <i class="fas fa-phone me-1"></i><a href="tel:<?= e($visitor['phone']) ?>" class="fw-semibold text-decoration-none"><?= e($visitor['phone']) ?></a> &bull; 
                        Visited: <?= formatDate($visitor['visit_date']) ?> &bull; 
                        Status: <?= getStatusBadge($visitor['follow_up_status']) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="church-card">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrfField(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Contact Method <span class="text-danger">*</span></label>
                            <select name="contact_type" class="form-select" required>
                                <option value="Phone Call" selected>Phone Call</option>
                                <option value="SMS/WhatsApp">SMS / WhatsApp Message</option>
                                <option value="Home Visit">Home Visit / Visitation</option>
                                <option value="Personal Meeting">Personal Meeting at Church</option>
                                <option value="Email">Email</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Follow-up Date <span class="text-danger">*</span></label>
                            <input type="date" name="follow_up_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Conversation Details & Spiritual Response <span class="text-danger">*</span></label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="Detail the conversation: Did they appreciate the Sunday service? Did you pray with them? Any special needs or desire to join a ministry?" required></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Update Visitor Status</label>
                            <select name="new_status" class="form-select">
                                <option value="Contacted" <?= $visitor['follow_up_status'] === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
                                <option value="Visited" <?= $visitor['follow_up_status'] === 'Visited' ? 'selected' : '' ?>>Visited in Person</option>
                                <option value="Prayed With" <?= $visitor['follow_up_status'] === 'Prayed With' ? 'selected' : '' ?>>Prayed With</option>
                                <option value="Converted" <?= $visitor['follow_up_status'] === 'Converted' ? 'selected' : '' ?>>Ready to Join Church</option>
                                <option value="Not Interested">Not Interested / Moved away</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Next Planned Follow-up Date</label>
                            <input type="date" name="next_action_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>visitors/view.php?id=<?= $visitor['id'] ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-check-circle me-1"></i>Save Follow-up Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

