// =====================================================================
//  Staff page (admin.html): appointments and animal management.
//  Needs common.js to be loaded first.
// =====================================================================

const appointmentsTable = document.getElementById('appointments_table');
const appointmentsMessage = document.getElementById('appointments_message');
const appointmentPeriod = document.getElementById('appointment_period');
const appointmentStatus = document.getElementById('appointment_status');
const animalsTable = document.getElementById('animals_table');
const animalsMessage = document.getElementById('animals_message');

let allAnimals = [];       // every animal, also used in the appointment edit form
let allAppointments = [];

// ----- Login check -----

async function init() {
    try {
        const { data: user } = await apiRequest('/auth/me');
        document.getElementById('current_user').textContent = user.name;
    } catch (error) {
        location.href = 'login.html'; // not logged in
        return;
    }
    await loadAnimals();
    await loadAppointments();
}

document.getElementById('logout_button').addEventListener('click', async () => {
    await apiRequest('/auth/logout', { method: 'POST' }).catch(() => {});
    location.href = 'login.html';
});

// ===================== APPOINTMENTS =====================

async function loadAppointments() {
    const params = new URLSearchParams({ sort: 'asc' });
    if (appointmentPeriod.value === 'upcoming') params.set('upcoming', '1');
    if (appointmentStatus.value) params.set('status', appointmentStatus.value);

    try {
        const result = await apiRequest('/appointments?' + params.toString());
        allAppointments = result.data;
        renderAppointments();
    } catch (error) {
        handleError(error, appointmentsMessage);
    }
}

function renderAppointments() {
    if (allAppointments.length === 0) {
        appointmentsTable.innerHTML = '<tr><td colspan="7">Nincs megjeleníthető időpont.</td></tr>';
        return;
    }

    const today = todayString();
    appointmentsTable.innerHTML = allAppointments.map(ap => `
        <tr class="${ap.appointment_date === today ? 'row_today' : ''}">
            <td>${formatDate(ap.appointment_date)}${ap.appointment_date === today ? ' <strong>(ma)</strong>' : ''}</td>
            <td>${escapeHtml(ap.appointment_time)}</td>
            <td>
                <strong>${escapeHtml(ap.visitor_name)}</strong><br>
                <a href="mailto:${escapeHtml(ap.visitor_email)}">${escapeHtml(ap.visitor_email)}</a>
                ${ap.visitor_phone ? '<br>' + escapeHtml(ap.visitor_phone) : ''}
            </td>
            <td>${escapeHtml(ap.animal_name)} <small>(${SPECIES_LABELS[ap.animal_species]})</small></td>
            <td>${escapeHtml(ap.note || '')}</td>
            <td>
                <select class="status_select" data-id="${ap.id}">
                    ${optionsHtml(APPOINTMENT_STATUS_LABELS, ap.status)}
                </select>
            </td>
            <td class="actions">
                <button class="button button_small button_secondary" data-action="edit-appointment" data-id="${ap.id}" type="button">Szerkesztés</button>
                <button class="button button_small button_danger" data-action="delete-appointment" data-id="${ap.id}" type="button">Törlés</button>
            </td>
        </tr>
    `).join('');
}

appointmentPeriod.addEventListener('change', loadAppointments);
appointmentStatus.addEventListener('change', loadAppointments);

// Changing the status dropdown saves immediately
appointmentsTable.addEventListener('change', async event => {
    if (!event.target.classList.contains('status_select')) return;
    const id = event.target.dataset.id;
    try {
        const result = await apiRequest('/appointments/' + id, { method: 'PUT', body: { status: event.target.value } });
        showMessage(appointmentsMessage, result.message, 'success');
    } catch (error) {
        handleError(error, appointmentsMessage);
    }
    loadAppointments();
});

appointmentsTable.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const appointment = allAppointments.find(ap => String(ap.id) === button.dataset.id);

    if (button.dataset.action === 'edit-appointment') {
        openAppointmentForm(appointment);
    }
    if (button.dataset.action === 'delete-appointment') {
        if (!confirm(`Biztosan törli ${appointment.visitor_name} időpontját (${formatDate(appointment.appointment_date)} ${appointment.appointment_time})?`)) return;
        try {
            const result = await apiRequest('/appointments/' + appointment.id, { method: 'DELETE' });
            showMessage(appointmentsMessage, result.message, 'success');
            loadAppointments();
        } catch (error) {
            handleError(error, appointmentsMessage);
        }
    }
});

function openAppointmentForm(ap) {
    const animalOptions = allAnimals.map(animal =>
        `<option value="${animal.id}" ${animal.id === ap.animal_id ? 'selected' : ''}>${escapeHtml(animal.name)} (${SPECIES_LABELS[animal.species]})</option>`
    ).join('');

    const modal = openModal(`
        <h2>Időpont szerkesztése</h2>
        <form id="appointment_form" class="booking_form">
            <label>Látogató neve <input name="visitor_name" type="text" maxlength="100" required value="${escapeHtml(ap.visitor_name)}"></label>
            <label>E-mail <input name="visitor_email" type="email" required value="${escapeHtml(ap.visitor_email)}"></label>
            <label>Telefonszám <input name="visitor_phone" type="tel" value="${escapeHtml(ap.visitor_phone || '')}"></label>
            <label>Állat <select name="animal_id" required>${animalOptions}</select></label>
            <div class="column_two">
                <label>Dátum <input name="appointment_date" type="date" required value="${escapeHtml(ap.appointment_date)}"></label>
                <label>Időpont <input name="appointment_time" type="time" required value="${escapeHtml(ap.appointment_time)}"></label>
            </div>
            <label>Megjegyzés <textarea name="note" rows="3" maxlength="1000">${escapeHtml(ap.note || '')}</textarea></label>
            <label>Állapot <select name="status">${optionsHtml(APPOINTMENT_STATUS_LABELS, ap.status)}</select></label>
            <button class="button" type="submit">Mentés</button>
            <p class="message form_message"></p>
        </form>
    `);

    const form = modal.querySelector('form');
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const body = formValues(form);
        body.animal_id = Number(body.animal_id);
        try {
            const result = await apiRequest('/appointments/' + ap.id, { method: 'PUT', body });
            closeModal();
            showMessage(appointmentsMessage, result.message, 'success');
            loadAppointments();
        } catch (error) {
            handleError(error, form.querySelector('.form_message'));
        }
    });
}

