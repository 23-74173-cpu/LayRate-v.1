{{--
    Egg stock QR scanner (Egg Stocks tab, opened from the mobile-only
    "Scan Egg QR" button or the dock "+" menu).

    Reads the label printed by EggStockController::qr()
    ("LAYRATE|id|harvested|cage|size|count") and shows the batch's live
    freshness from eggs.stocks.scan.

    Two ways to read the code:
      - Live camera: only where the browser allows camera access (https or
        localhost). Phones block it on plain http, which is how the Pi is
        served, so there the scanner opens in photo mode.
      - Photo: the phone's camera app takes a picture of the label (works on
        http), decoded on the device with jsQR (public/js/jsqr.min.js,
        loaded only when the scanner is first opened).
--}}
<div id="eggQrScanner" data-modal data-close="closeEggQrScanner" style="display: none;"
     class="fixed inset-0 z-50 flex-col min-h-screen min-h-[100dvh]" role="dialog" aria-modal="true" aria-labelledby="eggQrScannerTitle"
     >
    <div class="absolute inset-0" style="background-color: #0f1626;"></div>

    <div class="relative flex flex-col h-full max-h-[100dvh] w-full max-w-md mx-auto">
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 pt-4 pb-3 text-white">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0" style="background-color: rgba(255,255,255,0.12);">
                    <i data-lucide="scan-qr-code" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <h2 id="eggQrScannerTitle" class="text-base font-semibold leading-tight">Scan Egg QR</h2>
                    <p class="text-xs leading-tight" style="color: rgba(255,255,255,0.65);">Check the freshness of a stock batch</p>
                </div>
            </div>
            <button type="button" onclick="closeEggQrScanner()" class="p-2 rounded-full hover:bg-white/10 transition-colors" aria-label="Close scanner">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 pb-5 flex flex-col">
            {{-- Live camera --}}
            <div id="eggQrCameraView" class="hidden flex-col items-center">
                <div class="relative w-full aspect-square max-w-[22rem] rounded-2xl overflow-hidden" style="background-color: #000;">
                    <video id="eggQrVideo" class="absolute inset-0 w-full h-full object-cover" playsinline muted autoplay></video>
                    {{-- Aiming guide --}}
                    <div class="absolute inset-[14%] rounded-xl pointer-events-none" style="border: 3px solid rgba(255,255,255,0.9); box-shadow: 0 0 0 999px rgba(0,0,0,0.35);"></div>
                </div>
                <p class="mt-4 text-sm text-center text-white" aria-live="polite" id="eggQrCameraStatus">Point the camera at the QR code on the egg label.</p>
                <button type="button" onclick="eggQrUsePhoto()" class="mt-3 text-sm font-medium underline underline-offset-4" style="color: rgba(255,255,255,0.8);">
                    Take a photo instead
                </button>
            </div>

            {{-- Photo mode --}}
            <div id="eggQrPhotoView" class="hidden flex-col">
                <div class="rounded-2xl p-5 text-center" style="background-color: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);">
                    <div class="w-16 h-16 rounded-full mx-auto flex items-center justify-center" style="background-color: rgba(255,255,255,0.1);">
                        <i data-lucide="qr-code" class="w-8 h-8 text-white"></i>
                    </div>
                    <p class="mt-3 text-sm font-medium text-white">Take a clear photo of the QR code</p>
                    <p class="mt-1 text-xs leading-relaxed" style="color: rgba(255,255,255,0.65);">Fill most of the photo with the label, in good light.</p>
                    <p id="eggQrPhotoNote" class="hidden mt-3 text-xs leading-relaxed rounded-lg px-3 py-2" style="background-color: rgba(255,255,255,0.08); color: rgba(255,255,255,0.75);"></p>
                </div>
                <label class="mt-4 flex items-center justify-center gap-2 w-full rounded-full px-5 py-3.5 text-sm font-semibold cursor-pointer" style="background-color: #ffffff; color: var(--color-navy);">
                    <i data-lucide="camera" class="w-5 h-5"></i>
                    Take photo of QR
                    <input type="file" accept="image/*" capture="environment" class="sr-only" data-egg-qr-photo>
                </label>
                <label class="mt-2.5 flex items-center justify-center gap-2 w-full rounded-full px-5 py-3 text-sm font-medium cursor-pointer" style="background-color: rgba(255,255,255,0.08); color: #ffffff; border: 1px solid rgba(255,255,255,0.2);">
                    <i data-lucide="images" class="w-4 h-4"></i>
                    Choose from gallery
                    <input type="file" accept="image/*" class="sr-only" data-egg-qr-photo>
                </label>
            </div>

            {{-- Working --}}
            <div id="eggQrBusy" class="hidden flex-col items-center justify-center py-16 text-white">
                <span class="animate-spin inline-block w-8 h-8 border-[3px] border-white border-t-transparent rounded-full"></span>
                <p id="eggQrBusyText" class="mt-4 text-sm" aria-live="polite">Reading QR code…</p>
            </div>

            {{-- Error --}}
            <div id="eggQrError" class="hidden flex-col">
                <div class="rounded-2xl p-5 text-center" style="background-color: #ffffff;">
                    <div class="w-14 h-14 rounded-full mx-auto flex items-center justify-center" style="background-color: #fbe4e6;">
                        <i data-lucide="circle-alert" class="w-7 h-7" style="color: #9b1c24;"></i>
                    </div>
                    <p id="eggQrErrorText" class="mt-3 text-sm font-medium" style="color: #1f1f1f;" role="alert"></p>
                </div>
                <button type="button" onclick="eggQrRestart()" class="mt-4 w-full rounded-full px-5 py-3.5 text-sm font-semibold text-white" style="background-color: var(--color-navy); border: 1px solid rgba(255,255,255,0.35);">
                    Try again
                </button>
            </div>

            {{-- Result --}}
            <div id="eggQrResult" class="hidden flex-col"></div>
        </div>
    </div>
