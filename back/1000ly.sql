-- phpMyAdmin SQL Dump
-- version 4.6.2
-- https://www.phpmyadmin.net/
--
-- Client :  h2mysql5
-- Généré le :  Lun 22 Août 2016 à 19:18
-- Version du serveur :  5.5.49-log
-- Version de PHP :  5.6.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `cole_1000lightyears`
--

-- --------------------------------------------------------

--
-- Structure de la table `decks`
--

CREATE TABLE `decks` (
  `idDeck` int(11) NOT NULL,
  `cards` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `games`
--

CREATE TABLE `games` (
  `idGame` int(11) NOT NULL,
  `nbPlayers` int(11) NOT NULL,
  `activePlayerIndex` int(11) NOT NULL,
  `log` text NOT NULL,
  `gameFields` text NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `startedAt` datetime NOT NULL,
  `finishedAt` datetime NOT NULL,
  `aborted_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `idDeck` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `started` tinyint(1) NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL,
  `idWinner` int(11) NOT NULL,
  `difficulty` varchar(12) NOT NULL,
  `ongoingCoup` tinyint(1) NOT NULL,
  `distanceGoal` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `participations`
--

CREATE TABLE `participations` (
  `idGame` int(11) NOT NULL,
  `idPlayer` int(11) NOT NULL,
  `isActive` int(1) NOT NULL DEFAULT '1',
  `lastPing` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `joined_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `left_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `hand` text NOT NULL,
  `gameField` text NOT NULL,
  `score` int(11) NOT NULL,
  `distance` int(11) NOT NULL,
  `playerIndex` int(11) NOT NULL,
  `isBot` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `players`
--

CREATE TABLE `players` (
  `idPlayer` int(11) NOT NULL,
  `hashPlayer` varchar(64) NOT NULL,
  `nmPlayer` varchar(32) NOT NULL,
  `emailPlayer` varchar(255) NOT NULL,
  `descPlayer` text NOT NULL,
  `joinedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `victories` int(11) NOT NULL DEFAULT '0',
  `forfeits` int(11) NOT NULL DEFAULT '0',
  `totalPlayed` int(11) NOT NULL DEFAULT '0',
  `password` varchar(64) NOT NULL,
  `totalDistance` int(11) NOT NULL DEFAULT '0',
  `totalScore` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Index pour les tables exportées
--

--
-- Index pour la table `decks`
--
ALTER TABLE `decks`
  ADD PRIMARY KEY (`idDeck`);

--
-- Index pour la table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`idGame`);

--
-- Index pour la table `participations`
--
ALTER TABLE `participations`
  ADD PRIMARY KEY (`idGame`,`idPlayer`);

--
-- Index pour la table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`idPlayer`),
  ADD KEY `idPlayer` (`idPlayer`);

--
-- AUTO_INCREMENT pour les tables exportées
--

--
-- AUTO_INCREMENT pour la table `decks`
--
ALTER TABLE `decks`
  MODIFY `idDeck` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;
--
-- AUTO_INCREMENT pour la table `games`
--
ALTER TABLE `games`
  MODIFY `idGame` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;
--
-- AUTO_INCREMENT pour la table `players`
--
ALTER TABLE `players`
  MODIFY `idPlayer` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1006;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
