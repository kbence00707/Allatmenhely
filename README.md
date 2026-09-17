# Állatmenhely – Animal Shelter Website

University project: a website for an animal shelter.

- **Visitors** can browse the animals, open an animal's details, and book an appointment to meet it.
- **Shelter employees** log in to a staff page where they manage animals and see and manage every appointment in chronological order.

---

## Contents

1. [What the project does](#1-what-the-project-does)
2. [Technologies](#2-technologies)
3. [How the backend works](#3-how-the-backend-works)
4. [How the database works](#4-how-the-database-works)
5. [Installation (step by step)](#5-installation-step-by-step)
6. [Creating / importing the database](#6-creating--importing-the-database)
7. [Configuring the database connection](#7-configuring-the-database-connection)
8. [Starting the backend](#8-starting-the-backend)
9. [Starting the frontend](#9-starting-the-frontend)
10. [Testing that everything works](#10-testing-that-everything-works)
11. [API endpoints](#11-api-endpoints)
12. [Staff accounts](#12-staff-accounts)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. What the project does

| Page | Who uses it | What it does |
|------|-------------|--------------|
| `index.html` | visitors | Lists the animals (search by name, filter by dog/cat/other), shows an animal's details in a popup, and has the booking form |
| `login.html` | staff | Staff login |
| `admin.html` | staff | Appointments table (chronological, filter by status and upcoming/all, change the status, edit, delete) and animal management (add, edit, delete) |

## 2. Technologies

| Part | Technology |
|------|-----------|
| Frontend | HTML, CSS, JavaScript (no frameworks) |
| Backend | PHP 8 (plain PHP, no framework), PDO |
| Database | MySQL (XAMPP ships MariaDB, which is a drop-in MySQL replacement: same SQL, same PHP driver) |
| Local web server | Apache (from XAMPP) |
| Version control | Git + GitHub |
| Scrum board | GitHub Projects |
| Editor | Visual Studio Code |

## 3. How the backend works

The backend is a **REST API**: the JavaScript on the pages sends HTTP requests (for example `GET /api/animals`), and the PHP backend answers with **JSON** data.

```
Browser (index.html + script.js)
        │   fetch("backend/api/animals")
        ▼
Apache  ── backend/.htaccess sends every /backend/api/... request to ──►  backend/public/index.php
                                                                              │
                                                  routes/api.php: which URL → which controller function
                                                                              │
                                     controllers/  (checks input, decides what happens, sends the JSON answer)
                                                                              │
                                     models/       (the only place with SQL, always prepared statements)
                                                                              │
                                                                         MySQL database
```

### Folder structure

```
Allatmenhely/
├── index.html, style.css, script.js     public page (existing files, script.js was empty before)
├── common.js                            shared JS: API calls, Hungarian labels, popup window
├── login.html, login.js                 staff login page
├── admin.html, admin.js                 staff page
├── pictures/                            images (kutya.jpg, placeholder.svg)
└── backend/
    ├── .htaccess          Apache rules: routes /api/... to index.php, blocks the internal folders
    ├── .env.example       configuration template (copy to .env)
    ├── public/index.php   FRONT CONTROLLER – every API request starts here
    ├── routes/api.php     list of all endpoints
    ├── controllers/       AnimalController, AppointmentController, AuthController
    ├── models/            Animal, Appointment, User (SQL queries)
    ├── middleware/        Auth (login check with PHP sessions), Cors
    ├── helpers/           Router, Request, Response, Validator, HttpException
    ├── config/            config.php (reads .env), Database.php (PDO connection)
    ├── database/          allatmenhely.sql (tables + sample data), create_user.php
    └── tests/api_test.php automatic test of every endpoint
```

### Security measures (short explanation for the presentation)

| Threat | Protection |
|--------|-----------|
| SQL injection | Every query uses **PDO prepared statements**, so user input never becomes part of the SQL command |
| Invalid data | **Server-side validation** (`helpers/Validator.php`): required fields, lengths, e-mail, date/time format, allowed values, future dates, opening hours |
| Stolen passwords | Passwords are stored only as `password_hash()` hashes and checked with `password_verify()` |
| Unauthorized changes | Staff endpoints need a logged-in **PHP session** (`middleware/Auth.php`), otherwise **401** |
| XSS (injected `<script>`) | The frontend escapes every value before inserting it into HTML (`escapeHtml` in `common.js`); the session cookie is `HttpOnly` |
| CSRF | Session cookie is `SameSite=Lax`, and write requests must be `Content-Type: application/json` (a plain HTML form cannot send that) |
| Session fixation | `session_regenerate_id()` after login |
| Leaking secrets | Database password lives in `backend/.env`, which is in `.gitignore`; `.htaccess` blocks `.env` and internal folders; error messages never show internal details |
| Other websites calling the API | CORS only allows the origins listed in `CORS_ORIGINS` |

## 4. How the database works

Database name: **`allatmenhely`**. Three tables:

```
users                     animals                        appointments
─────                     ───────                        ────────────
id (PK)                   id (PK)          ◄───────┐     id (PK)
name                      name                     └──── animal_id (FK → animals.id)
email (UNIQUE)            species (dog/cat/other)        visitor_name
password_hash             breed                          visitor_email
created_at                age (years, 0 = under 1)       visitor_phone
                          sex (male/female/unknown)      appointment_date
                          description                    appointment_time
                          image_url                      note
                          status (available/             status (pending/confirmed/
                                  reserved/adopted)              completed/cancelled)
                          created_at, updated_at         created_at, updated_at
```

- **One-to-many relationship:** one animal can have many appointments; each appointment belongs to exactly one animal (`appointments.animal_id` is a **foreign key**).
- The foreign key uses `ON DELETE RESTRICT`: an animal that still has appointments **cannot be deleted** (the API answers 409). Staff should set it to "adopted" instead, which keeps the history.
- **Indexes:** `(appointment_date, appointment_time)` for the chronological list, plus `status`, `species` and `animal_id` for filtering.
- **Constraints:** `ENUM` columns only accept the listed values, `email` is `UNIQUE`, and a `CHECK` keeps `age` between 0 and 40.
- Visitors see animals with status `available` or `reserved`; only `available` animals can be booked. The same animal cannot be booked twice for the same date and time (409).

The full SQL is in [`backend/database/allatmenhely.sql`](backend/database/allatmenhely.sql). It creates everything from scratch and inserts sample data: 1 staff user, 8 animals and 6 appointments. The appointment dates are relative to today, so there are always upcoming ones.

---

## 5. Installation (step by step)

You need two programs: **XAMPP** (Apache web server + PHP + MySQL) and **Git**.

### Step 1 – Install XAMPP

1. Open https://www.apachefriends.org/download.html in your browser.
2. Download **XAMPP for Windows** with **PHP 8.2** (or newer).
3. Run the downloaded installer. Click **Next** on every page and keep the default folder **`C:\xampp`**.
   - If Windows asks "Do you want to allow this app to make changes?", click **Yes**.
   - If a Windows Firewall window appears for Apache or MySQL, click **Allow access**.
4. At the end, leave "Start the Control Panel" ticked and click **Finish**.

> If XAMPP is installed somewhere else (e.g. `D:\xampp`), use that folder everywhere this README says `C:\xampp`.

### Step 2 – Install Git

1. Open https://git-scm.com/download/win and download **64-bit Git for Windows Setup**.
2. Run it and click **Next** on every page (the defaults are fine), then **Install** and **Finish**.
3. **Close and reopen Visual Studio Code**, so that it notices Git.

### Step 3 – Download the project into the XAMPP web folder

Apache only serves files from the `htdocs` folder, so the project must be there.

1. Open **Visual Studio Code**.
2. Open the terminal: menu **Terminal → New Terminal**.
3. Type this command and press **Enter**:
   ```bash
   cd C:\xampp\htdocs
   ```
4. Then run:
   ```bash
   git clone https://github.com/kbence00707/Allatmenhely.git
   ```
5. In VS Code choose **File → Open Folder...**, select **`C:\xampp\htdocs\Allatmenhely`**, and click **Select Folder**.

## 6. Creating / importing the database

### Step 1 – Start MySQL

1. Open the **XAMPP Control Panel** (Start menu → type `XAMPP`, or run `C:\xampp\xampp-control.exe`).
2. Click **Start** next to **Apache** and next to **MySQL**. Both names should turn **green**.

### Step 2 – Import the SQL file (option A: phpMyAdmin, with the mouse)

1. In your browser open **http://localhost/phpmyadmin**.
2. Click the **Import** tab at the top.
3. Under "File to import" click **Choose File** (Browse...) and select
   `C:\xampp\htdocs\Allatmenhely\backend\database\allatmenhely.sql`.
4. Scroll down and click **Import** (or **Go**).
5. You should see a green message saying the import finished successfully, and a database called **`allatmenhely`** appears in the left sidebar with the tables `animals`, `appointments` and `users`.

### Step 2 – (option B: terminal)

In the VS Code terminal (inside the `Allatmenhely` folder) run:

```bash
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < backend\database\allatmenhely.sql"
```

If nothing is printed, it worked.

> ⚠️ Importing the file again **deletes all data** and restores the sample data. That is useful before a demo.

## 7. Configuring the database connection

**With a default XAMPP installation you don't need to do anything.** The backend automatically uses user `root` with an empty password, which is the XAMPP default.

Only if your MySQL has a password, or you want to change a setting:

1. In VS Code, find the file **`backend/.env.example`** in the left file list.
2. Right-click it → **Copy**, then right-click the `backend` folder → **Paste**.
3. Rename the copy (right-click → **Rename**) to exactly **`.env`**.
4. Open `backend/.env` and change the values, for example:
   ```
   DB_PASS=my_mysql_password
   ```
5. Save with **Ctrl+S**.

`backend/.env` is listed in `.gitignore`, so it is **never uploaded to GitHub**, and your password stays on your computer. Each team member creates their own.

## 8. Starting the backend

There is no separate "backend server" to start: **Apache runs the PHP files.**

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL** (both turn green).
3. Open **http://localhost/Allatmenhely/backend/api** in your browser.
4. You should see:
   ```json
   {"name":"Állatmenhely API","status":"ok","version":"1.0"}
   ```

## 9. Starting the frontend

1. Make sure Apache and MySQL are running (see step 8).
2. Open **http://localhost/Allatmenhely/** in your browser.

> ❗ Always open the site through **http://localhost/...**. Do **not** double-click `index.html`: a page opened as `file:///...` cannot talk to the API.
>
> The VS Code *Live Server* extension also works for the public page (CORS is configured for port 5500). For the staff login, use `http://localhost:5500` rather than `http://127.0.0.1:5500`.

## 10. Testing that everything works

### A) Automatic API test (58 checks)

1. Apache and MySQL must be running, and the database must be imported.
2. In the VS Code terminal (in the `Allatmenhely` folder) run:
   ```bash
   C:\xampp\php\php.exe backend\tests\api_test.php
   ```
3. You should see a list of `[OK]` lines, ending with:
   ```
   Result: 58 passed, 0 failed
   ```
   The test creates its own test animal and appointment and deletes them at the end, so your data is not changed.

It checks: listing/viewing/creating/editing/deleting animals, booking, double booking, past dates, opening hours, invalid input, nonexistent resources (404), unauthorized requests (401), login/logout, status changes, chronological order, and deleting protected animals (409).

### B) Manual test in the browser

1. Open **http://localhost/Allatmenhely/**. The animal cards appear.
2. Type `bod` in the search box → only **Bodri** remains. Choose **Macskák** in the dropdown → only cats.
3. Click a card → a popup shows the details. Click **Időpontot foglalok hozzá** → the animal is selected in the booking form.
4. Fill in the form (a date in the future, time between 09:00 and 17:00) and click **Időpont lefoglalása** → a green success message appears.
5. Click **Dolgozói felület** at the top and log in with:
   - E-mail: `admin@allatmenhely.hu`
   - Password: `Admin123!`
6. Your new booking is in the **Időpontfoglalások** table. Change its status in the dropdown, try **Szerkesztés** and **Törlés**.
7. Under **Állatok kezelése**, click **+ Új állat**, create an animal, then edit and delete it.
8. Click **Kijelentkezés**. Opening `http://localhost/Allatmenhely/admin.html` now sends you back to the login page.

### C) Trying single endpoints

Public GET endpoints can simply be opened in the browser, e.g. http://localhost/Allatmenhely/backend/api/animals/1

## 11. API endpoints

Base URL: **`http://localhost/Allatmenhely/backend/api`**

- Request bodies are JSON and need the header `Content-Type: application/json`.
- Successful answers: `{"data": ...}` (and `"message"` after changes).
- Error answers: `{"error": "message", "details": {"field": "problem"}}`.
- 🔒 = staff only (login needed, otherwise **401**).

### Status codes used

| Code | Meaning |
|------|---------|
| 200 OK | Success |
| 201 Created | Something new was created |
| 400 Bad Request | The body is not valid JSON |
| 401 Unauthorized | Not logged in / wrong password |
| 404 Not Found | No such animal / appointment / endpoint |
| 405 Method Not Allowed | Wrong HTTP method for this URL |
| 409 Conflict | Time slot already booked, or animal still has appointments |
| 415 Unsupported Media Type | Body is not `application/json` |
| 422 Unprocessable Entity | Validation error (see `details`) |
| 500 Internal Server Error | Server/database problem (safe message only) |

### Summary

| Method | URL | Who | Description |
|--------|-----|-----|-------------|
| GET | `/` | everyone | API status check |
| GET | `/animals` | everyone | List animals |
| GET | `/animals/{id}` | everyone | One animal |
| POST | `/animals` | 🔒 | Create animal |
| PUT / PATCH | `/animals/{id}` | 🔒 | Edit animal |
| DELETE | `/animals/{id}` | 🔒 | Delete animal |
| GET | `/appointments` | 🔒 | List appointments (chronological) |
| GET | `/appointments/{id}` | 🔒 | One appointment |
| POST | `/appointments` | everyone | Book an appointment |
| PUT / PATCH | `/appointments/{id}` | 🔒 | Edit appointment / change status |
| DELETE | `/appointments/{id}` | 🔒 | Delete appointment |
| POST | `/auth/login` | everyone | Staff login |
| POST | `/auth/logout` | everyone | Logout |
| GET | `/auth/me` | 🔒 | Who is logged in |

---

### GET `/animals`

Lists animals, ordered by name.

**Query parameters (all optional):**

| Name | Values | Meaning |
|------|--------|---------|
| `species` | `dog`, `cat`, `other` | filter by species |
| `search` | text | part of the name |
| `status` | `available`, `reserved` (staff also: `adopted`) | filter by status |

Visitors only get `available` and `reserved` animals; logged-in staff get all of them.

**Example:** `GET /animals?species=dog&search=bod`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Bodri",
      "species": "dog",
      "breed": "Keverék",
      "age": 3,
      "sex": "male",
      "description": "Barátságos, energikus kutya...",
      "image_url": "pictures/kutya.jpg",
      "status": "available",
      "created_at": "2026-09-17 14:44:06",
      "updated_at": "2026-09-17 14:44:06"
    }
  ]
}
```

**Errors:** `422` invalid filter value, e.g. `{"error":"Hibás vagy hiányzó adatok.","details":{"species":"Érvénytelen érték. Lehetséges értékek: dog, cat, other."}}`

### GET `/animals/{id}`

**Example:** `GET /animals/1` → **200** `{"data": { ...same fields as above... }}`

**Errors:** `404` `{"error":"Az állat nem található."}` (also for adopted animals when not logged in)

### POST `/animals` 🔒

| Field | Required | Rules |
|-------|----------|-------|
| `name` | yes | max. 100 characters |
| `species` | yes | `dog` / `cat` / `other` |
| `breed` | no | max. 100 characters |
| `age` | no | whole number 0–40 (0 = under one year) |
| `sex` | no (default `unknown`) | `male` / `female` / `unknown` |
| `description` | no | max. 5000 characters |
| `image_url` | no | relative path (`pictures/x.jpg`) or `http(s)://` link, max. 255 |
| `status` | no (default `available`) | `available` / `reserved` / `adopted` |

**Request:**
```json
{ "name": "Pötyi", "species": "cat", "breed": "Keverék", "age": 3, "sex": "female",
  "description": "Nyugodt cica.", "image_url": "pictures/potyi.jpg", "status": "available" }
```

**Response 201:**
```json
{ "message": "Az állat sikeresen létrehozva.", "data": { "id": 9, "name": "Pötyi", "...": "..." } }
```

**Errors:** `401` not logged in · `415` not JSON · `400` broken JSON · `422` validation, e.g.
```json
{ "error": "Hibás vagy hiányzó adatok.",
  "details": { "name": "Ez a mező kötelező.", "age": "Az értéknek 0 és 40 között kell lennie." } }
```

### PUT `/animals/{id}` 🔒

Send **only the fields you want to change** (same rules as POST).

**Request:** `PUT /animals/9` `{"status": "adopted"}`

**Response 200:** `{"message": "Az állat adatai sikeresen módosítva.", "data": { ...updated animal... }}`

**Errors:** `401` · `404` no such animal · `422` validation or empty body (`"Nincs módosítandó adat."`)

### DELETE `/animals/{id}` 🔒

**Response 200:** `{"message": "Az állat sikeresen törölve."}`

**Errors:** `401` · `404` · `409` the animal still has appointments:
```json
{ "error": "Az állathoz 2 időpontfoglalás tartozik, ezért nem törölhető. Állítsa az állapotát „örökbefogadva” értékre, vagy előbb törölje az időpontokat." }
```

---

### GET `/appointments` 🔒

Lists appointments **in chronological order**, with the animal's name and species.

**Query parameters (all optional):**

| Name | Values | Meaning |
|------|--------|---------|
| `upcoming` | `1` | only today and later |
| `status` | `pending` / `confirmed` / `completed` / `cancelled` | filter by status |
| `animal_id` | number | only this animal's appointments |
| `from`, `to` | `YYYY-MM-DD` | date range |
| `sort` | `asc` (default) / `desc` | oldest first / newest first |

**Example:** `GET /appointments?upcoming=1&status=pending`

**Response 200:**
```json
{
  "data": [
    {
      "id": 2,
      "animal_id": 5,
      "visitor_name": "Nagy Péter",
      "visitor_email": "nagy.peter@example.com",
      "visitor_phone": null,
      "appointment_date": "2026-09-18",
      "appointment_time": "14:30",
      "note": null,
      "status": "pending",
      "created_at": "2026-09-17 14:44:06",
      "updated_at": "2026-09-17 14:44:06",
      "animal_name": "Cirmi",
      "animal_species": "cat"
    }
  ]
}
```

**Errors:** `401` · `422` invalid filter

### GET `/appointments/{id}` 🔒

**Response 200:** `{"data": { ...one appointment, same fields as above... }}`

**Errors:** `401` · `404` `{"error":"Az időpont nem található."}`

### POST `/appointments` (public booking)

| Field | Required | Rules |
|-------|----------|-------|
| `animal_id` | yes | an existing animal with status `available` |
| `visitor_name` | yes | max. 100 characters |
| `visitor_email` | yes | valid e-mail |
| `visitor_phone` | no | digits, spaces, `+ ( ) - /`, 6–30 characters |
| `appointment_date` | yes | `YYYY-MM-DD`, in the future, max. 6 months ahead |
| `appointment_time` | yes | `HH:MM`, within opening hours (default 09:00–17:00) |
| `note` | no | max. 1000 characters |

New bookings always get status `pending` (only logged-in staff may set a different status).

**Request:**
```json
{ "animal_id": 1, "visitor_name": "Kiss Márta", "visitor_email": "kiss.marta@example.com",
  "visitor_phone": "+36 30 123 4567", "appointment_date": "2026-09-25",
  "appointment_time": "10:30", "note": "Gyerekkel érkezem." }
```

**Response 201:**
```json
{ "message": "Az időpontfoglalás sikeresen rögzítve. Munkatársaink hamarosan felveszik Önnel a kapcsolatot.",
  "data": { "id": 7, "status": "pending", "animal_name": "Bodri", "...": "..." } }
```

**Errors:**
- `422` validation, e.g. `{"error":"Hibás vagy hiányzó adatok.","details":{"appointment_date":"Csak jövőbeli időpontra lehet foglalni.","animal_id":"Ez az állat jelenleg nem foglalható."}}`
- `409` `{"error":"Erre az időpontra ez az állat már foglalt. Kérjük, válasszon másik időpontot."}`

### PUT `/appointments/{id}` 🔒

Send only the fields you want to change. Allowed: every field from POST plus `status`.

**Request (confirm):** `PUT /appointments/2` `{"status": "confirmed"}`

**Request (reschedule):** `PUT /appointments/2` `{"appointment_date": "2026-09-19", "appointment_time": "11:00"}`

**Response 200:** `{"message": "Az időpont sikeresen módosítva.", "data": { ...updated appointment... }}`

**Errors:** `401` · `404` · `422` validation · `409` the new time slot is already taken

### DELETE `/appointments/{id}` 🔒

To **cancel** but keep the record, use `PUT` with `{"status": "cancelled"}`. `DELETE` removes it permanently.

**Response 200:** `{"message": "Az időpont sikeresen törölve."}`

**Errors:** `401` · `404`

---

### POST `/auth/login`

**Request:** `{"email": "admin@allatmenhely.hu", "password": "Admin123!"}`

**Response 200** (and the browser receives the session cookie):
```json
{ "message": "Sikeres bejelentkezés.",
  "data": { "id": 1, "name": "Menhely Admin", "email": "admin@allatmenhely.hu", "created_at": "..." } }
```

**Errors:** `401` `{"error":"Hibás e-mail cím vagy jelszó."}` · `422` missing e-mail or password

### POST `/auth/logout`

**Response 200:** `{"message": "Sikeres kijelentkezés."}`

### GET `/auth/me` 🔒

**Response 200:** `{"data": {"id": 1, "name": "Menhely Admin", "email": "admin@allatmenhely.hu", "created_at": "..."}}`

**Errors:** `401` not logged in

---

## 12. Staff accounts

The sample data contains one demo account: **`admin@allatmenhely.hu` / `Admin123!`**.
This password is public (it's in this README), so it's only for local demos.

To **create a new staff member** or **change a password**, run this in the VS Code terminal (in the `Allatmenhely` folder):

```bash
C:\xampp\php\php.exe backend\database\create_user.php "Kiss Réka" reka@allatmenhely.hu "UjJelszo2026"
```

- If the e-mail doesn't exist yet → a new account is created.
- If it already exists → its password is changed.
- Passwords must be at least 8 characters long.

## 13. Troubleshooting

| Problem | Solution |
|---------|----------|
| "A szerver nem érhető el..." on the page | Apache is not running → XAMPP Control Panel → **Start** Apache |
| "Nem sikerült csatlakozni az adatbázishoz..." | MySQL is not running, or the database is not imported → start MySQL, then do section 6 |
| Apache won't start (port 80 in use) | Another program (Skype, IIS, another web server) uses port 80. Close it, or in XAMPP click **Config → httpd.conf** for Apache, change `Listen 80` to `Listen 8080`, and use `http://localhost:8080/Allatmenhely/` |
| MySQL won't start (port 3306 in use) | Another MySQL is already running. Stop it (Windows Services), or stop the other XAMPP |
| `http://localhost/Allatmenhely/backend/api` shows 404 | The project is not in `C:\xampp\htdocs\Allatmenhely`, or the folder name is different |
| The animal list is empty | The database was imported without data → import `allatmenhely.sql` again |
| I'm sent back to the login page | Your session expired or you logged out → log in again |
| `git` is not recognized | Git is not installed, or VS Code was not restarted after installing it |
