<?php
require_once __DIR__ . '/utils/env.php';
$salt = 'presenca_aom_salt';
$senha = env('DB_PASS', '');
echo hash('sha256', $salt . $senha);
