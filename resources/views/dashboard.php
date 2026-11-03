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
        <div class="brand">
            <img class="brand-logo" src="/assets/logo-proyecto-z.png" alt="Proyecto Z" width="800" height="240">
        </div>
        <form method="post" action="/logout">
            <input type="hidden" name="_csrf" value="<?= View::escape((string) $data['csrf']) ?>">
            <button class="ghost" type="submit">Salir</button>
        </form>
    </header>

    <section class="card">
        <h2>Nuevo PDF de Testigos</h2>
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
                <strong id="drop-title">Selecciona o arrastra las capturas</strong>
                <span id="drop-hint">JPEG o PNG · hasta <?= (int) $data['max_files'] ?> archivos · máximo <?= (int) $data['max_file_mb'] ?> MB por archivo</span>
            </label>
            <p id="selection" class="selection" aria-live="polite" hidden></p>

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
                        data-status="<?= View::escape((string) $job['status']) ?>">
                        <header>
                            <h3><?= View::escape((string) $job['title']) ?></h3>
                            <span class="badge badge--<?= View::escape((string) $job['status']) ?>">
                                <?= View::escape((string) $job['status_label']) ?>
                            </span>
                        </header>

                        <?php if ((bool) $job['is_active']): ?>
                            <progress max="100" value="<?= (int) $job['progress'] ?>"></progress>
                            <p class="muted job-info">
                                <?= (int) $job['uploaded_files'] ?> de <?= (int) $job['total_files'] ?> capturas
                                · <?= (int) $job['progress'] ?>%
                            </p>
                        <?php endif; ?>

                        <?php if ((bool) $job['pdf_ready']): ?>
                            <?php if ((string) $job['download_url'] !== ''): ?>
                                <p class="job-pdf">
                                    PDF de <?= View::escape((string) $job['pdf_size_label']) ?>
                                    · <?= (int) $job['pdf_pages'] ?> páginas
                                </p>
                                <p class="muted job-info">
                                    Disponible hasta <?= View::escape((string) $job['expires_at_label']) ?>.

                                    <?php if ((int) $job['expires_in'] > 0): ?>
                                        <?= View::escape((string) $job['expires_in_label']) ?>.
                                    <?php endif; ?>
                                </p>
                                <p class="job-actions">
                                    <a class="button" href="<?= View::escape((string) $job['download_url']) ?>">
                                        Descargar PDF
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="muted job-info">
                                    El PDF ya no está disponible. Genera uno nuevo desde el formulario.
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="job-destinations">
                            <span
                                class="chip chip--<?= View::escape((string) $job['telegram']['status']) ?>"
                                <?= (string) $job['telegram']['error'] !== '' ? 'title="' . View::escape((string) $job['telegram']['error']) . '"' : '' ?>>
                                Telegram: <?= View::escape((string) $job['telegram']['label']) ?>
                            </span>
                            <span
                                class="chip chip--<?= View::escape((string) $job['mail']['status']) ?>"
                                <?= (string) $job['mail']['error'] !== '' ? 'title="' . View::escape((string) $job['mail']['error']) . '"' : '' ?>>
                                Correo: <?= View::escape((string) $job['mail']['label']) ?>
                            </span>
                        </div>

                        <?php if ((bool) $job['has_error']): ?>
                            <p class="alert job-error"><?= View::escape((string) $job['error']) ?></p>
                        <?php endif; ?>

                        <?php if ((bool) $job['can_retry']): ?>
                            <p class="job-actions">
                                <button class="ghost retry" type="button" data-job="<?= View::escape((string) $job['id']) ?>">
                                    Reintentar envío
                                </button>
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
