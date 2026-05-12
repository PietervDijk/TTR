-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 12 mei 2026 om 13:05
-- Serverversie: 10.4.32-MariaDB
-- PHP-versie: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tntrandomizer`
--

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
  `type_onderwijs` enum('PO','VO','MBO') NOT NULL,
  `schooljaar` varchar(20) NOT NULL DEFAULT '',
  `pincode` varchar(10) NOT NULL,
  `max_keuzes` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `po_dag1` datetime DEFAULT NULL,
  `po_dag2` datetime DEFAULT NULL,
  `vo_week_start` date DEFAULT NULL,
  `vo_week_eind` date DEFAULT NULL,
  `actief` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `bezoek`
--

INSERT INTO `bezoek` (`bezoek_id`, `naam`, `type_onderwijs`, `schooljaar`, `pincode`, `max_keuzes`, `po_dag1`, `po_dag2`, `vo_week_start`, `vo_week_eind`, `actief`, `created_at`) VALUES
(1, 'Test Bezoek 123', 'PO', '2025 - 2026', '12345678', 2, '2026-04-14 21:56:00', '2026-04-15 21:56:00', NULL, NULL, 1, '2026-03-27 20:57:25'),
(3, 'Test Bezoek 2 - April', 'MBO', '2025 - 2026', '12345', 2, NULL, NULL, '2026-04-21', '2026-04-22', 1, '2026-04-15 08:29:09'),
(4, 'Test Bezoek - Met Renske', 'PO', '2025 - 2026', '123456', 3, '2026-06-16 10:40:00', '2026-06-17 10:41:00', NULL, NULL, 1, '2026-04-15 08:43:16');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `bezoek_klas`
--

CREATE TABLE `bezoek_klas` (
  `bezoek_id` int(11) NOT NULL,
  `klas_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `bezoek_klas`
--

INSERT INTO `bezoek_klas` (`bezoek_id`, `klas_id`) VALUES
(1, 3),
(1, 5),
(1, 7),
(3, 1),
(4, 18),
(4, 19),
(4, 20),
(4, 21);

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
  `max_leerlingen_dag1` int(11) DEFAULT NULL,
  `max_leerlingen_dag2` int(11) DEFAULT NULL,
  `dag_deel` enum('week','dag1','dag2','beide') NOT NULL DEFAULT 'week',
  `actief` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `bezoek_optie`
--

