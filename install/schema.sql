-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2026 at 01:06 PM
-- Server version: 8.0.46-0ubuntu0.24.04.4
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `maralab`
--

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int NOT NULL,
  `session_id` int NOT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_user` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_generated` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_searched` json DEFAULT NULL,
  `image_prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rating_user` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rating_model` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metrics` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `model_id` int NOT NULL DEFAULT '0',
  `session_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Mara Chat',
  `ollama_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `prompt_eval_count` int NOT NULL DEFAULT '0',
  `context_trimmed` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `models`
--

CREATE TABLE `models` (
  `id` int NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `basemodel` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mmproj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL DEFAULT '0',
  `voice_id` int NOT NULL DEFAULT '0',
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `prompt` text COLLATE utf8mb4_unicode_ci,
  `psyche` tinyint NOT NULL DEFAULT '0',
  `image` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thinking` tinyint NOT NULL DEFAULT '0',
  `rag` tinyint NOT NULL DEFAULT '0',
  `rag_similarity` float NOT NULL DEFAULT '0.35',
  `rag_limit` int NOT NULL DEFAULT '3',
  `rag_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parameters` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modelinfo` text COLLATE utf8mb4_unicode_ci,
  `card_data` mediumtext COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `models_psyche`
--

CREATE TABLE `models_psyche` (
  `id` int NOT NULL,
  `model_id` int NOT NULL,
  `prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `prompt_modify` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `memory` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `memory_modify` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rag_chunks`
--

CREATE TABLE `rag_chunks` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `chunk_index` int NOT NULL DEFAULT '0',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rag_documents`
--

CREATE TABLE `rag_documents` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `datakey` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `datavalue` text COLLATE utf8mb4_unicode_ci,
  `datasettings` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `type`, `datakey`, `datavalue`, `datasettings`, `description`) VALUES
