<?php
// index.php - RACIC Church Management System Gateway
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
} else {
    header('Location: ' . BASE_URL . 'login.php');
}
exit;

