-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 05 nov 2025 om 10:39
-- Serverversie: 10.4.32-MariaDB
-- PHP-versie: 8.1.25

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
  `pincode` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klas`
--

INSERT INTO `klas` (`klas_id`, `school_id`, `klasaanduiding`, `leerjaar`, `schooljaar`, `pincode`) VALUES
(1, 3, 'mondriaan', '4', '2025', '10'),
(2, 3, 'Roc', '1', '2026', 'mooi'),
(3, 1, 'ICT', '4', '2025', '12'),
(4, 5, 'rijland', '4', '2025', 'Amr'),
(5, 5, 'ICT', '1', '2026', '22'),
(6, 5, 'mondriaan', '3', '2025', '10');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `klas_voorkeur`
--

CREATE TABLE `klas_voorkeur` (
  `id` int(11) NOT NULL,
  `klas_id` int(11) NOT NULL,
  `volgorde` int(11) NOT NULL,
  `naam` varchar(100) NOT NULL,
  `actief` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klas_voorkeur`
--

INSERT INTO `klas_voorkeur` (`id`, `klas_id`, `volgorde`, `naam`, `actief`) VALUES
(1, 1, 1, 'mbo', 1),
(2, 1, 2, 'JS', 1),
(3, 1, 3, 'mbo3', 1),
(4, 1, 4, 'mbo4', 1),
(5, 2, 1, 'JS', 1),
(6, 2, 2, 'mbo', 1),
(7, 2, 3, 'java', 1),
(8, 3, 1, 'JS', 1),
(9, 3, 2, 'mbo', 1),
(10, 3, 3, 'JS', 1),
(11, 4, 1, 'java', 1),
(12, 4, 2, 'mbo1', 1),
(13, 4, 3, 'mbo3', 1),
(14, 4, 4, 'mbo5', 1),
(15, 5, 1, 'JS', 1),
(16, 5, 2, 'mbo', 1),
(17, 6, 1, 'JS', 1),
(18, 6, 2, 'java', 1),
(19, 6, 3, 'mbo', 1),
(20, 6, 4, 'mbo', 1),
(21, 6, 5, 'mbo', 1);

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
(1, 1, 'Amr', 'de', 'amr', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 3, 'amr', '', 'amr', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 'Anwer', '', 'Commerell', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 3, 'Amr', 'de', 'test', '8', '9', '10', NULL, NULL, NULL),
(5, 3, 'Amr', '', 'amr', '8', '9', '10', NULL, NULL, NULL),
(6, 4, 'test', '', 'test', '12', '11', '14', NULL, NULL, NULL);

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
(1, 'Vesterhavet', 'Hoofddorp', 'Primair Onderwijs'),
(2, 'Spaarne College', 'Haarlem', 'Voortgezet Onderwijs'),
(3, 'De Franciscus ', 'Bennebroek', 'Primair Onderwijs'),
(4, 'Willinkschool', 'Bennebroek', 'Primair Onderwijs'),
(5, 'Nova College', 'Haarlem', 'MBO');

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
-- Gegevens worden geëxporteerd voor tabel `voorkeur_opties`
--

INSERT INTO `voorkeur_opties` (`id`, `klas_voorkeur_id`, `naam`) VALUES
(1, 2, 'mbo'),
(2, 8, 'JS'),
(3, 9, 'mbo'),
(4, 10, 'JS'),
(5, 11, 'java'),
(6, 12, 'mbo1'),
(7, 13, 'mbo3'),
(8, 14, 'mbo5'),
(9, 15, 'JS'),
(10, 16, 'mbo'),
(11, 17, 'JS'),
(12, 18, 'java'),
(13, 19, 'mbo'),
(14, 20, 'mbo'),
(15, 21, 'mbo');

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
  ADD PRIMARY KEY (`klas_id`);

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
  MODIFY `klas_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT voor een tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT voor een tabel `leerling`
--
ALTER TABLE `leerling`
  MODIFY `leerling_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT voor een tabel `school`
--
ALTER TABLE `school`
  MODIFY `school_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT voor een tabel `voorkeur_opties`
--
ALTER TABLE `voorkeur_opties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
