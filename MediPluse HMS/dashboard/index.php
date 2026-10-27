<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireLogin();
redirect(roleDashboardPath((string) (currentUser()['role'] ?? '')));
