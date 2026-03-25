-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 25 mrt 2026 om 13:58
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
-- Tabelstructuur voor tabel `bezoek`
--

CREATE TABLE `bezoek` (
  `bezoek_id` int(11) NOT NULL,
  `naam` varchar(255) NOT NULL,
  `type_onderwijs` enum('PO','VO') NOT NULL,
  `pincode` varchar(50) NOT NULL,
  `max_keuzes` tinyint(3) UNSIGNED NOT NULL,
  `po_dag1` datetime DEFAULT NULL,
  `po_dag2` datetime DEFAULT NULL,
  `vo_week_start` date DEFAULT NULL,
  `vo_week_eind` date DEFAULT NULL,
  `actief` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `bezoek_klas`
--

CREATE TABLE `bezoek_klas` (
  `bezoek_id` int(11) NOT NULL,
  `klas_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `bezoek_optie`
--

CREATE TABLE `bezoek_optie` (
  `optie_id` int(11) NOT NULL,
  `bezoek_id` int(11) NOT NULL,
  `volgorde` int(11) NOT NULL,
  `naam` varchar(100) NOT NULL,
  `max_leerlingen` int(11) DEFAULT NULL,
  `dag_deel` enum('week','dag1','dag2','beide') NOT NULL DEFAULT 'week',
  `actief` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `bezoek_school`
--

CREATE TABLE `bezoek_school` (
  `bezoek_id` int(11) NOT NULL,
  `school_id` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Gegevens worden geëxporteerd voor tabel `klas`
--

INSERT INTO `klas` (`klas_id`, `school_id`, `klasaanduiding`, `leerjaar`, `schooljaar`, `pincode`, `max_keuzes`) VALUES
(1, 1, 'ZWSD23F', '3', '2025-2026', '23F', 2),
(2, 2, 'Groep 8', '8', '25/26', 'Week24', 2),
(3, 3, 'groep 1', '1', '2025-2026', 'r1', 2),
(4, 4, 'groep 8', '8', '25/26', 'ww1', 2),
(5, 4, 'groep 7', '7', '25/26', 'ww2', 2),
(6, 5, 'groep 7', '7', '25/26', 'ww7', 2),
(7, 5, 'groep 8', '8', '25/26', 'ww8', 2),
(8, 6, 'groep 8', '8', '25/26', 'kdjhsafk', 2);

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

--
-- Gegevens worden geëxporteerd voor tabel `klas_voorkeur`
--

INSERT INTO `klas_voorkeur` (`id`, `klas_id`, `volgorde`, `naam`, `actief`, `max_leerlingen`) VALUES
(1, 1, 1, 'ict', 1, 25),
(2, 1, 2, 'zorg', 1, 30),
(3, 1, 3, 'bouw', 1, 25),
(4, 1, 4, 'groen', 1, 30),
(5, 2, 1, 'Technische wereld', 1, 1),
(6, 2, 2, 'Zorgzame wereld', 1, 1),
(7, 2, 3, 'Wetenschappelijke wereld', 1, 1),
(8, 2, 4, 'Goede wereld', 1, 1),
(13, 4, 1, 'wereld1', 1, 1),
(14, 4, 2, 'wereld2', 1, 2),
(15, 4, 3, 'wereld3', 1, 3),
(16, 4, 4, 'wereld4', 1, 4),
(17, 5, 1, 'w1', 1, 1),
(18, 5, 2, 'w2', 1, 2),
(19, 5, 3, 'w3', 1, 3),
(20, 5, 4, 'w4', 1, 4),
(21, 6, 1, 'mbo', 1, 1),
(22, 6, 2, 'ict', 1, 2),
(23, 6, 3, 'wewew', 1, 3),
(24, 7, 1, 'mbo', 1, 2),
(25, 7, 2, 'ict', 1, 3),
(26, 7, 3, 'ict', 1, 1),
(27, 8, 1, 'asdfsa', 1, 1),
(28, 8, 2, 'DASa', 1, 2),
(29, 8, 3, 'dsfsf', 1, 3);

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

--
-- Gegevens worden geëxporteerd voor tabel `leerling`
--

INSERT INTO `leerling` (`leerling_id`, `klas_id`, `voornaam`, `tussenvoegsel`, `achternaam`, `voorkeur1`, `voorkeur2`, `voorkeur3`, `voorkeur4`, `voorkeur5`, `toegewezen_voorkeur`) VALUES
(1, 1, 'Jan', 'van', 'Rijsbergen', '2', '4', NULL, NULL, NULL, '2');

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

--
-- Gegevens worden geëxporteerd voor tabel `school`
--

INSERT INTO `school` (`school_id`, `schoolnaam`, `plaats`, `type_onderwijs`) VALUES
(1, 'Nova College', 'Haarlem', 'MBO'),
(2, 'KJS & ELS', 'Leiden', 'Primair Onderwijs'),
(3, 'De Regenboog', 'Leiden', 'Primair Onderwijs'),
(4, 'De Fransiscusschool', 'Bennebroek', 'Primair Onderwijs'),
(5, 'De Willinkschool', 'Bennebroek', 'Primair Onderwijs'),
(6, 'St. Bernardusschool', 'Haarlem', 'Primair Onderwijs'),
(7, 'Josephschool', 'Leiden', 'Primair Onderwijs');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `_voorkeur_opties`
--

CREATE TABLE `_voorkeur_opties` (
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
-- Indexen voor tabel `bezoek`
--
ALTER TABLE `bezoek`
  ADD PRIMARY KEY (`bezoek_id`),
  ADD UNIQUE KEY `uq_bezoek_pincode` (`pincode`);

--
-- Indexen voor tabel `bezoek_klas`
--
ALTER TABLE `bezoek_klas`
  ADD PRIMARY KEY (`bezoek_id`,`klas_id`),
  ADD KEY `idx_bezoek_klas_klas` (`klas_id`);

--
-- Indexen voor tabel `bezoek_optie`
--
ALTER TABLE `bezoek_optie`
  ADD PRIMARY KEY (`optie_id`),
  ADD KEY `idx_bezoek_optie_bezoek` (`bezoek_id`);

--
-- Indexen voor tabel `bezoek_school`
--
ALTER TABLE `bezoek_school`
  ADD PRIMARY KEY (`bezoek_id`,`school_id`),
  ADD KEY `idx_bezoek_school_school` (`school_id`);

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
-- Indexen voor tabel `_voorkeur_opties`
--
ALTER TABLE `_voorkeur_opties`
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
-- AUTO_INCREMENT voor een tabel `bezoek`
--
ALTER TABLE `bezoek`
  MODIFY `bezoek_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `bezoek_optie`
--
ALTER TABLE `bezoek_optie`
  MODIFY `optie_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `klas`
--
ALTER TABLE `klas`
  MODIFY `klas_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT voor een tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT voor een tabel `leerling`
--
ALTER TABLE `leerling`
  MODIFY `leerling_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `school`
--
ALTER TABLE `school`
  MODIFY `school_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT voor een tabel `_voorkeur_opties`
--
ALTER TABLE `_voorkeur_opties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `bezoek_klas`
--
ALTER TABLE `bezoek_klas`
  ADD CONSTRAINT `fk_bezoek_klas_bezoek` FOREIGN KEY (`bezoek_id`) REFERENCES `bezoek` (`bezoek_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bezoek_klas_klas` FOREIGN KEY (`klas_id`) REFERENCES `klas` (`klas_id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `bezoek_optie`
--
ALTER TABLE `bezoek_optie`
  ADD CONSTRAINT `fk_bezoek_optie_bezoek` FOREIGN KEY (`bezoek_id`) REFERENCES `bezoek` (`bezoek_id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `bezoek_school`
--
ALTER TABLE `bezoek_school`
  ADD CONSTRAINT `fk_bezoek_school_bezoek` FOREIGN KEY (`bezoek_id`) REFERENCES `bezoek` (`bezoek_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bezoek_school_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`) ON DELETE CASCADE;

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
-- Beperkingen voor tabel `_voorkeur_opties`
--
ALTER TABLE `_voorkeur_opties`
  ADD CONSTRAINT `_voorkeur_opties_ibfk_1` FOREIGN KEY (`klas_voorkeur_id`) REFERENCES `klas_voorkeur` (`id`) ON DELETE CASCADE;
COMMIT;
