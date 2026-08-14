-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2026 at 11:01 AM
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
-- Database: `netplaydb`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `CategoryID` int(11) NOT NULL,
  `CategoryName` varchar(255) NOT NULL,
  `CategoryAgeMin` int(11) DEFAULT NULL,
  `CategoryAgeMax` int(11) DEFAULT NULL,
  `CategoryGender` varchar(255) NOT NULL,
  `CategoryRegistrationFee` float NOT NULL,
  `CategoryTeamSize` varchar(255) NOT NULL,
  `CategoryLevel` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `playerapplications`
--

CREATE TABLE `playerapplications` (
  `ApplicationID` int(11) NOT NULL,
  `TournamentCategoryID` int(11) NOT NULL,
  `PlayerName` varchar(255) NOT NULL,
  `PlayerAge` int(11) NOT NULL,
  `PlayerAddress` varchar(255) NOT NULL,
  `PlayerPhoneNumber` varchar(64) NOT NULL,
  `PlayerLevel` varchar(255) NOT NULL,
  `PlayerEmail` varchar(255) NOT NULL,
  `PlayerPartnerID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `playerpartner`
--

CREATE TABLE `playerpartner` (
  `PlayerPartnerID` int(11) NOT NULL,
  `PartnerName` varchar(255) NOT NULL,
  `PartnerAge` int(11) NOT NULL,
  `PartnerAddress` varchar(255) NOT NULL,
  `PartnerClub` varchar(255) NOT NULL,
  `PlayerApplicationID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament`
--

CREATE TABLE `tournament` (
  `TournamentID` int(11) NOT NULL,
  `TournamentName` varchar(255) NOT NULL,
  `TournamentFormat` varchar(255) NOT NULL,
  `TournamentSport` varchar(255) NOT NULL,
  `TournamentScoringSystem` varchar(255) NOT NULL,
  `TournamentDateStart` date NOT NULL,
  `TournamentDateEnd` date NOT NULL,
  `TournamentSpecificDays` varchar(255) NOT NULL,
  `TournamentRegistrationDateStart` date NOT NULL,
  `TournamentRegistrationEnd` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `UserName` varchar(255) NOT NULL,
  `UserEmail` varchar(255) NOT NULL,
  `UserPassword` varchar(255) NOT NULL,
  `UserRole` varchar(32) NOT NULL,
  `UserPhone` varchar(255) NOT NULL,
  `EmailVerifiedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `statisticsID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`CategoryID`);

--
-- Indexes for table `playerapplications`
--
ALTER TABLE `playerapplications`
  ADD PRIMARY KEY (`ApplicationID`);

--
-- Indexes for table `playerpartner`
--
ALTER TABLE `playerpartner`
  ADD PRIMARY KEY (`PlayerPartnerID`);

--
-- Indexes for table `tournament`
--
ALTER TABLE `tournament`
  ADD PRIMARY KEY (`TournamentID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `playerapplications`
--
ALTER TABLE `playerapplications`
  MODIFY `ApplicationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `playerpartner`
--
ALTER TABLE `playerpartner`
  MODIFY `PlayerPartnerID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament`
--
ALTER TABLE `tournament`
  MODIFY `TournamentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
