<?php

declare(strict_types=1);

$errors = [];
$jokes = [];
$serviceDown = false;

[$manager, $ns] = mongo_jokes_collection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim((string) ($_POST['text'] ?? ''));
    $author = trim((string) ($_POST['author'] ?? ''));

    if ($text === '') {
        $errors[] = "La blague ne peut pas être vide.";
    } elseif (mb_strlen($text) > 500) {
        $errors[] = "500 caractères maximum, on n'écrit pas un roman.";
    }
    if (mb_strlen($author) > 50) {
        $errors[] = "Le pseudo fait 50 caractères maximum.";
    }

    if (!$errors) {
        try {
            $bulk = new MongoDB\Driver\BulkWrite();
            $bulk->insert([
                'text' => $text,
                'author' => $author !== '' ? $author : 'Anonyme',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
            ]);
            $manager->executeBulkWrite($ns, $bulk);

            // POST-redirect-GET : évite la double soumission au rechargement.
            header('Location: /blagues');
            exit;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('riz-goler: insertion blague échouée: ' . get_class($e) . ': ' . $e->getMessage());
            $serviceDown = true;
        }
    }
}

if (!$serviceDown) {
    try {
        $query = new MongoDB\Driver\Query([], ['sort' => ['createdAt' => -1], 'limit' => 50]);
        $cursor = $manager->executeQuery($ns, $query);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        $jokes = $cursor->toArray();
    } catch (MongoDB\Driver\Exception\Exception $e) {
        error_log('riz-goler: lecture blagues échouée: ' . get_class($e) . ': ' . $e->getMessage());
        $serviceDown = true;
    }
}

render('jokes', [
    'jokes' => $jokes,
    'errors' => $errors,
    'serviceDown' => $serviceDown,
]);
