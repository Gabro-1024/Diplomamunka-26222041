-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gép: 127.0.0.1
-- Létrehozás ideje: 2025. Sze 17. 21:17
-- Kiszolgáló verziója: 10.4.32-MariaDB
-- PHP verzió: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Adatbázis: `ticketing_system`
--

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `contact`
--

CREATE TABLE `contact` (
  `name` varchar(50) NOT NULL,
  `mail` varchar(60) NOT NULL,
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `slogan` varchar(255) DEFAULT NULL,
  `lineup` text NOT NULL DEFAULT 'No lineup announced yet :(',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `total_tickets` int(11) NOT NULL,
  `remaining_tickets` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `events`
--

INSERT INTO `events` (`id`, `name`, `slogan`, `lineup`, `start_date`, `end_date`, `description`, `cover_image`, `organizer_id`, `venue_id`, `total_tickets`, `remaining_tickets`) VALUES
(19, 'Sziget Festival', 'The Island of Freedom', 'Timmy Trumpet, The Straikerz, Angerfist, Ke$ha', '2026-08-07 10:00:00', '2026-08-13 23:59:59', 'One of Europe\'s largest music and cultural festivals, held on Óbuda Island in Budapest, Hungary. Features a diverse lineup of international and local artists across multiple stages.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-1.jpg', 16, 1, 50000, 0),
(20, 'Balaton Sound', 'The Biggest Lakeside Party', 'David Guetta, Armin van Buuren, Dimitri Vegas & Like Mike', '2026-06-26 12:00:00', '2026-06-30 23:59:59', 'Europe\'s premier open-air electronic music festival held on the shores of Lake Balaton. Known for its stunning location and world-class DJs.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-4.jpg', 16, 2, 35000, 0),
(21, 'VOLT Festival', 'Music. Love. Unity.', 'Imagine Dragons, Arctic Monkeys, Halott Pénz', '2026-06-19 14:00:00', '2026-06-23 23:59:59', 'Hungary\'s most popular music festival featuring a mix of rock, pop, electronic, and world music in the beautiful city of Sopron.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-3.jpg', 16, 3, 30000, 0),
(22, 'EFOTT', 'Hungary\'s Biggest Student Festival', 'Majka & Curtis, Wellhello, Tankcsapda', '2026-07-10 10:00:00', '2026-07-14 23:59:59', 'A week-long festival on the shores of Lake Velence, offering music, sports, and cultural programs for students and young adults.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-5.jpg', 16, 4, 25000, 0),
(23, 'SZIN festival', 'Zárjuk együtt a nyarat 2026-ban is!', 'Rúzsa Magdolna, Follow The Flow, Punnany Massif, Margaret Island, Carson Coma', '2026-08-06 10:00:00', '2026-08-12 23:59:59', 'The 2025 edition of Europe\'s most colorful festival, featuring an even more diverse lineup and exciting new programs.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-6.jpg', 16, 11, 50000, 0),
(24, 'Fishing on Orfű', 'Zenés nyár a Mecsek lábánál', '30Y, Quimby, Bagossy Brothers Company, Blahalouisiana, Péterfy Bori & Love Band', '2026-06-19 12:00:00', '2026-06-22 23:59:59', 'One of Hungary\'s most beloved smaller festivals, held near Pécs by Lake Orfű. Known for its cozy atmosphere, lakeside concerts, and family-friendly programs.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-2.jpg', 16, 12, 15000, 0);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `login_sessions`
--

CREATE TABLE `login_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `login_sessions`
--

INSERT INTO `login_sessions` (`id`, `user_id`, `user_agent`, `session_token`, `expires_at`, `created_at`) VALUES
(1, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '4749ee5e2a52154476bb357a39d384395ae4c9ce2f701950fc94705e8729e054', '2025-10-05 14:36:11', '2025-09-05 12:36:11'),
(2, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '5b0880c41c5bd265b25b2019983098f99892e5c6851567388308ebec81050c64', '2025-10-05 15:24:00', '2025-09-05 13:24:00'),
(3, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '021f41f358fe7b1d31d7d77d357dadd9512ed060f6440f5681c3a15feed3ab06', '2025-10-06 10:02:49', '2025-09-06 08:02:49'),
(4, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', 'e63fc2bb1d9ba6510bd1170f7f0176c8da0649d3c7fe11b11bb34cfdd5bb536d', '2025-10-06 11:19:24', '2025-09-06 09:19:24'),
(5, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '1af2972bce21d44f03bc3867b121cbb540c8310ed4ba360c7021bbbbf31268ba', '2025-10-06 11:32:59', '2025-09-06 09:32:59'),
(6, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', 'c02fbd7855b3e353d4e331950179fa2cd4195d64a1ca7c6815227456b4169e69', '2025-09-07 11:38:00', '2025-09-06 09:38:00'),
(7, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '36e5b239f0cbad9ae887358fc22781e9aa4fe09af006cdfa9841f993a3c4e87c', '2025-09-07 12:10:54', '2025-09-06 10:10:54'),
(8, 18, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', 'd16a63dc43eb0a7092176c3ccb1bc7eb7462fcf875daf7e341c1f944457fc3bb', '2025-09-09 13:30:15', '2025-09-08 11:30:15'),
(9, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '46d99115bfc3873f011a4c69796d7974b38f4ca2bd1e41d8c1a09e0b18bd96fe', '2025-09-09 13:36:10', '2025-09-08 11:36:10'),
(10, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', 'bd109cffdf99d78ab66ff60fda50aaed033da8e031cd4ae97fcbfe256a00857a', '2025-09-15 22:27:06', '2025-09-14 20:27:06'),
(11, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 OPR/120.0.0.0', '183273f334169718f36a2a1347f60fa32f59a4bb4e05030a9bda58f6debbe0aa', '2025-09-15 23:03:24', '2025-09-14 21:03:24'),
(12, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '7402269272b4a7637693947d410eceee1f42b07d26434eaf993ba6cc66130fb4', '2025-09-17 20:17:59', '2025-09-16 18:17:59');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- A tábla adatainak kiíratása `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `created_at`, `expires_at`, `used`) VALUES
(6, 'random.koala497@passinbox.com', '202981623c6aa89d4f52781fcba583c4a7f41e82b0ab66b86d6c8f1fea5992aa', '2025-09-08 13:28:31', '2025-09-08 14:28:31', 1),
(7, 'farkasgabor1024@gmail.com', '2ee16a03a931a4e4f830e9b01498f86516855f324817da11c263aaece477f400', '2025-09-08 13:34:30', '2025-09-08 14:34:30', 1);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('rejected','failed','completed') NOT NULL,
  `purchase_date` datetime DEFAULT current_timestamp(),
  `payment_method` enum('stripe','paypal') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `purchases`
