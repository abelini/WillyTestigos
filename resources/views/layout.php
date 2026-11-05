<?php

declare(strict_types=1);

use Willy\Support\View;

/** @var array<string, mixed> $data */

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::escape((string) $data['title']) ?> · Testigos</title>

    <link rel="stylesheet" href="/assets/app.css?v=6">

    <link rel="icon" href="https://proyectoz.com.mx/wp-content/uploads/2026/08/cropped-icon_proyecto_z-160x160.png" type="image/png" sizes="160x160">
    <link rel="icon" href="https://proyectoz.com.mx/wp-content/uploads/2026/08/cropped-icon_proyecto_z-320x320.png" type="image/png" sizes="320x320">
    <link rel="apple-touch-icon" href="https://proyectoz.com.mx/wp-content/uploads/2026/08/cropped-icon_proyecto_z-320x320.png">
    <meta name="msapplication-TileImage" content="https://proyectoz.com.mx/wp-content/uploads/2026/08/cropped-icon_proyecto_z-320x320.png">

    <script src="https://kit.fontawesome.com/9bef064d64.js" crossorigin="anonymous" referrerpolicy="origin"></script>
</head>

<body data-config="<?= View::escape((string) $data['config']) ?>">
    <?= $data['content'] ?>
    <script src="/assets/app.js?v=4" defer></script>
</body>

</html>