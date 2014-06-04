-- Adminer 4.0.2 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = '+01:00';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP DATABASE IF EXISTS flunorette_blog;
CREATE DATABASE IF NOT EXISTS flunorette_blog;
USE flunorette_blog;

DROP TABLE IF EXISTS `article`;
CREATE TABLE `article` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `published_at` datetime NOT NULL,
  `title` varchar(100) NOT NULL DEFAULT '',
  `content` text NOT NULL,
  `approver` int(11) unsigned NOT NULL COMMENT 'who allowed article to be public',
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_article_user1_idx` (`approver`),
  KEY `fk_article_categories1_idx` (`category_id`),
  CONSTRAINT `article_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_article_user1` FOREIGN KEY (`approver`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_article_categories1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `article` (`id`, `user_id`, `published_at`, `title`, `content`, `approver`, `category_id`) VALUES
(1,	2,	'2011-12-10 12:10:00',	'article 1',	'content 1',	1,	1),
(2,	3,	'2011-12-20 16:20:00',	'article 2',	'content 2',	1,	2),
(3,	4,	'2012-01-04 22:00:00',	'article 3',	'content 3',	1,	1),
(4,	2,	'2014-02-01 18:30:00',	'article 4',	'content 4',	1,	2),
(5,	1,	'2014-01-30 11:45:00',	'article 5',	'content 5',	2,	1);

DROP TABLE IF EXISTS `article_tag`;
CREATE TABLE `article_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) unsigned NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_article_tag` (`article_id`,`tag_id`),
  KEY `fk_article_tag_tag1_idx` (`tag_id`),
  KEY `fk_article_tag_article1_idx` (`article_id`),
  CONSTRAINT `fk_article_tag_article1` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_article_tag_tag1` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `article_tag` (`id`, `article_id`, `tag_id`) VALUES
(1,	1,	1),
(2,	1,	3),
(4,	2,	1),
(3,	2,	2),
(5,	2,	6),
(6,	3,	1),
(7,	3,	4),
(8,	3,	6),
(9,	5,	5);

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `categories` (`id`, `name`) VALUES
(1,	'news'),
(2,	'tutorials');

DROP TABLE IF EXISTS `city`;
CREATE TABLE `city` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `region_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_city_region1_idx` (`region_id`),
  CONSTRAINT `fk_city_region1` FOREIGN KEY (`region_id`) REFERENCES `region` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `city` (`id`, `name`, `region_id`) VALUES
(1,	'Praha',	1),
(2,	'Brno',	2),
(3,	'Bratislava',	3),
(4,	'Mělník',	1),
(5,	'Znojmo',	2);

DROP TABLE IF EXISTS `comment`;
CREATE TABLE `comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `content` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comment_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`),
  CONSTRAINT `comment_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `comment` (`id`, `article_id`, `user_id`, `content`) VALUES
(1,	1,	2,	'comment 1.1'),
(2,	1,	1,	'comment 1.2'),
(3,	2,	1,	'comment 2.1');

DROP TABLE IF EXISTS `country`;
CREATE TABLE `country` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `country` (`id`, `name`) VALUES
(1,	'Czech Republic'),
(2,	'Slovakia');

DROP TABLE IF EXISTS `region`;
CREATE TABLE `region` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `country_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_region_country1_idx` (`country_id`),
  CONSTRAINT `fk_region_country1` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `region` (`id`, `name`, `country_id`) VALUES
(1,	'Středočeský',	1),
(2,	'Jihomoravský',	1),
(3,	'Bratislavský',	2);

DROP TABLE IF EXISTS `tag`;
CREATE TABLE `tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `created_by` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `fk_tag_user1_idx` (`created_by`),
  CONSTRAINT `fk_tag_user1` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `tag` (`id`, `name`, `created_by`) VALUES
(1,	'php',	2),
(2,	'notorm',	3),
(3,	'nette',	2),
(4,	'fluent pdo',	4),
(5,	'flunorette',	1),
(6,	'mysql',	3);

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_by` int(11) unsigned NOT NULL,
  `type` enum('admin','author') NOT NULL,
  `name` varchar(20) NOT NULL,
  `city_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_user_user1_idx` (`created_by`),
  KEY `fk_user_city1_idx` (`city_id`),
  CONSTRAINT `fk_user_user1` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_city1` FOREIGN KEY (`city_id`) REFERENCES `city` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id`, `created_by`, `type`, `name`, `city_id`) VALUES
(1,	0,	'admin',	'Dan',	1),
(2,	1,	'author',	'David',	2),
(3,	1,	'author',	'Jakub',	1),
(4,	1,	'author',	'Marek',	3);

SET foreign_key_checks = 1;
-- 2014-02-07 14:16:49
