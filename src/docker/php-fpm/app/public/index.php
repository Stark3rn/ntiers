<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

switch ($path) {
    case '/':
        require __DIR__ . '/../src/pages/home.php';
        break;

    case '/blagues':
        require __DIR__ . '/../src/pages/jokes.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/../src/pages/not-found.php';
}
