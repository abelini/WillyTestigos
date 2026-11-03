<?php

declare(strict_types=1);

use Willy\Support\View;

/** @var array<string, mixed> $data */

?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::escape((string) $data['title']) ?> · Testigos</title>
    <link rel="stylesheet" href="/assets/app.css?v=3">
</head>
<body data-config="<?= View::escape((string) $data['config']) ?>">
<?= $data['content'] ?>
<script src="/assets/app.js?v=2" defer></script>
</body>
</html>
