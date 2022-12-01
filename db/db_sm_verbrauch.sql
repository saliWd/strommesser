SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `device` varchar(8) NOT NULL,
  `post_key` varchar(32) NOT NULL,
  `email` varchar(127) NOT NULL,
  `lastLogin` timestamp NOT NULL DEFAULT current_timestamp(),
  `pwHash` char(255) NOT NULL,
  `randCookie` char(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `verbrauch` (
  `id` bigint(20) NOT NULL,
  `device` varchar(8) DEFAULT NULL,
  `consumption` decimal(10,3) NOT NULL,
  `consDiff` decimal(10,3) NOT NULL,
  `aveConsDiff` double NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zeitDiff` int(11) NOT NULL,
  `aveZeitDiff` double NOT NULL,
  `thin` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `verbrauch`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `verbrauch`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
