<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';

session_start_safe();

if (is_logged_in()) {
    redirect('views/dashboard.php');
}

redirect('views/login.php');
