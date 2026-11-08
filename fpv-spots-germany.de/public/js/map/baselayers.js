// =============================================================
// Basiskarten: Strasse (OSM), Satellit (Esri) und Hybrid
// (Satellit + Beschriftungen) samt schwebendem Umschalter (ES-Modul)
//
// Neue Basiskarte = ein Eintrag in createBaseLayers() plus ein Icon
// in BASE_LAYER_ICONS.
// =============================================================
import { config } from './state.js';

const MAX_ZOOM  = config.mapMaxZoom;
const LAYER_KEY = 'fpv_map_layer'; // sessionStorage: zuletzt gewaehlte Basiskarte
const DEFAULT_LAYER = 'Straße';

const ESRI_IMAGERY_ATTR = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';
const ESRI = 'https://server.arcgisonline.com/ArcGIS/rest/services';

const BASE_LAYER_ICONS = { 'Straße': 'bi-map', 'Satellit': 'bi-image', 'Hybrid': 'bi-layers-half' };

function createBaseLayers() {
    return {
        'Straße': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: MAX_ZOOM,
        }),
        'Satellit': L.tileLayer(ESRI + '/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: ESRI_IMAGERY_ATTR,
            maxZoom: MAX_ZOOM,
        }),
        'Hybrid': L.layerGroup([
            L.tileLayer(ESRI + '/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: ESRI_IMAGERY_ATTR,
                maxZoom: MAX_ZOOM,
            }),
            L.tileLayer(ESRI + '/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', {
                attribution: '',
                maxZoom: MAX_ZOOM,
            }),
            L.tileLayer(ESRI + '/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                attribution: '',
                maxZoom: MAX_ZOOM,
            }),
        ]),
    };
}

/**
 * Legt die Basiskarte (zuletzt gewaehlte oder Strasse) auf die Karte und
 * haengt die schwebende Icon-Leiste ein – statt der klassischen
 * Leaflet-Radiobox, damit sie immer sichtbar ist.
 */
export function initBaseLayers(map) {
    const baseLayers = createBaseLayers();

    const saved = (() => { try { return sessionStorage.getItem(LAYER_KEY); } catch (_) { return null; } })();
    let activeName = baseLayers[saved] ? saved : DEFAULT_LAYER;
    baseLayers[activeName].addTo(map);

    let switchBox = null;

    function selectBaseLayer(name) {
        if (name === activeName || !baseLayers[name]) return;
        map.removeLayer(baseLayers[activeName]);
        map.addLayer(baseLayers[name]);
        activeName = name;
        try { sessionStorage.setItem(LAYER_KEY, name); } catch (_) { /* sessionStorage nicht verfügbar – ignorieren */ }
        switchBox.querySelectorAll('.layer-switch-btn').forEach((btn) => {
            const isActive = btn.title === name;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', String(isActive));
        });
    }

    const BaseLayerSwitch = L.Control.extend({
        options: { position: 'bottomleft' },
        onAdd: function () {
            const container = L.DomUtil.create('div', 'layer-switch');
            L.DomEvent.disableClickPropagation(container);
            Object.keys(baseLayers).forEach((name) => {
                const btn = L.DomUtil.create('button', 'layer-switch-btn', container);
                btn.type = 'button';
                btn.title = name;
                btn.setAttribute('aria-label', name);
                btn.setAttribute('aria-pressed', String(name === activeName));
                btn.classList.toggle('active', name === activeName);
                btn.innerHTML = `<i class="bi ${BASE_LAYER_ICONS[name]}" aria-hidden="true"></i>`;
                L.DomEvent.on(btn, 'click', () => selectBaseLayer(name));
            });
            return container;
        },
    });

    switchBox = new BaseLayerSwitch().addTo(map).getContainer();
}
