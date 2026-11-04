<?php

declare(strict_types=1);

use Willy\Support\View;

/** @var array<string, mixed> $data */

?><main class="shell shell-narrow">
    <section class="card">
        <p class="eyebrow">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <?= View::escape((string) $data['code']) ?>
        </p>
        <h1>Algo no salió bien</h1>
        <p class="alert" role="alert">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span><?= View::escape((string) $data['message']) ?></span>
        </p>
        <p><a class="button" href="/">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Volver al inicio
        </a></p>
    </section>
</main>