// ===================== ANIMALS =====================

async function loadAnimals() {
    try {
        const result = await apiRequest('/animals'); // logged in -> every animal, also adopted ones
        allAnimals = result.data;
        renderAnimals();
    } catch (error) {
        handleError(error, animalsMessage);
    }
}

function renderAnimals() {
    if (allAnimals.length === 0) {
        animalsTable.innerHTML = '<tr><td colspan="7">Még nincs állat a rendszerben.</td></tr>';
        return;
    }

    animalsTable.innerHTML = allAnimals.map(animal => `
        <tr>
            <td><strong>${escapeHtml(animal.name)}</strong></td>
            <td>${SPECIES_LABELS[animal.species]}</td>
            <td>${escapeHtml(animal.breed || '-')}</td>
            <td>${ageText(animal.age)}</td>
            <td>${SEX_LABELS[animal.sex]}</td>
            <td><span class="badge status_${escapeHtml(animal.status)}">${ANIMAL_STATUS_LABELS[animal.status]}</span></td>
            <td class="actions">
                <button class="button button_small button_secondary" data-action="edit-animal" data-id="${animal.id}" type="button">Szerkesztés</button>
                <button class="button button_small button_danger" data-action="delete-animal" data-id="${animal.id}" type="button">Törlés</button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('new_animal_button').addEventListener('click', () => openAnimalForm(null));

animalsTable.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const animal = allAnimals.find(a => String(a.id) === button.dataset.id);

    if (button.dataset.action === 'edit-animal') {
        openAnimalForm(animal);
    }
    if (button.dataset.action === 'delete-animal') {
        if (!confirm(`Biztosan törli ${animal.name} adatait?`)) return;
        try {
            const result = await apiRequest('/animals/' + animal.id, { method: 'DELETE' });
            showMessage(animalsMessage, result.message, 'success');
            loadAnimals();
        } catch (error) {
            handleError(error, animalsMessage); // e.g. 409: the animal still has appointments
        }
    }
});

/** animal = null -> new animal, otherwise edit */
function openAnimalForm(animal) {
    const isNew = animal === null;
    const a = animal || { name: '', species: 'dog', breed: '', age: '', sex: 'unknown', description: '', image_url: '', status: 'available' };

    const modal = openModal(`
        <h2>${isNew ? 'Új állat felvétele' : 'Állat szerkesztése'}</h2>
        <form id="animal_form" class="booking_form">
            <label>Név <input name="name" type="text" maxlength="100" required value="${escapeHtml(a.name)}"></label>
            <div class="column_two">
                <label>Faj <select name="species" required>${optionsHtml(SPECIES_LABELS, a.species)}</select></label>
                <label>Fajta <input name="breed" type="text" maxlength="100" value="${escapeHtml(a.breed || '')}"></label>
            </div>
            <div class="column_two">
                <label>Kor (év, 0 = 1 évnél fiatalabb) <input name="age" type="number" min="0" max="40" value="${escapeHtml(a.age ?? '')}"></label>
                <label>Nem <select name="sex" required>${optionsHtml(SEX_LABELS, a.sex)}</select></label>
            </div>
            <label>Kép útvonala vagy linkje <input name="image_url" type="text" maxlength="255" placeholder="pictures/bodri.jpg" value="${escapeHtml(a.image_url || '')}"></label>
            <label>Leírás <textarea name="description" rows="4" maxlength="5000">${escapeHtml(a.description || '')}</textarea></label>
            <label>Állapot <select name="status" required>${optionsHtml(ANIMAL_STATUS_LABELS, a.status)}</select></label>
            <button class="button" type="submit">${isNew ? 'Létrehozás' : 'Mentés'}</button>
            <p class="message form_message"></p>
        </form>
    `);

    const form = modal.querySelector('form');
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const body = formValues(form);
        body.age = body.age === '' ? null : Number(body.age);
        try {
            const result = isNew
                ? await apiRequest('/animals', { method: 'POST', body })
                : await apiRequest('/animals/' + animal.id, { method: 'PUT', body });
            closeModal();
            showMessage(animalsMessage, result.message, 'success');
            await loadAnimals();
            loadAppointments(); // animal names may have changed
        } catch (error) {
            handleError(error, form.querySelector('.form_message'));
        }
    });
}

// ===================== HELPERS =====================

/** <option> list from a label object, with the current value selected */
function optionsHtml(labels, selectedValue) {
    return Object.entries(labels).map(([value, label]) =>
        `<option value="${value}" ${value === selectedValue ? 'selected' : ''}>${label}</option>`
    ).join('');
}

/** Reads every named field of a form into an object: { name: '...', species: '...' } */
function formValues(form) {
    return Object.fromEntries(new FormData(form).entries());
}

function handleError(error, messageElement) {
    if (error.status === 401) {
        location.href = 'login.html'; // session expired
        return;
    }
    showMessage(messageElement, errorText(error), 'error');
}

init();
