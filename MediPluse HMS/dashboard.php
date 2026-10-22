<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    redirect('/auth/login.php');
}

redirect('/dashboard/index.php');
