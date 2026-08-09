(function () {
    'use strict';

    const CAMERA_STORAGE_KEY = 'inventoryPreferredScannerCamera';
    const MODE_STORAGE_KEY = 'inventoryScannerMovementMode';
    const CONFIRMATION_WINDOW_MS = 1250;
    const DUPLICATE_COOLDOWN_MS = 2200;

    const elements = {
        codeInput: document.getElementById('codeInput'),
        searchForm: document.getElementById('searchForm'),
        reader: document.getElementById('qr-reader'),
        launch: document.getElementById('scannerBtn'),
        fullscreen: document.getElementById('scannerFullscreen'),
        close: document.getElementById('scannerCloseBtn'),
        camera: document.getElementById('scannerCameraSelect'),
        torch: document.getElementById('scannerTorchBtn'),
        zoomControl: document.getElementById('scannerZoomControl'),
        zoom: document.getElementById('scannerZoomRange'),
        zoomValue: document.getElementById('scannerZoomValue'),
        feedback: document.getElementById('scanFeedback'),
        sessionCount: document.getElementById('scanSessionCount'),
        modeButtons: Array.from(document.querySelectorAll('.scanner-mode-btn')),
        modal: document.getElementById('scanResultModal'),
        modalFeedback: document.getElementById('scanModalFeedback'),
        resultIcon: document.getElementById('scanResultIcon'),
        code: document.getElementById('scanResultCode'),
        visual: document.getElementById('scanProductVisual'),
        productName: document.getElementById('scanProductName'),
        productMeta: document.getElementById('scanProductMeta'),
        productStock: document.getElementById('scanProductStock'),
        moveSelector: document.getElementById('scanMoveSelector'),
        typeButtons: Array.from(document.querySelectorAll('.scan-move-btn')),
        quantity: document.getElementById('scanQuantityInput'),
        quantityMinus: document.getElementById('scanQuantityMinus'),
        quantityPlus: document.getElementById('scanQuantityPlus'),
        reason: document.getElementById('scanReasonInput'),
        register: document.getElementById('scanRegisterBtn'),
        scanAgain: document.getElementById('scanAgainBtn'),
        useCode: document.getElementById('scanUseCodeBtn'),
        cancel: document.getElementById('scanCancelBtn')
    };

    if (!elements.launch || !elements.fullscreen || !elements.reader) {
        return;
    }

    const state = {
        scanner: null,
        active: false,
        paused: false,
        starting: false,
        cancelRequested: false,
        processing: false,
        selectedCameraId: localStorage.getItem(CAMERA_STORAGE_KEY) || '',
        movementType: localStorage.getItem(MODE_STORAGE_KEY) === 'salida' ? 'salida' : 'entrada',
        candidateCode: '',
        candidateHits: 0,
        candidateAt: 0,
        detectedCode: '',
        product: null,
        requestKey: '',
        lastAcceptedCode: '',
        lastAcceptedAt: 0,
        sessionCount: 0,
        mediaTrack: null,
        torchOn: false,
        audioContext: null
    };

    function normalizeCode(rawCode) {
        let code = String(rawCode || '').replace(/[\x00-\x1F\x7F]/g, '').trim();
        if (/^\][A-Za-z][0-9]/.test(code)) {
            code = code.slice(3).trim();
        }
        return code.slice(0, 128);
    }

    function createRequestKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return `scan-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
    }

    function primeAudio() {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        if (!state.audioContext) state.audioContext = new AudioContext();
        if (state.audioContext.state === 'suspended') state.audioContext.resume().catch(() => {});
    }

    function signal(type) {
        primeAudio();
        if (state.audioContext) {
            const oscillator = state.audioContext.createOscillator();
            const gain = state.audioContext.createGain();
            const now = state.audioContext.currentTime;
            oscillator.type = type === 'error' ? 'square' : 'sine';
            oscillator.frequency.setValueAtTime(type === 'success' ? 1060 : 330, now);
            gain.gain.setValueAtTime(0.001, now);
            gain.gain.exponentialRampToValueAtTime(type === 'success' ? 0.08 : 0.045, now + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.001, now + (type === 'success' ? 0.12 : 0.2));
            oscillator.connect(gain);
            gain.connect(state.audioContext.destination);
            oscillator.start(now);
            oscillator.stop(now + (type === 'success' ? 0.13 : 0.21));
        }
        if (navigator.vibrate) navigator.vibrate(type === 'success' ? 55 : [45, 35, 45]);
    }

    function showFeedback(message, type = 'ready') {
        elements.feedback.textContent = message;
        elements.feedback.className = `scan-feedback ${type}`;
    }

    function showModalFeedback(message = '', type = '') {
        elements.modalFeedback.textContent = message;
        elements.modalFeedback.className = `scan-modal-feedback${type ? ` ${type}` : ''}${message ? ' active' : ''}`;
    }

    function setPanelOpen(open) {
        elements.fullscreen.classList.toggle('active', open);
        document.body.classList.toggle('scanner-open', open);
        elements.launch.disabled = state.starting;
        if (!open) elements.modal.classList.remove('active');
    }

    function setLiveState(active) {
        state.active = active;
        elements.reader.classList.toggle('active', active);
        elements.fullscreen.classList.toggle('camera-ready', active);
        elements.launch.classList.toggle('is-active', active);
        elements.launch.innerHTML = active
            ? '<span class="btn-camera-icon" aria-hidden="true"></span> Cerrar escáner'
            : '<span class="btn-camera-icon" aria-hidden="true"></span> Iniciar escáner';
    }

    function setSelectMessage(message) {
        elements.camera.replaceChildren();
        const option = document.createElement('option');
        option.value = '';
        option.textContent = message;
        elements.camera.append(option);
    }

    function preferredCamera(cameras) {
        if (state.selectedCameraId && cameras.some((camera) => camera.id === state.selectedCameraId)) {
            return state.selectedCameraId;
        }
        const rear = cameras.find((camera) => /back|rear|environment|trasera|posterior/i.test(camera.label || ''));
        return rear ? rear.id : (cameras[0]?.id || '');
    }

    async function loadCameras() {
        if (!window.Html5Qrcode || typeof Html5Qrcode.getCameras !== 'function') {
            setSelectMessage('Lector no disponible');
            return [];
        }
        try {
            const cameras = await Html5Qrcode.getCameras();
            if (!cameras.length) {
                setSelectMessage('Sin cámaras detectadas');
                return [];
            }
            state.selectedCameraId = preferredCamera(cameras);
            elements.camera.replaceChildren();
            cameras.forEach((camera, index) => {
                const option = document.createElement('option');
                option.value = camera.id;
                option.textContent = camera.label?.trim() || `Cámara ${index + 1}`;
                option.selected = camera.id === state.selectedCameraId;
                elements.camera.append(option);
            });
            if (state.selectedCameraId) localStorage.setItem(CAMERA_STORAGE_KEY, state.selectedCameraId);
            return cameras;
        } catch (error) {
            setSelectMessage('Autoriza el uso de la cámara');
            return [];
        }
    }

    function formatsToSupport() {
        const format = window.Html5QrcodeSupportedFormats;
        if (!format) return undefined;
        return [
            format.CODE_128, format.CODE_39, format.CODE_93, format.EAN_13,
            format.EAN_8, format.UPC_A, format.UPC_E, format.ITF,
            format.CODABAR, format.QR_CODE
        ].filter((item) => item !== undefined);
    }

    function scannerErrorMessage(error) {
        const value = String(error?.name || error?.message || error || '');
        if (/NotAllowed|Permission|denied/i.test(value)) return 'Permiso de cámara bloqueado. Habilítalo en el navegador e intenta de nuevo.';
        if (/NotFound|DevicesNotFound|Overconstrained/i.test(value)) return 'No se encontró una cámara compatible en este dispositivo.';
        if (/NotReadable|TrackStart|Could not start/i.test(value)) return 'La cámara está siendo usada por otra aplicación.';
        if (!window.isSecureContext) return 'La cámara requiere una conexión HTTPS segura.';
        return 'No se pudo iniciar la cámara. Revisa los permisos e intenta de nuevo.';
    }

    async function configureCameraTrack() {
        await new Promise((resolve) => window.setTimeout(resolve, 280));
        const video = elements.reader.querySelector('video');
        state.mediaTrack = video?.srcObject?.getVideoTracks?.()[0] || null;
        const capabilities = state.mediaTrack?.getCapabilities?.() || {};

        if (Array.isArray(capabilities.focusMode) && capabilities.focusMode.includes('continuous')) {
            state.mediaTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
        }

        elements.torch.disabled = !capabilities.torch;
        elements.torch.classList.remove('active');
        state.torchOn = false;

        if (capabilities.zoom && Number.isFinite(capabilities.zoom.min) && Number.isFinite(capabilities.zoom.max)) {
            const currentZoom = state.mediaTrack.getSettings?.().zoom || capabilities.zoom.min;
            elements.zoom.min = String(capabilities.zoom.min);
            elements.zoom.max = String(capabilities.zoom.max);
            elements.zoom.step = String(capabilities.zoom.step || 0.1);
            elements.zoom.value = String(currentZoom);
            elements.zoomValue.textContent = `${Number(currentZoom).toFixed(1)}×`;
            elements.zoomControl.hidden = false;
        } else {
            elements.zoomControl.hidden = true;
        }
    }

    async function startScanner() {
        if (state.starting || state.active) return;
        primeAudio();
        setPanelOpen(true);
        showFeedback('Solicitando acceso a la cámara…', 'loading');

        if (!window.Html5Qrcode || !navigator.mediaDevices?.getUserMedia) {
            showFeedback(window.isSecureContext
                ? 'Este navegador no ofrece acceso compatible a la cámara.'
                : 'Abre el sitio mediante HTTPS para utilizar la cámara.', 'error');
            return;
        }

        state.starting = true;
        state.cancelRequested = false;
        elements.launch.disabled = true;
        try {
            await loadCameras();
            state.scanner = state.scanner || new Html5Qrcode('qr-reader');
            const config = {
                fps: 18,
                qrbox: (width, height) => ({
                    width: Math.max(120, Math.min(Math.round(width * 0.84), 560)),
                    height: Math.max(80, Math.min(Math.round(height * 0.27), 180))
                }),
                aspectRatio: 1.7777778,
                experimentalFeatures: { useBarCodeDetectorIfSupported: true }
            };
            const formats = formatsToSupport();
            if (formats?.length) config.formatsToSupport = formats;
            const cameraConfig = state.selectedCameraId
                ? { deviceId: { exact: state.selectedCameraId } }
                : { facingMode: { ideal: 'environment' } };

            await state.scanner.start(cameraConfig, config, handleDecoded, () => {});
            if (state.cancelRequested) {
                await state.scanner.stop().catch(() => {});
                setLiveState(false);
                return;
            }
            state.paused = false;
            state.processing = false;
            state.candidateCode = '';
            state.candidateHits = 0;
            setLiveState(true);
            showFeedback('Listo para leer · mantén el código estable', 'ready');
            await configureCameraTrack();
            loadCameras().catch(() => {});
        } catch (error) {
            console.error('No se pudo iniciar el lector:', error);
            setLiveState(false);
            showFeedback(scannerErrorMessage(error), 'error');
        } finally {
            state.starting = false;
            elements.launch.disabled = false;
        }
    }

    async function pauseScanner() {
        if (!state.active || state.paused || !state.scanner) return;
        try {
            state.scanner.pause(true);
            state.paused = true;
        } catch (error) {
            console.warn('No fue posible pausar el lector:', error);
        }
    }

    function resumeScanner() {
        if (!state.active || !state.paused || !state.scanner) return;
        try {
            state.scanner.resume();
            state.paused = false;
            state.processing = false;
            state.candidateCode = '';
            state.candidateHits = 0;
            showFeedback('Listo para leer · mantén el código estable', 'ready');
        } catch (error) {
            console.warn('No fue posible reanudar el lector:', error);
            stopScanner(true).then(startScanner);
        }
    }

    async function stopScanner(keepPanelOpen = false) {
        if (state.starting) state.cancelRequested = true;
        if (state.scanner && (state.active || state.paused)) {
            try {
                await state.scanner.stop();
            } catch (error) {
                console.warn('No fue posible detener el lector:', error);
            }
        }
        state.mediaTrack = null;
        state.paused = false;
        state.processing = false;
        state.candidateCode = '';
        state.candidateHits = 0;
        state.torchOn = false;
        elements.torch.disabled = true;
        elements.torch.classList.remove('active');
        elements.zoomControl.hidden = true;
        setLiveState(false);
        if (!keepPanelOpen) setPanelOpen(false);
    }

    function handleDecoded(rawCode) {
        if (state.processing || state.paused) return;
        const code = normalizeCode(rawCode);
        const now = Date.now();
        if (!code) return;
        if (code === state.lastAcceptedCode && now - state.lastAcceptedAt < DUPLICATE_COOLDOWN_MS) return;

        if (code === state.candidateCode && now - state.candidateAt <= CONFIRMATION_WINDOW_MS) {
            state.candidateHits += 1;
        } else {
            state.candidateCode = code;
            state.candidateHits = 1;
        }
        state.candidateAt = now;

        if (state.candidateHits < 2) {
            showFeedback('Código detectado · confirmando lectura…', 'detecting');
            return;
        }

        state.processing = true;
        state.detectedCode = code;
        state.lastAcceptedCode = code;
        state.lastAcceptedAt = now;
        state.requestKey = createRequestKey();
        signal('success');
        pauseScanner();
        openProductResult(code);
    }

    async function fetchProduct(code) {
        const response = await fetch(`quick-entry.php?action=scan-product&code=${encodeURIComponent(code)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) throw new Error('No fue posible consultar el producto.');
        return response.json();
    }

    function resetProductVisual() {
        elements.visual.replaceChildren();
        const barcode = document.createElement('span');
        barcode.className = 'barcode-glyph';
        elements.visual.append(barcode);
    }

    function setProductImage(imagePath, productName) {
        const safePath = String(imagePath || '');
        if (!/^(?:\.\/)?uploads\/products\/[a-zA-Z0-9._/-]+$/.test(safePath) || safePath.includes('..')) {
            resetProductVisual();
            return;
        }
        const image = document.createElement('img');
        image.src = safePath;
        image.alt = productName;
        image.addEventListener('error', resetProductVisual, { once: true });
        elements.visual.replaceChildren(image);
    }

    async function openProductResult(code) {
        elements.fullscreen.classList.add('modal-open');
        elements.modal.classList.add('active');
        elements.resultIcon.textContent = '…';
        elements.resultIcon.className = 'scan-result-check loading';
        elements.code.textContent = `Código leído: ${code}`;
        elements.productName.textContent = 'Localizando producto…';
        elements.productMeta.textContent = '';
        elements.productStock.textContent = '';
        elements.productStock.className = 'scan-product-stock';
        elements.quantity.value = '1';
        elements.reason.value = '';
        elements.register.disabled = true;
        elements.useCode.disabled = true;
        elements.moveSelector.classList.remove('disabled');
        showModalFeedback();
        resetProductVisual();

        try {
            const result = await fetchProduct(code);
            if (!result.success || !result.product) {
                state.product = null;
                elements.resultIcon.textContent = '!';
                elements.resultIcon.className = 'scan-result-check error';
                elements.productName.textContent = 'Producto no registrado';
                elements.productMeta.textContent = 'Puedes volver a leerlo o buscar el código manualmente.';
                elements.moveSelector.classList.add('disabled');
                elements.useCode.disabled = false;
                signal('error');
                return;
            }

            state.product = result.product;
            const product = state.product;
            const isLow = Number(product.quantity) <= Number(product.min_stock);
            elements.resultIcon.textContent = '✓';
            elements.resultIcon.className = 'scan-result-check';
            elements.code.textContent = `SKU ${product.code}`;
            elements.productName.textContent = product.name;
            elements.productMeta.textContent = `${product.category_name || 'Sin categoría'} · $${product.unit_price} por unidad`;
            elements.productStock.textContent = `${product.quantity} disponibles · mínimo ${product.min_stock}${isLow ? ' · requiere reposición' : ''}`;
            elements.productStock.className = `scan-product-stock ${isLow ? 'risk' : 'healthy'}`;
            setProductImage(product.image, product.name);
            elements.register.disabled = false;
            elements.useCode.disabled = false;
            updateQuantityLimit();
            window.setTimeout(() => elements.quantity.focus(), 80);
        } catch (error) {
            console.error('Error consultando el producto:', error);
            state.product = null;
            elements.resultIcon.textContent = '!';
            elements.resultIcon.className = 'scan-result-check error';
            elements.productName.textContent = 'No pudimos consultar el inventario';
            elements.productMeta.textContent = 'Comprueba la conexión y vuelve a escanear.';
            elements.moveSelector.classList.add('disabled');
            showModalFeedback('La lectura se conservó; puedes intentarlo nuevamente.', 'error');
            signal('error');
        }
    }

    function closeModal(resume = true) {
        elements.modal.classList.remove('active');
        elements.fullscreen.classList.remove('modal-open');
        showModalFeedback();
        state.product = null;
        state.detectedCode = '';
        state.processing = false;
        if (resume) window.setTimeout(resumeScanner, 120);
    }

    function setMovementType(type) {
        state.movementType = type === 'salida' ? 'salida' : 'entrada';
        localStorage.setItem(MODE_STORAGE_KEY, state.movementType);
        elements.modeButtons.forEach((button) => button.classList.toggle('active', button.dataset.mode === state.movementType));
        elements.typeButtons.forEach((button) => button.classList.toggle('active', button.dataset.type === state.movementType));
        updateQuantityLimit();
    }

    function updateQuantityLimit() {
        if (!state.product) return;
        const maximum = state.movementType === 'salida' ? Math.max(1, Number(state.product.quantity)) : 100000;
        elements.quantity.max = String(maximum);
        let quantity = Number.parseInt(elements.quantity.value, 10) || 1;
        quantity = Math.min(Math.max(quantity, 1), maximum);
        elements.quantity.value = String(quantity);
        if (state.movementType === 'salida' && Number(state.product.quantity) < 1) {
            elements.register.disabled = true;
            showModalFeedback('Este producto no tiene unidades disponibles para salida.', 'error');
        } else {
            elements.register.disabled = false;
            showModalFeedback();
        }
    }

    function stepQuantity(delta) {
        const current = Number.parseInt(elements.quantity.value, 10) || 1;
        const maximum = Number.parseInt(elements.quantity.max, 10) || 100000;
        elements.quantity.value = String(Math.min(Math.max(current + delta, 1), maximum));
    }

    async function registerMovement() {
        if (!state.product || !state.detectedCode) return;
        const quantity = Number.parseInt(elements.quantity.value, 10);
        if (!Number.isInteger(quantity) || quantity < 1) {
            showModalFeedback('Indica una cantidad válida.', 'error');
            elements.quantity.focus();
            return;
        }
        if (state.movementType === 'salida' && quantity > Number(state.product.quantity)) {
            showModalFeedback(`Solo hay ${state.product.quantity} unidades disponibles.`, 'error');
            elements.quantity.focus();
            return;
        }

        elements.register.disabled = true;
        elements.register.textContent = 'Registrando…';
        showModalFeedback('Guardando movimiento en inventario…', 'loading');
        const payload = new URLSearchParams({
            action: 'scan-movement',
            code: state.detectedCode,
            type: state.movementType,
            quantity: String(quantity),
            reason: elements.reason.value.trim() || 'Movimiento por escáner de cámara',
            request_key: state.requestKey
        });

        try {
            const response = await fetch('quick-entry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString()
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'No se pudo registrar el movimiento.');
            }

            state.sessionCount += 1;
            elements.sessionCount.textContent = String(state.sessionCount);
            const direction = state.movementType === 'entrada' ? 'Entrada' : 'Salida';
            const productName = result.product?.name || state.product.name;
            const newStock = result.product?.quantity;
            closeModal(false);
            showFeedback(`${direction} registrada · ${productName} · existencia ${newStock}`, 'success');
            signal('success');
            window.setTimeout(resumeScanner, 850);
        } catch (error) {
            console.error('Error registrando el movimiento:', error);
            showModalFeedback(error.message || 'Error de conexión al registrar.', 'error');
            signal('error');
        } finally {
            elements.register.disabled = false;
            elements.register.textContent = 'Confirmar movimiento';
        }
    }

    async function toggleTorch() {
        if (!state.mediaTrack) return;
        state.torchOn = !state.torchOn;
        try {
            await state.mediaTrack.applyConstraints({ advanced: [{ torch: state.torchOn }] });
            elements.torch.classList.toggle('active', state.torchOn);
            elements.torch.querySelector('small').textContent = state.torchOn ? 'Apagar' : 'Linterna';
        } catch (error) {
            state.torchOn = false;
            elements.torch.disabled = true;
            showFeedback('La linterna no está disponible en esta cámara.', 'error');
        }
    }

    async function changeZoom() {
        if (!state.mediaTrack) return;
        const zoom = Number(elements.zoom.value);
        elements.zoomValue.textContent = `${zoom.toFixed(1)}×`;
        try {
            await state.mediaTrack.applyConstraints({ advanced: [{ zoom }] });
        } catch (error) {
            showFeedback('No fue posible aplicar el zoom.', 'error');
        }
    }

    elements.launch.addEventListener('click', () => state.active ? stopScanner() : startScanner());
    elements.close.addEventListener('click', () => stopScanner());
    elements.torch.addEventListener('click', toggleTorch);
    elements.zoom.addEventListener('input', changeZoom);
    elements.camera.addEventListener('change', async () => {
        state.selectedCameraId = elements.camera.value;
        if (state.selectedCameraId) localStorage.setItem(CAMERA_STORAGE_KEY, state.selectedCameraId);
        if (state.active) {
            await stopScanner(true);
            startScanner();
        }
    });
    elements.modeButtons.forEach((button) => button.addEventListener('click', () => setMovementType(button.dataset.mode)));
    elements.typeButtons.forEach((button) => button.addEventListener('click', () => setMovementType(button.dataset.type)));
    elements.quantityMinus.addEventListener('click', () => stepQuantity(-1));
    elements.quantityPlus.addEventListener('click', () => stepQuantity(1));
    elements.quantity.addEventListener('change', updateQuantityLimit);
    elements.register.addEventListener('click', registerMovement);
    elements.scanAgain.addEventListener('click', () => closeModal(true));
    elements.cancel.addEventListener('click', () => closeModal(true));
    elements.useCode.addEventListener('click', async () => {
        if (!state.detectedCode) return;
        elements.codeInput.value = state.detectedCode;
        await stopScanner();
        elements.searchForm.submit();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'F2') {
            event.preventDefault();
            elements.codeInput.focus();
        } else if (event.key === 'F3') {
            event.preventDefault();
            state.active ? stopScanner() : startScanner();
        } else if (event.key === 'Escape') {
            if (elements.modal.classList.contains('active')) {
                event.preventDefault();
                closeModal(true);
            } else if (elements.fullscreen.classList.contains('active')) {
                event.preventDefault();
                stopScanner();
            }
        } else if (event.key === 'Enter' && elements.modal.classList.contains('active') && document.activeElement !== elements.reason) {
            event.preventDefault();
            if (!elements.register.disabled) registerMovement();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && state.active) stopScanner();
    });
    window.addEventListener('beforeunload', () => stopScanner());
    window.resetForm = () => { window.location.href = 'quick-entry.php'; };

    setMovementType(state.movementType);
    setSelectMessage('Se detectará al iniciar');
}());