--

INSERT INTO `purchases` (`id`, `user_id`, `amount`, `status`, `purchase_date`, `payment_method`) VALUES
(6, 16, 22470.00, 'completed', '2025-09-17 20:12:26', 'stripe'),
(7, 16, 15980.00, 'completed', '2025-09-17 20:54:49', 'paypal'),
(8, 16, 16470.00, 'completed', '2025-09-17 20:56:29', 'stripe'),
(9, 16, 4990.00, 'completed', '2025-09-17 21:03:38', 'paypal');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `qr_code_path` varchar(255) NOT NULL,
  `event_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `tickets`
--

INSERT INTO `tickets` (`id`, `purchase_id`, `qr_code_path`, `event_id`, `owner_id`, `is_used`, `price`) VALUES
(10, 6, 'worker_sites/qrcodes/cs_test_a1aiiPMeEfrCBpgsqiDBo6M4KebbpOv0S0LUfZT3YI4Yv3trGICECxv4Uw_3ee8736a99f67e55ffea84c926d19281.png', 21, 16, 0, 7490.00),
(11, 6, 'worker_sites/qrcodes/cs_test_a1aiiPMeEfrCBpgsqiDBo6M4KebbpOv0S0LUfZT3YI4Yv3trGICECxv4Uw_27ab4d8a210594de848a418f2b4dbc92.png', 21, 16, 0, 7490.00),
(12, 6, 'worker_sites/qrcodes/cs_test_a1aiiPMeEfrCBpgsqiDBo6M4KebbpOv0S0LUfZT3YI4Yv3trGICECxv4Uw_4518a0fdd0f38f4cb8cd76be3dbb8b23.png', 21, 16, 0, 7490.00),
(13, 7, 'worker_sites/qrcodes/2VR994650R130222F_c61825527065bd4578da879a522154fc.png', 24, 16, 0, 7990.00),
(14, 7, 'worker_sites/qrcodes/2VR994650R130222F_b8be9c46f800469b4833d6dd60dd5b45.png', 24, 16, 0, 7990.00),
(15, 8, 'worker_sites/qrcodes/cs_test_b1p4TTKHvvf0NY7YP56llRPvIlpi5toSD698caI51seXeyMXLl0MfCw1B0_1548dfabe14057239892fad35fc0d684.png', 21, 16, 0, 4490.00),
(16, 8, 'worker_sites/qrcodes/cs_test_b1p4TTKHvvf0NY7YP56llRPvIlpi5toSD698caI51seXeyMXLl0MfCw1B0_1367ba402e3c32939e62a3c586e58f33.png', 21, 16, 0, 4490.00),
(17, 8, 'worker_sites/qrcodes/cs_test_b1p4TTKHvvf0NY7YP56llRPvIlpi5toSD698caI51seXeyMXLl0MfCw1B0_a2e2482be119d4db2318ccd9ef9f37c2.png', 21, 16, 0, 7490.00),
(18, 9, 'worker_sites/qrcodes/4VJ29355P20669532_e8c8fc771e14b2ddb20de61536267ab4.png', 20, 16, 0, 4990.00);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `ticket_types`
--

CREATE TABLE `ticket_types` (
  `ticket_type_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `ticket_type` enum('regular','vip') NOT NULL,
  `price` int(11) NOT NULL,
  `remaining_tickets` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `ticket_types`
