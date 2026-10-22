<?php

session_start();

/*
|--------------------------------------------------------------------------
| APPLICATION CONFIGURATION
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'MediPulse HMS');


/*
|--------------------------------------------------------------------------
| DATABASE CONFIGURATION
|--------------------------------------------------------------------------
|
| Update these to match your local MySQL / XAMPP setup.
|
*/

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'medipulse_hms');
define('DB_USER', 'root');
define('DB_PASS', '');


/*
|--------------------------------------------------------------------------
| APPLICATION BASE URL (auto-detected)
|--------------------------------------------------------------------------
|
| This used to be hard-coded (e.g. "/MediPluse%20HMS"), which broke the
| moment the project folder was renamed or moved. Instead we work out
| where this file lives on disk relative to the web server's document
| root, and derive the URL prefix from that automatically. Rename the
| folder, move it into a subfolder, or deploy it anywhere — this still
| resolves correctly, with each path segment safely URL-encoded.
|
*/

function resolveAppBaseUrl(): string
{
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $documentRoot = realpath($documentRoot) ?: $documentRoot;
    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');

    $projectRoot = rtrim(str_replace('\\', '/', __DIR__), '/');

    if ($documentRoot === '' || strpos($projectRoot, $documentRoot) !== 0) {
        // Fallback: derive from the currently requested script instead.
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


/*
|--------------------------------------------------------------------------
| URL / REDIRECT HELPERS
|--------------------------------------------------------------------------
*/

function url(string $path = ''): string
{
    return APP_BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}


/*
|--------------------------------------------------------------------------
| OUTPUT HELPER (short alias for htmlspecialchars)
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
|
| Every form in the app includes csrf_field(). Every POST handler calls
| verify_csrf() before touching the database.
|
*/

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

const APP_ROLES = ['admin', 'doctor', 'receptionist', 'lab_technician', 'billing_officer', 'patient'];

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
    switch (strtolower($role)) {
        case 'admin':
            return '/dashboard/index.php';
        case 'doctor':
            return '/doctor/dashboard.php';
        case 'receptionist':
            return '/appointments/index.php';
        case 'lab_technician':
            return '/lab/dashboard.php';
        case 'billing_officer':
            return '/billing/dashboard.php';
        case 'patient':
            return '/patient/dashboard.php';
        default:
            return '/auth/login.php';
    }
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
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
}
