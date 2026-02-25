-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Feb 25, 2026 at 09:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tiranasolidare`
--

-- --------------------------------------------------------

--
-- Table structure for table `aplikimi`
--

CREATE TABLE `aplikimi` (
  `id_aplikimi` int(11) NOT NULL,
  `id_perdoruesi` int(11) DEFAULT NULL,
  `id_eventi` int(11) DEFAULT NULL,
  `statusi` enum('Në pritje','Pranuar','Refuzuar') DEFAULT 'Në pritje',
  `aplikuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Junction table for Many-to-Many relationship';

--
-- Dumping data for table `aplikimi`
--

INSERT INTO `aplikimi` (`id_aplikimi`, `id_perdoruesi`, `id_eventi`, `statusi`, `aplikuar_me`) VALUES
(20, 3, 1, 'Pranuar', '2026-02-24 22:16:35'),
(21, 4, 1, 'Pranuar', '2026-02-24 22:16:35'),
(22, 5, 1, 'Në pritje', '2026-02-24 22:16:35'),
(23, 6, 2, 'Pranuar', '2026-02-24 22:16:35'),
(24, 7, 2, 'Pranuar', '2026-02-24 22:16:35'),
(25, 8, 2, 'Në pritje', '2026-02-24 22:16:35'),
(26, 4, 3, 'Pranuar', '2026-02-24 22:16:35'),
(27, 9, 3, 'Pranuar', '2026-02-24 22:16:35'),
(28, 3, 4, 'Pranuar', '2026-02-24 22:16:35'),
(29, 6, 4, 'Në pritje', '2026-02-24 22:16:35'),
(30, 7, 5, 'Pranuar', '2026-02-24 22:16:35'),
(31, 9, 5, 'Refuzuar', '2026-02-24 22:16:35'),
(32, 3, 6, 'Pranuar', '2026-02-24 22:16:35'),
(33, 8, 6, 'Në pritje', '2026-02-24 22:16:35'),
(34, 5, 7, 'Pranuar', '2026-02-24 22:16:35'),
(35, 6, 7, 'Pranuar', '2026-02-24 22:16:35'),
(36, 9, 7, 'Në pritje', '2026-02-24 22:16:35'),
(37, 3, 8, 'Pranuar', '2026-02-24 22:16:35'),
(38, 4, 8, 'Në pritje', '2026-02-24 22:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `eventi`
--

CREATE TABLE `eventi` (
  `id_eventi` int(11) NOT NULL,
  `id_perdoruesi` int(11) DEFAULT NULL,
  `id_kategoria` int(11) DEFAULT NULL,
  `titulli` varchar(200) DEFAULT NULL,
  `pershkrimi` text DEFAULT NULL,
  `data` datetime DEFAULT NULL,
  `vendndodhja` varchar(255) DEFAULT NULL,
  `banner` varchar(500) DEFAULT NULL COMMENT 'URL to image storage',
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eventi`
--

INSERT INTO `eventi` (`id_eventi`, `id_perdoruesi`, `id_kategoria`, `titulli`, `pershkrimi`, `data`, `vendndodhja`, `banner`, `krijuar_me`) VALUES
(1, 2, 1, 'Pastrimi i Liqenit Artificial', 'Aktivitet pastrimi rreth Liqenit Artificial të Tiranës. Sjellni doreza dhe vullnet të mirë! Materialet e tjera do të sigurohen nga organizata.', '2026-03-15 08:00:00', 'Liqeni Artificial, Tiranë', 'https://images.unsplash.com/photo-1618477462146-050d2767eac4?q=80&w=800', '2026-02-24 22:16:35'),
(2, 2, 2, 'Shpërndarja e Ushqimit në Laprakë', 'Organizojmë shpërndarje ushqimi për familjet në nevojë në zonën e Laprakës. Kemi nevojë për vullnetarë që të ndihmojnë me paketimin dhe shpërndarjen.', '2026-03-10 09:00:00', 'Laprakë, Tiranë', 'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&w=800', '2026-02-24 22:16:35'),
(3, 3, 3, 'Tutoriale Falas për Nxënësit', 'Ofrojmë tutoriale falas në matematikë dhe shkenca për nxënësit e klasave 6-9 që kanë nevojë për ndihmë shtesë.', '2026-03-20 14:00:00', 'Biblioteka Kombëtare, Tiranë', 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=800', '2026-02-24 22:16:35'),
(4, 5, 4, 'Kontroll Mjekësor Falas', 'Në bashkëpunim me Kryqin e Kuq organizojmë kontroll mjekësor falas për të moshuarit në Kombinat.', '2026-03-25 10:00:00', 'Kombinat, Tiranë', 'https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=800', '2026-02-24 22:16:35'),
(5, 6, 2, 'Mbledhje Veshjesh për Dimrin', 'Mbledhim veshje dimri për fëmijët në nevojë. Mund të sillni xhaketa, çizme, doreza dhe shalle.', '2026-04-01 10:00:00', 'Qendra Sociale, Tiranë', 'https://images.unsplash.com/photo-1489710437720-ebb67ec84dd2?q=80&w=800', '2026-02-24 22:16:35'),
(6, 7, 3, 'Workshop Kodimi për të Rinjtë', 'Mësoni bazat e programimit në Python. I hapur për të gjithë të rinjtë 15-25 vjeç. Laptopët sigurohen nga ne.', '2026-04-05 15:00:00', 'Innovation Hub, Tiranë', 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=800', '2026-02-24 22:16:35'),
(7, 4, 5, 'Ndihma pas Përmbytjes në Kombinat', 'Organizojmë ndihmë emergjente për banorët e prekur nga përmbytja. Kemi nevojë për vullnetarë për pastrim dhe shpërndarje materialesh.', '2026-04-10 08:00:00', 'Kombinat, Tiranë', 'https://images.unsplash.com/photo-1547683905-f686c993aae5?q=80&w=800', '2026-02-24 22:16:35'),
(8, 8, 1, 'Mbjellja e Pemëve në Parkun e Ri', 'Bashkohu me ne për të mbjellur 200 pemë në parkun e ri të lagjes. Mjetet sigurohen nga bashkia.', '2026-04-15 09:00:00', 'Parku i Ri, Tiranë', 'https://images.unsplash.com/photo-1542601906990-b4d3fb773b09?q=80&w=800', '2026-02-24 22:16:35'),
(9, 2, 1, 'Pastrimi i Liqenit Artificial', 'Aktivitet pastrimi rreth Liqenit Artificial të Tiranës. Sjellni doreza dhe vullnet të mirë! Materialet e tjera do të sigurohen nga organizata.', '2026-03-15 08:00:00', 'Liqeni Artificial, Tiranë', 'https://images.unsplash.com/photo-1618477462146-050d2767eac4?q=80&w=800', '2026-02-24 22:15:18'),
(10, 2, 2, 'Shpërndarja e Ushqimit në Laprakë', 'Organizojmë shpërndarje ushqimi për familjet në nevojë në zonën e Laprakës. Kemi nevojë për vullnetarë që të ndihmojnë me paketimin dhe shpërndarjen.', '2026-03-10 09:00:00', 'Laprakë, Tiranë', 'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&w=800', '2026-02-24 22:15:18'),
(11, 3, 3, 'Tutoriale Falas për Nxënësit', 'Ofrojmë tutoriale falas në matematikë dhe shkenca për nxënësit e klasave 6-9 që kanë nevojë për ndihmë shtesë.', '2026-03-20 14:00:00', 'Biblioteka Kombëtare, Tiranë', 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=800', '2026-02-24 22:15:18'),
(12, 5, 4, 'Kontroll Mjekësor Falas', 'Në bashkëpunim me Kryqin e Kuq organizojmë kontroll mjekësor falas për të moshuarit në Kombinat.', '2026-03-25 10:00:00', 'Kombinat, Tiranë', 'https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=800', '2026-02-24 22:15:18'),
(13, 6, 2, 'Mbledhje Veshjesh për Dimrin', 'Mbledhim veshje dimri për fëmijët në nevojë. Mund të sillni xhaketa, çizme, doreza dhe shalle.', '2026-04-01 10:00:00', 'Qendra Sociale, Tiranë', 'https://images.unsplash.com/photo-1489710437720-ebb67ec84dd2?q=80&w=800', '2026-02-24 22:15:18'),
(14, 7, 3, 'Workshop Kodimi për të Rinjtë', 'Mësoni bazat e programimit në Python. I hapur për të gjithë të rinjtë 15-25 vjeç. Laptopët sigurohen nga ne.', '2026-04-05 15:00:00', 'Innovation Hub, Tiranë', 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=800', '2026-02-24 22:15:18'),
(15, 4, 5, 'Ndihma pas Përmbytjes në Kombinat', 'Organizojmë ndihmë emergjente për banorët e prekur nga përmbytja. Kemi nevojë për vullnetarë për pastrim dhe shpërndarje materialesh.', '2026-04-10 08:00:00', 'Kombinat, Tiranë', 'https://images.unsplash.com/photo-1547683905-f686c993aae5?q=80&w=800', '2026-02-24 22:15:18'),
(16, 8, 1, 'Mbjellja e Pemëve në Parkun e Ri', 'Bashkohu me ne për të mbjellur 200 pemë në parkun e ri të lagjes. Mjetet sigurohen nga bashkia.', '2026-04-15 09:00:00', 'Parku i Ri, Tiranë', 'https://images.unsplash.com/photo-1542601906990-b4d3fb773b09?q=80&w=800', '2026-02-24 22:15:18');

-- --------------------------------------------------------

--
-- Table structure for table `kategoria`
--

CREATE TABLE `kategoria` (
  `id_kategoria` int(11) NOT NULL,
  `emri` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategoria`
--

INSERT INTO `kategoria` (`id_kategoria`, `emri`) VALUES
(3, 'Edukimi'),
(5, 'Emergjenca'),
(1, 'Mjedis'),
(4, 'Shëndetësi'),
(2, 'Sociale');

-- --------------------------------------------------------

--
-- Table structure for table `kerkesa_per_ndihme`
--

CREATE TABLE `kerkesa_per_ndihme` (
  `id_kerkese_ndihme` int(11) NOT NULL,
  `id_perdoruesi` int(11) DEFAULT NULL,
  `tipi` enum('Kërkesë','Ofertë') DEFAULT NULL,
  `titulli` varchar(150) DEFAULT NULL,
  `pershkrimi` text DEFAULT NULL,
  `statusi` varchar(50) DEFAULT NULL COMMENT 'Open/Closed',
  `imazhi` varchar(500) DEFAULT NULL,
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kerkesa_per_ndihme`
--

INSERT INTO `kerkesa_per_ndihme` (`id_kerkese_ndihme`, `id_perdoruesi`, `tipi`, `titulli`, `pershkrimi`, `statusi`, `imazhi`, `krijuar_me`) VALUES
(1, 4, 'Kërkesë', 'Ndihmë me ushqim për familje me 4 anëtarë', 'Jemi familje me 4 anëtarë dhe po kalojmë një periudhë të vështirë financiare. Do na ndihmonte shumë çdo lloj ndihme me ushqime bazë.', 'Open', 'https://images.unsplash.com/photo-1593113598332-cd288d649433?q=80&w=800', '2026-02-24 22:16:35'),
(2, 5, 'Kërkesë', 'Kërkoj veshje dimri për 2 fëmijë', 'Kam 2 fëmijë, 6 dhe 9 vjeç, që kanë nevojë për xhaketa, çizme dhe veshje të ngrohta për dimrin.', 'Open', 'https://images.unsplash.com/photo-1532622785990-d2c36a76f5a6?q=80&w=800', '2026-02-24 22:16:35'),
(3, 3, 'Ofertë', 'Ofroj tutoriale falas në anglisht', 'Jam studente e gjuhës angleze dhe dua të ofroj tutoriale falas për fëmijët e klasave fillore.', 'Open', 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=800', '2026-02-24 22:16:35'),
(4, 6, 'Kërkesë', 'Ndihmë me riparim shtëpie pas përmbytjes', 'Banesat tona u dëmtuan nga përmbytja e fundit. Kemi nevojë për ndihmë me pastrim dhe riparime të vogla.', 'Open', 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?q=80&w=800', '2026-02-24 22:16:35'),
(5, 7, 'Ofertë', 'Ofroj transport falas për vizita mjekësore', 'Kam makinë dhe jam i disponueshëm të ofroj transport falas për të moshuarit që kanë vizita mjekësore.', 'Open', 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?q=80&w=800', '2026-02-24 22:16:35'),
(6, 8, 'Kërkesë', 'Kërkoj laptop për studime universitare', 'Jam student i vitit të parë dhe nuk kam laptop për të ndjekur leksionet online dhe detyrat.', 'Open', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?q=80&w=800', '2026-02-24 22:16:35'),
(7, 4, 'Ofertë', 'Ofroj kurse bazë kompjuteri', 'Dua të ofroj kurse bazë kompjuteri për të moshuarit që duan të mësojnë si të përdorin teknologjinë.', 'Open', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=800', '2026-02-24 22:16:35'),
(8, 9, 'Kërkesë', 'Ndihmë me furnizime shkollore', 'Kam 3 fëmijë në shkollë fillore dhe kam nevojë për furnizime shkollore: fleta, lapsa, çanta.', 'Open', 'https://images.unsplash.com/photo-1532622785990-d2c36a76f5a6?q=80&w=800', '2026-02-24 22:16:35'),
(9, 5, 'Kërkesë', 'Kërkoj ndihmë me qira', 'Po rrezikojmë të humbasim shtëpinë. Çdo ndihmë financiare do ishte e çmuar.', 'Closed', 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?q=80&w=800', '2026-02-24 22:16:35'),
(10, 6, 'Ofertë', 'Ofroj mobilje për familje në nevojë', 'Kam disa mobilje në gjendje të mirë që nuk i përdor më. Tavolinë, karrige dhe një divan.', 'Open', 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?q=80&w=800', '2026-02-24 22:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `njoftimi`
--

CREATE TABLE `njoftimi` (
  `id_njoftimi` int(11) NOT NULL,
  `id_perdoruesi` int(11) DEFAULT NULL,
  `mesazhi` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `njoftimi`
--

INSERT INTO `njoftimi` (`id_njoftimi`, `id_perdoruesi`, `mesazhi`, `is_read`, `krijuar_me`) VALUES
(1, 3, 'Aplikimi juaj për \"Pastrimi i Liqenit Artificial\" u pranua!', 1, '2026-02-24 22:16:35'),
(2, 4, 'Aplikimi juaj për \"Pastrimi i Liqenit Artificial\" u pranua!', 1, '2026-02-24 22:16:35'),
(3, 5, 'Aplikimi juaj për \"Pastrimi i Liqenit Artificial\" është në pritje.', 0, '2026-02-24 22:16:35'),
(4, 6, 'Aplikimi juaj për \"Shpërndarja e Ushqimit në Laprakë\" u pranua!', 0, '2026-02-24 22:16:35'),
(5, 7, 'Aplikimi juaj për \"Shpërndarja e Ushqimit në Laprakë\" u pranua!', 1, '2026-02-24 22:16:35'),
(6, 4, 'Aplikimi juaj për \"Tutoriale Falas për Nxënësit\" u pranua!', 1, '2026-02-24 22:16:35'),
(7, 9, 'Aplikimi juaj për \"Mbledhje Veshjesh për Dimrin\" u refuzua.', 0, '2026-02-24 22:16:35'),
(8, 3, 'Dikush ofroi ndihmë për kërkesën tuaj!', 0, '2026-02-24 22:16:35'),
(9, 6, 'Keni një event të ri në zonën tuaj: \"Ndihma pas Përmbytjes në Kombinat\"', 0, '2026-02-24 22:16:35'),
(10, 5, 'Mirë se vini në TiranaSolidare! Eksploroni mundësitë për vullnetarizëm.', 1, '2026-02-24 22:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `perdoruesi`
--

CREATE TABLE `perdoruesi` (
  `id_perdoruesi` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `fjalekalimi` varchar(255) NOT NULL,
  `roli` enum('Admin','Vullnetar') DEFAULT 'Vullnetar',
  `statusi_llogarise` enum('Aktiv','Bllokuar') DEFAULT 'Aktiv',
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Main user table storing auth details';

--
-- Dumping data for table `perdoruesi`
--

INSERT INTO `perdoruesi` (`id_perdoruesi`, `emri`, `email`, `fjalekalimi`, `roli`, `statusi_llogarise`, `krijuar_me`) VALUES
(2, 'admin', 'admin@tirana.al', '$2y$10$mVd06TmyRZLOBvbsAdN1J.GsIw1QbY5.e7g7vYW4OgtiODz4f.yX.', 'Admin', 'Aktiv', '2026-02-13 23:18:58'),
(3, 'Elisa Basha', 'elisa.basha@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', '2026-02-24 22:09:13'),
(4, 'Dritan Shehu', 'dritan.shehu@yahoo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', '2026-02-24 22:09:13'),
(5, 'Mira Kelmendi', 'mira.kelmendi@outlook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', '2026-02-24 22:09:13'),
(6, 'Besnik Duka', 'besnik.duka@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', '2026-02-24 22:09:13'),
(7, 'Anisa Rama', 'anisa.rama@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', '2026-02-24 22:09:13'),
(8, 'Genti Berisha', 'genti.berisha@hotmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', '2026-02-24 22:09:13'),
(9, 'Luana Topalli', 'luana.topalli@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', '2026-02-24 22:09:13'),
(10, 'Fatjon Muça', 'fatjon.muca@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Bllokuar', '2026-02-24 22:09:13');

-- --------------------------------------------------------

--
-- Table structure for table `raporti`
--

CREATE TABLE `raporti` (
  `id_raporti` int(11) NOT NULL,
  `id_perdoruesi` int(11) DEFAULT NULL,
  `tipi_raportit` varchar(50) DEFAULT NULL,
  `permbajtja` text DEFAULT NULL,
  `gjeneruar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `raporti`
--

INSERT INTO `raporti` (`id_raporti`, `id_perdoruesi`, `tipi_raportit`, `permbajtja`, `gjeneruar_me`) VALUES
(1, 2, 'Mujor', 'Raporti mujor i aktiviteteve: 8 evente të organizuara, 19 aplikime nga vullnetarë, 10 kërkesa/oferta aktive.', '2026-02-24 22:16:35'),
(2, 2, 'Statistikë', 'Statistikat e platformës: 9 përdorues të regjistruar, 8 evente aktive, 5 kategori.', '2026-02-24 22:16:35'),
(3, 3, 'Feedback', 'Përvoja ime si vullnetar ka qenë shumë pozitive. Platforma është e thjeshtë për tu përdorur.', '2026-02-24 22:16:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aplikimi`
--
ALTER TABLE `aplikimi`
  ADD PRIMARY KEY (`id_aplikimi`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`),
  ADD KEY `id_eventi` (`id_eventi`);

--
-- Indexes for table `eventi`
--
ALTER TABLE `eventi`
  ADD PRIMARY KEY (`id_eventi`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`),
  ADD KEY `id_kategoria` (`id_kategoria`);

--
-- Indexes for table `kategoria`
--
ALTER TABLE `kategoria`
  ADD PRIMARY KEY (`id_kategoria`),
  ADD UNIQUE KEY `emri` (`emri`);

--
-- Indexes for table `kerkesa_per_ndihme`
--
ALTER TABLE `kerkesa_per_ndihme`
  ADD PRIMARY KEY (`id_kerkese_ndihme`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`);

--
-- Indexes for table `njoftimi`
--
ALTER TABLE `njoftimi`
  ADD PRIMARY KEY (`id_njoftimi`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`);

--
-- Indexes for table `perdoruesi`
--
ALTER TABLE `perdoruesi`
  ADD PRIMARY KEY (`id_perdoruesi`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `raporti`
--
ALTER TABLE `raporti`
  ADD PRIMARY KEY (`id_raporti`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aplikimi`
--
ALTER TABLE `aplikimi`
  MODIFY `id_aplikimi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `eventi`
--
ALTER TABLE `eventi`
  MODIFY `id_eventi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `kategoria`
--
ALTER TABLE `kategoria`
  MODIFY `id_kategoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `kerkesa_per_ndihme`
--
ALTER TABLE `kerkesa_per_ndihme`
  MODIFY `id_kerkese_ndihme` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `njoftimi`
--
ALTER TABLE `njoftimi`
  MODIFY `id_njoftimi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `perdoruesi`
--
ALTER TABLE `perdoruesi`
  MODIFY `id_perdoruesi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `raporti`
--
ALTER TABLE `raporti`
  MODIFY `id_raporti` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aplikimi`
--
ALTER TABLE `aplikimi`
  ADD CONSTRAINT `aplikimi_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`),
  ADD CONSTRAINT `aplikimi_ibfk_2` FOREIGN KEY (`id_eventi`) REFERENCES `eventi` (`id_eventi`);

--
-- Constraints for table `eventi`
--
ALTER TABLE `eventi`
  ADD CONSTRAINT `eventi_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`),
  ADD CONSTRAINT `eventi_ibfk_2` FOREIGN KEY (`id_kategoria`) REFERENCES `kategoria` (`id_kategoria`);

--
-- Constraints for table `kerkesa_per_ndihme`
--
ALTER TABLE `kerkesa_per_ndihme`
  ADD CONSTRAINT `kerkesa_per_ndihme_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`);

--
-- Constraints for table `njoftimi`
--
ALTER TABLE `njoftimi`
  ADD CONSTRAINT `njoftimi_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`);

--
-- Constraints for table `raporti`
--
ALTER TABLE `raporti`
  ADD CONSTRAINT `raporti_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
