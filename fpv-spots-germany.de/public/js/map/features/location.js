// =============================================================
// Standort: Einwilligungs-Modal und Standort-Marker (ES-Modul)
// =============================================================
import { state } from '../state.js';

const LOCATION_KEY = 'location_consent';
const LOCATION_COOKIE_DAYS = 365;

function getLocationCookie() {
    const match = document.cookie.split('; ').find(row => row.startsWith(LOCATION_KEY + '='));
    return match ? match.split('=')[1] : null;
}

function setLocationCookie(value) {
    const expires = new Date(Date.now() + LOCATION_COOKIE_DAYS * 864e5).toUTCString();
    document.cookie = `${LOCATION_KEY}=${value}; expires=${expires}; path=/; SameSite=Lax`;
}

function showUserLocation() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords;
            const userIcon = L.divIcon({
                className: '',
                html: '<div class="user-location-marker"></div>',
                iconSize:   [20, 20],
                iconAnchor: [10, 10],
            });
            const userMarker = L.marker([latitude, longitude], { icon: userIcon, zIndexOffset: 1000 })
                .addTo(state.map)
                .bindPopup('Dein Standort');

            // Aktiv-Zustand wie beim gewaehlten Kartenlayer: blau,
            // sobald ein Standort vorliegt und der Button etwas tut.
            const btn = document.getElementById('locateMeBtn');
            btn.classList.add('active');
            btn.addEventListener('click', () => {
                state.map.setView([latitude, longitude], 13);
                userMarker.openPopup();
            });
        },
        () => { /* Standort nicht verfügbar – still ignorieren */ }
    );
}

export function initLocationRequest() {
    const consent = getLocationCookie();
    if (consent === 'granted') {
        showUserLocation();
        return;
    }

    const btn   = document.getElementById('locateMeBtn');
    const modal = new bootstrap.Modal(document.getElementById('locationModal'), { backdrop: 'static' });

    function openModal() {
        document.getElementById('locationAllowBtn').addEventListener('click', () => {
            setLocationCookie('granted');
            modal.hide();
            btn.removeEventListener('click', openModal);
            showUserLocation();
        }, { once: true });

        document.getElementById('locationDenyBtn').addEventListener('click', () => {
            setLocationCookie('denied');
            modal.hide();
        }, { once: true });

        modal.show();
    }

    btn.addEventListener('click', openModal);

    // Erstbesuch (kein Cookie): Modal wie bisher nach 800 ms automatisch zeigen
    if (!consent) {
        setTimeout(openModal, 800);
    }
}
