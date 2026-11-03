<?php

declare(strict_types=1);

use Willy\Support\View;

/** @var array<string, mixed> $data */

?><main class="shell shell-narrow">
    <section class="card">
        <p class="eyebrow"><?= View::escape((string) $data['code']) ?></p>
        <h1>Algo no salió bien</h1>
        <p class="alert" role="alert"><?= View::escape((string) $data['message']) ?></p>
        <p><a class="button" href="/">Volver al inicio</a></p>
    </section>
</main>
