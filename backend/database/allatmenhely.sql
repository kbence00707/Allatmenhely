-- =====================================================================
--  Állatmenhely (Animal Shelter) - complete MySQL database
--
--  This file creates the whole database FROM SCRATCH:
--    1. the database itself
--    2. the tables (users, animals, appointments)
--    3. realistic sample data for demonstration
--
--  WARNING: running this file deletes the existing tables and their data!
--
--  How to use: import it in phpMyAdmin (Import tab) or run
--    E:\xampp\mysql\bin\mysql.exe -u root < backend\database\allatmenhely.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS allatmenhely
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE allatmenhely;

-- Drop in reverse dependency order (appointments references animals).
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS animals;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------
--  users: shelter employees who can log in to the staff area
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(255)  NOT NULL,
    -- Never the real password: only the password_hash() result is stored.
    password_hash VARCHAR(255)  NOT NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  animals: every animal the shelter cares for
-- ---------------------------------------------------------------------
CREATE TABLE animals (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)     NOT NULL,
    species     ENUM('dog', 'cat', 'other')              NOT NULL,
    breed       VARCHAR(100)     NULL,
    age         TINYINT UNSIGNED NULL COMMENT 'Age in years (0 = younger than one year)',
    sex         ENUM('male', 'female', 'unknown')        NOT NULL DEFAULT 'unknown',
    description TEXT             NULL,
    image_url   VARCHAR(255)     NULL,
    status      ENUM('available', 'reserved', 'adopted') NOT NULL DEFAULT 'available',
    created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_animals_species (species),
    KEY idx_animals_status (status),
    CONSTRAINT chk_animals_age CHECK (age IS NULL OR age <= 40)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  appointments: a visitor wants to meet ONE animal at a date and time
--  (one animal can have many appointments -> one-to-many relationship)
-- ---------------------------------------------------------------------
CREATE TABLE appointments (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id        INT UNSIGNED NOT NULL,
    visitor_name     VARCHAR(100) NOT NULL,
    visitor_email    VARCHAR(255) NOT NULL,
    visitor_phone    VARCHAR(30)  NULL,
    appointment_date DATE         NOT NULL,
    appointment_time TIME         NOT NULL,
    note             TEXT         NULL,
    status           ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Chronological listing uses this index.
    KEY idx_appointments_datetime (appointment_date, appointment_time),
    KEY idx_appointments_status (status),
    KEY idx_appointments_animal (animal_id),
    -- RESTRICT: an animal that still has appointments cannot be deleted
    -- (staff should mark it as "adopted" instead, keeping the history).
    CONSTRAINT fk_appointments_animal
        FOREIGN KEY (animal_id) REFERENCES animals (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  SAMPLE DATA
-- =====================================================================

-- Demo staff login:  admin@allatmenhely.hu  /  Admin123!
-- (change it before using the site for real - see README)
INSERT INTO users (name, email, password_hash) VALUES
('Menhely Admin', 'admin@allatmenhely.hu', '$2y$10$vvVhF.UxwoZcbFhvGE6OZuyou472SvydW1Z2JcTVOKTigqlAZen/a');

INSERT INTO animals (id, name, species, breed, age, sex, description, image_url, status) VALUES
(1, 'Bodri',    'dog',   'Keverék',                  3,  'male',
    'Barátságos, energikus kutya, aki imád labdázni és hosszú sétákat tenni. Gyerekekkel és más kutyákkal is jól kijön.',
    'pictures/kutya.jpg', 'available'),
(2, 'Morzsa',   'dog',   'Beagle keverék',           1,  'female',
    'Kíváncsi és játékos fiatal kutya. Még tanulja az alapokat, ezért türelmes gazdit keres.',
    NULL, 'available'),
(3, 'Rex',      'dog',   'Németjuhász',              6,  'male',
    'Okos, jól tanítható, hűséges társ. Kertes házba ajánljuk, tapasztalt gazdihoz.',
    NULL, 'reserved'),
(4, 'Tappancs', 'dog',   'Tacskó keverék',           11, 'female',
    'Nyugodt, szeretetteljes idős hölgy. Egy csendes otthonra és egy puha kanapéra vágyik.',
    NULL, 'available'),
(5, 'Cirmi',    'cat',   'Európai rövidszőrű',       2,  'female',
    'Bújós, dorombolós cica. Lakásban tartásra is alkalmas, más macskákkal jól kijön.',
    NULL, 'available'),
(6, 'Mirci',    'cat',   'Perzsa keverék',           8,  'male',
    'Méltóságteljes, kicsit visszahúzódó kandúr. Idővel nagyon ragaszkodóvá válik.',
    NULL, 'available'),
(7, 'Pamacs',   'cat',   'Európai rövidszőrű',       0,  'male',
    'Néhány hónapos, csupa energia kiscica.',
    NULL, 'adopted'),
(8, 'Füles',    'other', 'Törpenyúl',                1,  'unknown',
    'Szelíd törpenyúl, szereti a friss zöldséget és ha simogatják.',
    NULL, 'available');

-- Dates are relative to "today", so the demo always has upcoming appointments.
INSERT INTO appointments (animal_id, visitor_name, visitor_email, visitor_phone, appointment_date, appointment_time, note, status) VALUES
(1, 'Kovács Anna',    'kovacs.anna@example.com',   '+36 30 123 4567', CURDATE() + INTERVAL 1 DAY, '10:00:00', 'Két gyerekkel érkezünk.',            'confirmed'),
(5, 'Nagy Péter',     'nagy.peter@example.com',    NULL,              CURDATE() + INTERVAL 1 DAY, '14:30:00', NULL,                                 'pending'),
(2, 'Szabó Eszter',   'szabo.eszter@example.com',  '+36 20 555 1234', CURDATE() + INTERVAL 3 DAY, '11:00:00', 'Van már egy kutyám, őt is hozhatom?', 'pending'),
(8, 'Tóth Gergely',   'toth.gergely@example.com',  NULL,              CURDATE() + INTERVAL 5 DAY, '09:30:00', NULL,                                 'pending'),
(4, 'Horváth Judit',  'horvath.judit@example.com', '+36 70 987 6543', CURDATE() - INTERVAL 2 DAY, '15:00:00', NULL,                                 'completed'),
(6, 'Varga Balázs',   'varga.balazs@example.com',  NULL,              CURDATE() - INTERVAL 1 DAY, '13:00:00', NULL,                                 'cancelled');
