DROP DATABASE Verztec;
CREATE DATABASE IF NOT EXISTS Verztec;
USE Verztec;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


-- COUNTRIES TABLE
CREATE TABLE `countries` (
    `country_id` int(11) AUTO_INCREMENT PRIMARY KEY NOT NULL,
    `country` varchar(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `countries` (`country_id`, `country`) VALUES
(1, 'Singapore');


-- USERS TABLE
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `email` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `role` enum('ADMIN','MANAGER','USER','') NOT NULL,
  `country` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `department`, `role`, `country`) VALUES
(1, 'char', '$2y$10$SfzkreVOAas3juveXaTZOeGPsHrNqg7fyUkkBSm0CCbqbkD5Qdzde', 'chuacharmaine648@gmail.com', 'IT', 'ADMIN', 'Singapore'),
(2, 'julia', '$2y$10$SfzkreVOAas3juveXaTZOeGPsHrNqg7fyUkkBSm0CCbqbkD5Qdzde', 'chewjulia1415@gmail.com', 'IT', 'ADMIN', 'Singapore'),
(3, 'rumaisa', '$2y$10$SfzkreVOAas3juveXaTZOeGPsHrNqg7fyUkkBSm0CCbqbkD5Qdzde', 'azzyy.rrafiudeen@gmail.com', 'IT', 'ADMIN', 'Singapore'),
(4, 'kai xin', '$2y$10$SfzkreVOAas3juveXaTZOeGPsHrNqg7fyUkkBSm0CCbqbkD5Qdzde', 'happykxin@gmail.com', 'IT', 'ADMIN', 'Singapore');


ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;



-- AUDIT LOG TABLE
CREATE TABLE `audit_log` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY NOT NULL,
  `user_id` INT,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `details` TEXT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- FILES TABLE
CREATE TABLE `files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(100) NOT NULL,      
  `file_size` BIGINT NOT NULL,         
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- FILE VISIBILITY TABLE
CREATE TABLE `file_visibility` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file_id` INT NOT NULL,
  `visibility_scope` ENUM('DEPARTMENT', 'COUNTRY', 'ALL') NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE
);

-- ANNOUNCEMENTS TABLE
CREATE TABLE `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `context` text,
  `target_audience` varchar(255) DEFAULT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- INSERT SAMPLE INTO ANNOUNCEMENTS TABLE
INSERT INTO `announcements` (`title`, `context`, `target_audience`, `priority`) VALUES
('System Maintenance', 'The platform will undergo maintenance from 10 PM to 12 AM tonight.', 'Everyone', 'High'),
('New Feature Released', 'Document tagging is now available under the files section!', 'Users', 'Medium');


COMMIT;