--

INSERT INTO `ticket_types` (`ticket_type_id`, `event_id`, `ticket_type`, `price`, `remaining_tickets`) VALUES
(1, 19, 'regular', 5990, 45000),
(2, 19, 'vip', 8990, 5000),
(3, 20, 'regular', 4990, 30000),
(4, 20, 'vip', 7990, 5000),
(5, 21, 'regular', 4490, 25000),
(6, 21, 'vip', 7490, 5000),
(7, 22, 'regular', 3990, 20000),
(8, 22, 'vip', 6990, 5000),
(9, 23, 'regular', 5990, 45000),
(10, 23, 'vip', 8990, 5000),
(11, 24, 'regular', 4990, 30000),
(12, 24, 'vip', 7990, 5000);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `email` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `city` varchar(40) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `role` enum('raver','organizer','worker','admin') NOT NULL DEFAULT 'raver',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reg_token` varchar(64) DEFAULT NULL,
  `reg_token_expires` datetime DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `users`
--

INSERT INTO `users` (`id`, `last_name`, `first_name`, `email`, `password_hash`, `city`, `birth_date`, `phone_number`, `role`, `created_at`, `reg_token`, `reg_token_expires`, `is_verified`) VALUES
(16, 'Farkas', 'Gábor', 'farkasgabor1024@gmail.com', '$2a$12$55rGML/byY0LfH/TOgdbUexjimugkjvI9N5CGqUtMiAalSVGZecES', 'Mol', '2003-10-24', '+381621516073', 'raver', '2025-09-05 11:44:02', '', '2025-09-06 13:44:02', 1),
(18, 'Miska', 'Fizi', 'random.koala497@passinbox.com', '$2y$10$WuIaFAi4P3dX4dK.nldIyOG2qenos2WbwLks/5maad6b6V0fWqEEi', NULL, '1979-07-09', NULL, 'raver', '2025-09-08 11:04:10', NULL, '2025-09-09 13:04:09', 1);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `user_interests`
--

CREATE TABLE `user_interests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `style_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `user_interests`
--

INSERT INTO `user_interests` (`id`, `user_id`, `style_name`) VALUES
(18, 16, 'Bass'),
(19, 16, 'Hardcore'),
(20, 16, 'Hardstyle'),
(21, 16, 'Psytrance'),
(22, 16, 'Techno'),
(23, 16, 'Trance'),
(26, 18, 'Bass');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(50) NOT NULL,
  `country` varchar(50) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `capacity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `venues`
