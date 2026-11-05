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
            <button class="ghost" type="submit">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                Salir
            </button>
        </form>
    </header>

    <section class="card">
        <h2>
            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
            Generación manual
        </h2>
        <p class="muted">
            Este es el método tradicional, el PDF se genera a partir de las capturas de pantalla que subas.
        </p>
        <form id="upload-form" novalidate>
            <input type="hidden" name="_csrf" value="<?= View::escape((string) $data['csrf']) ?>">

            <label class="field">
                <span>
                    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                    Mes
                </span>
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
                <i id="drop-icon" class="fa-solid fa-cloud-arrow-up drop-icon" aria-hidden="true"></i>
                <span id="drop-title">Selecciona de la Galería o arrastra las capturas</span>
            </label>
            <p id="selection" class="selection" aria-live="polite" hidden>
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span id="selection-text"></span>
            </p>

            <button id="generate" type="submit">
                <i id="generate-icon" class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                <span id="generate-label">Generar manualmente</span>
            </button>
        </form>

        <progress id="progress" max="100" value="0" hidden></progress>
        <p id="progress-text" class="muted progress-row" hidden>
            <i id="progress-icon" class="fa-solid fa-gear fa-spin" aria-hidden="true"></i>
            <span id="progress-label"></span>
        </p>
        <p id="message" class="alert" role="alert" hidden>
            <i id="message-icon" class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span id="message-text"></span>
        </p>
    </section>

    <?php if ((bool) $data['wordpress']): ?>
        <section class="card">
            <h2>
                <i class="fa-brands fa-wordpress-simple" aria-hidden="true"></i>
                Generación automática desde WordPress
            </h2>
            <p class="muted">
                Este método toma hasta <?= $data['wp_daily'] ?> notas diarias del mes elegido para generar el PDF.
            </p>
            <form id="wordpress-form" novalidate>
                <input type="hidden" name="_csrf" value="<?= View::escape((string) $data['csrf']) ?>">

                <label class="field">
                    <span>
                        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                        Mes
                    </span>
                    <select id="wp-month" name="month">
                        <?php foreach ($months as $month): ?>
                            <option value="<?= $month->number() ?>" <?= $month === $detected ? 'selected' : '' ?>>
                                <?= View::escape($month->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button id="wp-generate" type="submit">
                    <i id="wp-generate-icon" class="fa-brands fa-wordpress-simple" aria-hidden="true"></i>
                    <span id="wp-generate-label">Generar desde WordPress</span>
                </button>
            </form>

            <progress id="wp-progress" max="100" value="0" hidden></progress>
            <p id="wp-progress-text" class="muted progress-row" hidden>
                <i id="wp-progress-icon" class="fa-solid fa-gear fa-spin" aria-hidden="true"></i>
                <span id="wp-progress-label"></span>
            </p>
            <p id="wp-message" class="alert" role="alert" hidden>
                <i id="wp-message-icon" class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <span id="wp-message-text"></span>
            </p>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            Estado de los trabajos
        </h2>
        <div id="current">
            <?php if ($jobs === []): ?>
                <p class="muted empty">
                    <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                    Todavía no hay trabajos.
                </p>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $statusIcon = match ((string) $job['status']) {
                        'created' => 'fa-circle-plus',
                        'uploading' => 'fa-cloud-arrow-up',
                        'queued' => 'fa-hourglass-half',
                        'processing' => 'fa-gear fa-spin',
                        'delivering' => 'fa-paper-plane',
                        'completed' => 'fa-circle-check',
                        'failed' => 'fa-circle-xmark',
                        default => 'fa-circle-info',
                    };
                    ?>
                    <article
                        class="job"
                        data-job="<?= View::escape((string) $job['id']) ?>"
                        data-status="<?= View::escape((string) $job['status']) ?>">
                        <header>
                            <h3>
                                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                                <?= View::escape((string) $job['title']) ?>
                            </h3>
                            <div class="job-badges">
                                <span class="badge badge--<?= View::escape((string) $job['status']) ?>">
                                    <i class="fa-solid <?= View::escape($statusIcon) ?>" aria-hidden="true"></i>
                                    <?= View::escape((string) $job['status_label']) ?>
                                </span>
                                <?php if ((string) $job['source'] === 'wordpress'): ?>
                                    <span class="badge badge--wordpress">
                                        <i class="fa-brands fa-wordpress-simple" aria-hidden="true"></i>
                                        WordPress
                                    </span>
                                <?php endif; ?>
                            </div>
                        </header>

                        <?php if ((string) $job['source'] === 'wordpress' && (int) $job['wp_selected'] > 0): ?>
                            <p class="muted job-info">
                                <i class="fa-brands fa-wordpress-simple" aria-hidden="true"></i>
                                <?= (int) $job['wp_rendered'] ?> de <?= (int) $job['wp_selected'] ?> notas de WordPress
                            </p>
                        <?php endif; ?>

                        <?php if ((bool) $job['is_active']): ?>
                            <progress max="100" value="<?= (int) $job['progress'] ?>"></progress>
                            <p class="muted job-info">
                                <i class="fa-solid fa-images" aria-hidden="true"></i>
                                <?= (int) $job['uploaded_files'] ?> de <?= (int) $job['total_files'] ?> capturas
                                · <?= (int) $job['progress'] ?>%
                            </p>
                        <?php endif; ?>

                        <?php if ((bool) $job['pdf_ready']): ?>
                            <?php if ((string) $job['download_url'] !== ''): ?>
                                <p class="job-pdf">
                                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                                    PDF de <?= View::escape((string) $job['pdf_size_label']) ?>
                                    · <?= (int) $job['pdf_pages'] ?> páginas
                                </p>
                                <p class="muted job-info">
                                    <i class="fa-solid fa-hourglass-end" aria-hidden="true"></i>
                                    Disponible hasta <?= View::escape((string) $job['expires_at_label']) ?>.

                                    <?php if ((int) $job['expires_in'] > 0): ?>
                                        <?= View::escape((string) $job['expires_in_label']) ?>.
                                    <?php endif; ?>
                                </p>
                                <p class="job-actions">
                                    <a class="button" href="<?= View::escape((string) $job['download_url']) ?>">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        Descargar PDF
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="muted job-info">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    El PDF ya no está disponible. Genera uno nuevo desde el formulario.
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="job-destinations">
                            <span
                                class="chip chip--<?= View::escape((string) $job['telegram']['status']) ?>"
                                <?= (string) $job['telegram']['error'] !== '' ? 'title="' . View::escape((string) $job['telegram']['error']) . '"' : '' ?>>
                                <i class="fa-brands fa-telegram" aria-hidden="true"></i>
                                Telegram: <?= View::escape((string) $job['telegram']['label']) ?>
                            </span>
                            <span
                                class="chip chip--<?= View::escape((string) $job['mail']['status']) ?>"
                                <?= (string) $job['mail']['error'] !== '' ? 'title="' . View::escape((string) $job['mail']['error']) . '"' : '' ?>>
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                Correo: <?= View::escape((string) $job['mail']['label']) ?>
                            </span>
                        </div>

                        <?php if ((bool) $job['has_error']): ?>
                            <p class="alert job-error">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                <span><?= View::escape((string) $job['error']) ?></span>
                            </p>
                        <?php endif; ?>

                        <?php if ((bool) $job['can_retry']): ?>
                            <p class="job-actions">
                                <button class="ghost retry" type="button" data-job="<?= View::escape((string) $job['id']) ?>">
                                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
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