<?php
require_once 'config.php';
require_once 'includes/auth.php';

header('Location: ' . (isLoggedIn() ? 'dashboard.php' : 'login.php'));
exit;
