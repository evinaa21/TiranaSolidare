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

-- --------------------------------------------------------

--
-- Table structure for table `aplikimi_kerkese`
--

CREATE TABLE `aplikimi_kerkese` (
  `id_aplikimi_kerkese` int(11) NOT NULL,
  `id_kerkese_ndihme` int(11) NOT NULL,
  `id_perdoruesi` int(11) NOT NULL,
  `statusi` enum('Në pritje','Pranuar','Refuzuar') DEFAULT 'Në pritje',
  `aplikuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Aplikime të vullnetarëve për kërkesat e ndihmës';

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
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `banner` varchar(500) DEFAULT NULL COMMENT 'URL to image storage',
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eventi`
--

INSERT INTO `eventi` (`id_eventi`, `id_perdoruesi`, `id_kategoria`, `titulli`, `pershkrimi`, `data`, `vendndodhja`, `latitude`, `longitude`, `banner`, `krijuar_me`) VALUES
(1, 2, 1, 'Pastrimi i Liqenit Artificial', 'Aktivitet pastrimi rreth Liqenit Artificial të Tiranës. Sjellni doreza dhe vullnet të mirë! Materialet e tjera do të sigurohen nga organizata.', '2026-03-15 08:00:00', 'Liqeni Artificial, Tiranë', 41.3133000, 19.8195000, 'https://images.unsplash.com/photo-1618477462146-050d2767eac4?q=80&w=800', '2026-02-24 22:16:35'),
(2, 2, 2, 'Shpërndarja e Ushqimit në Laprakë', 'Organizojmë shpërndarje ushqimi për familjet në nevojë në zonën e Laprakës. Kemi nevojë për vullnetarë që të ndihmojnë me paketimin dhe shpërndarjen.', '2026-03-10 09:00:00', 'Laprakë, Tiranë', 41.3422000, 19.7919000, 'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&w=800', '2026-02-24 22:16:35'),
(3, 2, 3, 'Tutoriale Falas për Nxënësit', 'Ofrojmë tutoriale falas në matematikë dhe shkenca për nxënësit e klasave 6-9 që kanë nevojë për ndihmë shtesë.', '2026-03-20 14:00:00', 'Biblioteka Kombëtare, Tiranë', 41.3265000, 19.8195000, 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=800', '2026-02-24 22:16:35'),
(4, 2, 4, 'Kontroll Mjekësor Falas', 'Në bashkëpunim me Kryqin e Kuq organizojmë kontroll mjekësor falas për të moshuarit në Kombinat.', '2026-03-25 10:00:00', 'Kombinat, Tiranë', 41.3372000, 19.7707000, 'https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=800', '2026-02-24 22:16:35'),
(5, 2, 2, 'Mbledhje Veshjesh për Dimrin', 'Mbledhim veshje dimri për fëmijët në nevojë. Mund të sillni xhaketa, çizme, doreza dhe shalle.', '2026-04-01 10:00:00', 'Qendra Sociale, Tiranë', 41.3275000, 19.8187000, 'https://images.unsplash.com/photo-1489710437720-ebb67ec84dd2?q=80&w=800', '2026-02-24 22:16:35'),
(6, 2, 3, 'Workshop Kodimi për të Rinjtë', 'Mësoni bazat e programimit në Python. I hapur për të gjithë të rinjtë 15-25 vjeç. Laptopët sigurohen nga ne.', '2026-04-05 15:00:00', 'Innovation Hub, Tiranë', 41.3285000, 19.8180000, 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=800', '2026-02-24 22:16:35'),
(7, 2, 5, 'Ndihma pas Përmbytjes në Kombinat', 'Organizojmë ndihmë emergjente për banorët e prekur nga përmbytja. Kemi nevojë për vullnetarë për pastrim dhe shpërndarje materialesh.', '2026-04-10 08:00:00', 'Kombinat, Tiranë', 41.3372000, 19.7707000, 'https://images.unsplash.com/photo-1547683905-f686c993aae5?q=80&w=800', '2026-02-24 22:16:35'),
(8, 2, 1, 'Mbjellja e Pemëve në Parkun e Ri', 'Bashkohu me ne për të mbjellur 200 pemë në parkun e ri të lagjes. Mjetet sigurohen nga bashkia.', '2026-04-15 09:00:00', 'Parku i Ri, Tiranë', 41.3190000, 19.8230000, 'https://images.unsplash.com/photo-1542601906990-b4d3fb773b09?q=80&w=800', '2026-02-24 22:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `kategoria`
--

CREATE TABLE `kategoria` (
  `id_kategoria` int(11) NOT NULL,
  `emri` varchar(50) DEFAULT NULL,
  `banner_path` varchar(500) DEFAULT NULL
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
  `statusi` enum('Open','Closed') DEFAULT 'Open' COMMENT 'Open/Closed',
  `imazhi` varchar(500) DEFAULT NULL,
  `vendndodhja` varchar(255) DEFAULT NULL,  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kerkesa_per_ndihme`
--

INSERT INTO `kerkesa_per_ndihme` (`id_kerkese_ndihme`, `id_perdoruesi`, `tipi`, `titulli`, `pershkrimi`, `statusi`, `imazhi`, `vendndodhja`, `krijuar_me`) VALUES
(1, 4, 'Kërkesë', 'Ndihmë me ushqim për familje me 4 anëtarë', 'Jemi familje me 4 anëtarë dhe po kalojmë një periudhë të vështirë financiare. Do na ndihmonte shumë çdo lloj ndihme me ushqime bazë.', 'Open', 'https://images.unsplash.com/photo-1593113598332-cd288d649433?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35'),
(2, 5, 'Kërkesë', 'Kërkoj veshje dimri për 2 fëmijë', 'Kam 2 fëmijë, 6 dhe 9 vjeç, që kanë nevojë për xhaketa, çizme dhe veshje të ngrohta për dimrin.', 'Open', 'https://images.unsplash.com/photo-1532622785990-d2c36a76f5a6?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35'),
(3, 3, 'Ofertë', 'Ofroj tutoriale falas në anglisht', 'Jam studente e gjuhës angleze dhe dua të ofroj tutoriale falas për fëmijët e klasave fillore.', 'Open', 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35'),
(4, 6, 'Kërkesë', 'Ndihmë me riparim shtëpie pas përmbytjes', 'Banesat tona u dëmtuan nga përmbytja e fundit. Kemi nevojë për ndihmë me pastrim dhe riparime të vogla.', 'Open', 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?q=80&w=800', 'Kombinat, Tiranë', '2026-02-24 22:16:35'),
(5, 7, 'Ofertë', 'Ofroj transport falas për vizita mjekësore', 'Kam makinë dhe jam i disponueshëm të ofroj transport falas për të moshuarit që kanë vizita mjekësore.', 'Open', 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35'),
(6, 8, 'Kërkesë', 'Kërkoj laptop për studime universitare', 'Jam student i vitit të parë dhe nuk kam laptop për të ndjekur leksionet online dhe detyrat.', 'Open', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35'),
(7, 4, 'Ofertë', 'Ofroj kurse bazë kompjuteri', 'Dua të ofroj kurse bazë kompjuteri për të moshuarit që duan të mësojnë si të përdorin teknologjinë.', 'Open', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35'),
(8, 9, 'Kërkesë', 'Ndihmë me furnizime shkollore', 'Kam 3 fëmijë në shkollë fillore dhe kam nevojë për furnizime shkollore: fleta, lapsa, çanta.', 'Open', 'https://images.unsplash.com/photo-1532622785990-d2c36a76f5a6?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35'),
(9, 5, 'Kërkesë', 'Kërkoj ndihmë me qira', 'Po rrezikojmë të humbasim shtëpinë. Çdo ndihmë financiare do ishte e çmuar.', 'Closed', 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?q=80&w=800', 'Laprakë, Tiranë', '2026-02-24 22:16:35'),
(10, 6, 'Ofertë', 'Ofroj mobilje për familje në nevojë', 'Kam disa mobilje në gjendje të mirë që nuk i përdor më. Tavolinë, karrige dhe një divan.', 'Open', 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?q=80&w=800', 'Tiranë', '2026-02-24 22:16:35');

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
  `birthdate` date DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `profile_public` tinyint(1) NOT NULL DEFAULT 0,
  `profile_color` varchar(20) NOT NULL DEFAULT 'emerald',
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `fjalekalimi` varchar(255) NOT NULL,
  `roli` enum('Admin','Vullnetar') DEFAULT 'Vullnetar',
  `statusi_llogarise` enum('Aktiv','Bllokuar','Çaktivizuar') DEFAULT 'Aktiv',
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token_hash` varchar(64) DEFAULT NULL,
  `verification_token_expires` datetime DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_email` varchar(150) DEFAULT NULL,
  `guardian_relation` varchar(60) DEFAULT NULL,
  `guardian_consent_status` varchar(20) NOT NULL DEFAULT 'not_required',
  `guardian_consent_token_hash` varchar(64) DEFAULT NULL,
  `guardian_consent_token_expires` datetime DEFAULT NULL,
  `guardian_consent_verified_at` datetime DEFAULT NULL,
  `password_reset_token_hash` varchar(64) DEFAULT NULL,
  `password_reset_token_expires` datetime DEFAULT NULL,
  `krijuar_me` timestamp NOT NULL DEFAULT current_timestamp(),
  `deaktivizuar_me` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Main user table storing auth details';

--
-- Dumping data for table `perdoruesi`
--

INSERT INTO `perdoruesi` (`id_perdoruesi`, `emri`, `email`, `fjalekalimi`, `roli`, `statusi_llogarise`, `verified`, `krijuar_me`) VALUES
(2, 'admin', 'admin@tirana.al', '$2y$10$mVd06TmyRZLOBvbsAdN1J.GsIw1QbY5.e7g7vYW4OgtiODz4f.yX.', 'Admin', 'Aktiv', 1, '2026-02-13 23:18:58'),
(3, 'Elisa Basha', 'elisa.basha@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', 1, '2026-02-24 22:09:13'),
(4, 'Dritan Shehu', 'dritan.shehu@yahoo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', 1, '2026-02-24 22:09:13'),
(5, 'Mira Kelmendi', 'mira.kelmendi@outlook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', 1, '2026-02-24 22:09:13'),
(6, 'Besnik Duka', 'besnik.duka@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', 1, '2026-02-24 22:09:13'),
(7, 'Anisa Rama', 'anisa.rama@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', 1, '2026-02-24 22:09:13'),
(8, 'Genti Berisha', 'genti.berisha@hotmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', 1, '2026-02-24 22:09:13'),
(9, 'Luana Topalli', 'luana.topalli@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Aktiv', 1, '2026-02-24 22:09:13'),
(10, 'Fatjon Muça', 'fatjon.muca@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vullnetar', 'Bllokuar', 1, '2026-02-24 22:09:13');

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
  ADD UNIQUE KEY `uq_user_event` (`id_perdoruesi`, `id_eventi`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`),
  ADD KEY `id_eventi` (`id_eventi`),
  ADD KEY `idx_statusi` (`statusi`);

--
-- Indexes for table `aplikimi_kerkese`
--
ALTER TABLE `aplikimi_kerkese`
  ADD PRIMARY KEY (`id_aplikimi_kerkese`),
  ADD UNIQUE KEY `uq_user_request` (`id_kerkese_ndihme`, `id_perdoruesi`),
  ADD KEY `id_kerkese_ndihme` (`id_kerkese_ndihme`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`),
  ADD KEY `idx_statusi_kerkese` (`statusi`);

--
-- Indexes for table `eventi`
--
ALTER TABLE `eventi`
  ADD PRIMARY KEY (`id_eventi`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`),
  ADD KEY `id_kategoria` (`id_kategoria`),
  ADD KEY `idx_data` (`data`);

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
  ADD KEY `id_perdoruesi` (`id_perdoruesi`),
  ADD KEY `idx_statusi` (`statusi`);

--
-- Indexes for table `njoftimi`
--
ALTER TABLE `njoftimi`
  ADD PRIMARY KEY (`id_njoftimi`),
  ADD KEY `id_perdoruesi` (`id_perdoruesi`),
  ADD KEY `idx_is_read` (`is_read`);

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
-- AUTO_INCREMENT for table `aplikimi_kerkese`
--
ALTER TABLE `aplikimi_kerkese`
  MODIFY `id_aplikimi_kerkese` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eventi`
--
ALTER TABLE `eventi`
  MODIFY `id_eventi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  ADD CONSTRAINT `aplikimi_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`) ON DELETE CASCADE,
  ADD CONSTRAINT `aplikimi_ibfk_2` FOREIGN KEY (`id_eventi`) REFERENCES `eventi` (`id_eventi`) ON DELETE CASCADE;

--
-- Constraints for table `aplikimi_kerkese`
--
ALTER TABLE `aplikimi_kerkese`
  ADD CONSTRAINT `aplikimi_kerkese_ibfk_1` FOREIGN KEY (`id_kerkese_ndihme`) REFERENCES `kerkesa_per_ndihme` (`id_kerkese_ndihme`) ON DELETE CASCADE,
  ADD CONSTRAINT `aplikimi_kerkese_ibfk_2` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`) ON DELETE CASCADE;

--
-- Constraints for table `eventi`
--
ALTER TABLE `eventi`
  ADD CONSTRAINT `eventi_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`) ON DELETE SET NULL,
  ADD CONSTRAINT `eventi_ibfk_2` FOREIGN KEY (`id_kategoria`) REFERENCES `kategoria` (`id_kategoria`) ON DELETE SET NULL;

--
-- Constraints for table `kerkesa_per_ndihme`
--
ALTER TABLE `kerkesa_per_ndihme`
  ADD CONSTRAINT `kerkesa_per_ndihme_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`) ON DELETE CASCADE;

--
-- Constraints for table `njoftimi`
--
ALTER TABLE `njoftimi`
  ADD CONSTRAINT `njoftimi_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`) ON DELETE CASCADE;

--
-- Constraints for table `raporti`
--
ALTER TABLE `raporti`
  ADD CONSTRAINT `raporti_ibfk_1` FOREIGN KEY (`id_perdoruesi`) REFERENCES `perdoruesi` (`id_perdoruesi`) ON DELETE CASCADE;
COMMIT;

-- --------------------------------------------------------
--
-- Table structure for table `rate_limit_log`
--
CREATE TABLE IF NOT EXISTS `rate_limit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  INDEX `idx_ip_action` (`ip`, `action`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
-- Migration: Event capacity, lifecycle, waitlist, presence, notifications, audit log
-- --------------------------------------------------------

-- FIX 1: Event capacity
ALTER TABLE `eventi` ADD COLUMN `kapaciteti` INT NULL AFTER `pershkrimi`;

-- FIX 2: Event lifecycle statuses
ALTER TABLE `eventi` ADD COLUMN `statusi` ENUM('active','completed','cancelled') DEFAULT 'active' AFTER `banner`;
ALTER TABLE `eventi` ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0 AFTER `statusi`;
UPDATE `eventi` SET `statusi` = 'active' WHERE `statusi` IS NULL OR `statusi` = '';

-- FIX 1: Waitlist flag on applications
ALTER TABLE `aplikimi` ADD COLUMN `ne_liste_pritje` TINYINT(1) DEFAULT 0 AFTER `statusi`;

-- FIX 3: Presence tracking (extended statusi enum)
ALTER TABLE `aplikimi` MODIFY COLUMN `statusi` ENUM('Në pritje','Pranuar','Refuzuar','Prezent','Munguar') DEFAULT 'Në pritje';

-- FIX 5: Notification type/link columns
ALTER TABLE `njoftimi` ADD COLUMN `tipi` VARCHAR(30) NULL,
                       ADD COLUMN `target_type` VARCHAR(30) NULL,
                       ADD COLUMN `target_id` INT NULL,
                       ADD COLUMN `linku` VARCHAR(500) NULL;

-- FIX 4: Admin audit log table
CREATE TABLE IF NOT EXISTS `admin_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `admin_id` INT NOT NULL,
  `veprim` VARCHAR(50) NOT NULL,
  `target_type` VARCHAR(30) NULL,
  `target_id` INT NULL,
  `detaje` TEXT NULL,
  `krijuar_me` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_admin_action` (`admin_id`, `veprim`),
  INDEX `idx_target` (`target_type`, `target_id`),
  FOREIGN KEY (`admin_id`) REFERENCES `perdoruesi` (`id_perdoruesi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Migration: Standardize all status/role/type values to English
-- --------------------------------------------------------

-- Aplikimi: statusi enum Albanian → English
ALTER TABLE `aplikimi` MODIFY COLUMN `statusi`
  ENUM('pending','approved','rejected','present','absent') DEFAULT 'pending';
UPDATE `aplikimi` SET `statusi` = CASE `statusi`
  WHEN 'Në pritje' THEN 'pending'
  WHEN 'Pranuar'   THEN 'approved'
  WHEN 'Refuzuar'  THEN 'rejected'
  WHEN 'Prezent'   THEN 'present'
  WHEN 'Munguar'   THEN 'absent'
  ELSE `statusi`
END WHERE `statusi` IN ('Në pritje','Pranuar','Refuzuar','Prezent','Munguar');

-- Aplikimi_Kerkese: statusi enum Albanian → English
ALTER TABLE `aplikimi_kerkese` MODIFY COLUMN `statusi`
  ENUM('pending','approved','rejected') DEFAULT 'pending';
UPDATE `aplikimi_kerkese` SET `statusi` = CASE `statusi`
  WHEN 'Në pritje' THEN 'pending'
  WHEN 'Pranuar'   THEN 'approved'
  WHEN 'Refuzuar'  THEN 'rejected'
  ELSE `statusi`
END WHERE `statusi` IN ('Në pritje','Pranuar','Refuzuar');

-- Perdoruesi: roli enum Albanian → English
ALTER TABLE `perdoruesi` MODIFY COLUMN `roli`
  ENUM('admin','volunteer') DEFAULT 'volunteer';
UPDATE `perdoruesi` SET `roli` = CASE `roli`
  WHEN 'Admin'     THEN 'admin'
  WHEN 'Vullnetar' THEN 'volunteer'
  ELSE `roli`
END WHERE `roli` IN ('Admin','Vullnetar');

-- Perdoruesi: statusi_llogarise enum Albanian → English
ALTER TABLE `perdoruesi` MODIFY COLUMN `statusi_llogarise`
  ENUM('active','blocked','deactivated') DEFAULT 'active';
UPDATE `perdoruesi` SET `statusi_llogarise` = CASE `statusi_llogarise`
  WHEN 'Aktiv'       THEN 'active'
  WHEN 'Bllokuar'    THEN 'blocked'
  WHEN 'Çaktivizuar' THEN 'deactivated'
  ELSE `statusi_llogarise`
END WHERE `statusi_llogarise` IN ('Aktiv','Bllokuar','Çaktivizuar');

-- Kerkesa_per_Ndihme: tipi enum Albanian → English
ALTER TABLE `kerkesa_per_ndihme` MODIFY COLUMN `tipi`
  ENUM('request','offer') DEFAULT NULL;
UPDATE `kerkesa_per_ndihme` SET `tipi` = CASE `tipi`
  WHEN 'Kërkesë' THEN 'request'
  WHEN 'Ofertë'  THEN 'offer'
  ELSE `tipi`
END WHERE `tipi` IN ('Kërkesë','Ofertë');

-- --------------------------------------------------------
-- Migration: Add updated_at timestamp to main tables
-- --------------------------------------------------------

ALTER TABLE `eventi`
  ADD COLUMN `ndryshuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `aplikimi`
  ADD COLUMN `ndryshuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `aplikimi_kerkese`
  ADD COLUMN `ndryshuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `perdoruesi`
  ADD COLUMN `ndryshuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `kerkesa_per_ndihme`
  ADD COLUMN `ndryshuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `njoftimi`
  ADD COLUMN `ndryshuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- --------------------------------------------------------
-- Migration: Email queue with retry
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `to_email` VARCHAR(255) NOT NULL,
  `to_name` VARCHAR(255) NOT NULL DEFAULT '',
  `subject` VARCHAR(500) NOT NULL,
  `body_html` MEDIUMTEXT NOT NULL,
  `body_text` TEXT DEFAULT NULL,
  `status` ENUM('pending','sent','failed') DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED DEFAULT 3,
  `last_error` TEXT DEFAULT NULL,
  `next_retry_at` TIMESTAMP NULL DEFAULT NULL,
  `krijuar_me` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_status_retry` (`status`, `next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Migration: Standardize kerkesa_per_ndihme.statusi to lowercase English
-- (missed in original FIX 1 migration)
-- --------------------------------------------------------

ALTER TABLE `kerkesa_per_ndihme` MODIFY COLUMN `statusi`
  ENUM('open','closed') DEFAULT 'open';
UPDATE `kerkesa_per_ndihme` SET `statusi` = CASE `statusi`
  WHEN 'Open'   THEN 'open'
  WHEN 'Closed' THEN 'closed'
  ELSE `statusi`
END WHERE `statusi` IN ('Open','Closed');

-- --------------------------------------------------------
-- Migration: In-App Messaging (Mesazhi)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `Mesazhi` (
  `id_mesazhi` INT NOT NULL AUTO_INCREMENT,
  `derguesi_id` INT NOT NULL,
  `marruesi_id` INT NOT NULL,
  `mesazhi` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `krijuar_me` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mesazhi`),
  INDEX `idx_derguesi` (`derguesi_id`),
  INDEX `idx_marruesi` (`marruesi_id`),
  INDEX `idx_thread` (`derguesi_id`, `marruesi_id`, `krijuar_me`),
  INDEX `idx_unread` (`marruesi_id`, `is_read`),
  FOREIGN KEY (`derguesi_id`) REFERENCES `Perdoruesi` (`id_perdoruesi`) ON DELETE CASCADE,
  FOREIGN KEY (`marruesi_id`) REFERENCES `Perdoruesi` (`id_perdoruesi`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Mesazhet ndërmjet përdoruesve';

-- --------------------------------------------------------
-- Migration: Add category support to help requests
-- --------------------------------------------------------

ALTER TABLE `kerkesa_per_ndihme`
  ADD COLUMN `id_kategoria` INT DEFAULT NULL AFTER `id_perdoruesi`,
  ADD KEY `idx_kategoria` (`id_kategoria`),
  ADD CONSTRAINT `kerkesa_ndihme_kategoria_fk` FOREIGN KEY (`id_kategoria`) REFERENCES `kategoria` (`id_kategoria`) ON DELETE SET NULL;

-- Assign categories to existing seed requests
UPDATE `kerkesa_per_ndihme` SET `id_kategoria` = 2 WHERE `id_kerkese_ndihme` IN (1,2,9,10);
UPDATE `kerkesa_per_ndihme` SET `id_kategoria` = 3 WHERE `id_kerkese_ndihme` IN (3,6,7,8);
UPDATE `kerkesa_per_ndihme` SET `id_kategoria` = 5 WHERE `id_kerkese_ndihme` = 4;
UPDATE `kerkesa_per_ndihme` SET `id_kategoria` = 4 WHERE `id_kerkese_ndihme` = 5;

-- --------------------------------------------------------
-- Migration: Platform branding and organization onboarding
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('organization_name', 'Tirana Solidare'),
('hero_badge', 'Platforma Zyrtare e Vullnetarizmit — Tirana Solidare'),
('hero_title', 'Bashkohu me komunitetin\nqë ndryshon jetë'),
('hero_subtitle', 'Së bashku mund të bëjmë më shumë. Ndihmo dikë sot dhe bëhu ndryshimi që dëshiron të shohësh.'),
('footer_blurb', 'Ne besojmë se çdo akt i vogël mirësie ka fuqinë të ndryshojë jetën e dikujt. Platforma jonë është krijuar për të afruar njerëzit dhe për të ndërtuar një komunitet më të kujdesshëm dhe mbështetës.'),
('contact_phone', '+355 69 123 4567'),
('contact_address', 'Bashkia Tiranë, Tiranë'),
('theme_primary', '#00715D'),
('theme_accent', '#E17254')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

CREATE TABLE IF NOT EXISTS `organization_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `applicant_user_id` int(11) NOT NULL,
  `organization_name` varchar(160) NOT NULL,
  `contact_name` varchar(120) NOT NULL,
  `contact_email` varchar(160) NOT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  `reviewed_by_user_id` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_org_app_status` (`status`),
  KEY `idx_org_app_user` (`applicant_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `perdoruesi`
  ADD COLUMN IF NOT EXISTS `organization_name` varchar(160) DEFAULT NULL AFTER `guardian_consent_verified_at`;

ALTER TABLE `perdoruesi`
  ADD COLUMN IF NOT EXISTS `organization_website` varchar(255) DEFAULT NULL AFTER `organization_name`;

ALTER TABLE `perdoruesi`
  ADD COLUMN IF NOT EXISTS `organization_phone` varchar(40) DEFAULT NULL AFTER `organization_website`;

ALTER TABLE `perdoruesi`
  ADD COLUMN IF NOT EXISTS `organization_description` text DEFAULT NULL AFTER `organization_phone`;

ALTER TABLE `perdoruesi` MODIFY COLUMN `roli`
  VARCHAR(30) NOT NULL DEFAULT 'volunteer';
UPDATE `perdoruesi` SET `roli` = CASE LOWER(`roli`)
  WHEN 'admin' THEN 'admin'
  WHEN 'super admin' THEN 'super_admin'
  WHEN 'super_admin' THEN 'super_admin'
  WHEN 'organizator' THEN 'organizer'
  WHEN 'organizer' THEN 'organizer'
  ELSE 'volunteer'
END;
ALTER TABLE `perdoruesi` MODIFY COLUMN `roli`
  ENUM('admin','volunteer','super_admin','organizer') NOT NULL DEFAULT 'volunteer';

ALTER TABLE `eventi`
  ADD COLUMN IF NOT EXISTS `statusi` varchar(30) NOT NULL DEFAULT 'active' AFTER `banner`;

ALTER TABLE `eventi`
  ADD COLUMN IF NOT EXISTS `is_archived` tinyint(1) NOT NULL DEFAULT 0 AFTER `statusi`;

UPDATE `eventi` SET `statusi` = 'active' WHERE `statusi` IS NULL OR `statusi` = '';
ALTER TABLE `eventi` MODIFY COLUMN `statusi`
  ENUM('active','completed','cancelled','pending_review') NOT NULL DEFAULT 'active';