INSERT INTO `bezoek_optie` (`optie_id`, `bezoek_id`, `volgorde`, `naam`, `max_leerlingen`, `max_leerlingen_dag1`, `max_leerlingen_dag2`, `dag_deel`, `actief`) VALUES
(66, 4, 1, 'Goede Wereld', NULL, 25, 25, 'beide', 1),
(67, 4, 2, 'Groene Wereld', NULL, 24, 25, 'beide', 1),
(68, 4, 3, 'Technische Wereld', NULL, 16, 25, 'beide', 1),
(69, 4, 4, 'Wetenschappelijke Wereld', NULL, 25, 25, 'beide', 1),
(70, 4, 5, 'Zorgzame Wereld', NULL, 20, 20, 'beide', 1),
(71, 3, 1, 'Voorbereiding HBO', 10, NULL, NULL, 'week', 1),
(72, 3, 2, 'Verdieping Software', 20, NULL, NULL, 'week', 1),
(73, 3, 3, 'Engels - Extra', 10, NULL, NULL, 'week', 1),
(74, 3, 4, 'Sport', 9, NULL, NULL, 'week', 1),
(79, 1, 1, 'Groen', NULL, 20, 5, 'beide', 1),
(80, 1, 2, 'Techniek', NULL, 10, 15, 'beide', 1),
(81, 1, 3, 'Bouw', NULL, 15, 10, 'beide', 1),
(82, 1, 4, 'Zorg', NULL, 5, 20, 'beide', 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `bezoek_school`
--

CREATE TABLE `bezoek_school` (
  `bezoek_id` int(11) NOT NULL,
  `school_id` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `bezoek_school`
--

INSERT INTO `bezoek_school` (`bezoek_id`, `school_id`) VALUES
(1, 3),
(1, 4),
(1, 5),
(3, 1),
(4, 11);

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
  `schooljaar` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klas`
--

INSERT INTO `klas` (`klas_id`, `school_id`, `klasaanduiding`, `leerjaar`, `schooljaar`) VALUES
(1, 1, 'ZWSD23F', '3', '2025 - 2026'),
(3, 3, 'groep 1', '1', '2025 - 2026'),
(4, 4, 'groep 8', '8', '2025 - 2026'),
(5, 4, 'groep 7', '7', '2025 - 2026'),
(6, 5, 'groep 7', '7', '2025 - 2026'),
(7, 5, 'groep 8', '8', '2025 - 2026'),
(8, 6, 'groep 8', '8', '2025 - 2026'),
(9, 4, 'groep 7 (26/27)', '7', '2026 - 2027'),
(10, 7, '8a', '8', '2025 - 2026'),
(11, 7, '8b', '8', '2025 - 2026'),
(12, 7, '8c', '8', '2025 - 2026'),
(13, 7, '8d', '8', '2025 - 2026'),
(14, 8, '8a', '8', '2025 - 2026'),
(15, 8, '8b', '8', '2025 - 2026'),
(16, 9, '8', '8', '2025 - 2026'),
(17, 10, '8', '8', '2025 - 2026'),
(18, 11, '8a', '8', '2025 - 2026'),
(19, 11, '8b', '8', '2025 - 2026'),
(20, 11, '8c', '8', '2025 - 2026'),
(21, 11, '8d', '8', '2025 - 2026'),
(22, 12, '8', '8', '2025 - 2026'),
(23, 12, '7/8', '8', '2025 - 2026'),
(24, 13, '8', '8', '2025 - 2026'),
(25, 14, '8', '8', '2025 - 2026'),
(26, 14, '7', '7', '2025 - 2026'),
(27, 14, '7/8', '7/8', '2025 - 2026'),
(28, 15, 'Test Klas 1', '1', '2025 - 2026'),
(29, 15, 'Test Klas 2', '2', '2025 - 2026'),
(30, 16, 'Test Klas 1', '1', '2025 - 2026'),
(31, 16, 'Test Klas 2', '2', '2025 - 2026');

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
  `toegewezen_week` varchar(100) DEFAULT NULL,
  `toegewezen_dag1` varchar(100) DEFAULT NULL,
  `toegewezen_dag2` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `leerling`
--

INSERT INTO `leerling` (`leerling_id`, `klas_id`, `voornaam`, `tussenvoegsel`, `achternaam`, `voorkeur1`, `voorkeur2`, `voorkeur3`, `voorkeur4`, `voorkeur5`, `toegewezen_week`, `toegewezen_dag1`, `toegewezen_dag2`) VALUES
(2, 5, 'piet', 'de', 'vries', '81', '82', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 5, 'evert', 'de', 'jong', '80', '79', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 18, 'Renske', '', 'Peters', '69', '67', '68', NULL, NULL, '62|dag1', NULL, NULL),
(5, 18, 'Jan', 'van', 'Rijnsbergen', '66', '69', '67', NULL, NULL, '65|dag1', NULL, NULL),
(6, 18, 'Klaas', 'de', 'Groot', '67', '68', '70', NULL, NULL, '61|dag1', NULL, NULL),
(7, 1, 'Pieter', 'van', 'Dijk', '72', '71', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 1, 'Henk', 'de', 'Jong', '71', '73', NULL, NULL, NULL, NULL, NULL, NULL),
(9, 1, 'Jan', '', 'Jansen', '73', '74', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 1, 'Ferdy', 'van', 'Klink', '72', '71', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 1, 'Ahmed', '', 'Algurbani', '72', '71', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 1, 'Sam', 'de', 'Vries', '74', '72', NULL, NULL, NULL, NULL, NULL, NULL),
(13, 18, 'Pietertje', '', 'Klaassen', '70', '66', '69', NULL, NULL, '63|dag1', NULL, NULL),
(14, 7, 'Jan', '', 'Klaasen', '80', '79', NULL, NULL, NULL, NULL, NULL, NULL),
(15, 7, 'Peter', 'van', 'Groenendael', '81', '82', NULL, NULL, NULL, NULL, NULL, NULL),
(16, 7, 'Jantje', '', 'Cornelissen', '82', '81', NULL, NULL, NULL, NULL, NULL, NULL),
(17, 7, 'Keessie', '', 'Smits', '80', '81', NULL, NULL, NULL, NULL, NULL, NULL),
(18, 3, 'Henk', '', 'Roossen', '79', '82', NULL, NULL, NULL, NULL, NULL, NULL),
(19, 20, 'geert', 'wilt wat', 'wilders', '66', '68', '69', NULL, NULL, NULL, NULL, NULL),
(20, 20, 'anja', '', 'hhhhh', '67', '68', '69', NULL, NULL, NULL, NULL, NULL);

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
(3, 'De Regenboog', 'Leiden', 'Primair Onderwijs'),
(4, 'De Fransiscusschool', 'Bennebroek', 'Primair Onderwijs'),
(5, 'De Willinkschool', 'Bennebroek', 'Primair Onderwijs'),
(6, 'St. Bernardusschool', 'Haarlem', 'Primair Onderwijs'),
(7, 'Josephschool', 'Leiden', 'Primair Onderwijs'),
(8, 'Woutertje Pieterse', 'Leiden', 'Primair Onderwijs'),
(9, 'De Viersprong', 'Leiden', 'Primair Onderwijs'),
(10, 'De Pionier', 'Leiden', 'Primair Onderwijs'),
(11, 'Lorentzschool', 'Leiden', 'Primair Onderwijs'),
(12, 'ELS', 'Leiden', 'Primair Onderwijs'),
(13, 'Koningin Julianaschool', 'Leiderdorp', 'Primair Onderwijs'),
(14, 'De Vogels', 'Oegstgeest', 'Primair Onderwijs'),
(15, 'PO test School 1 ', 'Leiden', 'Primair Onderwijs'),
(16, 'PO test School 2', 'Leiden', 'Primair Onderwijs');

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
  ADD PRIMARY KEY (`bezoek_id`,`klas_id`);

--
-- Indexen voor tabel `bezoek_optie`
--
ALTER TABLE `bezoek_optie`
  ADD PRIMARY KEY (`optie_id`);

--
-- Indexen voor tabel `bezoek_school`
--
ALTER TABLE `bezoek_school`
  ADD PRIMARY KEY (`bezoek_id`,`school_id`);

--
-- Indexen voor tabel `klas`
--
ALTER TABLE `klas`
  ADD PRIMARY KEY (`klas_id`);

--
-- Indexen voor tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `leerling`
--
ALTER TABLE `leerling`
  ADD PRIMARY KEY (`leerling_id`);

--
-- Indexen voor tabel `school`
--
ALTER TABLE `school`
  ADD PRIMARY KEY (`school_id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `bezoek`
--
ALTER TABLE `bezoek`
  MODIFY `bezoek_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT voor een tabel `bezoek_optie`
--
ALTER TABLE `bezoek_optie`
  MODIFY `optie_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT voor een tabel `klas`
--
ALTER TABLE `klas`
  MODIFY `klas_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT voor een tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT voor een tabel `leerling`
--
ALTER TABLE `leerling`
  MODIFY `leerling_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT voor een tabel `school`
--
ALTER TABLE `school`
  MODIFY `school_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
