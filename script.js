// =====================================================================
//  Public page (index.html): animal list, details popup, booking form.
//  Needs common.js to be loaded first.
// =====================================================================

const animalsGrid = document.getElementById('animals_grid');
const searchInput = document.querySelector('.search');
const typeFilter = document.getElementById('typefilter');
const animalSelect = document.getElementById('animalselect');
const bookingForm = document.getElementById('booking');
const bookingMessage = document.getElementById('booking_message');
const bookingDate = document.getElementById('booking_date');

// The values of the <select id="typefilter"> options -> species names in the API
const TYPE_FILTER_TO_SPECIES = { dogs: 'dog', cats: 'cat', other: 'other' };

// ----- Animal list -----

async function loadAnimals() {
    const params = new URLSearchParams();
    const species = TYPE_FILTER_TO_SPECIES[typeFilter.value];
    if (species) params.set('species', species);
    if (searchInput.value.trim() !== '') params.set('search', searchInput.value.trim());

    try {
        const result = await apiRequest('/animals?' + params.toString());
        renderAnimals(result.data);
    } catch (error) {
        animalsGrid.innerHTML = `<p class="message error">${escapeHtml(error.message)}</p>`;
    }
}

function renderAnimals(animals) {
    if (animals.length === 0) {
        animalsGrid.innerHTML = '<p>Nincs a keresésnek megfelelő állat.</p>';
        return;
    }

    animalsGrid.innerHTML = animals.map(animal => `
        <article class="animal_card" data-id="${animal.id}">
            <img src="${escapeHtml(animal.image_url || PLACEHOLDER_IMAGE)}" alt="${escapeHtml(animal.name)}">
            <div class="animal_card_text">
                <h3>${escapeHtml(animal.name)}</h3>
                <span class="badge status_${escapeHtml(animal.status)}">${ANIMAL_STATUS_LABELS[animal.status]}</span>
                <p>${SPECIES_LABELS[animal.species]}${animal.breed ? ' · ' + escapeHtml(animal.breed) : ''} · ${ageText(animal.age)} · ${SEX_LABELS[animal.sex]}</p>
                <button class="button button_small" type="button">Részletek</button>
            </div>
        </article>
    `).join('');
    useImageFallback(animalsGrid);
}

// Clicking a card opens the details popup
animalsGrid.addEventListener('click', event => {
    const card = event.target.closest('.animal_card');
    if (card) showAnimalDetails(card.dataset.id);
});

async function showAnimalDetails(id) {
    try {
        const { data: animal } = await apiRequest('/animals/' + id);

        const modal = openModal(`
            <img class="modal_image" src="${escapeHtml(animal.image_url || PLACEHOLDER_IMAGE)}" alt="${escapeHtml(animal.name)}">
            <h2>${escapeHtml(animal.name)}</h2>
            <span class="badge status_${escapeHtml(animal.status)}">${ANIMAL_STATUS_LABELS[animal.status]}</span>
            <dl class="details_list">
                <dt>Faj</dt><dd>${SPECIES_LABELS[animal.species]}</dd>
                <dt>Fajta</dt><dd>${escapeHtml(animal.breed || '-')}</dd>
                <dt>Kor</dt><dd>${ageText(animal.age)}</dd>
                <dt>Nem</dt><dd>${SEX_LABELS[animal.sex]}</dd>
            </dl>
            <p class="modal_description">${escapeHtml(animal.description || 'Nincs leírás.')}</p>
            ${animal.status === 'available'
                ? '<button class="button" type="button" id="book_this_animal">Időpontot foglalok hozzá</button>'
                : '<p>Ez az állat jelenleg nem foglalható.</p>'}
        `);
        useImageFallback(modal);

        const bookButton = modal.querySelector('#book_this_animal');
        if (bookButton) {
            bookButton.addEventListener('click', () => {
                animalSelect.value = String(animal.id);
                closeModal();
                document.getElementById('time').scrollIntoView();
            });
        }
    } catch (error) {
        openModal(`<p class="message error">${escapeHtml(error.message)}</p>`);
    }
}

// Search while typing (waits 300 ms after the last key press)
let searchTimer;
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadAnimals, 300);
});
typeFilter.addEventListener('change', loadAnimals);

// ----- Booking form -----

async function loadBookableAnimals() {
    try {
        const result = await apiRequest('/animals?status=available');
        const options = result.data.map(animal =>
            `<option value="${animal.id}">${escapeHtml(animal.name)} (${SPECIES_LABELS[animal.species]})</option>`
        );
        animalSelect.innerHTML = '<option value="">Válassz állatot</option>' + options.join('');
    } catch (error) {
        showMessage(bookingMessage, error.message, 'error');
    }
}

bookingForm.addEventListener('submit', async event => {
    event.preventDefault(); // do not reload the page

    const submitButton = bookingForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    showMessage(bookingMessage, 'Foglalás küldése...', '');

    try {
        const result = await apiRequest('/appointments', {
            method: 'POST',
            body: {
                animal_id: Number(animalSelect.value),
                visitor_name: document.getElementById('visitor_name').value,
                visitor_email: document.getElementById('visitor_email').value,
                visitor_phone: document.getElementById('visitor_phone').value,
                appointment_date: bookingDate.value,
                appointment_time: document.getElementById('booking_time').value,
                note: document.getElementById('booking_note').value
            }
        });
        showMessage(bookingMessage, result.message, 'success');
        bookingForm.reset();
    } catch (error) {
        showMessage(bookingMessage, errorText(error), 'error');
    } finally {
        submitButton.disabled = false;
    }
});

// ----- Start -----
bookingDate.min = todayString();
loadAnimals();
loadBookableAnimals();