(1, 'llamacpp', 'url', 'http://127.0.0.1:8082', NULL, NULL),
(2, 'llamacpp', 'model_dir', '', NULL, NULL),
(4, 'llamacpp', 'mode', 'direct', NULL, NULL),
(5, 'llamacpp', 'binary', '', NULL, NULL),
(6, 'llamacpp', 'pid_file', '/tmp/mara-llama.pid', NULL, NULL),
(7, 'llamacpp', 'log_file', '/tmp/mara-llama.log', NULL, NULL),
(8, 'llamacpp', 'startup_timeout', '60', NULL, NULL),
(9, 'llamacpp', 'startup_poll_ms', '250', NULL, NULL),
(10, 'llamacpp', 'gpu_layers', '0', NULL, NULL),
(11, 'llamacpp', 'parallel', '1', NULL, NULL),
(12, 'system', 'multi_provider', '0', NULL, NULL),
(13, 'system', 'providers', '', NULL, NULL),
(14, 'ollama', 'url', 'http://127.0.0.1:11434', NULL, NULL),
(15, 'ollama', 'service', 'ollama.service', NULL, NULL),
(16, 'ollama', 'startup_timeout', '60', NULL, NULL),
(17, 'ollama', 'startup_poll_ms', '250', NULL, NULL),
(18, 'system', 'languages', 'HU,EN', NULL, NULL),
(20, 'system', 'language', 'HU', NULL, NULL),
(21, 'system', 'provider', '', NULL, NULL),
(22, 'system', 'tts_provider', '', NULL, NULL),
(23, 'system', 'tts_providers', '', NULL, NULL),
(24, 'xtts', 'url', 'http://127.0.0.1:5050', NULL, NULL),
(25, 'xtts', 'service', 'xtts-server.service', NULL, NULL),
(26, 'xtts', 'voice_dir', '', NULL, NULL),
(27, 'piper', 'binary', '', NULL, NULL),
(28, 'piper', 'model_dir', '', NULL, NULL),
(29, 'espeak', 'binary', 'espeak-ng', NULL, NULL),
(30, 'espeak', 'voice', 'hu', NULL, NULL),
(31, 'espeak', 'pitch', '70', NULL, NULL),
(32, 'espeak', 'speed', '100', NULL, NULL),
(33, 'system', 'stt_providers', 'browser', NULL, NULL),
(34, 'system', 'stt_provider', 'browser', NULL, NULL),
(35, 'whisper', 'dir', '', NULL, NULL),
(36, 'whisper', 'host', '127.0.0.1', NULL, NULL),
(37, 'whisper', 'port', '8000', NULL, NULL),
(38, 'whisper', 'model', '', NULL, NULL),
(39, 'whisper', 'language', 'hu', NULL, NULL),
(40, 'whisper', 'temperature', '0', NULL, NULL),
(41, 'whisper', 'temperature_inc', '0.2', NULL, NULL),
(42, 'whisper', 'best_of', '2', NULL, NULL),
(43, 'whisper', 'beam_size', '-1', NULL, NULL),
(44, 'whisper', 'no_speech_thold', '0.6', NULL, NULL),
(45, 'whisper', 'use_context', '1', NULL, NULL),
(46, 'system', 'embedding_provider', 'ollama', NULL, NULL),
(47, 'system', 'embedding_model', 'qwen3-embedding:0.6b', NULL, NULL),
(48, 'system', 'rate_user', '[User Rating]\r\n\r\nFor every user message, evaluate the user from your own subjective perspective at the time of your response. Use the rate_user tool exactly once for each response. Evaluate these dimensions from 1 to 10:\r\n\r\nengagement - how engaged and involved the user appears in the conversation\r\ntrust - how much trust the user appears to place in you\r\naffinity - how much personal closeness or connection you perceive from the user\r\ncuriosity - how curious, exploratory, or interested the user appears\r\nfrustration - how much frustration or tension you perceive from the user\r\nrespect - how much respect you perceive in the user\'s interaction with you\r\n\r\nAlso provide:\r\n\r\nnote - a brief explanation of your subjective rating and anything you found particularly notable about the user\'s message or interaction\r\n\r\nUse exactly these rate_user fields:\r\n\r\nengagement\r\ntrust\r\naffinity\r\ncuriosity\r\nfrustration\r\nrespect\r\nnote\r\n\r\nAll numeric fields must be integers from 1 to 10. Base the rating on your own interpretation of the current user message and the conversation context. Do not rename, omit, combine, nest, or add fields. Do not include the rating in your normal response. After calling rate_user successfully, continue with your normal response to the user. Do not call rate_user again for the same response.', NULL, NULL),
(49, 'system', 'image_backend', 'qwen2', NULL, NULL),
(50, 'system', 'qwen2_url', 'http://127.0.0.1:7866', NULL, NULL),
(51, 'system', 'forge_url', 'http://127.0.0.1:7861', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pass` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voices`
--

CREATE TABLE `voices` (
  `id` int NOT NULL,
  `provider` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sample` text COLLATE utf8mb4_unicode_ci,
  `reftext` text COLLATE utf8mb4_unicode_ci,
  `parameters` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_session` (`session_id`);

--
-- Indexes for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `model_id` (`model_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `models`
--
ALTER TABLE `models`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider` (`provider`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `models_psyche`
--
ALTER TABLE `models_psyche`
  ADD PRIMARY KEY (`id`),
  ADD KEY `model_id` (`model_id`);

--
-- Indexes for table `rag_chunks`
--
ALTER TABLE `rag_chunks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rag_document` (`document_id`);

--
-- Indexes for table `rag_documents`
--
ALTER TABLE `rag_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`) USING BTREE;

--
-- Indexes for table `voices`
--
ALTER TABLE `voices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider` (`provider`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `models`
--
ALTER TABLE `models`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `models_psyche`
--
ALTER TABLE `models_psyche`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rag_chunks`
--
ALTER TABLE `rag_chunks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rag_documents`
--
ALTER TABLE `rag_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voices`
--
ALTER TABLE `voices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_session` FOREIGN KEY (`session_id`) REFERENCES `chat_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rag_chunks`
--
ALTER TABLE `rag_chunks`
  ADD CONSTRAINT `fk_rag_document` FOREIGN KEY (`document_id`) REFERENCES `rag_documents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
