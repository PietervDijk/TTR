-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 28 okt 2025 om 13:46
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
  `klas_id` smallint(6) NOT NULL,
  `school_id` smallint(6) NOT NULL,
  `klasaanduiding` varchar(100) NOT NULL,
  `leerjaar` varchar(100) DEFAULT NULL,
  `schooljaar` varchar(100) NOT NULL,
  `pincode` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klas`
--

INSERT INTO `klas` (`klas_id`, `school_id`, `klasaanduiding`, `leerjaar`, `schooljaar`, `pincode`) VALUES
(1, 5, 'ZWSD23F', '3', '2025-2026', '1234'),
(2, 5, 'ICT', '3', '3', '12');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `leerling`
--

CREATE TABLE `leerling` (
  `leerling_id` smallint(6) NOT NULL,
  `klas_id` smallint(6) NOT NULL,
  `voornaam` varchar(100) NOT NULL,
  `tussenvoegsel` varchar(100) DEFAULT NULL,
  `achternaam` varchar(100) NOT NULL,
  `voorkeur1_wereld_sector_id` smallint(6) NOT NULL,
  `voorkeur2_wereld_sector_id` smallint(6) NOT NULL,
  `voorkeur3_wereld_sector_id` smallint(6) NOT NULL,
  `toegewezen_wereld_sector_id` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `leerling`
--

INSERT INTO `leerling` (`leerling_id`, `klas_id`, `voornaam`, `tussenvoegsel`, `achternaam`, `voorkeur1_wereld_sector_id`, `voorkeur2_wereld_sector_id`, `voorkeur3_wereld_sector_id`, `toegewezen_wereld_sector_id`) VALUES
(1, 1, 'Amr', 'de', 'amr', 6, 4, 5, 0),
(2, 1, 'Amr', 'de', 'Anwer', 3, 1, 2, 0),
(3, 1, 'Robert', 'de', 'Commerell', 6, 4, 2, 0),
(4, 2, 'Anwer', '', 'amr', 3, 6, 4, 0),
(5, 2, 'Robert', '', 'Commerell', 3, 6, 6, 0),
(6, 2, 'Robert', '', 'Commerell', 3, 6, 6, 0),
(7, 2, 'amr', '', 'alhemyari', 3, 6, 4, 0);

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
-- Tabelstructuur voor tabel `wereld_sector`
--

CREATE TABLE `wereld_sector` (
  `wereld_sector_id` smallint(6) NOT NULL,
  `naam` varchar(100) NOT NULL,
  `type` set('wereld','sector') NOT NULL,
  `beschrijving` varchar(100) DEFAULT NULL,
  `actief` tinyint(1) NOT NULL,
  `max_aantal_leerlingen` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `wereld_sector`
--

INSERT INTO `wereld_sector` (`wereld_sector_id`, `naam`, `type`, `beschrijving`, `actief`, `max_aantal_leerlingen`) VALUES
(1, 'Techniek & Constructie', 'sector', 'Ontwerpen, bouwen en techniek toepassen', 1, 25),
(2, 'Zorg & Welzijn', 'sector', 'Werken met mensen en gezondheid', 1, 25),
(3, 'Economie & Ondernemen', 'sector', 'Bedrijf, geld en organisatie', 1, 25),
(4, 'Media & Vormgeving', 'sector', 'Creatief werken met media en design', 1, 25),
(5, 'Natuur & Milieu', 'wereld', 'Duurzaamheid, buitenwerk en biologie', 1, 25),
(6, 'ICT & Digitalisering', 'wereld', 'Programmeren, netwerken en AI', 1, 25);

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
  ADD KEY `school_id` (`school_id`);

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
-- Indexen voor tabel `wereld_sector`
--
ALTER TABLE `wereld_sector`
  ADD PRIMARY KEY (`wereld_sector_id`);

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
  MODIFY `klas_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT voor een tabel `leerling`
--
ALTER TABLE `leerling`
  MODIFY `leerling_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT voor een tabel `school`
--
ALTER TABLE `school`
  MODIFY `school_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT voor een tabel `wereld_sector`
--
ALTER TABLE `wereld_sector`
  MODIFY `wereld_sector_id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `klas`
--
ALTER TABLE `klas`
  ADD CONSTRAINT `klas_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`);

--
-- Beperkingen voor tabel `leerling`
--
ALTER TABLE `leerling`
  ADD CONSTRAINT `leerling_ibfk_1` FOREIGN KEY (`klas_id`) REFERENCES `klas` (`klas_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
