// =============================================================
// Einstieg der Karte: Karte erzeugen, Module initialisieren (ES-Modul)
// Reihenfolge der Controls (bottomleft): Basiskarte, Layer-Box, Hinweis.
// =============================================================
import { createMap } from './core.js';
import { initBaseLayers } from './baselayers.js';
import { mountOverlays } from './layers/registry.js';
import { registerDipulOverlays, initDipulZoomHint } from './layers/dipul.js';
import { loadAllSpots } from './layers/spots.js';
import { openSpotDetail, initSpotPanel } from './features/spot_panel.js';
import { initPlacing } from './features/placing.js';
import { initLegend } from './features/legend.js';
import { initLocationRequest } from './features/location.js';
import { initPilotClassSwitch } from './features/pilot_class.js';

// Dashboard-Link: ?spot=ID frueh auslesen
const urlSpotId = new URLSearchParams(window.location.search).get('spot');

const map = createMap(urlSpotId);

// Basiskarten und Overlays
initBaseLayers(map);
registerDipulOverlays();
const { box: layerControlBox } = mountOverlays(map);
initDipulZoomHint(map, layerControlBox);

// Features
initPlacing();
initLegend();
initPilotClassSwitch();
initSpotPanel();
loadAllSpots({ onSelect: openSpotDetail, focusSpotId: urlSpotId });
initLocationRequest();
