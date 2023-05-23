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
  `ledMaxValue` smallint(5) UNSIGNED NOT NULL DEFAULT 405,
  `ledBrightness` tinyint(3) UNSIGNED NOT NULL DEFAULT 80
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `pwForgot` (
  `id` int(10) UNSIGNED NOT NULL,
  `userid` int(11) NOT NULL,
  `hexval` char(64) NOT NULL,
  `validUntil` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `verbrauch` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `consumption` decimal(10,3) NOT NULL,
  `consDiff` decimal(10,3) NOT NULL,
  `consNt` decimal(10,3) NOT NULL,
  `consNtDiff` decimal(10,3) NOT NULL,
  `consHt` decimal(10,3) NOT NULL,
  `consHtDiff` decimal(10,3) NOT NULL,
  `gen` decimal(10,3) NOT NULL,
  `genDiff` decimal(10,3) NOT NULL,
  `genNt` decimal(10,3) NOT NULL,
  `genNtDiff` decimal(10,3) NOT NULL,
  `genHt` decimal(10,3) NOT NULL,
  `genHtDiff` decimal(10,3) NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zeitDiff` int(11) NOT NULL,
  `thin` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


ALTER TABLE `kunden`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pwForgot`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `verbrauch`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `kunden`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `pwForgot`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `verbrauch`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
