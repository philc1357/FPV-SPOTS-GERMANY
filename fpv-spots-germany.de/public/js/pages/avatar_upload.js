// =============================================================
// Profilbild: Auswahl, Kreis-Zuschnitt (Eigenbau) und Upload
//
// Ablauf: Datei wählen → im Kreis verschieben/zoomen → Speichern.
// Der Browser schickt die ORIGINAL-Datei plus Ausschnitt in
// Originalpixeln (crop_x, crop_y, crop_size); geschnitten und neu
// kodiert wird ausschliesslich auf dem Server (/api/avatar.php).
//
// Hinweis CSP: img-src erlaubt data:, aber kein blob: – die Vorschau
// wird deshalb per FileReader als Data-URL geladen.
// =============================================================
'use strict';

(() => {
    const MAX_BYTES  = 10 * 1024 * 1024;
    const ALLOWED    = ['image/jpeg', 'image/png'];
    const MAX_ZOOM   = 4;          // Faktor relativ zur „cover“-Skalierung
    const CSRF_TOKEN = document.querySelector('meta[name="app-csrf-token"]').content;

    const modalEl  = document.getElementById('avatarModal');
    const stage    = document.getElementById('avatarStage');
    const img      = document.getElementById('avatarCropImg');
    const empty    = document.getElementById('avatarEmpty');
    const zoom     = document.getElementById('avatarZoom');
    const zoomRow  = document.getElementById('avatarZoomRow');
    const hint     = document.getElementById('avatarHint');
    const fileIn   = document.getElementById('avatarFile');
    const saveBtn  = document.getElementById('avatarSaveBtn');
    const errorBox = document.getElementById('avatarModalError');
    const avatar   = document.getElementById('dbAvatar');
    const inner    = document.getElementById('dbAvatarInner');
    const delItem  = document.getElementById('avatarDeleteItem');
    const alertBox = document.getElementById('avatarAlert');

    if (!modalEl || !stage || !img) return;

    // ---------------------------------------------------------
    // Zustand des Zuschneiders
    // ---------------------------------------------------------
    const st = {
        file: null,
        natW: 0, natH: 0,     // Bildgröße (EXIF-gedreht, wie angezeigt)
        stageSize: 0,         // Kantenlänge der Bühne in CSS-Pixeln
        minScale: 1,          // Bild füllt den Kreis gerade vollständig
        scale: 1,
        offX: 0, offY: 0,     // Position der linken oberen Bildecke auf der Bühne
        drag: null,           // { id, startX, startY, offX, offY }
    };

    // ---------------------------------------------------------
    // Meldungen
    // ---------------------------------------------------------
    function showModalError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.toggle('d-none', !msg);
    }

    function showHeroAlert(type, msg) {
        if (!alertBox) return;
        alertBox.textContent = msg;
        alertBox.classList.remove('d-none', 'alert-success', 'alert-danger');
        alertBox.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
        clearTimeout(showHeroAlert.t);
        showHeroAlert.t = setTimeout(() => alertBox.classList.add('d-none'), 4000);
    }

    // ---------------------------------------------------------
    // Geometrie: Klemmen und Darstellen
    // ---------------------------------------------------------
    /** Offsets so begrenzen, dass im Kreis (= Bühne) nie leerer Rand entsteht. */
    function clampOffsets() {
        const w = st.natW * st.scale;
        const h = st.natH * st.scale;
        st.offX = Math.min(0, Math.max(st.stageSize - w, st.offX));
        st.offY = Math.min(0, Math.max(st.stageSize - h, st.offY));
    }

    function render() {
        img.style.transform = `translate(${st.offX}px, ${st.offY}px) scale(${st.scale})`;
    }

    /** Neue Skalierung setzen, Bühnenmitte bleibt dabei fix. */
    function setScale(next) {
        next = Math.min(st.minScale * MAX_ZOOM, Math.max(st.minScale, next));
        const c = st.stageSize / 2;
        // Bildpunkt unter der Mitte bleibt unter der Mitte
        st.offX = c - (c - st.offX) * (next / st.scale);
        st.offY = c - (c - st.offY) * (next / st.scale);
        st.scale = next;
        clampOffsets();
        render();
    }

    /** Slider (0–1000) ↔ Skalierung, logarithmisch für gleichmäßiges Zoomgefühl. */
    function sliderToScale(v) {
        return st.minScale * Math.pow(MAX_ZOOM, v / 1000);
    }
    function scaleToSlider(s) {
        return Math.round(1000 * Math.log(s / st.minScale) / Math.log(MAX_ZOOM));
    }

    /** Bühne neu vermessen (Modal-Öffnung, Fenstergröße) und Bild einpassen. */
    function layout(reset) {
        if (!st.natW) return;
        const prevStage = st.stageSize;
        const prevRel   = st.scale / st.minScale;
        st.stageSize = stage.clientWidth;
        if (!st.stageSize) return;
        st.minScale = st.stageSize / Math.min(st.natW, st.natH);

        if (reset || !prevStage) {
            st.scale = st.minScale;
            st.offX  = (st.stageSize - st.natW * st.scale) / 2;
            st.offY  = (st.stageSize - st.natH * st.scale) / 2;
        } else {
            // Gleichen Ausschnitt bei geänderter Bühnengröße beibehalten
            const k = st.stageSize / prevStage;
            st.scale = st.minScale * prevRel;
            st.offX *= k;
            st.offY *= k;
        }
        clampOffsets();
        zoom.value = String(scaleToSlider(st.scale));
        render();
    }

    // ---------------------------------------------------------
    // Datei wählen und als Vorschau laden
    // ---------------------------------------------------------
    function resetCropper() {
        st.file = null;
        st.natW = st.natH = 0;
        st.stageSize = 0;
        img.hidden = true;
        img.removeAttribute('src');
        empty.hidden = false;
        zoomRow.hidden = true;
        hint.hidden = true;
        saveBtn.disabled = true;
        fileIn.value = '';
        showModalError('');
    }

    fileIn.addEventListener('change', () => {
        const file = fileIn.files && fileIn.files[0];
        showModalError('');
        if (!file) return;

        if (!ALLOWED.includes(file.type)) {
            showModalError('Kein erlaubtes Format (nur JPG/PNG).');
            fileIn.value = '';
            return;
        }
        if (file.size > MAX_BYTES) {
            showModalError('Datei zu groß (max. 10 MB).');
            fileIn.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onerror = () => showModalError('Datei konnte nicht gelesen werden.');
        reader.onload = () => {
            img.onload = () => {
                // naturalWidth/Height berücksichtigen die EXIF-Ausrichtung –
                // der Server dreht vor dem Zuschnitt genauso.
                st.file = file;
                st.natW = img.naturalWidth;
                st.natH = img.naturalHeight;
                img.hidden = false;
                empty.hidden = true;
                zoomRow.hidden = false;
                hint.hidden = false;
                saveBtn.disabled = false;
                layout(true);
            };
            img.onerror = () => {
                showModalError('Keine gültige Bilddatei.');
                resetCropper();
            };
            img.src = String(reader.result);
        };
        reader.readAsDataURL(file);
    });

    // ---------------------------------------------------------
    // Verschieben (Pointer-Events: Maus, Touch, Stift)
    // ---------------------------------------------------------
    stage.addEventListener('pointerdown', (ev) => {
        if (!st.natW || st.drag) return;
        ev.preventDefault();
        stage.setPointerCapture(ev.pointerId);
        st.drag = { id: ev.pointerId, startX: ev.clientX, startY: ev.clientY, offX: st.offX, offY: st.offY };
        stage.classList.add('is-dragging');
    });

    stage.addEventListener('pointermove', (ev) => {
        if (!st.drag || ev.pointerId !== st.drag.id) return;
        st.offX = st.drag.offX + (ev.clientX - st.drag.startX);
        st.offY = st.drag.offY + (ev.clientY - st.drag.startY);
        clampOffsets();
        render();
    });

    const endDrag = (ev) => {
        if (!st.drag || ev.pointerId !== st.drag.id) return;
        st.drag = null;
        stage.classList.remove('is-dragging');
    };
    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', endDrag);

    // ---------------------------------------------------------
    // Zoomen (Regler, Mausrad, Pfeiltasten über den Regler)
    // ---------------------------------------------------------
    zoom.addEventListener('input', () => {
        if (st.natW) setScale(sliderToScale(Number(zoom.value)));
    });

    stage.addEventListener('wheel', (ev) => {
        if (!st.natW) return;
        ev.preventDefault();
        setScale(st.scale * (ev.deltaY < 0 ? 1.08 : 1 / 1.08));
        zoom.value = String(scaleToSlider(st.scale));
    }, { passive: false });

    window.addEventListener('resize', () => layout(false));
    modalEl.addEventListener('shown.bs.modal', () => layout(!st.stageSize));
    modalEl.addEventListener('hidden.bs.modal', resetCropper);

    // ---------------------------------------------------------
    // Hero-Avatar austauschen (nur DOM-API, kein innerHTML)
    // ---------------------------------------------------------
    function setHeroAvatar(url) {
        inner.replaceChildren();
        if (url) {
            const pic = document.createElement('img');
            pic.src = url;
            pic.alt = '';
            pic.width = 84;
            pic.height = 84;
            pic.decoding = 'async';
            pic.draggable = false;
            inner.appendChild(pic);
        } else {
            inner.textContent = avatar.dataset.initials || '?';
        }
        avatar.classList.toggle('has-img', Boolean(url));
        if (delItem) delItem.hidden = !url;
    }

    // ---------------------------------------------------------
    // Speichern: Original + Ausschnitt an den Server
    // ---------------------------------------------------------
    saveBtn.addEventListener('click', async () => {
        if (!st.file || !st.natW) return;

        // Sichtbarer Kreis in Originalpixeln
        let size = Math.round(st.stageSize / st.scale);
        size = Math.max(1, Math.min(size, st.natW, st.natH));
        const x = Math.max(0, Math.min(st.natW - size, Math.round(-st.offX / st.scale)));
        const y = Math.max(0, Math.min(st.natH - size, Math.round(-st.offY / st.scale)));

        const fd = new FormData();
        fd.set('csrf_token', CSRF_TOKEN);
        fd.set('avatar', st.file, st.file.name);
        fd.set('crop_x', String(x));
        fd.set('crop_y', String(y));
        fd.set('crop_size', String(size));

        saveBtn.disabled = true;
        showModalError('');
        try {
            const res  = await fetch('/api/avatar.php', { method: 'POST', body: fd });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                showModalError(data.error || 'Fehler beim Speichern.');
                saveBtn.disabled = false;
                return;
            }
            setHeroAvatar(data.url);
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            showHeroAlert('success', 'Profilbild gespeichert.');
        } catch {
            showModalError('Netzwerkfehler. Bitte erneut versuchen.');
            saveBtn.disabled = false;
        }
    });

    // ---------------------------------------------------------
    // Entfernen (Konto-Menü)
    // ---------------------------------------------------------
    UiActions.register('dash-avatar-delete', async (btn) => {
        if (!confirm('Profilbild wirklich entfernen?')) return;
        btn.disabled = true;

        const fd = new FormData();
        fd.set('csrf_token', CSRF_TOKEN);
        fd.set('_method', 'DELETE');

        try {
            const res  = await fetch('/api/avatar.php', { method: 'POST', body: fd });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                showHeroAlert('error', data.error || 'Fehler beim Entfernen.');
                return;
            }
            setHeroAvatar(null);
            showHeroAlert('success', 'Profilbild entfernt.');
        } catch {
            showHeroAlert('error', 'Netzwerkfehler. Bitte erneut versuchen.');
        } finally {
            btn.disabled = false;
        }
    });
})();
