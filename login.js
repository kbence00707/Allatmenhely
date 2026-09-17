// =====================================================================
//  Staff login page (login.html)
// =====================================================================

const loginForm = document.getElementById('login_form');
const loginMessage = document.getElementById('login_message');

// Already logged in? Go straight to the staff page.
apiRequest('/auth/me')
    .then(() => { location.href = 'admin.html'; })
    .catch(() => { /* not logged in - stay here */ });

loginForm.addEventListener('submit', async event => {
    event.preventDefault();
    showMessage(loginMessage, 'Bejelentkezés...', '');

    try {
        await apiRequest('/auth/login', {
            method: 'POST',
            body: {
                email: document.getElementById('login_email').value,
                password: document.getElementById('login_password').value
            }
        });
        location.href = 'admin.html';
    } catch (error) {
        showMessage(loginMessage, errorText(error), 'error');
    }
});
