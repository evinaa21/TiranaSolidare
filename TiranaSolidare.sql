CREATE TABLE `Perdoruesi` (
  `id_perdoruesi` int PRIMARY KEY AUTO_INCREMENT,
  `emri` varchar(100) NOT NULL,
  `email` varchar(150) UNIQUE NOT NULL,
  `fjalekalimi` varchar(255) NOT NULL,
  `roli` ENUM ('Admin', 'Vullnetar') DEFAULT 'Vullnetar',
  `statusi_llogarise` ENUM ('Aktiv', 'Bllokuar') DEFAULT 'Aktiv',
  `krijuar_me` timestamp DEFAULT (now())
);

CREATE TABLE `Njoftimi` (
  `id_njoftimi` int PRIMARY KEY AUTO_INCREMENT,
  `id_perdoruesi` int,
  `mesazhi` text,
  `is_read` boolean DEFAULT false,
  `krijuar_me` timestamp DEFAULT (now())
);

CREATE TABLE `Kategoria` (
  `id_kategoria` int PRIMARY KEY AUTO_INCREMENT,
  `emri` varchar(50) UNIQUE
);

CREATE TABLE `Eventi` (
  `id_eventi` int PRIMARY KEY AUTO_INCREMENT,
  `id_perdoruesi` int,
  `id_kategoria` int,
  `titulli` varchar(200),
  `pershkrimi` text,
  `data` datetime,
  `vendndodhja` varchar(255),
  `banner` varchar(500) COMMENT 'URL to image storage',
  `krijuar_me` timestamp DEFAULT (now())
);

CREATE TABLE `Aplikimi` (
  `id_aplikimi` int PRIMARY KEY AUTO_INCREMENT,
  `id_perdoruesi` int,
  `id_eventi` int,
  `statusi` ENUM ('Në pritje', 'Pranuar', 'Refuzuar') DEFAULT 'Në pritje',
  `aplikuar_me` timestamp DEFAULT (now())
);

CREATE TABLE `Kerkesa_per_Ndihme` (
  `id_kerkese_ndihme` int PRIMARY KEY AUTO_INCREMENT,
  `id_perdoruesi` int,
  `tipi` ENUM ('Kërkesë', 'Ofertë'),
  `titulli` varchar(150),
  `pershkrimi` text,
  `statusi` varchar(50) COMMENT 'Open/Closed',
  `krijuar_me` timestamp DEFAULT (now())
);

CREATE TABLE `Raporti` (
  `id_raporti` int PRIMARY KEY AUTO_INCREMENT,
  `id_perdoruesi` int,
  `tipi_raportit` varchar(50),
  `permbajtja` text,
  `gjeneruar_me` timestamp DEFAULT (now())
);

ALTER TABLE `Perdoruesi` COMMENT = 'Main user table storing auth details';

ALTER TABLE `Aplikimi` COMMENT = 'Junction table for Many-to-Many relationship';

ALTER TABLE `Njoftimi` ADD FOREIGN KEY (`id_perdoruesi`) REFERENCES `Perdoruesi` (`id_perdoruesi`);

ALTER TABLE `Eventi` ADD FOREIGN KEY (`id_perdoruesi`) REFERENCES `Perdoruesi` (`id_perdoruesi`);

ALTER TABLE `Eventi` ADD FOREIGN KEY (`id_kategoria`) REFERENCES `Kategoria` (`id_kategoria`);

ALTER TABLE `Aplikimi` ADD FOREIGN KEY (`id_perdoruesi`) REFERENCES `Perdoruesi` (`id_perdoruesi`);

ALTER TABLE `Aplikimi` ADD FOREIGN KEY (`id_eventi`) REFERENCES `Eventi` (`id_eventi`);

ALTER TABLE `Kerkesa_per_Ndihme` ADD FOREIGN KEY (`id_perdoruesi`) REFERENCES `Perdoruesi` (`id_perdoruesi`);

ALTER TABLE `Raporti` ADD FOREIGN KEY (`id_perdoruesi`) REFERENCES `Perdoruesi` (`id_perdoruesi`);
