<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'][$type][] = $message;
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function public_user_payload(array $user): array {
    unset($user['password_hash']);
    return $user;
}

function require_login(): void {
    if (empty($_SESSION['user'])) {
        set_flash('warning', 'Please login to continue.');
        redirect('/paradiseresort/auth/login.php');
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['user']['role'] ?? 'customer') !== 'admin') {
        set_flash('danger', 'Admins only.');
        redirect('/paradiseresort/index.php');
    }
}

/**
 * Resolve an image path from DB to a real file, trying common fallbacks.
 * - Accepts relative path like 'room/xxx.jpeg'
 * - Tries .jpg/.jpeg swap
 * - Falls back to 'images/banner-item1.jpg' or 'logo/logo.jpeg'
 */
function resolve_image_path(string $relativePath): string {
    $relativePath = ltrim($relativePath, '/');
    $projectRoot = __DIR__;

    $tryPaths = [];
    $tryPaths[] = $relativePath;

    // Swap jpeg/jpg extension if needed
    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $tryPaths[] = preg_replace('/\.jpeg$/i', '.jpg', $relativePath);
    } elseif ($ext === 'jpg') {
        $tryPaths[] = preg_replace('/\.jpg$/i', '.jpeg', $relativePath);
    }

    foreach ($tryPaths as $candidate) {
        $abs = $projectRoot . DIRECTORY_SEPARATOR . $candidate;
        if (is_file($abs)) {
            return $candidate;
        }
    }

    // Fallback to a known banner if exists
    if (is_file($projectRoot . '/images/banner-item1.jpg')) {
        return 'images/banner-item1.jpg';
    }
    // Final fallback: logo
    if (is_file($projectRoot . '/logo/logo.jpeg')) {
        return 'logo/logo.jpeg';
    }
    return $relativePath; // return original if nothing else found
}

?>


