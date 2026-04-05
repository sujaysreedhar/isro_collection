-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 04, 2026 at 01:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */
;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */
;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */
;
/*!40101 SET NAMES utf8mb4 */
;

--
-- Database: `eish`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
    `id` int(11) NOT NULL,
    `username` varchar(100) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO
    `admins` (
        `id`,
        `username`,
        `password_hash`,
        `created_at`
    )
VALUES (
        4,
        'sujay',
        '$2y$10$Xkc.xMhSuTNfT9sR.RVwbu3hrxAGVZ7m2O9pFrGb0UkTKIc62TmJ6',
        '2026-03-04 11:10:29'
    );

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
    `id` int(11) NOT NULL,
    `name` varchar(255) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO
    `categories` (`id`, `name`)
VALUES (2, 'Engineering & Technology'),
    (
        5,
        'Fine Art & Decorative Arts'
    ),
    (7, 'Indigenous Culture'),
    (1, 'Launch Vehichles'),
    (6, 'Maritime & Exploration'),
    (4, 'Natural Sciences'),
    (8, 'Photography & Media'),
    (3, 'Social History'),
    (9, 'Transport & Mobility');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
    `id` int(11) NOT NULL,
    `category_id` int(11) NOT NULL,
    `reg_number` varchar(100) NOT NULL,
    `title` varchar(255) NOT NULL,
    `physical_description` text DEFAULT NULL,
    `historical_significance` text DEFAULT NULL,
    `production_date` varchar(100) DEFAULT NULL,
    `credit_line` varchar(255) DEFAULT NULL,
    `is_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=visible, 0=hidden'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO
    `items` (
        `id`,
        `category_id`,
        `reg_number`,
        `title`,
        `physical_description`,
        `historical_significance`,
        `production_date`,
        `credit_line`,
        `is_visible`
    )
VALUES (
        1,
        2,
        'ST 001234',
        'Robey & Co. Portable Steam Engine',
        'A single-cylinder portable steam engine manufactured by Robey & Company of Lincoln, England. The engine features a horizontal fire-tube boiler mounted on four cast-iron wheels, enabling it to be transported between agricultural or construction sites.',
        'Used on Victorian wheat farms during the 1880s; donated by the Mackintosh family of Ballarat.',
        '1878',
        'Gift of the Mackintosh Estate, 1962',
        1
    ),
    (
        2,
        2,
        'ST 001235',
        'Cornish Beam Engine Flywheel',
        'Cast iron flywheel, diameter 2.4 m, from a Cornish-pattern beam engine formerly installed at the Eureka Lead gold mine, Ballarat. The spoke pattern and hub casting are characteristic of mid-Victorian foundry work.',
        'Recovered during demolition of the Eureka Lead pumphouse, 1957.',
        'Circa 1860',
        'Museum Purchase, 1958',
        1
    ),
    (
        3,
        3,
        'SH 002011',
        'Sewing Machine — Household Model',
        'Wilcox & Gibbs chain-stitch sewing machine in original oak carry case with gilt transfer decoration. Mechanism in working order; includes original needles, bobbins and instruction booklet in English and German.',
        'Demonstrates the democratisation of home dressmaking in Edwardian Australia.',
        '1905',
        'Bequest of Miss Vera Holden, 1988',
        1
    ),
    (
        4,
        3,
        'SH 002215',
        'Miner\'s Cradle (Gold Rush)',
        'Timber and wire-mesh gold-washing cradle constructed from pit-sawn hardwood. The rocker base, hopper and riffle bars survive intact. Minor losses to the mesh lining.',
        'Typical equipment used on the Ballarat and Bendigo goldfields during the early 1850s rush.',
        '1852–1855',
        'Found collection, date unknown',
        1
    ),
    (
        5,
        4,
        'NS 003101',
        'Mounted Platypus Specimen',
        'Taxidermy mount of a male platypus (Ornithorhynchus anatinus), posed on a naturalised log base with artificial water surface. Mount prepared by staff taxidermist C. H. Williams.',
        'Among the first professionally mounted platypus specimens in a Victorian public collection.',
        '1891',
        'Museum Commission, 1891',
        1
    ),
    (
        6,
        4,
        'NS 003440',
        'Ammonite Fossil — Jurassic Period',
        'Polished cross-section of an ammonite (Perisphinctes sp.) revealing the characteristic nautiloid chamber structure. Diameter 32 cm. Origin: Madagascar.',
        'Illustrates cephalopod evolution and the invertebrate fossil record of the Mesozoic era.',
        'c. 150 million years BP',
        'Gift of Dr R. Fairfax, 2001',
        1
    ),
    (
        7,
        5,
        'FA 004021',
        'Oil Portrait — Colonial Governor',
        'Oil on canvas, 102 × 76 cm. Three-quarter-length portrait of Sir Henry Barkly, Governor of Victoria 1856–1863, in official dress uniform. Unsigned but attributed to Thomas Clarke based on stylistic comparison.',
        'Commissioned by the Melbourne Club and presented to the museum in 1921.',
        '1872',
        'Presented by the Melbourne Club, 1921',
        1
    ),
    (
        8,
        5,
        'FA 004305',
        'Wedgwood Jasperware Vase Pair',
        'Pair of Wedgwood blue jasperware ovoid vases with white sprig relief depicting classical figures. Each approximately 28 cm tall. Original stoppers present.',
        'Brought to the colony by the Syme family circa 1855; a rare example of English neo-classical ceramics in Australia.',
        '1790–1800',
        'Bequest of Lady M. Syme, 1934',
        1
    ),
    (
        9,
        6,
        'MM 005008',
        'Ship\'s Compass — Binnacle Type',
        'Brass and mahogany binnacle compass by Henry Hughes & Son, London. Includes gimballed compass card, compensating magnets and kerosene lamp housing. Card reads to ½-degree increments.',
        'Log books suggest this compass served aboard the barque SS Loch Katrine, trading between Melbourne and London.',
        '1881',
        'Museum Purchase, 1975',
        1
    ),
    (
        10,
        6,
        'MM 005212',
        'Navigational Chart — Port Phillip',
        'Manuscript chart on vellum, 64 × 92 cm, depicting Port Phillip Bay with depth soundings, anchorages and early settlement locations annotated in ink. Attributed to Surveyor-General Robert Hoddle.',
        'One of the earliest detailed cartographic surveys of Port Phillip Bay; instrumental in planning Melbourne\'s street grid.',
        '1839',
        'State Library of Victoria transfer, 1968',
        1
    ),
    (
        11,
        7,
        'IC 006043',
        'Koori Shield — Carved Hardwood',
        'Shield carved from a single piece of hardwood (species undetermined), with incised geometric decoration along both faces. Length 62 cm, width 18 cm. Surface shows traces of red ochre.',
        'Collected before 1860 in the Upper Murray region; repatriation discussions ongoing with the Yorta Yorta Nation.',
        'Pre-1860',
        'Old Collection, pre-1900',
        1
    ),
    (
        12,
        7,
        'IC 006210',
        'Possum-Skin Cloak Fragment',
        'Fragment (approx. 40 × 55 cm) of a possum-skin cloak with incised geometric patterning on the inner surface. Skins are stitched with plant-fibre cord. Conservation assessment completed 2019.',
        'Possum-skin cloaks are among the most culturally significant objects of south-eastern Aboriginal peoples. Fewer than 20 complete examples survive worldwide.',
        'Pre-1850',
        'Old Collection, pre-1900',
        1
    ),
    (
        13,
        8,
        'PM 007015',
        'Daguerreotype Portrait — Unknown Woman',
        'Quarter-plate daguerreotype in original thermoplastic case with velvet mat. Subject: unidentified woman in mid-Victorian dress with lace collar. Image sharp with minor tarnish to the lower right corner.',
        'Daguerreotypes were the dominant photographic medium in colonial Victoria before being displaced by wet collodion in the 1860s.',
        '1855–1860',
        'Gift of Mrs P. Arnott, 1976',
        1
    ),
    (
        14,
        9,
        'TR 008003',
        'Penny-Farthing Bicycle',
        'Ordinary bicycle (penny-farthing) with 54-inch front wheel and 14-inch rear wheel. Steel frame with nickel-plated fittings, rubber tyres, and leather saddle. Both wheels complete with original spokes.',
        'Ridden competitively by W. J. Christie in the 1887 Melbourne to Ballarat road race; presented by his grandson.',
        '1885',
        'Gift of Mr I. Christie, 1993',
        1
    ),
    (
        15,
        9,
        'TR 008201',
        'Horse-Drawn Cab — Hansom Type',
        'Two-wheeled hansom cab with folding hood, rear-entry passenger compartment, and driver\'s seat mounted at rear. Original paintwork (black and dark red) partially intact. Undercarriage complete with iron-tyred wheels.',
        'Operated in Melbourne\'s CBD circa 1900–1910 before motor taxicabs displaced horse-drawn transport.',
        'c. 1900',
        'Transferred from City of Melbourne, 1952',
        1
    );

-- --------------------------------------------------------

--
-- Table structure for table `item_narrative`
--

CREATE TABLE `item_narrative` (
    `item_id` int(11) NOT NULL,
    `narrative_id` int(11) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `item_narrative`
--

INSERT INTO
    `item_narrative` (`item_id`, `narrative_id`)
VALUES (1, 1),
    (2, 1),
    (5, 2),
    (6, 2),
    (9, 3),
    (10, 3),
    (11, 4),
    (12, 4);

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
    `id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `caption` varchar(255) DEFAULT NULL,
    `license_type` varchar(100) DEFAULT NULL,
    `file_size` int(10) UNSIGNED DEFAULT NULL COMMENT 'Size in bytes',
    `mime_type` varchar(50) DEFAULT NULL COMMENT 'e.g. image/webp',
    `dimensions` varchar(50) DEFAULT NULL COMMENT 'e.g. 1920x1080',
    `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
    `media_type` enum('image', 'pdf', 'youtube') NOT NULL DEFAULT 'image' COMMENT 'Type of media attached',
    `youtube_url` varchar(512) DEFAULT NULL COMMENT 'Full YouTube URL for youtube type'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `narratives`
--

CREATE TABLE `narratives` (
    `id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `content_body` text NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `narratives`
