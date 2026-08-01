<?php
declare(strict_types=1);

/**
 * Simple PSR-0-style autoloader for the bundled TrilbyMedia\Cap library.
 * Maps TrilbyMedia\Cap\... to backend/includes/cap/...
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'TrilbyMedia\\Cap\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