</div>

<script>
(function () {
    var SCAN_URL = @json(route('eggs.stocks.scan'));
    var JSQR_SRC = '/js/jsqr.min.js?v={{ @filemtime(public_path('js/jsqr.min.js')) }}';
    var VIEWS = ['eggQrCameraView', 'eggQrPhotoView', 'eggQrBusy', 'eggQrError', 'eggQrResult'];

    var stream = null;
    var loopTimer = null;
    var detector = null;
    var lastResult = null;

    function el(id) { return document.getElementById(id); }

    // Camera starts and lookups finish asynchronously; by then the user may
    // have closed the scanner or left the page, so text updates are no-ops
    // when the element is gone.
    function setText(id, text) {
        var node = el(id);
        if (node) node.textContent = text;
    }

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function show(id) {
        VIEWS.forEach(function (v) {
            var node = el(v);
            if (!node) return;
            node.classList.toggle('hidden', v !== id);
            node.classList.toggle('flex', v === id);
        });
    }

    function canUseLiveCamera() {
        return !!(window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    var jsqrPromise = null;
    function loadJsQR() {
        if (window.jsQR) return Promise.resolve(window.jsQR);
        if (jsqrPromise) return jsqrPromise;
        jsqrPromise = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = JSQR_SRC;
            s.onload = function () { window.jsQR ? resolve(window.jsQR) : reject(new Error('jsQR missing')); };
            s.onerror = function () { jsqrPromise = null; reject(new Error('jsQR failed to load')); };
            document.head.appendChild(s);
        });
        return jsqrPromise;
    }

    // Native detector where the browser has one (Chrome on Android, https only).
    function getDetector() {
        if (detector !== null) return Promise.resolve(detector);
        if (!('BarcodeDetector' in window)) { detector = false; return Promise.resolve(false); }
        return Promise.resolve(window.BarcodeDetector.getSupportedFormats ? window.BarcodeDetector.getSupportedFormats() : ['qr_code'])
            .then(function (formats) {
                detector = formats.indexOf('qr_code') >= 0 ? new window.BarcodeDetector({ formats: ['qr_code'] }) : false;
                return detector;
            })
            .catch(function () { detector = false; return false; });
    }

    // Decode a drawable source (video frame or photo) at a given max size.
    function decodeWithJsQR(source, srcW, srcH, maxSide) {
        var scale = Math.min(1, maxSide / Math.max(srcW, srcH));
        var w = Math.max(1, Math.round(srcW * scale));
        var h = Math.max(1, Math.round(srcH * scale));
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(source, 0, 0, w, h);
        var img = ctx.getImageData(0, 0, w, h);
        var hit = window.jsQR(img.data, w, h, { inversionAttempts: 'attemptBoth' });
        return hit && hit.data ? hit.data : null;
    }

    // ── Live camera ──
    function stopCamera() {
        if (loopTimer) { clearTimeout(loopTimer); loopTimer = null; }
        if (stream) {
            stream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} });
            stream = null;
        }
        var v = el('eggQrVideo');
        if (v) { try { v.pause(); } catch (e) {} v.srcObject = null; }
    }

    function startCamera() {
        show('eggQrCameraView');
        setText('eggQrCameraStatus', 'Starting camera…');
        Promise.all([
            navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }),
            getDetector(),
            loadJsQR().catch(function () { return null; }),
        ]).then(function (res) {
            if (!isOpen()) { res[0].getTracks().forEach(function (t) { t.stop(); }); return; }
            stream = res[0];
            var v = el('eggQrVideo');
            v.srcObject = stream;
            var p = v.play();
            if (p && p.catch) p.catch(function () {});
            setText('eggQrCameraStatus', 'Point the camera at the QR code on the egg label.');
            scanFrame();
        }).catch(function () {
            stopCamera();
            showPhoto('The camera could not be opened here, so take a photo of the QR instead.');
        });
    }

    function scanFrame() {
        if (!stream) return;
        var v = el('eggQrVideo');
        var next = function () { loopTimer = setTimeout(scanFrame, 180); };
        if (!v || v.readyState < 2 || !v.videoWidth) { next(); return; }

        var found = function (code) {
            if (code) { stopCamera(); lookup(code); } else next();
        };
        if (detector) {
            detector.detect(v).then(function (codes) {
                found(codes && codes.length ? codes[0].rawValue : null);
            }).catch(function () {
                found(window.jsQR ? decodeWithJsQR(v, v.videoWidth, v.videoHeight, 640) : null);
            });
        } else if (window.jsQR) {
            found(decodeWithJsQR(v, v.videoWidth, v.videoHeight, 640));
        } else {
            next();
        }
    }

    // ── Photo mode ──
    function showPhoto(note) {
        show('eggQrPhotoView');
        var n = el('eggQrPhotoNote');
        if (n) {
            n.textContent = note || '';
            n.classList.toggle('hidden', !note);
        }
        loadJsQR().catch(function () {});
    }

    function decodePhoto(file) {
        show('eggQrBusy');
        setText('eggQrBusyText', 'Reading QR code…');
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            loadJsQR().then(function () {
                var w = img.naturalWidth, h = img.naturalHeight, code = null;
                // Small first (fast); larger sizes help with small or distant labels.
                [1000, 1600, 700, 2400].some(function (side) {
                    try { code = decodeWithJsQR(img, w, h, side); } catch (e) { code = null; }
                    return !!code;
                });
                URL.revokeObjectURL(url);
                if (code) lookup(code);
                else showError('No QR code was found in the photo. Take it closer, with the whole label in view and in good light.');
            }).catch(function () {
                URL.revokeObjectURL(url);
                showError('The QR reader could not be loaded. Check your connection to the Pi and try again.');
            });
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            showError('That photo could not be opened. Try taking it again.');
        };
        img.src = url;
    }

    // ── Lookup + result ──
    function showError(msg) {
        stopCamera();
        setText('eggQrErrorText', msg);
        show('eggQrError');
        if (window.lucide) lucide.createIcons();
    }

    function lookup(code) {
        show('eggQrBusy');
        setText('eggQrBusyText', 'Checking freshness…');
        if (navigator.vibrate) { try { navigator.vibrate(60); } catch (e) {} }
        fetch(SCAN_URL + '?code=' + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (r.status === 401 || r.status === 419) throw { message: 'Your session has ended. Sign in again, then scan.' };
            return r.json().then(function (body) {
                if (!r.ok || !body.ok) throw { message: (body && body.message) || 'This QR code could not be checked.' };
                return body;
            }, function () { throw { message: 'This QR code could not be checked.' }; });
        }).then(renderResult).catch(function (err) {
            showError(err && err.message ? err.message : 'Could not reach the Pi. Check the connection and try again.');
        });
    }

    var TONES = {
        fresh: { fg: '#1f6b3a', bg: '#e8f5ec', border: '#cfe8d6', icon: 'circle-check' },
        aging: { fg: '#b45309', bg: '#fef3cd', border: '#f5dcc4', icon: 'clock' },
        old:   { fg: '#9b1c24', bg: '#fbe4e6', border: '#f3cdd0', icon: 'circle-alert' },
    };

    function renderResult(data) {
        var box = el('eggQrResult');
        if (!box || !isOpen()) return; // closed or left the page meanwhile
        lastResult = data;
        var f = data.freshness, b = data.batch;
        var t = TONES[f.status] || TONES.old;
        var age = f.days_old === 0 ? 'Harvested today' : (f.days_old === 1 ? '1 day old' : f.days_old + ' days old');
        var note = '';
        if (!data.found) {
            note = 'This batch is no longer in the stock records (it may have been deleted). Freshness is from the date on the label.';
        } else if (data.changed) {
            note = 'This batch was edited after the label was printed. Showing its current details; consider printing a new label.';
        }
        var row = function (label, value) {
            return '<div class="flex items-center justify-between gap-3 py-2.5" style="border-top: 1px solid #f0efee;">'
                + '<span class="text-xs font-medium uppercase tracking-wide" style="color: #6B7280;">' + esc(label) + '</span>'
                + '<span class="text-sm font-semibold text-right" style="color: #1f1f1f;">' + esc(value) + '</span></div>';
        };
        var canEdit = data.found && typeof window.openEditStock === 'function';

        box.innerHTML =
            '<div class="rounded-2xl overflow-hidden" style="background-color: #ffffff;">'
            + '<div class="px-5 py-5 text-center" style="background-color: ' + t.bg + '; border-bottom: 1px solid ' + t.border + ';">'
            +   '<div class="w-14 h-14 rounded-full mx-auto flex items-center justify-center" style="background-color: #ffffff; color: ' + t.fg + ';"><i data-lucide="' + t.icon + '" class="w-8 h-8"></i></div>'
            +   '<p class="mt-2 text-2xl font-bold tracking-tight" style="color: ' + t.fg + ';">' + esc(f.label) + '</p>'
            +   '<p class="text-sm font-medium" style="color: ' + t.fg + ';">' + esc(age) + '</p>'
            +   '<p class="mt-2 text-sm leading-relaxed" style="color: #31302e;">' + esc(f.message) + '</p>'
            + '</div>'
            + '<div class="px-5 pt-1 pb-2">'
            +   row('Size', b.size_label)
            +   row(data.found ? 'In stock' : 'On label', b.count.toLocaleString() + ' eggs · ' + b.trays + (b.trays === 1 ? ' tray' : ' trays'))
            +   row('Harvested', b.harvested)
            +   row('Cage', b.cage_code)
            +   row('Batch', '#' + b.id)
            + '</div>'
            + '<p class="px-5 pb-4 text-[11px] leading-relaxed" style="color: #6B7280;">Fresh: up to ' + esc(f.fresh_days) + ' days. Aging: up to ' + esc(f.aging_days) + ' days. Older is Old.</p>'
            + '</div>'
            + (note ? '<p class="mt-3 text-xs leading-relaxed rounded-xl px-3.5 py-2.5" style="background-color: rgba(255,255,255,0.08); color: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.15);">' + esc(note) + '</p>' : '')
            + '<button type="button" onclick="eggQrRestart()" class="mt-4 flex items-center justify-center gap-2 w-full rounded-full px-5 py-3.5 text-sm font-semibold" style="background-color: #ffffff; color: var(--color-navy);"><i data-lucide="scan-qr-code" class="w-5 h-5"></i>Scan another</button>'
            + (canEdit ? '<button type="button" onclick="eggQrEditBatch()" class="mt-2.5 flex items-center justify-center gap-2 w-full rounded-full px-5 py-3 text-sm font-medium text-white" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2);"><i data-lucide="pencil" class="w-4 h-4"></i>Edit this batch</button>' : '');
        show('eggQrResult');
        if (window.lucide) lucide.createIcons();
    }

    // ── Open / close ──
    function isOpen() {
        var m = el('eggQrScanner');
        return !!(m && m.style.display !== 'none');
    }

    function begin() {
        stopCamera();
        if (canUseLiveCamera()) startCamera();
        else showPhoto('');
    }

    window.openEggQrScanner = function () {
        var m = el('eggQrScanner');
        if (!m) return;
        m.style.display = 'flex';
        begin();
        if (window.lucide) lucide.createIcons();
    };

    window.closeEggQrScanner = function () {
        stopCamera();
        var m = el('eggQrScanner');
        if (m) m.style.display = 'none';
    };

    window.eggQrRestart = begin;

    window.eggQrUsePhoto = function () {
        stopCamera();
        showPhoto('');
    };

    window.eggQrEditBatch = function () {
        var b = lastResult && lastResult.batch;
        if (!b) return;
        window.closeEggQrScanner();
        window.openEditStock(b.id, b.size, b.count, b.harvested_iso);
    };

    // This script re-runs on every Egg Stocks render; bind document-level
    // listeners once. They always call the current window functions.
    window.__eggQrStopCamera = stopCamera;
    if (!window.__eggQrScannerBound) {
        window.__eggQrScannerBound = true;

        document.addEventListener('change', function (e) {
            var input = e.target;
            if (!input || !input.matches || !input.matches('input[data-egg-qr-photo]')) return;
            var file = input.files && input.files[0];
            input.value = '';
            if (file && window.__eggQrDecodePhoto) window.__eggQrDecodePhoto(file);
        });

        // Never leave the camera running after leaving the page or tab.
        var stopAll = function () { if (window.__eggQrStopCamera) window.__eggQrStopCamera(); };
        document.addEventListener('turbo:before-render', stopAll);
        document.addEventListener('turbo:before-cache', stopAll);
        document.addEventListener('turbo:before-frame-render', function (e) {
            if (e.target && e.target.id === 'egg-content') stopAll();
        });
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stopAll();
            else if (window.__eggQrResumeCamera) window.__eggQrResumeCamera();
        });
    }
    window.__eggQrDecodePhoto = decodePhoto;
    // Coming back to the app with the camera view still open: start it again.
    window.__eggQrResumeCamera = function () {
        var cam = el('eggQrCameraView');
        if (isOpen() && cam && !cam.classList.contains('hidden') && !stream) startCamera();
    };
})();
</script>