--

INSERT INTO
    `narratives` (`id`, `title`, `content_body`)
VALUES (
        1,
        'The Industrial Revolution in Victoria',
        '<p>The industrial revolution transformed Victoria\'s economy from the 1850s onward. Driven by gold rush wealth, factories, railways and steam-powered machinery reshaped Melbourne into a major industrial city within a generation.</p><p>Workers flooded into newly built factories. Living conditions were harsh, wages low, but ambition was everywhere.</p>'
    ),
    (
        2,
        'Under Southern Skies: Early Natural History',
        '<p>Colonial naturalists catalogued an astonishing array of flora and fauna entirely unknown to European science. The specimens they collected formed the nucleus of the museum\'s natural history collection.</p>'
    ),
    (
        3,
        'Voyages of Discovery: The Port Phillip Bay Settlements',
        '<p>The first European settlers arrived at Port Phillip Bay in 1835. This collection of maritime artefacts documents the voyages, hardships and commerce that defined early colonial Victoria.</p>'
    ),
    (
        4,
        'First Peoples: Culture and Connection to Country',
        '<p>The Aboriginal and Torres Strait Islander collections represent thousands of years of cultural practice, spiritual knowledge and artistic tradition across the continent.</p>'
    );

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `reg_number` (`reg_number`),
ADD KEY `category_id` (`category_id`),
ADD KEY `idx_is_visible` (`is_visible`);

ALTER TABLE `items`
ADD FULLTEXT KEY `title` (
    `title`,
    `physical_description`
);

--
-- Indexes for table `item_narrative`
--
ALTER TABLE `item_narrative`
ADD PRIMARY KEY (`item_id`, `narrative_id`),
ADD KEY `narrative_id` (`narrative_id`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
ADD PRIMARY KEY (`id`),
ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `narratives`
--
ALTER TABLE `narratives` ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 10;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 16;

--
-- AUTO_INCREMENT for table `media`
--
ALTER TABLE `media` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `narratives`
--
ALTER TABLE `narratives`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `items`
--
ALTER TABLE `items`
ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `item_narrative`
--
ALTER TABLE `item_narrative`
ADD CONSTRAINT `item_narrative_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `item_narrative_ibfk_2` FOREIGN KEY (`narrative_id`) REFERENCES `narratives` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media`
--
ALTER TABLE `media`
ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `slug` varchar(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_tag`
--

CREATE TABLE `item_tag` (
    `item_id` int(11) NOT NULL,
    `tag_id` int(11) NOT NULL,
    PRIMARY KEY (`item_id`, `tag_id`),
    KEY `tag_id` (`tag_id`),
    CONSTRAINT `item_tag_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `item_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;