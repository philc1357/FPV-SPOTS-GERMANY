-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 20, 2026 at 12:46 AM
-- Server version: 10.11.14-MariaDB-0ubuntu0.24.04.1-log
-- PHP Version: 7.4.33-nmm8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `d046b4e2`
--

-- --------------------------------------------------------

--
-- Table structure for table `spots`
--

CREATE TABLE `spots` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL DEFAULT '',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `spot_type` enum('Bando','Feld','Gebirge','Park','Verein','Wasser','Sonstige') NOT NULL,
  `difficulty` enum('Anfänger','Mittel','Fortgeschritten','Profi') NOT NULL,
  `parking_info` varchar(500) NOT NULL DEFAULT 'Unbekannt',
  `parking_updated_by` int(10) UNSIGNED DEFAULT NULL,
  `parking_updated_at` datetime DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spots`
--

INSERT INTO `spots` (`id`, `user_id`, `name`, `description`, `latitude`, `longitude`, `spot_type`, `difficulty`, `parking_info`, `parking_updated_by`, `parking_updated_at`, `is_private`, `created_at`) VALUES
(1, 1, 'Eisenwerk Zwickau', 'Rießiger Bando. Offener Zugang. Sehr vielseitig mit großen offenen Hallen, viele Gebäude.', 50.7515470, 12.4815740, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-03 22:17:38'),
(2, 1, 'Kieswerk Rehbach', '3 Schaufelbagger. Unter der Woche ist ab und zu Berufsbetrieb aber am Wochenende meist frei. Naturschutzgebiet. Bitte auf die Brutstätten an den Klippen acht geben. Windoffen daher eher fuer größere Copter geeignet. Ab und an kommt einer vom Naturschutzverband, der ist aber cool solange man nicht die Tiere am Wasser stört.', 51.2656640, 12.2786440, 'Sonstige', 'Anfänger', 'Man kann direkt bis ans Kieswerk ran fahren und parken.', 1, '2026-04-13 21:40:07', 0, '2026-04-03 22:28:47'),
(3, 1, 'Spielplatz Großmannsplatz', 'Ruhiger wenig besuchter Spielplatz für Tinywhoops. Coole Mauer mit Löchern zum durch fliegen.  Mittlerweile kennen mich ein paar Kinder. Die rennen gerne mal der Drohne hinterher. Mit nem Air65 hab ich hier noch nie blöde Blicke bekommen.', 51.3409910, 12.3205390, 'Park', 'Mittel', 'Überall in den Seitenstraßen', NULL, NULL, 0, '2026-04-03 22:29:59'),
(5, 1, 'Bando am Bayerischen Bahnhof', 'Tinywhoop Spot. Viele kleine offene Fenster, Säulen und Gänge. Sehr leicht zu betreten aber dennoch gut geschützt.', 51.3270580, 12.3835120, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-04 01:06:26'),
(6, 1, 'Bockwindmühle Lindenaunhof', 'Super Spot für Tinywhoop - 2 Zoll. Windmühle und Spielplatz. Viel Platz.', 51.3334180, 12.2434210, 'Park', 'Anfänger', 'Parkplatz direkt vor Ort.', 1, '2026-04-13 21:52:59', 0, '2026-04-04 21:51:27'),
(7, 1, 'Schleusenruine Wüsteneutzsch', 'Toller Cinematic Spot. Eher weniger für Freestyle. Guter Anfänger Spot mit einer schönen geschichtlichen Landschaft.', 51.3301090, 12.0707560, 'Feld', 'Anfänger', 'Parkplatz direkt am Spot.', 1, '2026-04-13 21:52:21', 0, '2026-04-06 19:04:34'),
(8, 1, 'Basso Hallenbad', 'Bekannt aus sämtlichen Youtube Videos\r\nVerlassenes Hallenbad mit vielen offenen Fenstern.\r\nEnge Gänge und offene Hallen. Auch für Anfänger geeignet.', 51.6909490, 12.7210520, 'Bando', 'Mittel', 'Man kann direkt an den Spot ranfahren und ist auch sichtgeschützt.', 1, '2026-04-14 23:54:46', 0, '2026-04-09 01:37:37'),
(9, 1, 'Chemiewerk Rüdersdorf', 'Chemiewerk Rüdersdorf ist ein ikonischer Lost-Place-FPV-Spot mit weitläufigen Industrieflächen, leerstehenden Hallen und markanten Betonstrukturen. Der Mix aus engen Durchflügen, offenen Bereichen und rauer Industrie-Atmosphäre macht den Ort ideal für kreative Freestyle-Lines', 52.4842800, 13.7889090, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-09 01:52:56'),
(10, 5, 'Verlassener Sowjet Flughafen', 'Große Halle sehr viel Metall gut um Digital durch Gebäude zu fliegen, drum herum einzelne verlassene Gebäude und Feld, wenig los nie Probleme mit Passanten, auf der anderen Straßenseite gutes Feld mit Waldstück und verlassenen Bunkern und Lagerhallen gute Split-S und PowerLoop Möglichkeiten sowie enge Lücken außerdem kann man Full Send gehen ohne Angst bei crash alles zu zerstören', 52.7383690, 13.2191970, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-09 12:57:23'),
(11, 5, 'Kornfeld Frohnau', 'Gut für Anfänger die zum 1. Mal mit größeren Koptern fliegen und erstmal auf einer Freifläche üben wollen', 52.6485580, 13.2773210, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-09 13:11:02'),
(12, 5, 'Lichtung an A111', 'Durch kurze Entfernung zur Bundesstraße nur nach der 1 : 1 Regel fliegen!!! Ansonsten für 3.5\"-5\" gut für Freestyle und sehr abgelegen. Ab und zu kommt mal der Jäger vorbei, solange man freundlich ist, nicht den Wald betritt (Schutzzone Wild) und seinen Müll wieder mitnimmt ist alles entspannt.', 52.6371520, 13.2429460, 'Sonstige', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-09 13:22:06'),
(13, 5, 'Feld mit Bäumen', 'Schön abgeschieden gut für alles ab 3.5\". Gute Mischung aus freiem Feld fürs rumcruising und Baumfreestyle.', 52.7155280, 13.1762980, 'Feld', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-09 13:31:55'),
(14, 1, 'Schwibbogen Windrad Lippoldsruhe', 'Großes Feld mit einem (besonders zur Weihnachtszeit) wunder schönen Windrad.', 50.7371420, 12.5586870, 'Feld', 'Anfänger', 'Direkt am Windrad', 1, '2026-04-15 21:40:28', 0, '2026-04-09 17:58:50'),
(15, 1, 'Alte Spitzenfabrik', 'Offene Wiese. Die Eigentümer des Geländes kennen FPV Piloten und sind total cool damit. Im Hinterhof gibts noch einen Garten für kleinere Copter. 2 Brücken sind auch da.', 51.2423930, 12.7360030, 'Sonstige', 'Anfänger', 'Direkt an der Spitzenfabrik', 1, '2026-04-14 22:04:07', 0, '2026-04-09 23:20:26'),
(16, 1, 'Bando an der Mauritiusbrauerei', 'Kleiner Bando der auch gut für Anfänger geeignet ist, da dort nur eine große Halle vorhanden ist. Es gibt auch einen großen Schornstein aber da kann man nicht durch diven. Wohngebiet ist nebenan aber gut geschützt durch Bäume.', 50.7261910, 12.5030180, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-10 21:35:02'),
(17, 1, 'Wasserkugel Deutzen', 'Perfekt um Dives zu üben. Großflächiges Feld. Am besten an Wochenende besuchen da nebenan ein Firmengelände ist. An Wochenende aber super ruhig.', 51.1174180, 12.4234690, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-11 01:12:50'),
(18, 1, 'Dreiländereck', 'Der Bando an sich hat eher enge Räume. Eine größere Halle vorhanden und eine große Freifläche. Sehr unauffällig.', 51.0913980, 12.2844830, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-11 19:58:05'),
(19, 10, 'Heinrich List', 'Steht unter Denkmalschutz\r\nAlte Fabrik (Bando)', 48.3264510, 7.6983420, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 13:14:47'),
(20, 10, 'Spielplatz', 'Fliege sehr oft hier.\r\nIst mein main spot.\r\nEher am Abend kommen wenn meistens niemand da.\r\nNicht fliegen wenn Kinder da sind!!!\r\n1-4 Zoll geeignet 5 machbar jedoch spot zu klein.', 48.2454410, 7.9547050, 'Sonstige', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 13:19:29'),
(29, 1, 'Kraftwerk Vogelsang', 'Bekannter Bando. 2 perfekte Türme für Dives mit sehr großem Durchmesser. Kaum Gänge. Alles sehr offen. Perfekter Bando.', 52.1678630, 14.7005980, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:04:35'),
(30, 1, 'Schaufelradbagger', 'Für Anfänger geeignet da der Spot sehr offen ist. Kein Betonboden. Auch cool für Cinematics. Sehr abgelegen.', 51.5353520, 13.9495790, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:13:04'),
(31, 1, 'Altes Möbelhaus', 'Relativ bekannter LP. Reinkommen ist nicht garantiert. Wenn man reinkommt aber ein geiler Spot.', 51.1018480, 13.4783950, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:17:50'),
(32, 1, 'Ehemals Magnesitwerk', 'Rießiges Gelände. Sehr vielfältig und auch für Änfänger geeignet ihre ersten Bandoerfahrungen zu machen. Große hohe Hallen um einfach zu üben. Man kann aber auch full send ballern.', 51.8543630, 12.0785450, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:24:04'),
(33, 1, 'Windpark Woltersdorf', 'Offene Felder mit zahlreichen Windrädern. Am besten mit 5 Zoll da sehr windoffen (logisch)', 52.1573480, 11.8030070, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:27:05'),
(34, 1, 'Reichsbahn Ausbesserungswerk', 'Für Anfänger geeigneter Bando. Alles vorhanden. Offene Hallen, offene Fenster, Gänge, Außengelände. Alles da.', 52.0818930, 11.6616230, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:31:38'),
(35, 1, 'Verlassener Bauernanlage', 'Entspannter kleiner \"Bando\". Klassischer Anfängerspot aber auch als Erfahrener kann man hier senden.', 52.0483210, 11.7562720, 'Sonstige', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:34:20'),
(36, 1, 'Windpark Bördeland', 'Klassischer Windpark. Offene Felder, viele Windräder.', 51.9711870, 11.6250800, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:37:57'),
(37, 1, 'Windpark Egeln', 'Klassischer Windpark. Offene Felder, viele Windräder', 51.9782720, 11.4549640, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:39:09'),
(38, 1, 'Fabrik Güsten', 'Kleiner Bando. Man kann den Turm außen diven und erste Bando Erfahrungen sammeln. Nichts für full-sends.', 51.8024720, 11.6038370, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:43:09'),
(39, 1, 'Ehemaliges Ferienheim', 'Kein Anfängerspot da alles sehr eng ist. Für Erfahrene kann das hier sehr interessant sein. Sehr enge Manöver sind hier möglich.', 51.7187340, 11.1451360, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 15:47:39'),
(40, 1, 'Ruine', 'Super abgelegener Anfängerfreundlicher Bando. Sehr viele offene und \"weitläufige\" Strukturen. Wenig engen Strukturen. Full-Send ohne großes Risiko.', 50.3977400, 12.6305870, 'Bando', 'Anfänger', 'Da das Gebiet quasi im Wald ist kann man fast überall parken und direkt an den Spot ran fahren', 1, '2026-04-14 23:53:09', 0, '2026-04-12 15:57:00'),
(41, 1, 'Teufelsberg', 'Oft besuchter Bekannter LP. Wenn man man Zeit findet zum fliegen wirklich ein super Spot.', 52.4974690, 13.2409180, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:01:08'),
(42, 1, 'Luftmunitionslager', 'Super Anfänger-Bando. Warum? Wiese als Bodengrund, sehr versteckt, offenes Gelände. Einige enge Fenster und Gänge sind natürlich auch da.', 52.9414490, 8.6396420, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:04:41'),
(43, 1, 'Zementwerk', 'Großer Bando. Hallen mit engen Gaps. Kein Anfängerspot. Sehr abgelegen', 51.8181000, 8.0180880, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:25:03'),
(44, 1, 'Gieserei', 'Enger anspruchsvoller Bando mit vielen engen Gängen, kleinen Fenstern und kleinen Hallen.', 51.3568220, 7.4245640, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:28:48'),
(45, 1, 'Landschaftspark Duisburg', 'Rießiger Bando der aber stark besucht wird. Nur nach Regeln fliegen und am besten mit kleineren Drohnen um nicht so viel Stress zu verursachen.', 51.4814230, 6.7902160, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:33:36'),
(46, 1, 'Fabrik', 'Enger Kompakter Bando für Erfahrene Piloten.', 51.3421170, 6.8954550, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:37:01'),
(47, 1, 'Verlassenes Parkhaus', 'Klassisches aber großes offenes Parkhaus', 51.1884420, 6.8470140, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:40:32'),
(48, 1, 'Fabrik', 'Sehr runtergekommen aber genau so lieben wir es. kleinere Hallen, Räume und Gänge vorhanden. Offene Fenster und coole Hinterhöfe.', 51.2156210, 7.0720200, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:43:38'),
(49, 1, 'Windpark am Tagebau', 'Mehrere Windräder und Schaufelradbagger und große Felder. ACHTUNG: NICHT ÜBER DIE AUTOBAHN FLIEGEN!', 51.0593340, 6.4954520, 'Feld', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:45:55'),
(50, 1, 'Tagebau Hamach', 'Man kann bis unten rein laufen oder auch fliegen. Je nach Laune. Fliegen auf eigene Gefahr. Schaufelradbagger und tolle Landschaft', 50.9030330, 6.5389250, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:48:06'),
(51, 1, 'Klinik', 'Brutal großes Klinikgelände mit Wohnblöcken, einer Schwimmhalle und einem gigantischen Gelände. Hier findet man alles was man braucht..', 50.8834210, 9.3769010, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 16:56:07'),
(52, 1, 'Schotterwerk', 'Ähnlich wie Schaufelradbagger. Sehr abgelegen.', 50.3168770, 7.9496760, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:02:08'),
(53, 1, 'Ziegelei', 'Eine große Halle mit viele Säulen, Gaps und offenen Fenstern. Kleinerer Bando. Sehr verrottet.', 50.1064880, 8.2798020, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:05:00'),
(54, 1, 'IKEA Schild', 'Bitte nur Sonntags fliegen. Großer Parkplatz mit IkeaSchild', 50.0573280, 8.3717910, 'Sonstige', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:06:40'),
(55, 1, 'Großer Feldberg', 'Aussichtsturm, Felsen, Wiese, Spielplatz. Super Anfängerspot. Bitte unbedingt auf andere Menschen achten!', 50.2320900, 8.4579730, 'Park', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:09:31'),
(56, 1, 'Fabrik', 'Kleiner enger Bando mit engen Räumen und einem kleinen Außengelände', 50.1629890, 8.5325410, 'Bando', 'Fortgeschritten', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:13:14'),
(57, 1, 'Lichtung an der Autobahn', 'Kleiner Park. Super für 3 Zoll Copter. Gut zum cruisen und ein paar Hindernisse sind auch da. Anfängerspot', 50.1550880, 8.7119170, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:18:03'),
(58, 1, 'Parkplatz am Feld', 'Offenes Feld mit Parkmöglichkeit.', 50.1658670, 8.7135370, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:19:24'),
(59, 1, 'Fernsehturm am Steinkopf', 'Großer Fernsehturm sehr abgelegen. Perfekt für fette Dives.', 50.3264810, 8.6600830, 'Sonstige', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:23:09'),
(60, 1, 'Windräder Galgenberg', 'Windpark mit Feldern', 50.1874100, 8.8635640, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:25:24'),
(61, 1, 'IKEA-Schild', 'Ikea Schild. NUR SONNTAGS FLIEGEN!', 50.1496440, 8.9443410, 'Sonstige', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:26:32'),
(63, 1, 'Ausbesserungswerk', 'Altes Bahnwerk mit großen Hallen. Anfängerfreundlicher Bando.', 49.3919230, 8.5783640, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:33:50'),
(64, 1, 'Ziegelei', 'Großer Bando mit vielen großen offenen Hallen. Sehr weitläufig. Hier kann man viel Zeit verbringen. Groß genug um mit mehreren Piloten zu fliegen.', 49.3417900, 8.4775400, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:37:10'),
(65, 1, 'Schotterwerk', 'Cooles altes Schotterwerk. Für Anfänger geeignet. Full-Send auch gut möglich.', 47.9201820, 9.0613600, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:42:51'),
(66, 1, 'Beton-Fabrik', 'Einige relativ offene Hallen. Viel Platz', 47.8299210, 9.6419570, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:46:31'),
(67, 1, 'Schotterwerk', 'Großflächiges Schotterwerk / Kiesabbaugelänge', 49.3673120, 11.4794040, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:51:55'),
(68, 1, 'Betonwerk', 'Kleines Betonwerk am Wasser. ACHTUNG WASSER!', 49.6542730, 12.0169030, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-12 17:55:34'),
(69, 1, 'Kleine Kirche am Feld', 'Süße kleine Kirche am Rande eines großen Feldes', 48.8164580, 11.3298090, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 18:02:02'),
(71, 1, 'Kies- und Splittwerk', 'Cooles großes Kieswerk. Offenes Gelände', 48.7026660, 11.2604180, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 18:12:23'),
(74, 12, 'Wald & Feldmix Brechten', 'Gemischtes Gelände aus Wald und offenem Feld.', 51.5923150, 7.4936940, 'Feld', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 22:02:42'),
(75, 1, 'Betonsteinwerk Rüdersdorf', 'Kleinerer Bando neden Chemiefabrik.\r\nKeine Gebäude nur Förderbänder etc.', 52.4884860, 13.7959140, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-12 22:02:50'),
(76, 12, 'Wald & Feldmix Lünen', 'Mix aus Wald und Feld.\r\nNach Regen etwas matschig!', 51.6091410, 7.4590920, 'Feld', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-13 14:51:28'),
(77, 1, 'Kieswerk', 'Dieser Spot wurde mir zugeschickt. Falls jemand Infos dazu hat gerne in die Kommentare.', 48.1265270, 16.2279280, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-14 16:56:13'),
(78, 1, 'altes Zementwerk', 'Dieser Spot wurde mir zugeschickt falls jemand Infos dazu hat gerne in die Kommentare.\r\n\r\nSieht nach einem kleineren Bando aus.', 48.4276590, 16.4855000, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-14 17:04:56'),
(79, 1, 'Kraftwerk Chavalon', 'Sehr sehr schöner Bando aber immer mal wieder von Security überwacht.', 46.3464680, 6.8783430, 'Bando', 'Fortgeschritten', 'Parken vor der Wasseraufbereitungsanlage möglich', NULL, NULL, 0, '2026-04-14 17:18:05'),
(80, 1, 'Aluminuim Werk', 'Sehr abwechslungsreiche und spannende Location mit vielen unterschiedlichen Spots auf engem Raum. Das Gelände eines alten Aluminiumwerks bietet große, offene Hallen, interessante Strukturen und eine richtig starke Aussicht.\r\n\r\nDer Zugang ist etwas versteckt: Die beiden offiziellen Tore sind verschlossen und nicht überwindbar. Wir sind über das Flussufer rein – dort gibt es auf Höhe der Häuser ein Loch im Zaun. Alternativ ist das Werkstor aktuell offen, wodurch man relativ unkompliziert aufs Gelände kommt.\r\n\r\nZum Spot gehören auch drei verlassene Wohnhäuser am Hang vor dem Werk. Diese sind stark beschädigt, teilweise angekohlt, aber ebenfalls interessant zu erkunden und für Shots geeignet.\r\n\r\nBesonders spannend:\r\n\r\nEin kleines, eigenes Kraftwerk auf dem Gelände\r\nEin kleines Laborgebäude\r\nGroße Hallen mit viel Freiraum zum Fliegen\r\nSchöne Aussichtspunkte rund um das Areal\r\n\r\nHinweise:\r\n\r\nAn der Brücke zum Hauptwerk hängt eine Kamera, vermutlich außer Betrieb – die Brücke selbst ist ein öffentlicher Weg\r\nAuf dem restlichen Gelände sind keine aktiven Kameras oder Sicherungen erkennbar\r\nGebäude sind leer, aber teilweise beschädigt → Vorsicht beim Betreten', 45.8361400, 10.9999970, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-14 17:23:41'),
(81, 1, 'Oculus Tower', 'Ein riesiges Industriegebiet, das von der Via Luigi Turchi aus leicht zu erreichen ist. Es gibt keine Überwachungskameras und keine Alarmanlagen, aber es gibt deutliche Anzeichen dafür, dass dort jemand wohnt. Der Turm mit dem sechseckigen Dach ist zugänglich und bietet einen Blick von oben.', 44.8419260, 11.5921640, 'Bando', 'Anfänger', 'Unbekannt', NULL, NULL, 0, '2026-04-14 17:28:42'),
(82, 1, 'Zementfabrik', 'Rießiges Industriegelände. Leider keinerlei mehr Infos. Gerne Infos in die Kommentare.', 44.0364470, 12.4269800, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-14 17:32:03'),
(83, 1, 'Kohlenmine', 'Keine Security, kein Alarmsystem. Einfacher Zugang von der Straße.  Viele (sehr viele) offene Fenster, Löcher in Dächer. Perfekt für fette Bando Sessions.', 46.1279800, 18.2815500, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-14 17:44:15'),
(84, 1, 'University Hospital (Abandoned Construction)', 'Große, unfertige Krankenhausanlage mit massivem Betonbau – perfekt für alle, die auf strukturiertes Freestyle und cineastische Lines stehen. Der Spot besteht aus mehreren Gebäudeteilen im Rohbauzustand mit offenen Etagen, langen Fluren und vielen Durchflugmöglichkeiten.\r\n\r\nDer Zugang ist in der Regel unkompliziert, da es sich um eine nie fertiggestellte Baustelle handelt. Zäune sind teilweise beschädigt oder offen, sodass man meist ohne großen Aufwand aufs Gelände kommt.\r\n\r\nWas den Spot besonders macht:\r\n\r\nMehrstöckige Betonbauten mit offenen Stockwerken\r\nLange, gerade Flure für schnelle Lines\r\nViele Fensteröffnungen und Durchbrüche\r\nTreppenhäuser und Schächte für technische Moves\r\nGroße Freiflächen zwischen den Gebäuden für Diving-Lines\r\n\r\nDie Atmosphäre ist typisch „roh“: viel Beton, wenig Einrichtung, dafür aber maximale Freiheit beim Fliegen. Perfekt, um kreative Lines zu bauen und auch für Mid-Range geeignet.\r\n\r\nHinweise:\r\n\r\nKeine aktiven Bauarbeiten, aber typischer Baustellenzustand → lose Teile, Kanten, Schächte\r\nAbsturzgefahr durch fehlende Geländer und offene Etagen\r\nTeilweise unebenes Gelände und Schutt\r\nWetter kann den Spot stark beeinflussen (Wind durch offene Struktur)', 45.7708160, 15.9198760, 'Bando', 'Mittel', 'Unbekannt', NULL, NULL, 0, '2026-04-14 17:47:09'),
(85, 1, 'Haludovo Palace Hotel', 'Großes, frei zugängliches Lost-Place-Areal direkt am Meer. Keine Zäune oder aktiven Zugangshürden – du kannst problemlos aufs Gelände und in die Gebäude.\r\n\r\nSpot-Features:\r\n\r\n🏢 Größe: Sehr weitläufiges Gelände mit mehreren Gebäuden → lange Lines & viel Variation\r\n🧱 Struktur: Große offene Hallen, lange Korridore, Treppenhäuser\r\n🪟 Open Spots: Viele kaputte Fenster, offene Wände und Dächer → ideale Entry-/Exit-Points\r\n🔲 Gaps: Zahlreiche Fenster-Gaps, Door-Gaps und größere Freestyle-Lines zwischen Gebäudeteilen\r\n🛗 Verticality: Mehrere Stockwerke + Dachzugang → gute Möglichkeiten für Diving & Powerloops\r\n\r\nRahmenbedingungen:\r\n\r\n👥 Traffic: Häufig viele Touristen unterwegs → Lines vorher checken, eher früh morgens/spät abends fliegen\r\n🚫 Security: Keine aktive Security bekannt, Gelände wirkt frei zugänglich\r\n\r\nRisiken:\r\n\r\n⚠️ Stark beschädigte Bausubstanz (lose Teile, Einsturzgefahr)\r\n⚠️ Viele scharfe Kanten & Hindernisse\r\n\r\nSkill-Level:\r\n\r\nAnfänger können außen fliegen, aber innen wird’s schnell technisch durch enge Gaps und unübersichtliche Struktur\r\n\r\nKurzfazit:\r\nExtrem vielseitiger FPV-Spot mit Fokus auf Freestyle & Exploration. Viel Platz, viele Gaps, aber auch echte Risiken – kein „einfach drauf los“-Spot.', 45.1312110, 14.5276810, 'Bando', 'Mittel', 'Parkplätze vorhanden, teilweise kostenpflichtig', NULL, NULL, 0, '2026-04-14 18:10:55'),
(86, 1, 'Fabrik', 'Großes, teilweise verlassen wirkendes Gelände mit viel Platz für FPV-Flüge – selbst nach 2+ Stunden ist nicht alles erkundet. Ideal für Freestyle und lange Lines dank abwechslungsreicher Strukturen.\r\n\r\nHighlight: Mit etwas Suche findet man Spots mit starkem Blick auf die Krk-Wasserfälle, perfekt für Cinematic-Aufnahmen.\r\n\r\nVor Ort können Tiere (z. B. Ziegen) sein, also aufmerksam und respektvoll fliegen.\r\n\r\nZugang ist nicht immer sicher – gelegentlich verweigert ein Besitzer den Eintritt. Daher flexibel bleiben und ggf. von außen fliegen (rechtliche Lage beachten).', 43.8029120, 15.9546380, 'Bando', 'Fortgeschritten', 'Parken in nahem Umfeld möglich', NULL, NULL, 0, '2026-04-14 20:14:28'),
(87, 1, 'Windpark Rehfeld', 'Viele Windräder. Schöner Wald und offene Felder', 51.5658970, 13.1706240, 'Feld', 'Anfänger', 'Es sind überall befahrbare Feldwege', NULL, NULL, 0, '2026-04-14 23:40:36'),
(90, 1, 'Ehem. Wasserkraftwerk Canitz', 'Allrounder Spot\r\nInnen gut für kleinere Drohnen geeignet (2-3,5 Zoll) wer gut geübt ist kann auch mit 5 Zoll fliegen. Es gibt innen einige Metallstreben und eine große Halle. Ebenso ein paar coole Betonstrukturen zum durch fliegen.\r\n\r\nDas Außengeläde ist auch ein Top Cinematic Spot. Generell sehr schön in der Natur gelegen\r\n\r\nAber Vorsicht! Wasser vorhanden', 51.4011770, 12.6841410, 'Bando', 'Mittel', 'Man kann über den Feldweg direkt bis zum Spot fahren', NULL, NULL, 0, '2026-04-16 21:49:22'),
(91, 1, 'Ehemaliges Bahnbetriebswerk Leipzig Wahren', 'Sehr runtergekommener Bando. Einige Hallen und 2 geräumige Türme. Man kann fast überall gut fliegen. Außengelände ist auch cool. Nebenan befindet sich ein Gelände der DB wo gearbeitet wird.', 51.3887150, 12.3059300, 'Bando', 'Mittel', 'Erich Thiele Straße', NULL, NULL, 0, '2026-04-19 18:46:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `spots`
--
ALTER TABLE `spots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spots_user_id` (`user_id`),
  ADD KEY `idx_spots_parking_updated_by` (`parking_updated_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `spots`
--
ALTER TABLE `spots`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `spots`
--
ALTER TABLE `spots`
  ADD CONSTRAINT `fk_spots_parking_updated_by` FOREIGN KEY (`parking_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_spots_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
