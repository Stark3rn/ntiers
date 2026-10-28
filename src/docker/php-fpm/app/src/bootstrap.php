<?php

declare(strict_types=1);

function env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

/**
 * @return array{0: MongoDB\Driver\Manager, 1: string} [manager, "db.collection"]
 */
function mongo_jokes_collection(): array
{
    $uri = env('MONGODB_URI', 'mongodb://localhost:27017/ntiers');
    $database = env('MONGODB_DATABASE', 'ntiers');
    $manager = new MongoDB\Driver\Manager($uri);

    return [$manager, $database . '.jokes'];
}

function render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $staticBase = $staticBase ?? env('STATIC_BASE_URL', 'http://nfs.riz-goler.fr');

    require __DIR__ . '/../templates/layout-start.php';
    require __DIR__ . '/../templates/' . $template . '.php';
    require __DIR__ . '/../templates/layout-end.php';
}
