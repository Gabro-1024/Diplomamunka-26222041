-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gép: 127.0.0.1
-- Létrehozás ideje: 2025. Sze 24. 18:32
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

--
-- A tábla adatainak kiíratása `contact`
--

INSERT INTO `contact` (`name`, `mail`, `message`) VALUES
('CoolClothes', 'random.koala497@passinbox.com', 'gecimbe vagytok');

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
  `total_tickets` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `events`
--

INSERT INTO `events` (`id`, `name`, `slogan`, `lineup`, `start_date`, `end_date`, `description`, `cover_image`, `organizer_id`, `venue_id`, `total_tickets`) VALUES
(19, 'Sziget Festival', 'The Island of Freedom', 'Timmy Trumpet, The Straikerz, Angerfist, Ke$ha', '2026-08-07 10:00:00', '2026-08-13 23:59:59', 'One of Europe\'s largest music and cultural festivals, held on Óbuda Island in Budapest, Hungary. Features a diverse lineup of international and local artists across multiple stages.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-1.jpg', 18, 1, 50000),
(20, 'Balaton Sound', 'The Biggest Lakeside Party', 'David Guetta, Armin van Buuren, Dimitri Vegas & Like Mike', '2025-06-26 12:00:00', '2027-06-30 23:59:59', 'Europe\'s premier open-air electronic music festival held on the shores of Lake Balaton. Known for its stunning location and world-class DJs.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-4.jpg', 18, 2, 35000),
(21, 'VOLT Festival', 'Music. Love. Unity.', 'Imagine Dragons, Arctic Monkeys, Halott Pénz', '2026-06-19 14:00:00', '2026-06-23 23:59:59', 'Hungary\'s most popular music festival featuring a mix of rock, pop, electronic, and world music in the beautiful city of Sopron.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-3.jpg', 18, 3, 30000),
(22, 'EFOTT', 'Hungary\'s Biggest Student Festival', 'Majka & Curtis, Wellhello, Tankcsapda', '2026-07-10 10:00:00', '2026-07-14 23:59:59', 'A week-long festival on the shores of Lake Velence, offering music, sports, and cultural programs for students and young adults.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-5.jpg', 18, 4, 25000),
(23, 'SZIN festival', 'Zárjuk együtt a nyarat 2026-ban is!', 'Rúzsa Magdolna, Follow The Flow, Punnany Massif, Margaret Island, Carson Coma', '2026-08-06 10:00:00', '2026-08-12 23:59:59', 'The 2025 edition of Europe\'s most colorful festival, featuring an even more diverse lineup and exciting new programs.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-6.jpg', 16, 11, 50000),
(24, 'Fishing on Orfű', 'Zenés nyár a Mecsek lábánál', '30Y, Quimby, Bagossy Brothers Company, Blahalouisiana, Péterfy Bori & Love Band', '2026-06-19 12:00:00', '2026-06-22 23:59:59', 'One of Hungary\'s most beloved smaller festivals, held near Pécs by Lake Orfű. Known for its cozy atmosphere, lakeside concerts, and family-friendly programs.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-2.jpg', 18, 12, 15000),
(63, 'Ultimate Graduation Party @ Gábor', 'Nyomooddd', 'Gábor, Zlatko, Pintér, Anita, Simon, Anyu, Apu, Ildi, Dani', '2025-10-07 10:00:00', '2025-10-08 16:00:00', 'Diplomálásom eseménye, a Szabadkai Műszaki Szakfőiskolán, informatika területen.', 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-63.jpg', 18, 11, 15);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `event_categories`
--

CREATE TABLE `event_categories` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- A tábla adatainak kiíratása `event_categories`
--

INSERT INTO `event_categories` (`id`, `event_id`, `category`, `created_at`) VALUES
(21, 19, 'Electronic', '2025-09-23 16:29:16'),
(22, 19, 'Rock', '2025-09-23 16:29:16'),
(23, 19, 'Pop', '2025-09-23 16:29:16'),
(24, 19, 'Hip Hop', '2025-09-23 16:29:16'),
(25, 20, 'EDM', '2025-09-23 16:29:16'),
(26, 20, 'House', '2025-09-23 16:29:16'),
(27, 20, 'Trance', '2025-09-23 16:29:16'),
(28, 20, 'Techno', '2025-09-23 16:29:16'),
(29, 21, 'Rock', '2025-09-23 16:29:16'),
(30, 21, 'Indie', '2025-09-23 16:29:16'),
(31, 21, 'Alternative', '2025-09-23 16:29:16'),
(32, 21, 'Pop', '2025-09-23 16:29:16'),
(33, 22, 'Rock', '2025-09-23 16:29:16'),
(34, 22, 'Pop', '2025-09-23 16:29:16'),
(35, 22, 'Hip Hop', '2025-09-23 16:29:16'),
(36, 22, 'Electronic', '2025-09-23 16:29:16'),
(37, 23, 'Pop', '2025-09-23 16:29:16'),
(38, 23, 'Hip Hop', '2025-09-23 16:29:16'),
(39, 23, 'Indie', '2025-09-23 16:29:16'),
(40, 23, 'Alternative', '2025-09-23 16:29:16'),
(41, 24, 'Rock', '2025-09-23 16:29:16'),
(42, 24, 'Indie', '2025-09-23 16:29:16'),
(43, 24, 'Folk', '2025-09-23 16:29:16'),
(44, 24, 'Alternative', '2025-09-23 16:29:16'),
(97, 63, 'EDM', '2025-09-24 14:01:22'),
(98, 63, 'Hardcore', '2025-09-24 14:01:22'),
(99, 63, 'Hardstyle', '2025-09-24 14:01:22'),
(100, 63, 'Metal', '2025-09-24 14:01:22'),
(101, 63, 'Techno', '2025-09-24 14:01:22');

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
(12, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '7402269272b4a7637693947d410eceee1f42b07d26434eaf993ba6cc66130fb4', '2025-09-17 20:17:59', '2025-09-16 18:17:59'),
(13, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '275a479b2c743c91cb8bbf2a6d9e7168b9b7931e21392a9950ce3a830d9acad5', '2025-09-19 22:17:38', '2025-09-18 20:17:38'),
(14, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '12556511b8192d2596bd1e82d1c875ab5d65fac12371ab27c360d7a4cf34902f', '2025-09-22 22:00:22', '2025-09-21 20:00:22'),
(15, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '2b656bdc9eb7ab991d27886975e6b977e3e01369ba1a21d0ea6399d1481e9f55', '2025-09-22 22:04:47', '2025-09-21 20:04:47'),
(16, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '685bb8ba8ceefcef57c2580bfa95ef2ce24ac272468b5aaa205b3ed9a0a7c4fb', '2025-10-22 21:36:42', '2025-09-22 19:36:42'),
(17, 18, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '0965d57acd6c42b8b4d888c2516345e3dee49d127db15523047927c8e4065655', '2025-09-23 22:26:37', '2025-09-22 20:26:37'),
(18, 18, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '8a524a13d0360e31adde4bacae62c6d291ed9eca4f8c8a4431bcf0d9667d2426', '2025-10-23 19:30:33', '2025-09-23 17:30:33'),
(19, 18, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', 'fdeb911a754bbf92ea2565fd88174eae5ce99ba75026363c793c68b8e5d2079f', '2025-09-25 14:08:44', '2025-09-24 12:08:44'),
(20, 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', '08201b9acb313a7ffd90f99e3cd7a2da8043f7c47a327ca076ab42201672996d', '2025-09-25 17:33:59', '2025-09-24 15:33:59');

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
(7, 'farkasgabor1024@gmail.com', '2ee16a03a931a4e4f830e9b01498f86516855f324817da11c263aaece477f400', '2025-09-08 13:34:30', '2025-09-08 14:34:30', 1),
(8, 'farkasgabor1024@gmail.com', '6601dfa19da9ef6601d41a85cbeba5bea52741356d219dc6d4ab20eb4afeb399', '2025-09-21 21:58:41', '2025-09-21 22:58:41', 1),
(9, 'farkasgabor1024@gmail.com', 'e3b0acb7c8a9659107c466c34cf112badeb3ea069830461d42667d54add1fcad', '2025-09-21 22:03:02', '2025-09-21 22:04:00', 0);

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
(20, 16, 17970.00, 'completed', '2025-09-18 23:33:41', 'stripe'),
(21, 16, 20960.00, 'completed', '2025-09-21 22:16:37', 'stripe'),
(22, 18, 19950.00, 'completed', '2025-09-23 14:20:54', 'stripe'),
(23, 18, 28960.00, 'completed', '2025-09-23 14:27:58', 'paypal'),
(24, 18, 27960.00, 'completed', '2025-09-23 14:29:24', 'paypal'),
(25, 18, 44950.00, 'completed', '2025-09-23 16:47:22', 'stripe'),
(26, 16, 25000.00, 'completed', '2025-09-24 17:47:23', 'paypal');

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
(80, 26, 'worker_sites/qrcodes/0J6949920H6422137_b56bfcd5270cd9323ef2358ccd9c6926.png', 63, 16, 0, 5000.00),
(81, 26, 'worker_sites/qrcodes/0J6949920H6422137_63d8b0f3ad057ad5649e5d537d5d96f7.png', 63, 16, 0, 5000.00),
(82, 26, 'worker_sites/qrcodes/0J6949920H6422137_9acd3dca5325c330dbea86cd69571c2b.png', 63, 16, 1, 5000.00),
(83, 26, 'worker_sites/qrcodes/0J6949920H6422137_7a352de02a3e15328aab9a837b5ec24c.png', 63, 16, 0, 5000.00),
(84, 26, 'worker_sites/qrcodes/0J6949920H6422137_0468022bb6b4aafee465472109d7ae51.png', 63, 16, 0, 5000.00);

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
(1, 19, 'regular', 5990, 44999),
(2, 19, 'vip', 8990, 4995),
(3, 20, 'regular', 4990, 29999),
(4, 20, 'vip', 7990, 4997),
(5, 21, 'regular', 4490, 24993),
(6, 21, 'vip', 7490, 4999),
(7, 22, 'regular', 3990, 19994),
(8, 22, 'vip', 6990, 4994),
(9, 23, 'regular', 5990, 45000),
(10, 23, 'vip', 8990, 4995),
(11, 24, 'regular', 4990, 457),
(12, 24, 'vip', 7990, 0),
(77, 63, 'regular', 100, 10),
(78, 63, 'vip', 5000, 0);

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
(16, 'Farkase', 'Gábore', 'farkasgabor1024@gmail.com', '$2y$10$dvmce/ukNVhJICCD98yTouBE/Spgx0OG5vT2v3LDYNl8ycFCw0ZRm', 'Mol', '2003-10-24', '+381621516073', 'raver', '2025-09-05 11:44:02', '', '2025-09-06 13:44:02', 1),
(18, 'Miska', 'Fizi', 'random.koala497@passinbox.com', '$2a$12$Lkhnyot5oB2fKZGYzrnGVOvPaMLIa7WNpbXQ4KX9L9Y2wLXXMbiQ6', NULL, '1979-07-09', NULL, 'organizer', '2025-09-08 11:04:10', NULL, '2025-09-09 13:04:09', 1);

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
(26, 18, 'Bass'),
(48, 16, 'Hardcore'),
(49, 16, 'House'),
(50, 16, 'Metal'),
(51, 16, 'Minimal'),
(52, 16, 'Progressive House'),
(53, 16, 'Psytrance'),
(54, 16, 'Reggae'),
(55, 16, 'Rock'),
(56, 16, 'Techno'),
(57, 16, 'Trance');

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
  `capacity` int(11) NOT NULL,
  `story` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `venues`
--

INSERT INTO `venues` (`id`, `name`, `address`, `city`, `country`, `cover_image`, `capacity`, `story`) VALUES
(1, 'Óbuda Island', 'Móricz Zsigmond 103', 'Budapest', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/obuda-island-cover.jpg', 100000, 'Óbuda Island is one of the largest open-air concert venues in Budapest, alongside Budapest Park. Previously home to the Sziget Festival, it now hosts numerous major international and domestic performers. It features excellent infrastructure, multiple stages, and easy accessibility.'),
(2, 'Zamárdi Beach', 'József Attila 86', 'Zamárdi', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/zamardi-beach-cover.jpg', 50000, 'Zamárdi Beach annually hosts Balaton Sound, one of Europe\'s largest electronic music festivals. The lakeside venue offers a unique atmosphere where the world\'s best DJs entertain tens of thousands of attendees. The sandy shoreline and crystal-clear water provide an ideal backdrop for the party.'),
(3, 'Lővér Camping', 'Sopron 9400', 'Sopron', 'Hungary', ' http://localhost/Diplomamunka-26222041/assets/images/venues/lover-camping-cover.jpg\r\n', 40000, 'Lővér Camping was the former home of the Volt Festival near Sopron. The picturesque forests and green meadows created a perfect environment for the multi-day music and cultural event. Although Volt ended in 2019, the venue remains open for other events.'),
(4, 'Velencei-tó', 'Szegedi út 24', 'Velence', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/velencei-to-cover.jpg', 30000, 'The Strand Festival, held annually on the shore of Lake Velence, is one of the most popular summer music events in Hungary. The shallow lake and sandy beach offer a pleasant resort experience while domestic and international stars perform. The sunset concerts provide a special experience.'),
(5, 'Dürer Kert', 'Bartók Béla Krt. 107/A', 'Budapest', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/durer-kert-cover.jpg', 2000, 'Dürer Kert is one of Budapest\'s most iconic independent music venues. It typically features alternative rock, punk, and indie music, and has launched the careers of many emerging artists. The garden section is open in summer, while the indoor stage takes over in winter.'),
(6, 'Barba Negra', 'Kandó Kálmán 109', 'Budapest', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/barba-negra-cover.jpg', 3500, 'Barba Negra is one of Budapest\'s largest indoor concert venues by capacity. It primarily hosts rock and metal concerts but also organizes electronic music events. Programs run simultaneously in its two halls (Track and Music Club), and it has its own parking lot.'),
(7, 'Akvárium Klub', 'Jókai Mór 105/B', 'Budapest', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/akvarium-klub-cover.jpg', 2500, 'Akvárium Klub is located at Deák Square and is one of the most modern concert venues in Budapest. Besides the main stage, there is a smaller, pub-like room for intimate concerts. The rooftop terrace is open in summer, offering a beautiful view of the city center.'),
(8, 'Budapest Park', 'Nagyboldogasszony Sgt. 179', 'Budapest', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/budapest-park-cover.jpg', 10000, 'Budapest Park is an open-air, partially roofed concert venue on the Danube embankment. In summer, the biggest domestic and international artists perform here, making it one of the most popular summer entertainment spots in Budapest. The view of the Danube and excellent cuisine make it special.'),
(9, 'A38 Hajó', 'Pesti rakpart (1117)', 'Budapest', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/a38-hajo-cover.jpg', 800, 'A38 Ship is a former Ukrainian coal carrier, now renowned as one of the best clubs in the world. The concerts on the ship\'s deck offer an intimate atmosphere in the middle of the Danube. The creative restaurant and summer terrace complement the cultural experience.'),
(10, 'MVM Dome', 'Oktogon 110', 'Budapest', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/mvm-dome-cover.jpg', 20000, 'MVM Dome is Budapest\'s most modern and largest indoor arena by capacity. Opened in 2021, it has since hosted concerts by the biggest international stars. It boasts excellent acoustics and all modern facilities for top-tier events.'),
(11, 'Újszegedi Partfürdő és Kemping', 'Torontál tér 1', 'Szeged', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/szegedi-partfurdo-cover.jpg', 25000, 'Újszegedi Beach and Camping, launched as a successor to the Sziget Festival in Szeged-Szabadkikötő, was home to the Szeged Youth Days. The Tisza riverside camping and beach provided an excellent venue for multi-day festivals in the southern city.'),
(12, 'Orfűi-tó Kemping', 'Pécsi út 45', 'Orfű', 'Hungary', 'http://localhost/Diplomamunka-26222041/assets/images/venues/orfui-to-cover.jpg', 15000, 'The camping and beach by Lake Orfű host Fishing on Orfű and other smaller festivals. The crystal-clear lake at the foot of the Mecsek Mountains and the surrounding protected natural areas create a unique environment for summer concerts and cultural programs.');

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
-- A tábla adatainak kiíratása `venue_images`
--

INSERT INTO `venue_images` (`id`, `venue_id`, `image_path`) VALUES
(1, 7, 'http://localhost/Diplomamunka-26222041/assets/images/venues/akvarium-klub-1.jpg'),
(2, 8, 'http://localhost/Diplomamunka-26222041/assets/images/venues/budapest-park-1.jpg'),
(3, 11, 'http://localhost/Diplomamunka-26222041/assets/images/venues/szegedi-partfurdo-1.jpg'),
(4, 10, 'http://localhost/Diplomamunka-26222041/assets/images/venues/mvm-dome-1.jpg'),
(5, 8, 'http://localhost/Diplomamunka-26222041/assets/images/venues/budapest-park-2.jpg'),
(6, 2, 'http://localhost/Diplomamunka-26222041/assets/images/venues/zamardi-beach-1.jpg'),
(7, 12, 'http://localhost/Diplomamunka-26222041/assets/images/venues/orfui-to-1.jpg'),
(8, 4, 'http://localhost/Diplomamunka-26222041/assets/images/venues/velencei-to-1.jpg'),
(9, 9, 'http://localhost/Diplomamunka-26222041/assets/images/venues/a38-hajo-1.jpg');

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
-- A tábla indexei `event_categories`
--
ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT a táblához `event_categories`
--
ALTER TABLE `event_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT a táblához `login_sessions`
--
ALTER TABLE `login_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT a táblához `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT a táblához `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT a táblához `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT a táblához `ticket_types`
--
ALTER TABLE `ticket_types`
  MODIFY `ticket_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT a táblához `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT a táblához `user_interests`
--
ALTER TABLE `user_interests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT a táblához `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT a táblához `venue_images`
--
ALTER TABLE `venue_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
-- Megkötések a táblához `event_categories`
--
ALTER TABLE `event_categories`
  ADD CONSTRAINT `event_categories_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

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
