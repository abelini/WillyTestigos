(() => {
    'use strict';

    const body = document.body;
    let config = {};

    try {
        config = JSON.parse(body.dataset.config || '{}');
    } catch (error) {
        config = {};
    }

    const form = document.getElementById('upload-form');
    const photos = document.getElementById('photos');
    const month = document.getElementById('month');
    const generate = document.getElementById('generate');
    const progress = document.getElementById('progress');
    const progressText = document.getElementById('progress-text');
    const message = document.getElementById('message');
    const dropZone = document.getElementById('drop-zone');

    const showMessage = (text) => {
        if (!message) {
            return;
        }

        message.textContent = text;
        message.hidden = text === '';
    };

    const setProgress = (value, text) => {
        if (progress) {
            progress.value = value;
        }

        if (progressText) {
            progressText.textContent = text;
        }
    };

    const request = async (url, formData) => {
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': config.csrf || '',
            },
        });

        let payload = {};

        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok) {
            throw new Error(payload.message || 'La solicitud falló.');
        }

        return payload;
    };

    const uploadOne = (jobId, file, position, onProgress) => new Promise((resolve, reject) => {
        const data = new FormData();
        data.append('_csrf', config.csrf || '');
        data.append('position', String(position));
        data.append('photo', file, file.name);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `/api/jobs/${jobId}/photos`);
        xhr.setRequestHeader('X-CSRF-Token', config.csrf || '');

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                onProgress(event.loaded / event.total);
            }
        });

        xhr.addEventListener('load', () => {
            let payload = {};

            try {
                payload = JSON.parse(xhr.responseText || '{}');
            } catch (error) {
                payload = {};
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(payload);
                return;
            }

            reject(new Error(payload.message || `La subida falló (${xhr.status}).`));
        });

        xhr.addEventListener('error', () => reject(new Error('No se pudo subir la captura.')));
        xhr.send(data);
    });

    const disableForm = (disabled) => {
        if (!form) {
            return;
        }

        form.querySelectorAll('input, select, button').forEach((element) => {
            element.disabled = disabled;
        });
    };

    if (form && photos && month && generate) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const files = Array.from(photos.files || []);

            if (files.length === 0) {
                showMessage('Selecciona al menos una captura.');
                return;
            }

            if (files.length > config.maxFiles) {
                showMessage(`Puedes subir hasta ${config.maxFiles} capturas.`);
                return;
            }

            const limit = config.maxFileMb * 1024 * 1024;
            const invalid = files.find((file) => file.size > limit || !['image/jpeg', 'image/png'].includes(file.type));

            if (invalid) {
                showMessage(`La captura ${invalid.name} no es válida o supera el tamaño permitido.`);
                return;
            }

            disableForm(true);
            showMessage('');
            setProgress(0, 'Creando el trabajo...');

            try {
                const createData = new FormData();
                createData.append('_csrf', config.csrf || '');
                createData.append('month', month.value);

                const created = await request('/api/jobs', createData);
                const jobId = created.job.id;

                for (let index = 0; index < files.length; index++) {
                    await uploadOne(jobId, files[index], index + 1, (fraction) => {
                        const value = Math.round(((index + fraction) / files.length) * 100);
                        setProgress(value, `Subiendo ${index + 1} de ${files.length}...`);
                    });

                    setProgress(
                        Math.round(((index + 1) / files.length) * 100),
                        `Subidas ${index + 1} de ${files.length} capturas.`,
                    );
                }

                setProgress(100, 'Generando el PDF...');

                const startData = new FormData();
                startData.append('_csrf', config.csrf || '');
                await request(`/api/jobs/${jobId}/start`, startData);

                window.location.reload();
            } catch (error) {
                showMessage(error.message || 'No se pudo generar el PDF.');
                disableForm(false);
            }
        });
    }

    if (dropZone && photos) {
        ['dragenter', 'dragover'].forEach((name) => {
            dropZone.addEventListener(name, (event) => {
                event.preventDefault();
                dropZone.classList.add('over');
            });
        });

        ['dragleave', 'drop'].forEach((name) => {
            dropZone.addEventListener(name, (event) => {
                event.preventDefault();
                dropZone.classList.remove('over');
            });
        });

        dropZone.addEventListener('drop', (event) => {
            if (event.dataTransfer && event.dataTransfer.files.length > 0) {
                photos.files = event.dataTransfer.files;
            }
        });
    }

    let polling = false;

    const poll = async () => {
        if (polling) {
            return;
        }

        polling = true;

        try {
            const response = await fetch('/api/state', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const active = document.querySelector(
                '#current .job[data-status="queued"], '
                + '#current .job[data-status="processing"], '
                + '#current .job[data-status="delivering"], '
                + '#current .job[data-status="uploading"]',
            );

            if (!active) {
                return;
            }

            const job = (payload.jobs || []).find((item) => item.id === active.dataset.job);

            if (!job) {
                return;
            }

            const bar = active.querySelector('progress');

            if (bar) {
                bar.value = job.progress;
            }

            if (job.status === 'completed' || job.status === 'failed') {
                window.location.reload();
                return;
            }

            window.setTimeout(poll, config.pollMs || 2000);
        } finally {
            polling = false;
        }
    };

    document.querySelectorAll('.retry').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;

            try {
                const data = new FormData();
                data.append('_csrf', config.csrf || '');
                await request(`/api/jobs/${button.dataset.job}/retry`, data);
                window.location.reload();
            } catch (error) {
                showMessage(error.message || 'No se pudo reintentar el envío.');
                button.disabled = false;
            }
        });
    });

    const activeOnLoad = document.querySelector(
        '#current .job[data-status="queued"], '
        + '#current .job[data-status="processing"], '
        + '#current .job[data-status="delivering"], '
        + '#current .job[data-status="uploading"]',
    );

    if (activeOnLoad) {
        poll();
    }
})();
