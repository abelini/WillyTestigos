<?php

declare(strict_types=1);

use Willy\Support\View;

/** @var array<string, mixed> $data */

$error = (string) $data['error'];
?><main class="shell shell-narrow">
    <section class="card login">
        <p class="eyebrow">Proyecto Z</p>
        <h1>Testigos</h1>
        <p class="muted">Genera el PDF mensual de capturas.</p>

        <?php if ($error !== ''): ?>
            <p class="alert" role="alert"><?= View::escape($error) ?></p>
        <?php endif; ?>

        <form method="post" action="/login" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= View::escape((string) $data['csrf']) ?>">
            <label class="field">
                <span>Contraseña</span>
                <input type="password" name="password" required autofocus>
            </label>
            <button type="submit">Entrar</button>
        </form>
    </section>
</main>
