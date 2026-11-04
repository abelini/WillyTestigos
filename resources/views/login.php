<?php

declare(strict_types=1);

use Willy\Support\View;

/** @var array<string, mixed> $data */

$error = (string) $data['error'];
?><main class="shell shell-narrow">
    <section class="card login login-card">
        <img
            class="login-banner"
            src="/assets/login-header.jpeg"
            alt="Proyecto Z — Generación de PDF mensual de testigos"
            width="1702"
            height="630">
        <div class="login-body">
            <p class="muted">Introduce la llave secreta para continuar.</p>

            <?php if ($error !== ''): ?>
                <p class="alert" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?= View::escape($error) ?></span>
                </p>
            <?php endif; ?>

            <form method="post" action="/login" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= View::escape((string) $data['csrf']) ?>">
                <label class="field">
                    <span class="input-icon">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input type="password" name="password" aria-label="Contraseña" required autofocus>
                    </span>
                </label>
                <button type="submit">
                    <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                    Entrar
                </button>
            </form>
        </div>
    </section>
</main>