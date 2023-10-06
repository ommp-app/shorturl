--
-- Online Module Management Platform
-- 
-- SQL installation file for shorturl module
-- 
-- Author: The OMMP Team
-- Version: 1.0
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create the short urls table
DROP TABLE IF EXISTS `{PREFIX}shorturl`;
CREATE TABLE IF NOT EXISTS `{PREFIX}shorturl` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` int(11) NOT NULL,
  `target` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `creation_ts` int(11) NOT NULL,
  `edit_ts` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the visits history table
DROP TABLE IF EXISTS `{PREFIX}shorturl_visits`;
CREATE TABLE IF NOT EXISTS `{PREFIX}shorturl_visits` (
  `link_id` int(11) NOT NULL,
  `ip` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` int(11) NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `referrer` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;