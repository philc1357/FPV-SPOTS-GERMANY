// =============================================================
// Spot-Detail – Wetter
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird am Seitenende synchron geladen, d. h. das HTML ist beim
// Ausführen bereits vorhanden. Geladen wird erst, wenn der Bereich
// in Sichtnähe scrollt (IntersectionObserver) – kein Request beim
// Seitenaufbau, solange niemand bis zum Wetter kommt.
// =============================================================
(function () {
    var box = document.getElementById('wetterBox');
    if (!box) return;

    var body  = document.getElementById('wetterBody');
    var stand = document.getElementById('wetterStand');
    var laeuft = false;   // schuetzt vor doppeltem Laden

    // ── Hilfsfunktionen ──────────────────────────────────────

    function esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str === null || str === undefined ? '' : str));
        return d.innerHTML;
    }

    // Icon-Klassen kommen aus einer festen Tabelle auf dem Server.
    // Die Pruefung ist zweite Verteidigungslinie, kein Ersatz dafuer.
    function icon(name) {
        return /^bi-[a-z0-9-]+$/.test(name || '') ? name : 'bi-cloud';
    }

    // Zahl mit deutschem Dezimalkomma; fehlende Werte als Gedankenstrich.
    function num(wert, einheit, stellen) {
        if (wert === null || wert === undefined || isNaN(wert)) return '–';
        var s = Number(wert).toLocaleString('de-DE', {
            minimumFractionDigits: stellen || 0,
            maximumFractionDigits: stellen || 0
        });
        if (!einheit) return s;
        // Nach DIN 5008 steht vor einer Einheit ein Leerzeichen ("14,8 °C").
        // Das blosse Gradzeichen als Kurzform in den Streifen bildet die
        // Ausnahme – dort waere "15 °" typografisch falsch getrennt.
        return einheit === '\u00b0' ? s + einheit : s + ' ' + einheit;
    }

    // Windpfeil: Open-Meteo nennt die Richtung, AUS DER der Wind weht.
    // Fuer einen Pfeil in Bewegungsrichtung sind das +180 Grad.
    function pfeil(grad, kuerzel) {
        if (grad === null || grad === undefined || isNaN(grad)) return '';
        return '<i class="bi bi-arrow-up wx-arrow" aria-hidden="true" style="transform:rotate('
             + (Number(grad) + 180) % 360 + 'deg)"></i> ' + esc(kuerzel || '');
    }

    function kachel(bi, titel, inhalt) {
        return '<div class="wx-tile">'
             + '<div class="wx-tile-label"><i class="bi ' + icon(bi) + ' me-1" aria-hidden="true"></i>'
             + esc(titel) + '</div>'
             + '<div class="wx-tile-value">' + inhalt + '</div>'
             + '</div>';
    }

    // ── Rendern ──────────────────────────────────────────────

    function render(d) {
        var a = d.aktuell || {};
        var h = '';

        // Aktueller Zustand
        h += '<div class="wx-now">'
           +   '<i class="bi ' + icon(a.icon) + ' wx-now-icon" aria-hidden="true"></i>'
           +   '<div>'
           +     '<div class="wx-now-temp">' + num(a.temp, '°C', 1) + '</div>'
           +     '<div class="wx-now-text">' + esc(a.text)
           +       ' &middot; gefühlt ' + num(a.gefuehlt, '°C', 1) + '</div>'
           +   '</div>'
           + '</div>';

        // Messwert-Kacheln
        h += '<div class="wx-tiles">'
           + kachel('bi-wind',            'Wind',       num(a.wind, 'km/h') + ' ' + pfeil(a.richtung, a.richtung_text))
           + kachel('bi-wind',            'Böen',       num(a.boeen, 'km/h'))
           + kachel('bi-umbrella',        'Niederschlag', num(a.niederschlag, 'mm', 1))
           + kachel('bi-clouds',          'Bewölkung',  num(a.bewoelkung, '%'))
           + kachel('bi-eye',             'Sicht',      num(a.sicht_km, 'km', 1))
           + kachel('bi-droplet-half',    'Luftfeuchte', num(a.feuchte, '%'))
           + '</div>';

        // Sonnenzeiten + Kp-Index in einer ruhigen Zeile. Der Kp-Index
        // entfaellt still, wenn die NOAA nicht erreichbar war.
        var s = d.sonne || {};
        var extra = '';
        if (s.aufgang || s.untergang) {
            extra += '<span><i class="bi bi-sunrise" aria-hidden="true"></i>Aufgang ' + esc(s.aufgang) + ' Uhr</span>'
                   + '<span><i class="bi bi-sunset" aria-hidden="true"></i>Untergang ' + esc(s.untergang) + ' Uhr</span>';
        }
        if (d.kp) {
            extra += '<span title="Geomagnetische Aktivität – hohe Werte können die GPS-Genauigkeit verringern.">'
                   + '<i class="bi bi-activity" aria-hidden="true"></i>'
                   + 'Kp-Index ' + num(d.kp.wert, '', 1) + ' (' + esc(d.kp.text) + ')</span>';
        }
        if (extra) h += '<p class="wx-extra">' + extra + '</p>';

        // Naechste 24 Stunden – horizontal scrollbarer Streifen
        if (d.stunden && d.stunden.length) {
            h += '<h3 class="wx-h3">Nächste 24 Stunden</h3><div class="wx-hours">';
            d.stunden.forEach(function (x) {
                h += '<div class="wx-hour">'
                   +   '<div class="wx-hour-time">' + esc(x.zeit) + '</div>'
                   +   '<i class="bi ' + icon(x.icon) + '" title="' + esc(x.text) + '"></i>'
                   +   '<div class="wx-hour-temp">' + num(x.temp, '°') + '</div>'
                   +   '<div class="wx-hour-wind">' + num(x.wind) + '/'
                   +     num(x.boeen) + '</div>'
                   +   '<div class="wx-hour-dir">' + esc(x.richtung_text || '') + '</div>'
                   +   '<div class="wx-hour-rain">' + num(x.regen_prozent, '%') + '</div>'
                   + '</div>';
            });
            h += '</div><p class="wx-legend">Zeit &middot; Wetter &middot; Temperatur &middot; '
               + 'Wind/Böen in km/h &middot; Richtung &middot; Regenwahrscheinlichkeit</p>';
        }

        // 7-Tage-Uebersicht
        if (d.tage && d.tage.length) {
            h += '<h3 class="wx-h3">7 Tage</h3><div class="wx-days">';
            d.tage.forEach(function (x) {
                h += '<div class="wx-day">'
                   +   '<div class="wx-day-name">' + (x.heute ? 'Heute' : esc(x.wochentag))
                   +     '<span class="wx-day-date">' + esc(x.datum) + '</span></div>'
                   +   '<i class="bi ' + icon(x.icon) + '" title="' + esc(x.text) + '"></i>'
                   +   '<div class="wx-day-temp">' + num(x.tmax, '°') + '<span> / '
                   +     num(x.tmin, '°') + '</span></div>'
                   +   '<div class="wx-day-wind"><i class="bi bi-wind me-1" aria-hidden="true"></i>'
                   +     num(x.boeen, 'km/h') + '</div>'
                   +   '<div class="wx-day-rain"><i class="bi bi-umbrella me-1" aria-hidden="true"></i>'
                   +     num(x.regen_prozent, '%') + '</div>'
                   + '</div>';
            });
            h += '</div><p class="wx-legend">Wetter &middot; Höchst-/Tiefsttemperatur &middot; '
               + 'stärkste Böen &middot; Regenwahrscheinlichkeit</p>';
        }

        // Quellenangabe – Open-Meteo steht unter CC BY 4.0 und verlangt sie
        h += '<p class="fsg-fineprint">'
           + 'Prognosen sind Modellrechnungen und keine Zusage. Ob ein Flug möglich und '
           + 'zulässig ist, entscheidest du vor Ort.'
           + '<br>Wetterdaten: <a href="https://open-meteo.com/" target="_blank" rel="noopener" '
           + '>Open-Meteo.com</a> (CC BY 4.0), für Deutschland auf Basis von DWD ICON. '
           + 'Kp-Index: <a href="https://www.swpc.noaa.gov/" target="_blank" rel="noopener" '
           + '>NOAA SWPC</a>.'
           + '</p>';

        body.innerHTML = h;

        if (stand && d.stand_text) {
            stand.textContent = 'Stand: ' + d.stand_text;
        }
    }

    function renderFehler(meldung) {
        body.innerHTML =
            '<div class="fsg-empty">'
          +   '<p class="mb-2"><i class="bi bi-cloud-slash me-1" aria-hidden="true"></i>' + esc(meldung) + '</p>'
          +   '<button type="button" class="fsg-btn fsg-btn--sm" id="wetterRetry">'
          +     'Erneut versuchen</button>'
          + '</div>';
        if (stand) stand.textContent = '';

        var btn = document.getElementById('wetterRetry');
        if (btn) btn.addEventListener('click', function () {
            body.innerHTML = '<div class="wx-skeleton" style="height:5.5rem"></div>'
                           + '<div class="wx-skeleton" style="height:5rem"></div>';
            laden();
        });
    }

    // ── Laden ────────────────────────────────────────────────

    function laden() {
        if (box.dataset.loaded === '1' || laeuft) return;
        laeuft = true;
        body.setAttribute('aria-busy', 'true');

        fetch('/api/weather.php?spot_id=' + encodeURIComponent(box.dataset.spotId), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (res) {
            return res.json().then(function (daten) {
                if (!res.ok) throw new Error(daten && daten.error ? daten.error : 'HTTP ' + res.status);
                return daten;
            });
        })
        .then(function (daten) {
            render(daten);
            box.dataset.loaded = '1';
        })
        .catch(function (e) {
            renderFehler(e && e.message ? e.message : 'Wetterdaten konnten nicht geladen werden.');
        })
        .finally(function () {
            laeuft = false;
            body.setAttribute('aria-busy', 'false');
        });
    }

    // Erst laden, wenn der Bereich bis auf 400 px an den sichtbaren
    // Ausschnitt heranrueckt. Ohne IntersectionObserver (sehr alte
    // Browser) sofort laden, damit der Bereich nie leer bleibt.
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (eintraege) {
            if (eintraege.some(function (e) { return e.isIntersecting; })) {
                io.disconnect();
                laden();
            }
        }, { rootMargin: '400px 0px' });
        io.observe(box);
    } else {
        laden();
    }
})();
