CREATE DATABASE IF NOT EXISTS gestion_absences CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE gestion_absences;

CREATE TABLE admins(
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE filieres(
    id_filiere INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE responsables(
    id_responsable INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE modules(
    id_module INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    semestre INT NOT NULL,
    id_filiere INT,
    FOREIGN KEY(id_filiere) REFERENCES filieres(id_filiere),
    id_responsable int,
    FOREIGN KEY(id_responsable) REFERENCES responsables(id_responsable)
);

CREATE TABLE etudiants(
    id_etudiant INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    numero_apogee INT UNIQUE,
    password VARCHAR(255) NOT NULL,
    id_filiere int,
    FOREIGN KEY(id_filiere) REFERENCES filieres(id_filiere)
);

CREATE TABLE absences(
    id_absence INT AUTO_INCREMENT PRIMARY KEY,
    id_etudiant int,
    FOREIGN KEY(id_etudiant) REFERENCES etudiants(id_etudiant),
    id_module int,
    FOREIGN KEY(id_module) REFERENCES modules(id_module),
    date DATE NOT NULL,
    justifiee BOOLEAN DEFAULT FALSE,
    commentaire TEXT
);

CREATE TABLE justificatifs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    module_id INT NOT NULL,
    date_absence DATE NOT NULL,
    fichier_path VARCHAR(255) NOT NULL,
    statut ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id_etudiant),
    FOREIGN KEY (module_id) REFERENCES modules(id_module)
);

ALTER TABLE absences ADD COLUMN justificatif VARCHAR(255) DEFAULT NULL;

ALTER TABLE etudiants ADD COLUMN photo_profil VARCHAR(255) DEFAULT NULL;