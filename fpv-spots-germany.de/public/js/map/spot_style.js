// =============================================================
// Spot-Typ → Marker-Farbe und Marker-Icon (ES-Modul)
// =============================================================

export const TYPE_COLORS = {
    'Bando':    '#4b4e5a',
    'Feld':     '#ffe224',
    'Gebirge':  '#b1602d',
    'Park':     '#4ab52c',
    'Wald':     '#2d6a4f',
    'Windpark': '#74c2e0',
    'Sonstige': '#ffffff',
};

export const TYPE_TEXT_COLORS = {
    'Feld':     '#000000',
    'Sonstige': '#000000',
};

export const DIFF_COLORS = {
    'Anfänger':        '#54846c',
    'Mittel':          '#fcc03c',
    'Fortgeschritten': '#d83c48',
    'Profi':           '#242430',
};

/** Erstellt ein farbiges, kreisförmiges Leaflet-Icon für einen Spot-Typ und Schwierigkeitsgrad */
export function buildIcon(spotType, difficulty) {
    const color       = TYPE_COLORS[spotType] || '#fd7e14';
    const borderColor = DIFF_COLORS[difficulty] || '#fff';
    return L.divIcon({
        className: '',
        html: `<div style="
                    background:${color};
                    width:20px; height:20px;
                    border-radius:50%;
                    border:3px solid ${borderColor};
                    box-shadow:0 2px 6px rgba(0,0,0,.45);
               "></div>`,
        iconSize:   [26, 26],
        iconAnchor: [13, 13],
        popupAnchor:[0, -14],
    });
}
