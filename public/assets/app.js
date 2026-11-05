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
    const dropTitle = document.getElementById('drop-title');
    const dropHint = document.getElementById('drop-hint');
    const dropIcon = document.getElementById('drop-icon');
    const selection = document.getElementById('selection');
    const selectionText = document.getElementById('selection-text');
    const messageText = document.getElementById('message-text');
    const messageIcon = document.getElementById('message-icon');
    const generateLabel = document.getElementById('generate-label');
    const generateIcon = document.getElementById('generate-icon');
    const progressLabel = document.getElementById('progress-label');

    const maxFiles = Number.isInteger(config.maxFiles) ? config.maxFiles : 200;
    const maxFileMb = Number.isInteger(config.maxFileMb) ? config.maxFileMb : 25;
    const defaultHint = dropHint ? dropHint.textContent : '';
    const allowedTypes = ['image/jpeg', 'image/png'];

    let selectedFiles = [];

    const showMessage = (text, kind = 'error') => {
        if (!message) {
            return;
        }

        message.classList.toggle('success', kind === 'success');
        message.hidden = text === '';

        if (messageText) {
            messageText.textContent = text;
        }

        if (messageIcon) {
            messageIcon.className = kind === 'success'
                ? 'fa-solid fa-circle-check'
                : 'fa-solid fa-circle-exclamation';
        }
    };

    const formatSize = (bytes) => {
        if (bytes < 1024 * 1024) {
            return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        }

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    const findInvalid = (files) => {
        const limit = maxFileMb * 1024 * 1024;

        return files.find((file) => {
            if (file.size > limit) {
                return true;
            }

            if (allowedTypes.includes(file.type)) {
                return false;
            }

            if (file.type === '') {
                return !/\.(jpe?g|png)$/i.test(file.name);
            }

            return true;
        });
    };

    const renderSelection = () => {
        const hasFiles = selectedFiles.length > 0;
        const invalid = hasFiles ? findInvalid(selectedFiles) : null;
        const overLimit = selectedFiles.length > maxFiles;

        if (dropZone) {
            dropZone.classList.toggle('ready', hasFiles && !invalid && !overLimit);
            dropZone.classList.toggle('needs-files', hasFiles && (Boolean(invalid) || overLimit));
        }

        if (dropIcon) {
            dropIcon.className = hasFiles
                ? 'fa-solid fa-images drop-icon'
                : 'fa-solid fa-cloud-arrow-up drop-icon';
        }

        if (dropTitle) {
            dropTitle.textContent = hasFiles
                ? `${selectedFiles.length} captura${selectedFiles.length === 1 ? '' : 's'} seleccionada${selectedFiles.length === 1 ? '' : 's'}`
                : 'Selecciona o arrastra las capturas';
        }

        if (dropHint) {
            dropHint.textContent = hasFiles
                ? 'Pulsa "Generar PDF" para subirlas.'
                : defaultHint;
        }

        if (generateLabel && generate) {
            generateLabel.textContent = hasFiles ? `Generar PDF (${selectedFiles.length})` : 'Generar PDF';
        }

        if (!selection) {
            return;
        }

        if (!hasFiles) {
            if (selectionText) {
                selectionText.textContent = '';
            }

            selection.hidden = true;
            return;
        }

        const total = selectedFiles.reduce((sum, file) => sum + file.size, 0);
        const names = selectedFiles.slice(0, 3).map((file) => file.name);
        const extra = selectedFiles.length - names.length;
        const list = extra > 0 ? `${names.join(', ')} y ${extra} más` : names.join(', ');

        if (selectionText) {
            selectionText.textContent = `${list} · ${formatSize(total)}`;
        }

        selection.hidden = false;
    };

    const syncSelection = (files) => {
        selectedFiles = Array.from(files || []);
        const invalid = findInvalid(selectedFiles);

        if (selectedFiles.length === 0) {
            showMessage('');
        } else if (invalid) {
            showMessage(`"${invalid.name}" no es JPEG/PNG o supera los ${maxFileMb} MB.`);
        } else if (selectedFiles.length > maxFiles) {
            showMessage(`Puedes subir hasta ${maxFiles} capturas.`);
        } else {
            showMessage('');
        }

        renderSelection();
    };

    const setProgress = (value, text) => {
        if (progress) {
            progress.hidden = text === '';
            progress.value = value;
        }

        if (progressText) {
            progressText.hidden = text === '';
        }

        if (progressLabel) {
            progressLabel.textContent = text;
        }
    };

    const setBusy = (busy) => {
        if (form) {
            form.classList.toggle('busy', busy);
            form.setAttribute('aria-busy', busy ? 'true' : 'false');
        }

        if (generateIcon) {
            generateIcon.className = busy
                ? 'fa-solid fa-gear fa-spin'
                : 'fa-solid fa-file-pdf';
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

            const files = selectedFiles.length > 0 ? selectedFiles : Array.from(photos.files || []);

            if (files.length === 0) {
                showMessage('Selecciona al menos una captura antes de generar el PDF.');
                if (dropZone) {
                    dropZone.classList.add('needs-files');
                    dropZone.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            if (files.length > maxFiles) {
                showMessage(`Puedes subir hasta ${maxFiles} capturas.`);
                return;
            }

            const invalid = findInvalid(files);

            if (invalid) {
                showMessage(`"${invalid.name}" no es JPEG/PNG o supera los ${maxFileMb} MB.`);
                return;
            }

            selectedFiles = files;
            disableForm(true);
            setBusy(true);
            showMessage('');
            setProgress(0, 'Creando el trabajo...');

            if (generateLabel) {
                generateLabel.textContent = 'Preparando...';
            }

            if (progress) {
                progress.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            try {
                const createData = new FormData();
                createData.append('_csrf', config.csrf || '');
                createData.append('month', month.value);

                const created = await request('/api/jobs', createData);
                const jobId = created && created.job && created.job.id ? created.job.id : '';

                if (jobId === '') {
                    throw new Error('No se pudo crear el trabajo.');
                }

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

                try {
                    window.sessionStorage.setItem(
                        'willy.flash',
                        'Trabajo creado. Revisa el estado del PDF abajo.',
                    );
                } catch (error) {
                    // Si el navegador bloquea sessionStorage, seguimos con la recarga.
                }

                window.location.reload();
            } catch (error) {
                showMessage(error.message || 'No se pudo generar el PDF.');
                disableForm(false);
                setBusy(false);
                renderSelection();

                if (message) {
                    message.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
    }

    const wordpressForm = document.getElementById('wordpress-form');
    const wpMonth = document.getElementById('wp-month');
    const wpGenerate = document.getElementById('wp-generate');
    const wpGenerateLabel = document.getElementById('wp-generate-label');
    const wpGenerateIcon = document.getElementById('wp-generate-icon');

    if (wordpressForm && wpMonth && wpGenerate) {
        wordpressForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            wpGenerate.disabled = true;
            showMessage('');

            if (wpGenerateLabel) {
                wpGenerateLabel.textContent = 'Consultando WordPress...';
            }

            if (wpGenerateIcon) {
                wpGenerateIcon.className = 'fa-solid fa-gear fa-spin';
            }

            setProgress(0, 'Creando el trabajo de WordPress...');

            try {
                const data = new FormData();
                data.append('_csrf', config.csrf || '');
                data.append('month', wpMonth.value);
                await request('/api/jobs/wordpress', data);

                try {
                    window.sessionStorage.setItem(
                        'willy.flash',
                        'Trabajo de WordPress creado. Las capturas se están generando.',
                    );
                } catch (error) {
                    // Si el navegador bloquea sessionStorage, seguimos con la recarga.
                }

                window.location.reload();
            } catch (error) {
                showMessage(error.message || 'No se pudo generar desde WordPress.');
                wpGenerate.disabled = false;

                if (wpGenerateLabel) {
                    wpGenerateLabel.textContent = 'Generar desde WordPress';
                }

                if (wpGenerateIcon) {
                    wpGenerateIcon.className = 'fa-brands fa-wordpress';
                }

                setProgress(0, '');
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
            if (!event.dataTransfer || event.dataTransfer.files.length === 0) {
                return;
            }

            try {
                photos.files = event.dataTransfer.files;
            } catch (error) {
                // Algunos navegadores no permiten asignar files; usamos la lista propia.
            }

            syncSelection(event.dataTransfer.files);
        });
    }

    if (photos) {
        photos.addEventListener('change', () => syncSelection(photos.files));
    }

    try {
        const flash = window.sessionStorage.getItem('willy.flash');

        if (flash && form) {
            window.sessionStorage.removeItem('willy.flash');
            showMessage(flash, 'success');
        }
    } catch (error) {
        // sessionStorage puede estar bloqueado; no es crítico.
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
