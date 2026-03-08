<?php
require_once __DIR__ . '/config/seo.php';
$pdo = app_pdo();
render_public_404($pdo);
