<?php
require_once __DIR__ . '/../config/auth.php';
admin_logout();
header('Location: login.php');
exit;