--

INSERT INTO `venues` (`id`, `name`, `address`, `city`, `country`, `cover_image`, `capacity`) VALUES
(1, 'Óbuda Island', 'Móricz Zsigmond 103', 'Budapest', 'Hungary', 'portfolio-img-1.jpg', 100000),
(2, 'Zamárdi Beach', 'József Attila 86', 'Zamárdi', 'Hungary', 'portfolio-img-2.jpg', 50000),
(3, 'Lővér Camping', 'Petőfi Sándor 94', 'Sopron', 'Hungary', 'portfolio-img-3.jpg', 40000),
(4, 'Velencei-tó', 'Szegedi út 24', 'Velence', 'Hungary', 'portfolio-img-4.jpg', 30000),
(5, 'Dürer Kert', 'Bartók Béla Krt. 107/A', 'Budapest', 'Hungary', 'portfolio-img-5.jpg', 2000),
(6, 'Barba Negra', 'Kandó Kálmán 109', 'Budapest', 'Hungary', 'portfolio-img-1.jpg', 3500),
(7, 'Akvárium Klub', 'Jókai Mór 105/B', 'Budapest', 'Hungary', 'portfolio-img-2.jpg', 2500),
(8, 'Budapest Park', 'Nagyboldogasszony Sgt. 179', 'Budapest', 'Hungary', 'portfolio-img-3.jpg', 10000),
(9, 'A38 Hajó', 'Pesti rakpart (1117)', 'Budapest', 'Hungary', 'portfolio-img-4.jpg', 800),
(10, 'MVM Dome', 'Oktogon 110', 'Budapest', 'Hungary', 'portfolio-img-5.jpg', 20000),
(11, 'Újszegedi Partfürdő és Kemping', 'Torontál tér 1', 'Szeged', 'Hungary', 'portfolio-img-6.jpg', 25000),
(12, 'Orfűi-tó Kemping', 'Pécsi út 45', 'Orfű', 'Hungary', 'portfolio-img-7.jpg', 15000);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `venue_images`
--

CREATE TABLE `venue_images` (
  `id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexek a kiírt táblákhoz
--

--
-- A tábla indexei `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`),
  ADD KEY `venue_id` (`venue_id`);

--
-- A tábla indexei `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- A tábla indexei `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- A tábla indexei `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- A tábla indexei `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `tickets_ibfk_1` (`event_id`),
  ADD KEY `tickets_ibfk_2` (`owner_id`);

--
-- A tábla indexei `ticket_types`
--
ALTER TABLE `ticket_types`
  ADD PRIMARY KEY (`ticket_type_id`),
  ADD KEY `fk_ticket_event` (`event_id`);

--
-- A tábla indexei `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- A tábla indexei `user_interests`
--
ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- A tábla indexei `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- A tábla indexei `venue_images`
--
ALTER TABLE `venue_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venue_id` (`venue_id`);

--
-- A kiírt táblák AUTO_INCREMENT értéke
--

--
-- AUTO_INCREMENT a táblához `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT a táblához `login_sessions`
--
ALTER TABLE `login_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT a táblához `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT a táblához `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT a táblához `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT a táblához `ticket_types`
--
ALTER TABLE `ticket_types`
  MODIFY `ticket_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT a táblához `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT a táblához `user_interests`
--
ALTER TABLE `user_interests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT a táblához `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT a táblához `venue_images`
--
ALTER TABLE `venue_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Megkötések a kiírt táblákhoz
--

--
-- Megkötések a táblához `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`);

--
-- Megkötések a táblához `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD CONSTRAINT `login_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Megkötések a táblához `ticket_types`
--
ALTER TABLE `ticket_types`
  ADD CONSTRAINT `fk_ticket_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Megkötések a táblához `user_interests`
--
ALTER TABLE `user_interests`
  ADD CONSTRAINT `user_interests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `venue_images`
--
ALTER TABLE `venue_images`
  ADD CONSTRAINT `venue_images_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
