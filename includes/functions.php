<?php
// includes/functions.php - Common Utility Functions for RACIC CMS

/**
 * Escape HTML output for XSS protection
 */
if (!function_exists('e')) {
    function e(?string $string): string {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Set flash alert message
 */
if (!function_exists('flash')) {
    function flash(string $type, string $message): void {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }
}

/**
 * Render all flash messages
 */
if (!function_exists('displayFlash')) {
    function displayFlash(): string {
        if (empty($_SESSION['flash'])) {
            return '';
        }

        $html = '';
        foreach ($_SESSION['flash'] as $flash) {
            $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info', 'primary']) ? $flash['type'] : 'info';
            $icon = match($type) {
                'success' => 'fa-check-circle',
                'danger' => 'fa-exclamation-triangle',
                'warning' => 'fa-exclamation-circle',
                'info', 'primary' => 'fa-info-circle',
                default => 'fa-bell'
            };
            $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show shadow-sm" role="alert">
                        <i class="fas ' . $icon . ' me-2"></i>' . e($flash['message']) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
        }
        unset($_SESSION['flash']);
        return $html;
    }
}

/**
 * Format currency
 */
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount): string {
        return CURRENCY_SYMBOL . number_format((float)($amount ?? 0), 2);
    }
}

/**
 * Format date nicely
 */
if (!function_exists('formatDate')) {
    function formatDate(?string $date, string $format = 'M d, Y'): string {
        if (empty($date) || $date === '0000-00-00') {
            return '—';
        }
        try {
            $dt = new DateTime($date);
            return $dt->format($format);
        } catch (Exception $e) {
            return (string)$date;
        }
    }
}

/**
 * Format date time
 */
if (!function_exists('formatDateTime')) {
    function formatDateTime(?string $dateTime, string $format = 'M d, Y h:i A'): string {
        if (empty($dateTime) || $dateTime === '0000-00-00 00:00:00') {
            return '—';
        }
        try {
            $dt = new DateTime($dateTime);
            return $dt->format($format);
        } catch (Exception $e) {
            return (string)$dateTime;
        }
    }
}

/**
 * Generate sequential formatted codes (e.g., RAC-M008, RAC-V004, RAC-ACT005)
 */
if (!function_exists('generateCode')) {
    function generateCode(string $prefix, string $table, string $column): string {
        $db = getDB();
        $stmt = $db->query("SELECT {$column} FROM {$table} ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetchColumn();

        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf("%s%04d", $prefix, $nextNum);
    }
}

/**
 * Secure file upload handler
 */
if (!function_exists('uploadFile')) {
    function uploadFile(string $inputName, string $subDir, array $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx'], int $maxBytes = 10485760): ?string {
        if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$inputName];
        if ($file['size'] > $maxBytes) {
            throw new Exception("File size exceeds maximum allowed limit (" . round($maxBytes / 1024 / 1024) . "MB).");
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            throw new Exception("Invalid file type: '{$ext}'. Allowed types: " . implode(', ', $allowedExts));
        }

        // MIME type check
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];

            $valid = false;
            foreach ($allowedExts as $allowed) {
                if (isset($allowedMimes[$allowed]) && (
                    $mime === $allowedMimes[$allowed] || 
                    strpos($mime, 'image/') === 0 || 
                    strpos($mime, 'application/') === 0
                )) {
                    $valid = true;
                    break;
                }
            }

            if (!$valid) {
                throw new Exception("Security validation failed for uploaded file MIME: {$mime}");
            }
        }

        $targetDirectory = UPLOAD_PATH . trim($subDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDirectory)) {
            @mkdir($targetDirectory, 0755, true);
        }

        $filename = uniqid('racic_' . date('Ymd_His') . '_', true) . '.' . $ext;
        $destination = $targetDirectory . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Failed to save uploaded file on server.");
        }

        return trim($subDir, '/\\') . '/' . $filename;
    }
}

/**
 * Return badge HTML for status strings
 */
if (!function_exists('getStatusBadge')) {
    function getStatusBadge(string $status): string {
        $map = [
            'Active' => 'success',
            'active' => 'success',
            'Completed' => 'success',
            'Approved' => 'success',
            'Baptized' => 'success',
            'Present' => 'success',
            'Converted' => 'success',

            'Upcoming' => 'primary',
            'Contacted' => 'primary',
            'Normal' => 'secondary',
            'In Training' => 'info',
            'Submitted' => 'info',
            'Visited' => 'info',
            'Prayed With' => 'info',

            'In Progress' => 'warning text-dark',
            'Pending' => 'warning text-dark',
            'Important' => 'warning text-dark',
            'Needs Revision' => 'warning text-dark',

            'Inactive' => 'secondary',
            'inactive' => 'secondary',
            'Not Baptized' => 'secondary',
            'Archived' => 'secondary',
            'Draft' => 'secondary',
            'Absent' => 'secondary',

            'Postponed' => 'dark',
            'Cancelled' => 'danger',
            'Rejected' => 'danger',
            'Suspended' => 'danger',
            'suspended' => 'danger',
            'Urgent' => 'danger',
            'Deceased' => 'dark',
            'Not Interested' => 'danger'
        ];

        $color = $map[$status] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . e($status) . '</span>';
    }
}

/**
 * Create user notification
 */
if (!function_exists('createNotification')) {
    function createNotification(int $userId, string $title, string $message, ?string $link = null): bool {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            return $stmt->execute([$userId, $title, $message, $link]);
        } catch (Exception $e) {
            return false;
        }
    }
}
