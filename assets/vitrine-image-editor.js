(function () {
    'use strict';

    const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    const MAX_PIXELS = 40000000;

    class VitrineImageEditor {
        constructor(options) {
            this.fields = options.fields;
            this.feedback = options.feedback || (() => {});
            this.onStateChange = options.onStateChange || (() => {});
            this.CropperClass = window.Cropper?.default;
            this.modal = document.getElementById(options.modalId);
            this.stage = document.getElementById('cropEditorStage');
            this.loading = document.getElementById('cropEditorLoading');
            this.applyButton = document.getElementById('cropApply');
            this.zoomInput = document.getElementById('cropZoom');
            this.zoomOutput = document.getElementById('cropZoomOutput');
            this.states = {};
            this.cropper = null;
            this.session = null;
            this.lastTrigger = null;
            this.previousBodyOverflow = '';
            this.webPSupported = null;

            if (!this.CropperClass || !this.modal || !this.stage) {
                throw new Error('Editor de imagens indisponível.');
            }

            Object.entries(this.fields).forEach(([key, config]) => {
                this.states[key] = {
                    persistedUrl: config.initialSrc || '',
                    editorSourceUrl: config.initialSrc || '',
                    editorSourceOwned: false,
                    previewUrl: null,
                    processedFile: null,
                    cropSnapshot: null,
                    dirty: false,
                };
                this.bindField(key, config);
                this.renderField(key, config.initialSrc || '');
            });
            this.bindModal();
            window.addEventListener('beforeunload', () => this.dispose());
        }

        bindField(key, config) {
            const input = document.getElementById(config.inputId);
            const dropzone = document.getElementById(config.dropzoneId);
            document.querySelectorAll(`[data-image-choose="${key}"]`).forEach(button => {
                button.addEventListener('click', event => {
                    this.lastTrigger = event.currentTarget;
                    input.click();
                });
            });
            document.querySelectorAll(`[data-image-edit="${key}"]`).forEach(button => {
                button.addEventListener('click', event => this.editCurrent(key, event.currentTarget));
            });
            input.addEventListener('change', () => {
                const file = input.files?.[0];
                input.value = '';
                if (file) this.selectFile(key, file).catch(error => this.handleError(error));
            });
            if (!dropzone) return;
            dropzone.addEventListener('click', event => {
                this.lastTrigger = event.currentTarget;
                input.click();
            });
            ['dragenter', 'dragover'].forEach(name => dropzone.addEventListener(name, event => {
                event.preventDefault();
                if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
                dropzone.classList.add('is-dragging');
            }));
            ['dragleave', 'drop'].forEach(name => dropzone.addEventListener(name, event => {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            }));
            dropzone.addEventListener('drop', event => {
                const file = event.dataTransfer?.files?.[0];
                this.lastTrigger = dropzone;
                if (file) this.selectFile(key, file).catch(error => this.handleError(error));
            });
        }

        bindModal() {
            document.getElementById('cropCancel').addEventListener('click', () => this.closeModal());
            document.getElementById('cropClose').addEventListener('click', () => this.closeModal());
            document.getElementById('cropReset').addEventListener('click', () => this.resetCrop());
            document.getElementById('cropRotateLeft').addEventListener('click', () => this.rotate('-90deg'));
            document.getElementById('cropRotateRight').addEventListener('click', () => this.rotate('90deg'));
            this.applyButton.addEventListener('click', () => this.applyCrop());
            this.zoomInput.addEventListener('input', () => this.applySliderZoom());
            this.modal.addEventListener('click', event => {
                if (event.target === this.modal) this.closeModal();
            });
            this.modal.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.closeModal();
                    return;
                }
                if (event.key === 'Tab') this.trapFocus(event);
            });
        }

        validateFile(file, config) {
            const extension = (file.name.split('.').pop() || '').toLowerCase();
            if (!ACCEPTED_MIME.includes(file.type) || !ACCEPTED_EXTENSIONS.includes(extension)) {
                throw new Error('Formato não suportado. Utilize JPG, PNG ou WebP.');
            }
            if (file.size < 1) throw new Error('Não foi possível processar esta imagem.');
            if (file.size > config.maxBytes) throw new Error('O arquivo ultrapassa o tamanho permitido.');
        }

        async selectFile(key, file) {
            const config = this.fields[key];
            this.validateFile(file, config);
            this.openModalShell(key, this.lastTrigger);
            this.setLoading(true, 'Preparando imagem…');
            try {
                const normalized = await this.normalizeSource(file, config);
                this.session.sourceUrl = normalized.url;
                this.session.ownsSource = true;
                this.session.fileName = file.name;
                await this.initializeCropper(config, normalized.url, null);
            } catch (error) {
                this.closeModal(false, true);
                throw error;
            }
        }

        async editCurrent(key, trigger) {
            const state = this.states[key];
            const source = state.editorSourceUrl || state.previewUrl || state.persistedUrl;
            if (!source) {
                document.getElementById(this.fields[key].inputId).click();
                return;
            }
            this.openModalShell(key, trigger);
            this.session.sourceUrl = source;
            this.setLoading(true, 'Carregando imagem…');
            try {
                await this.initializeCropper(this.fields[key], source, state.cropSnapshot);
            } catch (error) {
                this.closeModal(false, true);
                this.handleError(error);
            }
        }

        openModalShell(key, trigger) {
            const config = this.fields[key];
            this.lastTrigger = trigger || this.lastTrigger || document.activeElement;
            this.session = { key, sourceUrl: '', ownsSource: false, fileName: config.defaultName, zoom: 1 };
            document.getElementById('cropEditorSubtitle').textContent = config.editorHint;
            this.zoomInput.value = '1';
            this.zoomOutput.textContent = '100%';
            this.previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.modal.classList.add('show');
            this.modal.setAttribute('aria-hidden', 'false');
            document.getElementById('cropClose').focus();
        }

        async normalizeSource(file, config) {
            const inputUrl = URL.createObjectURL(file);
            let source = null;
            try {
                source = await this.decodeImage(file, inputUrl);
                const width = source.width;
                const height = source.height;
                if (!width || !height || width * height > (config.maxPixels || MAX_PIXELS)) {
                    throw new Error('A imagem possui dimensões grandes demais para processamento.');
                }
                if (width < config.minWidth || height < config.minHeight) {
                    throw new Error(`A imagem é pequena demais. Escolha uma imagem com pelo menos ${config.minWidth} × ${config.minHeight} px.`);
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d', { alpha: true });
                if (!context) throw new Error('Não foi possível processar esta imagem.');
                context.drawImage(source.drawable, 0, 0, width, height);
                const mime = this.supportsWebP() ? 'image/webp' : (file.type === 'image/png' ? 'image/png' : 'image/jpeg');
                const blob = await this.canvasToBlob(canvas, mime, .92);
                canvas.width = canvas.height = 1;
                return { url: URL.createObjectURL(blob), width, height };
            } finally {
                URL.revokeObjectURL(inputUrl);
                if (source?.close) source.close();
            }
        }

        async decodeImage(file, inputUrl) {
            if ('createImageBitmap' in window) {
                let bitmap;
                try {
                    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                } catch {
                    bitmap = await createImageBitmap(file);
                }
                return { drawable: bitmap, width: bitmap.width, height: bitmap.height, close: () => bitmap.close() };
            }
            const image = await this.loadImage(inputUrl);
            return { drawable: image, width: image.naturalWidth, height: image.naturalHeight, close: null };
        }

        loadImage(src) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                image.decoding = 'async';
                image.style.imageOrientation = 'from-image';
                image.onload = () => resolve(image);
                image.onerror = () => reject(new Error('Não foi possível processar esta imagem.'));
                image.src = src;
            });
        }

        async initializeCropper(config, sourceUrl, snapshot) {
            this.destroyCropper();
            this.stage.querySelectorAll('img,cropper-canvas').forEach(element => element.remove());
            const image = document.createElement('img');
            image.src = sourceUrl;
            image.alt = 'Imagem para recorte';
            image.decoding = 'async';
            this.stage.insertBefore(image, this.loading);
            const template = `
                <cropper-canvas scale-step="0.05">
                    <cropper-image rotatable scalable translatable></cropper-image>
                    <cropper-shade theme-color="rgba(7, 12, 20, .68)"></cropper-shade>
                    <cropper-handle action="move" plain></cropper-handle>
                    <cropper-selection aspect-ratio="${config.aspectRatio}" initial-coverage="0.84">
                        <cropper-grid role="grid" bordered covered></cropper-grid>
                        <cropper-crosshair centered></cropper-crosshair>
                    </cropper-selection>
                </cropper-canvas>`;
            this.cropper = new this.CropperClass(image, { container: this.stage, template });
            const cropperImage = this.cropper.getCropperImage();
            const cropperCanvas = this.cropper.getCropperCanvas();
            await cropperImage.$ready();
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            if (snapshot?.matrix?.length === 6) {
                cropperImage.$setTransform(snapshot.matrix);
                this.session.zoom = snapshot.zoom || 1;
            } else {
                cropperImage.$center('cover');
                this.session.zoom = 1;
            }
            this.zoomInput.value = String(Math.min(3, Math.max(1, this.session.zoom)));
            this.updateZoomOutput();
            cropperCanvas.addEventListener('action', event => this.syncGestureZoom(event));
            this.setLoading(false);
            this.applyButton.focus();
        }

        syncGestureZoom(event) {
            if (!this.session || !['scale', 'transform'].includes(event.detail?.action)) return;
            const amount = Number(event.detail.scale || 0);
            const factor = amount < 0 ? 1 / (1 - amount) : 1 + amount;
            const rawZoom = this.session.zoom * factor;
            const boundedZoom = Math.min(3, Math.max(1, rawZoom));
            if (boundedZoom !== rawZoom && rawZoom > 0) {
                const correctionRatio = boundedZoom / rawZoom;
                const correction = correctionRatio >= 1
                    ? correctionRatio - 1
                    : 1 - (1 / correctionRatio);
                this.cropper.getCropperImage().$zoom(correction);
            }
            this.session.zoom = boundedZoom;
            this.zoomInput.value = String(this.session.zoom);
            this.updateZoomOutput();
        }

        applySliderZoom() {
            if (!this.cropper || !this.session) return;
            const next = Number(this.zoomInput.value);
            const current = this.session.zoom || 1;
            if (!Number.isFinite(next) || next === current) return;
            const ratio = next / current;
            const amount = ratio >= 1 ? ratio - 1 : 1 - (1 / ratio);
            this.cropper.getCropperImage().$zoom(amount);
            this.session.zoom = next;
            this.updateZoomOutput();
        }

        updateZoomOutput() {
            this.zoomOutput.textContent = `${Math.round((this.session?.zoom || 1) * 100)}%`;
        }

        rotate(angle) {
            this.cropper?.getCropperImage()?.$rotate(angle);
        }

        resetCrop() {
            if (!this.cropper || !this.session) return;
            const cropperImage = this.cropper.getCropperImage();
            cropperImage.$resetTransform();
            this.cropper.getCropperSelection().$reset();
            cropperImage.$center('cover');
            this.session.zoom = 1;
            this.zoomInput.value = '1';
            this.updateZoomOutput();
        }

        async applyCrop() {
            if (!this.cropper || !this.session || this.applyButton.disabled) return;
            const config = this.fields[this.session.key];
            this.setBusy(true);
            try {
                const selection = this.cropper.getCropperSelection();
                const canvas = await selection.$toCanvas({ width: config.outputWidth, height: config.outputHeight });
                if (!canvas || canvas.width !== config.outputWidth || canvas.height !== config.outputHeight) {
                    throw new Error('Falha ao gerar o recorte da imagem.');
                }
                const mime = this.supportsWebP() ? 'image/webp' : 'image/jpeg';
                const blob = await this.canvasToBlob(canvas, mime, config.quality);
                canvas.width = canvas.height = 1;
                const extension = mime === 'image/webp' ? 'webp' : 'jpg';
                const baseName = config.defaultName.replace(/\.[^.]+$/, '');
                const file = new File([blob], `${baseName}.${extension}`, { type: mime, lastModified: Date.now() });
                const snapshot = {
                    matrix: [...this.cropper.getCropperImage().$getTransform()],
                    zoom: this.session.zoom,
                };
                this.acceptCrop(this.session.key, file, snapshot);
                this.closeModal(true, true);
            } catch (error) {
                this.handleError(error);
            } finally {
                this.setBusy(false);
            }
        }

        acceptCrop(key, file, snapshot) {
            const state = this.states[key];
            if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
            state.previewUrl = URL.createObjectURL(file);
            state.processedFile = file;
            state.cropSnapshot = snapshot;
            state.dirty = true;
            if (this.session.ownsSource) {
                if (state.editorSourceOwned && state.editorSourceUrl) URL.revokeObjectURL(state.editorSourceUrl);
                state.editorSourceUrl = this.session.sourceUrl;
                state.editorSourceOwned = true;
                this.session.ownsSource = false;
            }
            this.renderField(key, state.previewUrl);
            this.onStateChange(key, true);
        }

        renderField(key, src) {
            const config = this.fields[key];
            config.previewIds.forEach(id => {
                const image = document.getElementById(id);
                if (!image) return;
                image.src = src || '';
                image.hidden = !src;
                image.style.objectPosition = 'center center';
                image.style.transform = 'none';
            });
            const empty = document.getElementById(config.emptyId);
            if (empty) empty.hidden = !!src;
            (config.fallbackIds || []).forEach(id => {
                const fallback = document.getElementById(id);
                if (fallback) fallback.hidden = !!src;
            });
            document.querySelectorAll(`[data-image-edit="${key}"]`).forEach(button => button.hidden = !src);
            document.querySelectorAll(`[data-image-label="${key}"]`).forEach(label => label.textContent = src ? 'Trocar imagem' : 'Escolher imagem');
            const pending = document.getElementById(config.pendingId);
            if (pending) pending.hidden = !this.states[key].dirty;
        }

        appendPendingFiles(formData) {
            Object.entries(this.fields).forEach(([key, config]) => {
                const file = this.states[key].processedFile;
                if (file) formData.append(config.uploadField, file, file.name);
            });
        }

        commit(key, persistedUrl) {
            const state = this.states[key];
            state.persistedUrl = persistedUrl || state.persistedUrl;
            state.processedFile = null;
            state.cropSnapshot = null;
            state.dirty = false;
            this.renderField(key, state.persistedUrl);
            if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
            if (state.editorSourceOwned && state.editorSourceUrl) URL.revokeObjectURL(state.editorSourceUrl);
            state.previewUrl = null;
            state.editorSourceUrl = state.persistedUrl;
            state.editorSourceOwned = false;
            this.onStateChange(key, false);
        }

        canvasToBlob(canvas, mime, quality) {
            return new Promise((resolve, reject) => {
                canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Não foi possível converter a imagem.')), mime, quality);
            });
        }

        supportsWebP() {
            if (this.webPSupported !== null) return this.webPSupported;
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            this.webPSupported = canvas.toDataURL('image/webp').startsWith('data:image/webp');
            return this.webPSupported;
        }

        setLoading(visible, text = 'Carregando…') {
            this.loading.hidden = !visible;
            this.modal.setAttribute('aria-busy', visible ? 'true' : 'false');
            const label = this.loading.querySelector('[data-loading-label]');
            if (label) label.textContent = text;
            this.applyButton.disabled = visible;
            ['cropClose', 'cropCancel', 'cropReset', 'cropRotateLeft', 'cropRotateRight'].forEach(id => {
                document.getElementById(id).disabled = visible;
            });
            this.zoomInput.disabled = visible;
        }

        setBusy(busy) {
            this.applyButton.disabled = busy;
            this.modal.setAttribute('aria-busy', busy ? 'true' : 'false');
            this.applyButton.innerHTML = busy
                ? '<span class="spinner"></span>Processando…'
                : '<span class="material-symbols-outlined">crop</span>Aplicar corte';
            ['cropClose', 'cropCancel', 'cropReset', 'cropRotateLeft', 'cropRotateRight'].forEach(id => {
                document.getElementById(id).disabled = busy;
            });
            this.zoomInput.disabled = busy;
        }

        closeModal(applied = false, force = false) {
            if (!this.session) return;
            if (!force && this.applyButton.disabled) return;
            if (this.session.ownsSource && this.session.sourceUrl) URL.revokeObjectURL(this.session.sourceUrl);
            this.destroyCropper();
            this.stage.querySelectorAll('img,cropper-canvas').forEach(element => element.remove());
            this.modal.classList.remove('show');
            this.modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = this.previousBodyOverflow;
            const focusTarget = this.lastTrigger;
            this.session = null;
            this.setLoading(false);
            if (focusTarget?.isConnected) focusTarget.focus();
        }

        destroyCropper() {
            if (this.cropper) this.cropper.destroy();
            this.cropper = null;
        }

        trapFocus(event) {
            const focusable = [...this.modal.querySelectorAll('button:not([disabled]),input:not([disabled])')].filter(el => !el.hidden && el.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        handleError(error) {
            console.error(error);
            this.feedback(error?.message || 'Não foi possível processar esta imagem.', 'err');
        }

        dispose() {
            this.destroyCropper();
            Object.values(this.states).forEach(state => {
                if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
                if (state.editorSourceOwned && state.editorSourceUrl) URL.revokeObjectURL(state.editorSourceUrl);
            });
            if (this.session?.ownsSource && this.session.sourceUrl) URL.revokeObjectURL(this.session.sourceUrl);
        }
    }

    window.VitrineImageEditor = VitrineImageEditor;
})();
