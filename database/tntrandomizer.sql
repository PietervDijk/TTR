-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 17 nov 2025 om 14:32
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
  `pincode` varchar(50) DEFAULT NULL,
  `max_keuzes` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klas`
--

INSERT INTO `klas` (`klas_id`, `school_id`, `klasaanduiding`, `leerjaar`, `schooljaar`, `pincode`, `max_keuzes`) VALUES
(7, 5, 'zwsd23f', '3', '2025/2026', '123456', 3),
(10, 5, 'testklas', '1', '2025/2026', '1234', 2),
(11, 5, 'ICT', '3', '2026', 'ICT', 3),
(20, 5, 'rijland', '4', '2026', '67', 2);

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
(28, 7, 4, 'Bouw', 1, NULL),
(29, 7, 5, 'Groen', 1, NULL),
(30, 7, 6, 'Ondernemen', 1, NULL),
(31, 7, 7, 'ICT', 1, NULL),
(32, 7, 8, 'Koken', 1, NULL),
(33, 7, 9, 'Economie', 1, NULL),
(34, 7, 10, 'Biologie', 1, NULL),
(38, 10, 1, 'Koken', 1, 4),
(39, 10, 2, 'ICT', 1, 5),
(40, 10, 3, 'Zorg', 1, 3),
(41, 10, 4, 'Ondernemen', 1, 2),
(42, 10, 5, 'Groen', 1, 6),
(43, 11, 1, 'java', 1, NULL),
(44, 11, 2, 'mbo', 1, NULL),
(45, 11, 3, 'JS', 1, NULL),
(46, 11, 4, 'mbo', 1, NULL),
(47, 20, 1, 'JS', 1, NULL),
(48, 20, 2, 'mbo', 1, NULL),
(49, 20, 3, 'java', 1, NULL);

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
(9, 7, 'Pieter', 'van', 'Dijk', '31', '29', '32', NULL, NULL, NULL),
(10, 7, 'Jan', 'de', 'Allenman', '30', '32', '31', NULL, NULL, NULL),
(11, 10, 'Anwer', 'de', 'amr', '38', '39', NULL, NULL, NULL, NULL),
(12, 10, 'martijn', '', 'amr', '40', '39', NULL, NULL, NULL, NULL),
(13, 11, 'Amr', '', 'Commerell', '43', '45', '44', NULL, NULL, NULL),
(14, 11, 'martijn', '', 'martijn', '44', '46', '43', NULL, NULL, NULL),
(15, 10, 'martijn', '', 'amr', '38', '39', NULL, NULL, NULL, NULL),
(16, 10, 'martijn', '', 'amr', '41', '40', NULL, NULL, NULL, NULL),
(17, 10, 'martijn', '', 'amr', '38', '41', NULL, NULL, NULL, NULL),
(18, 10, 'martijn', '', 'amr', '39', '38', NULL, NULL, NULL, NULL),
(19, 10, 'martijn', '', 'amr', '41', '42', NULL, NULL, NULL, NULL),
(20, 10, 'martijn', '', 'amr', '42', '40', NULL, NULL, NULL, NULL),
(21, 10, 'martijn', '', 'amr', '38', '40', NULL, NULL, NULL, NULL),
(22, 10, 'martijn', '', 'amr', '42', '39', NULL, NULL, NULL, NULL),
(23, 10, 'martijn', '', 'amr', '41', '38', NULL, NULL, NULL, NULL),
(24, 20, 'Amr', '', 'Commerell', '47', '48', NULL, NULL, NULL, '47'),
(25, 20, 'Amr', '', 'Commerell', '49', '47', NULL, NULL, NULL, '48'),
(26, 20, 'Amr', '', 'Commerell', '48', '49', NULL, NULL, NULL, '49'),
(27, 20, 'Amr', '', 'Commerell', '48', '47', NULL, NULL, NULL, '49'),
(28, 20, 'Amr', '', 'Commerell', '48', '49', NULL, NULL, NULL, '48'),
(29, 20, 'Amr', '', 'Commerell', '47', '48', NULL, NULL, NULL, '48'),
(30, 20, 'Amr', '', 'Commerell', '49', '48', NULL, NULL, NULL, '47'),
(31, 20, 'Amr', '', 'Commerell', '48', '47', NULL, NULL, NULL, '47'),
(32, 20, 'Amr', '', 'Commerell', '49', '48', NULL, NULL, NULL, '47'),
(33, 20, 'Anwer', '', 'martijn', '48', '47', NULL, NULL, NULL, '47');

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
(22, 28, 'Bouw'),
(23, 29, 'Groen'),
(24, 30, 'Ondernemen'),
(25, 31, 'ICT'),
(26, 32, 'Koken'),
(27, 33, 'Economie'),
(28, 34, 'Biologie'),
(32, 38, 'Koken'),
(33, 39, 'ICT'),
(34, 40, 'Zorg'),
(35, 41, 'Ondernemen'),
(36, 42, 'Groen'),
(37, 43, 'java'),
(38, 44, 'mbo'),
(39, 45, 'JS'),
(40, 46, 'mbo'),
(41, 47, 'JS'),
(42, 48, 'mbo'),
(43, 49, 'java');

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
  MODIFY `klas_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT voor een tabel `klas_voorkeur`
--
ALTER TABLE `klas_voorkeur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT voor een tabel `leerling`
--
ALTER TABLE `leerling`
  MODIFY `leerling_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT voor een tabel `school`
--
ALTER TABLE `school`
  MODIFY `school_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT voor een tabel `voorkeur_opties`
--
ALTER TABLE `voorkeur_opties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

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
