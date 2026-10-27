<?php

function loadLocalEnvironment(): void
{
    $envFile = __DIR__ . '/.env';
    if (!is_readable($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name) || getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"')
            || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

loadLocalEnvironment();

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();


define('APP_NAME', 'MediPulse HMS');



$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'medipulse_hms';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if (!empty($databaseUrl)) {
    $parsedUrl = parse_url($databaseUrl);
    if ($parsedUrl) {
        $dbHost = $parsedUrl['host'] ?? $dbHost;
        $dbPort = (string) ($parsedUrl['port'] ?? $dbPort);
        $dbUser = isset($parsedUrl['user']) ? urldecode($parsedUrl['user']) : $dbUser;
        $dbPass = isset($parsedUrl['pass']) ? urldecode($parsedUrl['pass']) : $dbPass;
        if (!empty($parsedUrl['path'])) {
            $dbName = ltrim($parsedUrl['path'], '/');
        }
    }
}

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('APP_URL', rtrim(getenv('APP_URL') ?: '', '/'));
define('MAIL_HOST', getenv('MAIL_HOST') ?: '');
define('MAIL_PORT', (int) (getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);
define('PASSWORD_RESET_TTL_MINUTES', max(5, (int) (getenv('PASSWORD_RESET_TTL_MINUTES') ?: 30)));



function resolveAppBaseUrl(): string
{
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $documentRoot = realpath($documentRoot) ?: $documentRoot;
    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');

    $projectRoot = rtrim(str_replace('\\', '/', __DIR__), '/');

    if ($documentRoot === '' || strpos($projectRoot, $documentRoot) !== 0) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return rtrim($scriptDir, '/');
    }

    $relative = substr($projectRoot, strlen($documentRoot));
    $relative = trim(str_replace('\\', '/', $relative), '/');

    if ($relative === '') {
        return '';
    }

    $segments = array_map('rawurlencode', explode('/', $relative));

    return '/' . implode('/', $segments);
}

define('APP_BASE_URL', resolveAppBaseUrl());



function url(string $path = ''): string
{
    return APP_BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}



function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(419);
        die('Your session expired or the form was submitted incorrectly. Please go back and try again.');
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT USER HELPER
|--------------------------------------------------------------------------
*/

function currentUser(): array
{
    return $_SESSION['user'] ?? [];
}


/*
|--------------------------------------------------------------------------
| ROLE-BASED ACCESS CONTROL (route guards)
|--------------------------------------------------------------------------
|
| Call requireRole([...allowed roles...]) at the very top of every
| protected page, before any output or database work. It redirects users
| who are not allowed to the dashboard for their role.
|
| requireAdmin() is kept as a convenience wrapper so existing admin-only
| pages don't need to change.
|
*/

const APP_ROLES = ['admin', 'supervisor', 'doctor', 'receptionist', 'lab_technician', 'billing_officer', 'patient', 'pending'];

function requireRole(array $allowedRoles): void
{
    if (!isset($_SESSION['user'])) {
        redirect('/auth/login.php');
    }

    $role = strtolower((string) ($_SESSION['user']['role'] ?? ''));
    $allowedRoles = array_map('strtolower', $allowedRoles);

    if (!in_array($role, $allowedRoles, true)) {
        redirect(roleDashboardPath($role));
    }
}

function requireAdmin(): void
{
    requireRole(['admin']);
}

/**
 * Where a given role lands right after login, and where unauthorized
 * redirects send them instead of an error page.
 */
function roleDashboardPath(string $role): string
{
    $role = strtolower(str_replace([' ', '-'], '_', trim($role)));
    $routes = [
        'admin' => '/dashboard/admin.php',
        'administrator' => '/dashboard/admin.php',
        'supervisor' => '/dashboard/manager.php',
        'manager' => '/dashboard/manager.php',
        'doctor' => '/dashboard/doctor.php',
        'receptionist' => '/dashboard/reception.php',
        'lab_technician' => '/dashboard/laboratory.php',
        'laboratory_scientist' => '/dashboard/laboratory.php',
        'billing_officer' => '/dashboard/billing.php',
        'patient' => '/dashboard/patient.php',
        'pending' => '/dashboard/pending.php',
    ];

    return $routes[$role] ?? '/dashboard/unassigned.php';
}

function roleLandingSection(string $role): ?string
{
    $role = strtolower(str_replace([' ', '-'], '_', trim($role)));
    $sections = [
        'doctor' => 'appointments',
        'receptionist' => 'patients',
        'lab_technician' => 'lab',
        'laboratory_scientist' => 'lab',
        'billing_officer' => 'billing',
    ];

    return $sections[$role] ?? null;
}


/*
|--------------------------------------------------------------------------
| PDO DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST
            . ';port=' . DB_PORT
            . ';dbname=' . DB_NAME
            . ';charset=utf8mb4';

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('The service is temporarily unavailable. Please try again later.');
    }
}
