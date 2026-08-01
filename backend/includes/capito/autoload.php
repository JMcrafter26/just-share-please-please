<?php
declare(strict_types=1);

/**
 * Autoloader for Capito\CapPhpServer library bundled in backend/includes/capito/
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Capito\\CapPhpServer\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
