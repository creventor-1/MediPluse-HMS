<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireLogin();
denyAccess('No dashboard is configured for your account role. Contact an authorized manager.');
