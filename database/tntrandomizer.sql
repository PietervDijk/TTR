-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 08 apr 2026 om 10:47
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
  `schooljaar` varchar(20) NOT NULL,
  `pincode` varchar(50) NOT NULL,
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
(1, 'Test Bezoek 123', 'PO', '', '12345678', 2, '2026-04-14 21:56:00', '2026-04-15 21:56:00', NULL, NULL, 1, '2026-03-27 20:57:25');

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
(1, 7);

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
  `max_leerlingen_dag1` int(11) DEFAULT NULL,
  `max_leerlingen_dag2` int(11) DEFAULT NULL,
  `actief` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `bezoek_optie`
--

INSERT INTO `bezoek_optie` (`optie_id`, `bezoek_id`, `volgorde`, `naam`, `max_leerlingen`, `dag_deel`, `actief`) VALUES
(9, 1, 1, 'Groen', 10, 'week', 1),
(10, 1, 2, 'Techniek', 10, 'week', 1),
(11, 1, 3, 'Bouw', 10, 'week', 1),
(12, 1, 4, 'Zorg', 10, 'week', 1);

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
(1, 5);

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
(1, 1, 'ZWSD23F', '3', '25/26'),
(2, 2, 'Groep 8', '8', '25/26'),
(3, 3, 'groep 1', '1', '25/26'),
(4, 4, 'groep 8', '8', '25/26'),
(5, 4, 'groep 7', '7', '25/26'),
(6, 5, 'groep 7', '7', '25/26'),
(7, 5, 'groep 8', '8', '25/26'),
(8, 6, 'groep 8', '8', '25/26'),
(9, 4, 'groep 7 (26/27)', '7', '26/27'),
(10, 7, '8a', '8', '25/26'),
(11, 7, '8b', '8', '25/26'),
(12, 7, '8c', '8', '25/26'),
(13, 7, '8d', '8', '25/26'),
(14, 8, '8a', '8', '25/26'),
(15, 8, '8b', '8', '25/26'),
(16, 9, '8', '8', '25/26'),
(17, 10, '8', '8', '25/26'),
(18, 11, '8a', '8', '25/26'),
(19, 11, '8b', '8', '25/26'),
(20, 11, '8c', '8', '25/26'),
(21, 11, '8d', '8', '25/26'),
(22, 12, '8', '8', '25/26'),
(23, 12, '7/8', '8', '25/26'),
(24, 13, '8', '8', '25/26'),
(25, 14, '8', '8', '25/26'),
(26, 14, '7', '7', '25/26'),
(27, 14, '7/8', '7/8', '25/26');

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
(2, 5, 'piet', 'de', 'vries', '1', '2', NULL, NULL, NULL, NULL),
(3, 5, 'evert', 'de', 'jong', '3', '1', NULL, NULL, NULL, NULL);

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
(14, 'De Vogels', 'Oegstgeest', 'Primair Onderwijs');

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
  MODIFY `bezoek_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `bezoek_optie`
--
ALTER TABLE `bezoek_optie`
  MODIFY `optie_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT voor een tabel `klas`
--
ALTER TABLE `klas`
  MODIFY `klas_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT voor een tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT voor een tabel `leerling`
--
ALTER TABLE `leerling`
  MODIFY `leerling_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT voor een tabel `school`
--
ALTER TABLE `school`
  MODIFY `school_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
