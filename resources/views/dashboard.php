<?php

declare(strict_types=1);

use Willy\Domain\Month;
use Willy\Support\View;

/** @var array<string, mixed> $data */

/** @var list<Month> $months */
$months = $data['months'];
/** @var Month $detected */
$detected = $data['detected'];
/** @var list<array<string, mixed>> $jobs */
$jobs = $data['jobs'];
?><main class="shell">
    <header class="topbar">
        <div>
            <p class="eyebrow">Portal: Proyecto Z</p>
            <h1>Testigos</h1>
        </div>
        <form method="post" action="/logout">
            <input type="hidden" name="_csrf" value="<?= View::escape((string) $data['csrf']) ?>">
            <button class="ghost" type="submit">Salir</button>
        </form>
    </header>

    <section class="card">
        <h2>Nuevo PDF</h2>
        <form id="upload-form" novalidate>
            <input type="hidden" name="_csrf" value="<?= View::escape((string) $data['csrf']) ?>">

            <label class="field">
                <span>Mes</span>
                <select id="month" name="month">
                    <?php foreach ($months as $month): ?>
                        <option value="<?= $month->number() ?>" <?= $month === $detected ? 'selected' : '' ?>>
                            <?= View::escape($month->label()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="drop" id="drop-zone">
                <input id="photos" type="file" accept="image/jpeg,image/png" multiple>
                <strong>Selecciona o arrastra las capturas</strong>
                <span>JPEG o PNG · hasta <?= (int) $data['max_files'] ?> archivos · máximo <?= (int) $data['max_file_mb'] ?> MB por archivo</span>
            </label>

            <button id="generate" type="submit">Generar PDF</button>
        </form>

        <progress id="progress" max="100" value="0"></progress>
        <p id="progress-text" class="muted"></p>
        <p id="message" class="alert" role="alert" hidden></p>
    </section>

    <section class="card">
        <h2>Estado de los trabajos</h2>
        <div id="current">
            <?php if ($jobs === []): ?>
                <p class="muted">Todavía no hay trabajos.</p>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <article
                        class="job"
                        data-job="<?= View::escape((string) $job['id']) ?>"
                        data-status="<?= View::escape((string) $job['status']) ?>"
                    >
                        <header>
                            <h3><?= View::escape((string) $job['title']) ?></h3>
                            <span class="badge"><?= View::escape((string) $job['status_label']) ?></span>
                        </header>

                        <progress max="100" value="<?= (int) $job['progress'] ?>"></progress>
                        <p class="muted">
                            <?= (int) $job['uploaded_files'] ?> de <?= (int) $job['total_files'] ?> capturas
                            · <?= (int) $job['progress'] ?>%
                        </p>

                        <?php if ((bool) $job['pdf_ready']): ?>
                            <p>
                                PDF de <?= View::escape((string) $job['pdf_size_label']) ?>,
                                <?= (int) $job['pdf_pages'] ?> páginas.
                                <?php if ((string) $job['expires_at_iso'] !== ''): ?>
                                    Disponible hasta <?= View::escape((string) $job['expires_at_iso']) ?>.
                                <?php endif; ?>
                            </p>
                            <?php if ((string) $job['download_url'] !== ''): ?>
                                <a class="button" href="<?= View::escape((string) $job['download_url']) ?>">Descargar PDF</a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <p class="muted">
                            Telegram: <?= View::escape((string) $job['telegram']['label']) ?>
                            · Correo: <?= View::escape((string) $job['mail']['label']) ?>
                        </p>

                        <?php if ((bool) $job['has_error']): ?>
                            <p class="alert"><?= View::escape((string) $job['error']) ?></p>
                        <?php endif; ?>

                        <?php if ((bool) $job['can_retry']): ?>
                            <button class="ghost retry" type="button" data-job="<?= View::escape((string) $job['id']) ?>">
                                Reintentar envío
                            </button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
