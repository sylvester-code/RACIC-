<?php
// logout.php - Sign out handler
require_once __DIR__ . '/config/app.php';

logoutUser();
flash('info', 'You have been signed out successfully. God bless you!');
header('Location: ' . BASE_URL . 'login.php');
exit;

