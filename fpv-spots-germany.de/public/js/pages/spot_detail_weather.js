// =============================================================
// Spot-Detail – Wetter
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
(function () {
    var box = document.getElementById('wetterCollapse');
    if (!box) return;

    var body  = document.getElementById('wetterBody');
    var stand = document.getElementById('wetterStand');
    var laeuft = false;   // schuetzt vor schnellem Auf/Zu/Auf

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
        return '<div class="col-6 col-md-4"><div class="wx-tile h-100">'
             + '<div class="wx-tile-label"><i class="bi ' + icon(bi) + ' me-1" aria-hidden="true"></i>'
             + esc(titel) + '</div>'
             + '<div class="wx-tile-value">' + inhalt + '</div>'
             + '</div></div>';
    }

    // ── Rendern ──────────────────────────────────────────────

    function render(d) {
        var a = d.aktuell || {};
        var h = '';

        // Aktueller Zustand
        h += '<div class="d-flex align-items-center gap-3 mb-3">'
           +   '<i class="bi ' + icon(a.icon) + ' wx-now-icon" aria-hidden="true"></i>'
           +   '<div>'
           +     '<div class="wx-now-temp">' + num(a.temp, '°C', 1) + '</div>'
           +     '<div class="text-secondary small">' + esc(a.text)
           +       ' &middot; gefühlt ' + num(a.gefuehlt, '°C', 1) + '</div>'
           +   '</div>'
           + '</div>';

        // Messwert-Kacheln
        h += '<div class="row g-2 mb-3">'
           + kachel('bi-wind',            'Wind',       num(a.wind, 'km/h') + ' ' + pfeil(a.richtung, a.richtung_text))
           + kachel('bi-wind',            'Böen',       num(a.boeen, 'km/h'))
           + kachel('bi-umbrella',        'Niederschlag', num(a.niederschlag, 'mm', 1))
           + kachel('bi-clouds',          'Bewölkung',  num(a.bewoelkung, '%'))
           + kachel('bi-eye',             'Sicht',      num(a.sicht_km, 'km', 1))
           + kachel('bi-droplet-half',    'Luftfeuchte', num(a.feuchte, '%'))
           + '</div>';

        // Sonnenzeiten
        var s = d.sonne || {};
        if (s.aufgang || s.untergang) {
            h += '<p class="small text-secondary mb-2">'
               + '<i class="bi bi-sunrise me-1" aria-hidden="true"></i>' + esc(s.aufgang) + ' Uhr'
               + '<span class="mx-2">&middot;</span>'
               + '<i class="bi bi-sunset me-1" aria-hidden="true"></i>' + esc(s.untergang) + ' Uhr'
               + '</p>';
        }

        // Kp-Index – entfaellt still, wenn die NOAA nicht erreichbar war
        if (d.kp) {
            h += '<p class="small text-secondary mb-3">'
               + '<i class="bi bi-activity me-1" aria-hidden="true"></i>'
               + 'Kp-Index ' + num(d.kp.wert, '', 1) + ' (' + esc(d.kp.text) + ') '
               + '<span class="text-secondary">– geomagnetische Aktivität, '
               + 'hohe Werte können die GPS-Genauigkeit verringern.</span>'
               + '</p>';
        }

        // Naechste 24 Stunden – horizontal scrollbarer Streifen
        if (d.stunden && d.stunden.length) {
            h += '<h3 class="h6 mt-4 mb-2">Nächste 24 Stunden</h3><div class="wx-hours">';
            d.stunden.forEach(function (x) {
                h += '<div class="wx-hour">'
                   +   '<div class="wx-hour-time">' + esc(x.zeit) + '</div>'
                   +   '<i class="bi ' + icon(x.icon) + '" title="' + esc(x.text) + '"></i>'
                   +   '<div class="wx-hour-temp">' + num(x.temp, '°') + '</div>'
                   +   '<div class="wx-hour-wind">' + num(x.wind) + '<span class="text-secondary">/</span>'
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
            h += '<h3 class="h6 mt-4 mb-2">7 Tage</h3><div class="wx-days">';
            d.tage.forEach(function (x) {
                h += '<div class="wx-day">'
                   +   '<div class="wx-day-name">' + (x.heute ? 'Heute' : esc(x.wochentag))
                   +     '<span class="wx-day-date">' + esc(x.datum) + '</span></div>'
                   +   '<i class="bi ' + icon(x.icon) + '" title="' + esc(x.text) + '"></i>'
                   +   '<div class="wx-day-temp">' + num(x.tmax, '°') + '<span class="text-secondary"> / '
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
        h += '<p class="small text-secondary border-top border-secondary pt-3 mt-4 mb-0">'
           + '<i class="bi bi-info-circle me-1" aria-hidden="true"></i>'
           + 'Prognosen sind Modellrechnungen und keine Zusage. Ob ein Flug möglich und '
           + 'zulässig ist, entscheidest du vor Ort.'
           + '<br>Wetterdaten: <a href="https://open-meteo.com/" target="_blank" rel="noopener" '
           + 'class="text-info">Open-Meteo.com</a> (CC BY 4.0), für Deutschland auf Basis von DWD ICON. '
           + 'Kp-Index: <a href="https://www.swpc.noaa.gov/" target="_blank" rel="noopener" '
           + 'class="text-info">NOAA SWPC</a>.'
           + '</p>';

        body.innerHTML = h;

        if (stand && d.stand_text) {
            stand.textContent = 'Stand: ' + d.stand_text;
        }
    }

    function renderFehler(meldung) {
        body.innerHTML =
            '<div class="alert alert-secondary mb-0">'
          +   '<p class="mb-2"><i class="bi bi-cloud-slash me-1" aria-hidden="true"></i>' + esc(meldung) + '</p>'
          +   '<button type="button" class="btn btn-outline-secondary btn-sm" id="wetterRetry">'
          +     'Erneut versuchen</button>'
          + '</div>';
        if (stand) stand.textContent = '';

        var btn = document.getElementById('wetterRetry');
        if (btn) btn.addEventListener('click', function () {
            body.innerHTML = '<div class="wx-skeleton" style="height:5.5rem"></div>'
                           + '<div class="wx-skeleton mt-2" style="height:5rem"></div>';
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

    box.addEventListener('shown.bs.collapse', laden);
})();
