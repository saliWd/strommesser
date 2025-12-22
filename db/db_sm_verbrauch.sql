SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `kunden` (
  `id` int(10) UNSIGNED NOT NULL,
  `description` varchar(63) NOT NULL,
  `post_key` varchar(32) NOT NULL,
  `email` varchar(127) NOT NULL,
  `lastLogin` timestamp NOT NULL DEFAULT current_timestamp(),
  `pwHash` char(255) NOT NULL,
  `randCookie` char(64) NOT NULL,
  `ledMinValCon` smallint(5) UNSIGNED NOT NULL DEFAULT 405,
  `ledMaxValGen` smallint(5) UNSIGNED NOT NULL DEFAULT 2000,
  `ledBrightness` tinyint(3) UNSIGNED NOT NULL DEFAULT 160,
  `priceConsHt` decimal(5,4) NOT NULL,
  `priceConsNt` decimal(5,4) NOT NULL,
  `priceGen` decimal(5,4) NOT NULL,
  `paidUntil` date NOT NULL,
  `local_ip` varchar(15) NOT NULL,
  `rateConW` decimal(5,4) NOT NULL,
  `rateConS` decimal(5,4) NOT NULL,
  `rateGenW` decimal(5,4) NOT NULL,
  `rateGenS` decimal(5,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `pico_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `loopCount` bigint(20) NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `thin` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `pwForgot` (
  `id` int(10) UNSIGNED NOT NULL,
  `userid` int(11) NOT NULL,
  `hexval` char(64) NOT NULL,
  `validUntil` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `status` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `ok` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `verbrauch` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `cons` decimal(10,3) NOT NULL,
  `consDiff` decimal(10,3) NOT NULL,
  `consNt` decimal(10,3) NOT NULL,
  `consNtDiff` decimal(10,3) NOT NULL,
  `consHt` decimal(10,3) NOT NULL,
  `consHtDiff` decimal(10,3) NOT NULL,
  `gen` decimal(10,3) NOT NULL,
  `genDiff` decimal(10,3) NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zeitDiff` int(11) NOT NULL,
  `thin` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `verbrauchArchive` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `cons` decimal(10,3) NOT NULL,
  `consDiff` decimal(10,3) NOT NULL,
  `consNt` decimal(10,3) NOT NULL,
  `consNtDiff` decimal(10,3) NOT NULL,
  `consHt` decimal(10,3) NOT NULL,
  `consHtDiff` decimal(10,3) NOT NULL,
  `gen` decimal(10,3) NOT NULL,
  `genDiff` decimal(10,3) NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zeitDiff` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `verbrauch_26` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `con` decimal(10,3) NOT NULL,
  `conDiff` decimal(10,3) NOT NULL,
  `conRate` decimal(10,3) NOT NULL,
  `gen` decimal(10,3) NOT NULL,
  `genDiff` decimal(10,3) NOT NULL,
  `genRate` decimal(10,3) NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zeitDiff` int(11) NOT NULL,
  `thin` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


ALTER TABLE `kunden`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pico_log`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pwForgot`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `status`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `verbrauch`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `verbrauchArchive`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `verbrauch_26`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `kunden`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `pico_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `pwForgot`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `status`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `verbrauch`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `verbrauchArchive`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `verbrauch_26`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
