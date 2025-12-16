-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 16 dec 2025 om 15:27
-- Serverversie: 10.4.32-MariaDB
-- PHP-versie: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `tntrandomizer`
--
CREATE DATABASE IF NOT EXISTS `tntrandomizer` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tntrandomizer`;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `naam` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `admin`
--

INSERT INTO `admin` (`id`, `email`, `password`, `naam`) VALUES
(1, 'amr@technolableiden.nl', '1234', 'Amr'),
(2, 'admin@gmail.com', '123', 'Admin');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `instellingen`
--

CREATE TABLE `instellingen` (
  `aantal_voorkeuren` int(11) NOT NULL,
  `hoofdwachtwoord` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `klas`
--

CREATE TABLE `klas` (
  `klas_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `klasaanduiding` varchar(100) NOT NULL,
  `leerjaar` varchar(100) DEFAULT NULL,
  `schooljaar` varchar(100) NOT NULL,
  `pincode` varchar(50) NOT NULL,
  `max_keuzes` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `klas_voorkeur`
--

CREATE TABLE `klas_voorkeur` (
  `id` int(11) NOT NULL,
  `klas_id` int(11) NOT NULL,
  `volgorde` int(11) NOT NULL,
  `naam` varchar(100) NOT NULL,
  `actief` tinyint(1) DEFAULT 1,
  `max_leerlingen` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `leerling`
--

CREATE TABLE `leerling` (
  `leerling_id` int(11) NOT NULL,
  `klas_id` int(11) NOT NULL,
  `voornaam` varchar(100) NOT NULL,
  `tussenvoegsel` varchar(100) DEFAULT NULL,
  `achternaam` varchar(100) NOT NULL,
  `voorkeur1` varchar(100) DEFAULT NULL,
  `voorkeur2` varchar(100) DEFAULT NULL,
  `voorkeur3` varchar(100) DEFAULT NULL,
  `voorkeur4` varchar(100) DEFAULT NULL,
  `voorkeur5` varchar(100) DEFAULT NULL,
  `toegewezen_voorkeur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `school`
--

CREATE TABLE `school` (
  `school_id` smallint(6) NOT NULL,
  `schoolnaam` varchar(100) NOT NULL,
  `plaats` varchar(100) NOT NULL,
  `type_onderwijs` set('Primair Onderwijs','Voortgezet Onderwijs','MBO') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `voorkeur_opties`
--

CREATE TABLE `voorkeur_opties` (
  `id` int(11) NOT NULL,
  `klas_voorkeur_id` int(11) NOT NULL,
  `naam` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexen voor tabel `klas`
--
ALTER TABLE `klas`
  ADD PRIMARY KEY (`klas_id`),
  ADD UNIQUE KEY `pincode` (`pincode`);

--
-- Indexen voor tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `klas_id` (`klas_id`);

--
-- Indexen voor tabel `leerling`
--
ALTER TABLE `leerling`
  ADD PRIMARY KEY (`leerling_id`),
  ADD KEY `klas_id` (`klas_id`);

--
-- Indexen voor tabel `school`
--
ALTER TABLE `school`
  ADD PRIMARY KEY (`school_id`);

--
-- Indexen voor tabel `voorkeur_opties`
--
ALTER TABLE `voorkeur_opties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `klas_voorkeur_id` (`klas_voorkeur_id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT voor een tabel `klas`
--
ALTER TABLE `klas`
  MODIFY `klas_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `leerling`
--
ALTER TABLE `leerling`
  MODIFY `leerling_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `school`
--
ALTER TABLE `school`
  MODIFY `school_id` smallint(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `voorkeur_opties`
--
ALTER TABLE `voorkeur_opties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  ADD CONSTRAINT `klas_voorkeur_ibfk_1` FOREIGN KEY (`klas_id`) REFERENCES `klas` (`klas_id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `leerling`
--
ALTER TABLE `leerling`
  ADD CONSTRAINT `leerling_ibfk_1` FOREIGN KEY (`klas_id`) REFERENCES `klas` (`klas_id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `voorkeur_opties`
--
ALTER TABLE `voorkeur_opties`
  ADD CONSTRAINT `voorkeur_opties_ibfk_1` FOREIGN KEY (`klas_voorkeur_id`) REFERENCES `klas_voorkeur` (`id`) ON DELETE CASCADE;
COMMIT;
