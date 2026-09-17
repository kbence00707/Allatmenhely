// =====================================================================
//  Shared code for every page (index.html, login.html, admin.html):
//  talking to the PHP API, Hungarian labels, small helper functions.
// =====================================================================

// Where the backend API is.
// Opened through XAMPP (http://localhost/Allatmenhely/) -> relative path, same server.
// Opened with VS Code Live Server (port 5500, 5501, ...) -> full XAMPP address.
const XAMPP_API_URL = 'http://localhost/Allatmenhely/backend/api';
const isLiveServer = location.port.startsWith('550');
const API_URL = (location.protocol.startsWith('http') && !isLiveServer) ? 'backend/api' : XAMPP_API_URL;

const SPECIES_LABELS = { dog: 'Kutya', cat: 'Macska', other: 'Egyéb' };
const SEX_LABELS = { male: 'Hím', female: 'Nőstény', unknown: 'Ismeretlen' };
const ANIMAL_STATUS_LABELS = { available: 'Örökbefogadható', reserved: 'Foglalt', adopted: 'Örökbefogadva' };
const APPOINTMENT_STATUS_LABELS = { pending: 'Függőben', confirmed: 'Megerősítve', completed: 'Teljesítve', cancelled: 'Lemondva' };

// Readable names for the fields in validation error messages
const FIELD_LABELS = {
    name: 'Név', species: 'Faj', breed: 'Fajta', age: 'Kor', sex: 'Nem', description: 'Leírás',
    image_url: 'Kép', status: 'Állapot', animal_id: 'Állat', visitor_name: 'Név',
    visitor_email: 'E-mail', visitor_phone: 'Telefonszám', appointment_date: 'Dátum',
    appointment_time: 'Időpont', note: 'Megjegyzés', email: 'E-mail', password: 'Jelszó'
};

const PLACEHOLDER_IMAGE = 'pictures/placeholder.svg';

class ApiError extends Error {
    constructor(message, status, details) {
        super(message);
        this.status = status;
        this.details = details || {};
    }
}

/**
 * Sends a request to the API and returns the JSON answer.
 * Example: const result = await apiRequest('/animals/1');
 *          await apiRequest('/animals', { method: 'POST', body: { name: 'Bodri', ... } });
 * Throws an ApiError if the server answers with an error.
 */
async function apiRequest(path, options = {}) {
    const fetchOptions = {
        method: options.method || 'GET',
        credentials: 'include', // send the login (session) cookie too
        headers: {}
    };
    if (options.body !== undefined) {
        fetchOptions.headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(options.body);
    }

    let response;
    try {
        response = await fetch(API_URL + path, fetchOptions);
    } catch (networkError) {
        throw new ApiError('A szerver nem érhető el. Fut az Apache és a MySQL az XAMPP-ban?', 0);
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new ApiError(data.error || 'Ismeretlen hiba történt.', response.status, data.details);
    }
    return data;
}

/** Error message + every field error, e.g. "Hibás adatok. Dátum: Csak jövőbeli időpontra lehet foglalni." */
function errorText(error) {
    const parts = [error.message];
    for (const [field, message] of Object.entries(error.details || {})) {
        parts.push(`${FIELD_LABELS[field] || field}: ${message}`);
    }
    return parts.join(' ');
}

/**
 * Makes text safe to put into HTML.
 * Without this, a visitor could type <script> into a form and run code on the staff page (XSS).
 */
function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function ageText(age) {
    if (age === null || age === undefined) return 'Ismeretlen kor';
    return age === 0 ? '1 évnél fiatalabb' : `${age} éves`;
}

/** Today's date as YYYY-MM-DD (local time) */
function todayString() {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** "2026-09-18" -> "2026. 09. 18." */
function formatDate(date) {
    return date ? date.replaceAll('-', '. ') + '.' : '';
}

function showMessage(element, text, type) {
    element.textContent = text;
    element.className = 'message ' + (type || '');
}

// ----- Popup window (uses the <div id="cenzur"> overlay) -----

function openModal(innerHtml) {
    const overlay = document.getElementById('cenzur');
    overlay.innerHTML = `
        <div class="modal" role="dialog" aria-modal="true">
            <button class="modal_close" type="button" aria-label="Bezárás">&times;</button>
            ${innerHtml}
        </div>`;
    overlay.classList.add('open');
    document.body.classList.add('modal_open');
    overlay.querySelector('.modal_close').addEventListener('click', closeModal);
    return overlay.querySelector('.modal');
}

function closeModal() {
    const overlay = document.getElementById('cenzur');
    overlay.classList.remove('open');
    overlay.innerHTML = '';
    document.body.classList.remove('modal_open');
}

document.addEventListener('click', event => {
    // Clicking the dark background closes the popup
    if (event.target.id === 'cenzur') closeModal();
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeModal();
});

/** If an image cannot be loaded, show the placeholder instead. */
function useImageFallback(container) {
    container.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', () => { img.src = PLACEHOLDER_IMAGE; }, { once: true });
    });
}
