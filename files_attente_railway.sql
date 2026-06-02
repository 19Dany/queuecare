-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- HÃ´te : 127.0.0.1:3306
-- GÃ©nÃ©rÃ© le : sam. 30 mai 2026 Ã  13:31
-- Version du serveur : 8.0.31
-- Version de PHP : 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de donnÃ©es : `files_attente`
--

DELIMITER $$
--
-- ProcÃ©dures
--
DROP PROCEDURE IF EXISTS `sp_recalcul_estimation_nuit`$$
CREATE PROCEDURE `sp_recalcul_estimation_nuit` ()   BEGIN
    DECLARE v_ss_id        INT UNSIGNED;
    DECLARE v_nouvelle     INT UNSIGNED;
    DECLARE v_ancienne     INT UNSIGNED;
    DECLARE v_nb_obs       INT UNSIGNED;
    DECLARE done           INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT id FROM sous_services WHERE statut = 'actif';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    boucle: LOOP
        FETCH cur INTO v_ss_id;
        IF done THEN LEAVE boucle; END IF;

        -- Moyenne pondÃ©rÃ©e sur les 100 derniÃ¨res durÃ©es non aberrantes
        SELECT
            COUNT(*),
            ROUND(
                SUM(duree_reelle / rang_obs) /
                NULLIF(SUM(1.0   / rang_obs), 0)
            )
        INTO v_nb_obs, v_nouvelle
        FROM (
            SELECT
                duree_reelle,
                ROW_NUMBER() OVER (
                    PARTITION BY sous_service_id
                    ORDER BY created_at DESC
                ) AS rang_obs
            FROM historique_durees
            WHERE sous_service_id = v_ss_id
              AND est_aberrant     = 0
            LIMIT 100
        ) ranked;

        -- Minimum 5 observations requises pour mettre Ã  jour
        IF v_nb_obs >= 5 AND v_nouvelle IS NOT NULL THEN
            SELECT duree_estimee INTO v_ancienne
            FROM sous_services WHERE id = v_ss_id;

            UPDATE sous_services
               SET duree_estimee = v_nouvelle
             WHERE id = v_ss_id;

            INSERT INTO logs_estimation
                (sous_service_id, date_calcul, nb_observations, ancienne_duree, nouvelle_duree)
            VALUES
                (v_ss_id, CURDATE(), v_nb_obs, v_ancienne, v_nouvelle);
        END IF;

    END LOOP;
    CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `consultations`
--

DROP TABLE IF EXISTS `consultations`;
CREATE TABLE IF NOT EXISTS `consultations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `sous_service_id` int UNSIGNED NOT NULL,
  `medecin_id` int UNSIGNED DEFAULT NULL COMMENT 'MÃ©decin qui prend en charge',
  `emploi_temps_id` int UNSIGNED DEFAULT NULL COMMENT 'CrÃ©neau de l''emploi du temps rÃ©servÃ©',
  `qr_code_id` int UNSIGNED DEFAULT NULL COMMENT 'QR code utilisÃ© pour la prise de rendez-vous',
  `statut` enum('en_attente','confirme','en_cours','traite','annule','absent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `rang` int UNSIGNED DEFAULT NULL COMMENT 'Position dans la file d''attente du jour',
  `mode_prise` enum('LIGNE','PLACE','MANUEL','QR_CODE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLACE' COMMENT 'LIGNE=domicile, PLACE=QR Code scannÃ©, MANUEL=Saisie manuelle, QR_CODE=GÃ©nÃ©rÃ© par QR',
  `heure_emission` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Heure de crÃ©ation du ticket',
  `heure_passage_estimee` datetime DEFAULT NULL COMMENT 'Heure de passage calculÃ©e (duree_estimee Ã— rang)',
  `heure_debut_reelle` datetime DEFAULT NULL COMMENT 'Heure rÃ©elle de dÃ©but de consultation',
  `heure_fin_reelle` datetime DEFAULT NULL COMMENT 'Heure rÃ©elle de fin de consultation',
  `duree_estimee` int UNSIGNED DEFAULT NULL COMMENT 'DurÃ©e estimÃ©e en secondes au moment de la rÃ©servation',
  `motif` text COLLATE utf8mb4_unicode_ci COMMENT 'Motif de la consultation (optionnel)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_consult_patient` (`patient_id`),
  KEY `idx_consult_ss` (`sous_service_id`),
  KEY `idx_consult_medecin` (`medecin_id`),
  KEY `idx_consult_edt` (`emploi_temps_id`),
  KEY `idx_consult_statut` (`statut`),
  KEY `idx_consult_emission` (`heure_emission`),
  KEY `idx_consult_rang` (`sous_service_id`,`rang`),
  KEY `idx_consult_qrcode` (`qr_code_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Consultations des patients (remplace rendez_vous)';

--
-- DÃ©chargement des donnÃ©es de la table `consultations`
--

INSERT INTO `consultations` (`id`, `patient_id`, `sous_service_id`, `medecin_id`, `emploi_temps_id`, `qr_code_id`, `statut`, `rang`, `mode_prise`, `heure_emission`, `heure_passage_estimee`, `heure_debut_reelle`, `heure_fin_reelle`, `duree_estimee`, `motif`, `created_at`) VALUES
(1, 1, 2, NULL, NULL, NULL, 'confirme', 1, 'PLACE', '2026-05-04 10:30:30', '2026-05-04 10:00:30', NULL, NULL, 1800, 'mal au ventre', '2026-05-04 10:30:30'),
(2, 2, 1, 5, NULL, NULL, 'traite', 1, 'PLACE', '2026-05-26 15:58:14', '2026-05-26 14:58:14', NULL, NULL, 1800, NULL, '2026-05-26 15:58:14'),
(3, 2, 1, 5, NULL, NULL, 'traite', 2, 'PLACE', '2026-05-26 15:59:15', '2026-05-26 15:29:15', NULL, NULL, 1800, NULL, '2026-05-26 15:59:15'),
(4, 2, 1, 5, NULL, NULL, 'traite', 3, 'PLACE', '2026-05-26 16:00:16', '2026-05-26 16:00:16', NULL, NULL, 1800, NULL, '2026-05-26 16:00:16'),
(5, 2, 1, 5, NULL, NULL, 'absent', 4, 'PLACE', '2026-05-26 16:01:17', '2026-05-26 15:01:17', NULL, NULL, 1800, NULL, '2026-05-26 16:01:17'),
(6, 2, 1, 5, NULL, NULL, 'absent', 5, 'PLACE', '2026-05-26 16:02:22', '2026-05-26 15:32:22', NULL, NULL, 1800, NULL, '2026-05-26 16:02:22'),
(7, 2, 1, 5, NULL, NULL, 'traite', 6, 'PLACE', '2026-05-26 16:03:24', '2026-05-26 15:03:24', NULL, NULL, 1800, NULL, '2026-05-26 16:03:24'),
(8, 3, 1, 5, NULL, NULL, 'absent', 1, 'PLACE', '2026-05-27 14:41:58', '2026-05-27 13:41:58', NULL, NULL, 1800, NULL, '2026-05-27 14:41:58'),
(9, 3, 1, 5, NULL, NULL, 'traite', 99, 'PLACE', '2026-05-27 20:00:09', '2026-05-27 15:00:00', NULL, NULL, 1800, 'Test manuel', '2026-05-27 20:00:09'),
(10, 4, 1, 5, NULL, NULL, 'traite', 1, 'PLACE', '2026-05-28 21:34:43', '2026-05-28 21:34:43', NULL, NULL, 1800, NULL, '2026-05-28 21:34:43'),
(11, 4, 1, 5, NULL, NULL, 'absent', 2, 'PLACE', '2026-05-28 21:36:32', '2026-05-28 22:06:32', NULL, NULL, 1800, NULL, '2026-05-28 21:36:32'),
(12, 4, 1, 5, NULL, NULL, 'traite', 3, 'PLACE', '2026-05-28 21:37:33', '2026-05-28 22:37:33', NULL, NULL, 1800, NULL, '2026-05-28 21:37:33'),
(13, 4, 1, 5, NULL, NULL, 'annule', 4, 'PLACE', '2026-05-28 21:38:34', '2026-05-28 23:08:34', NULL, NULL, 1800, NULL, '2026-05-28 21:38:34'),
(14, 4, 1, 5, NULL, NULL, 'annule', 5, 'PLACE', '2026-05-28 21:39:35', '2026-05-28 23:39:35', NULL, NULL, 1800, NULL, '2026-05-28 21:39:35'),
(15, 4, 1, 5, NULL, NULL, 'annule', 6, 'PLACE', '2026-05-28 21:40:36', '2026-05-29 00:10:36', NULL, NULL, 1800, NULL, '2026-05-28 21:40:36'),
(16, 5, 1, 5, NULL, NULL, 'traite', 7, 'PLACE', '2026-05-28 21:59:12', '2026-05-28 21:59:12', '2026-05-28 23:30:06', '2026-05-28 23:30:09', 1800, NULL, '2026-05-28 21:59:12'),
(17, 6, 1, 5, NULL, NULL, 'traite', 1, 'PLACE', '2026-05-29 12:12:44', '2026-05-29 12:12:44', '2026-05-29 12:12:59', '2026-05-29 12:17:18', 1800, NULL, '2026-05-29 12:12:44'),
(18, 7, 1, NULL, NULL, NULL, 'en_attente', 2, 'PLACE', '2026-05-29 12:13:49', '2026-05-29 12:13:49', NULL, NULL, 1800, NULL, '2026-05-29 12:13:49'),
(19, 8, 1, NULL, NULL, NULL, 'en_attente', 3, 'PLACE', '2026-05-29 12:30:59', '2026-05-29 12:30:59', NULL, NULL, 1800, NULL, '2026-05-29 12:30:59'),
(20, 9, 1, NULL, NULL, NULL, 'en_attente', 4, 'PLACE', '2026-05-29 12:45:12', '2026-05-29 12:45:12', NULL, NULL, 1800, NULL, '2026-05-29 12:45:12'),
(21, 10, 1, 5, NULL, NULL, 'traite', 5, 'PLACE', '2026-05-29 13:12:17', '2026-05-29 13:12:17', '2026-05-29 13:12:32', '2026-05-29 13:26:32', 1800, NULL, '2026-05-29 13:12:17'),
(22, 11, 1, 5, NULL, NULL, 'traite', 6, 'PLACE', '2026-05-29 13:16:15', '2026-05-29 13:16:15', NULL, '2026-05-29 13:30:20', 1800, 'Avortement', '2026-05-29 13:16:15'),
(23, 12, 1, 5, NULL, NULL, 'traite', 1, 'PLACE', '2026-05-30 13:27:37', '2026-05-30 13:27:37', '2026-05-30 13:27:56', '2026-05-30 13:59:44', 1800, NULL, '2026-05-30 13:27:37'),
(24, 13, 1, 5, NULL, NULL, 'traite', 2, 'PLACE', '2026-05-30 13:28:30', '2026-05-30 13:28:30', '2026-05-30 13:59:51', '2026-05-30 14:11:28', 1800, NULL, '2026-05-30 13:28:30'),
(25, 14, 1, 5, NULL, NULL, 'en_cours', 3, 'PLACE', '2026-05-30 13:29:13', '2026-05-30 13:29:13', '2026-05-30 14:12:10', NULL, 1800, NULL, '2026-05-30 13:29:13');

--
-- DÃ©clencheurs `consultations`
--
DROP TRIGGER IF EXISTS `trg_consult_enregistrer_duree`;
DELIMITER $$
CREATE TRIGGER `trg_consult_enregistrer_duree` AFTER UPDATE ON `consultations` FOR EACH ROW BEGIN
    IF NEW.statut = 'traite'
       AND OLD.statut != 'traite'
       AND NEW.heure_debut_reelle IS NOT NULL
       AND NEW.heure_fin_reelle   IS NOT NULL THEN

        INSERT INTO historique_durees
            (sous_service_id, consultation_id, medecin_id,
             duree_reelle, heure_debut,
             jour_semaine, tranche_horaire)
        VALUES (
            NEW.sous_service_id,
            NEW.id,
            NEW.medecin_id,
            TIMESTAMPDIFF(SECOND, NEW.heure_debut_reelle, NEW.heure_fin_reelle),
            NEW.heure_debut_reelle,
            DAYOFWEEK(NEW.heure_debut_reelle),   -- 1=Dim â€¦ 7=Sam (MySQL)
            HOUR(NEW.heure_debut_reelle)
        );

        -- IncrÃ©menter le compteur de la session active
        UPDATE session_service
           SET nb_traites = nb_traites + 1
         WHERE gestionnaire_id IN (
             SELECT id FROM gestionnaires WHERE sous_service_id = NEW.sous_service_id
         )
           AND heure_fin IS NULL
        LIMIT 1;

    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_consult_increment_creneaux`;
DELIMITER $$
CREATE TRIGGER `trg_consult_increment_creneaux` AFTER INSERT ON `consultations` FOR EACH ROW BEGIN
    IF NEW.emploi_temps_id IS NOT NULL
       AND NEW.statut IN ('en_attente', 'confirme') THEN
        UPDATE emplois_du_temps
           SET nb_creneaux = nb_creneaux + 1
         WHERE id = NEW.emploi_temps_id;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_consult_liberer_creneau`;
DELIMITER $$
CREATE TRIGGER `trg_consult_liberer_creneau` AFTER UPDATE ON `consultations` FOR EACH ROW BEGIN
    IF NEW.statut IN ('annule', 'absent')
       AND OLD.statut NOT IN ('annule', 'absent')
       AND NEW.emploi_temps_id IS NOT NULL THEN
        UPDATE emplois_du_temps
           SET nb_creneaux = GREATEST(nb_creneaux - 1, 0)
         WHERE id = NEW.emploi_temps_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `emplois_du_temps`
--

DROP TABLE IF EXISTS `emplois_du_temps`;
CREATE TABLE IF NOT EXISTS `emplois_du_temps` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL,
  `medecin_id` int UNSIGNED DEFAULT NULL COMMENT 'MÃ©decin responsable du crÃ©neau',
  `jour` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `nb_creneaux` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Nombre de crÃ©neaux rÃ©servÃ©s sur ce planning',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_edt_ss` (`sous_service_id`),
  KEY `idx_edt_medecin` (`medecin_id`),
  KEY `idx_edt_jour` (`jour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planning journalier des sous-services par mÃ©decin';

-- --------------------------------------------------------

--
-- Structure de la table `gestionnaires`
--

DROP TABLE IF EXISTS `gestionnaires`;
CREATE TABLE IF NOT EXISTS `gestionnaires` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL COMMENT 'Sous-service auquel le gestionnaire est affectÃ©',
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'HachÃ© avec bcrypt (coÃ»t 12)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gestionnaires_email` (`email`),
  UNIQUE KEY `uq_gestionnaires_telephone` (`telephone`),
  KEY `idx_gestionnaires_ss` (`sous_service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Agents de gestion â€” chacun affectÃ© Ã  un sous-service';

--
-- DÃ©chargement des donnÃ©es de la table `gestionnaires`
--

INSERT INTO `gestionnaires` (`id`, `sous_service_id`, `nom`, `telephone`, `email`, `password`, `created_at`) VALUES
(1, 1, 'KEUMBOU YEMLONG CEDRIC BERTINO', '654210976', 'bert1nokembou@gmail.com', '$2y$12$jsw0ZUbMhliSG5VpvJTnuOIk6tgIdkNd00vOCucrNlBp1sfKBwRi2', '2026-04-30 07:26:45'),
(2, 2, 'MOUMI DEUTCHOUA MATHILDE', '677453688', 'mathildemoumi@gmail.com', '$2y$12$KGN5YGC6gmkpEavFlE5xCOhyamLEatMCkU91y0XJtyxyt2e/vTgT.', '2026-05-04 09:23:48'),
(3, 1, 'Kala armand', '699123456', 'kalaarmand@gmail.com', '$2y$12$hsqc31ZKk1G1LNzeyXgiZeH/S65Kf6GqvTzamgwyyzHhgdYFG4NlC', '2026-05-26 15:48:32');

-- --------------------------------------------------------

--
-- Structure de la table `historique_durees`
--

DROP TABLE IF EXISTS `historique_durees`;
CREATE TABLE IF NOT EXISTS `historique_durees` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL,
  `consultation_id` int UNSIGNED DEFAULT NULL,
  `medecin_id` int UNSIGNED DEFAULT NULL,
  `duree_reelle` int UNSIGNED NOT NULL COMMENT 'DurÃ©e rÃ©elle en secondes',
  `heure_debut` datetime NOT NULL,
  `jour_semaine` tinyint UNSIGNED NOT NULL COMMENT '1=Lundi â€¦ 7=Dimanche',
  `tranche_horaire` tinyint UNSIGNED NOT NULL COMMENT 'Heure de dÃ©but 0-23',
  `est_aberrant` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = exclue du calcul (< 60s ou > 10800s)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_ss` (`sous_service_id`),
  KEY `idx_hist_consultation` (`consultation_id`),
  KEY `idx_hist_medecin` (`medecin_id`),
  KEY `idx_hist_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des durÃ©es rÃ©elles â€” base du recalcul nocturne';

--
-- DÃ©chargement des donnÃ©es de la table `historique_durees`
--

INSERT INTO `historique_durees` (`id`, `sous_service_id`, `consultation_id`, `medecin_id`, `duree_reelle`, `heure_debut`, `jour_semaine`, `tranche_horaire`, `est_aberrant`, `created_at`) VALUES
(1, 1, 16, 5, 3, '2026-05-28 23:30:06', 5, 23, 1, '2026-05-28 23:30:09'),
(2, 1, 17, 5, 259, '2026-05-29 12:12:59', 6, 12, 0, '2026-05-29 12:17:18'),
(3, 1, 21, 5, 840, '2026-05-29 13:12:32', 6, 13, 0, '2026-05-29 13:26:32'),
(4, 1, 23, 5, 1908, '2026-05-30 13:27:56', 7, 13, 0, '2026-05-30 13:59:44'),
(5, 1, 24, 5, 697, '2026-05-30 13:59:51', 7, 13, 0, '2026-05-30 14:11:28');

--
-- DÃ©clencheurs `historique_durees`
--
DROP TRIGGER IF EXISTS `trg_historique_aberrant`;
DELIMITER $$
CREATE TRIGGER `trg_historique_aberrant` BEFORE INSERT ON `historique_durees` FOR EACH ROW BEGIN
    IF NEW.duree_reelle < 60 OR NEW.duree_reelle > 10800 THEN
        SET NEW.est_aberrant = 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `logs_estimation`
--

DROP TABLE IF EXISTS `logs_estimation`;
CREATE TABLE IF NOT EXISTS `logs_estimation` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL,
  `date_calcul` date NOT NULL,
  `nb_observations` int UNSIGNED NOT NULL DEFAULT '0',
  `ancienne_duree` int UNSIGNED NOT NULL COMMENT 'DurÃ©e avant recalcul (secondes)',
  `nouvelle_duree` int UNSIGNED NOT NULL COMMENT 'DurÃ©e aprÃ¨s recalcul (secondes)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_ss` (`sous_service_id`),
  KEY `idx_logs_date` (`date_calcul`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TraÃ§abilitÃ© des recalculs nocturnes de la moyenne pondÃ©rÃ©e';

-- --------------------------------------------------------

--
-- Structure de la table `medecins`
--

DROP TABLE IF EXISTS `medecins`;
CREATE TABLE IF NOT EXISTS `medecins` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialite` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_id` int UNSIGNED DEFAULT NULL COMMENT 'Service hospitalier auquel appartient le mÃ©decin',
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('disponible','indisponible','conge') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `photo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chemin vers la photo du mÃ©decin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medecins_email` (`email`),
  KEY `idx_medecins_service` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MÃ©decins intervenant dans les sous-services';

--
-- DÃ©chargement des donnÃ©es de la table `medecins`
--

INSERT INTO `medecins` (`id`, `nom`, `prenom`, `specialite`, `telephone`, `service_id`, `email`, `password`, `statut`, `photo`, `created_at`) VALUES
(1, 'TEWALO NODZE', 'DANIELLE ERICA', 'Cardiologie', '+237678026482', NULL, 'ericatewalo@gmail.com', '$2y$12$J1BR/v.elXD6zDEbmlBw1eOFQe2v2egL1LVzgNefB2Cqcn4rGwvNK', 'disponible', NULL, '2026-04-28 22:04:00'),
(2, 'KEITA', 'FLORA', 'PÃ©diatrie', '+237698003468', NULL, 'keitaflora@gmail.com', '$2y$12$epliY0pZjcOmpCp.QshT8On9hnjvJvNg7dM.Zn48yrAzLQ58ruMIW', 'disponible', NULL, '2026-05-04 09:26:19'),
(3, 'KALA', 'Armand', 'Cardiologie', '+237698013425', 1, 'kalaarmand@gmail.com', '$2y$12$9oxOX.TLXB8fYz4QKYjtVu//8S7GiMT0q7bn34AbtnPsBWzZZC9O6', 'disponible', NULL, '2026-05-07 20:08:14'),
(4, 'MATHILDE', 'MOUMI', 'Cardiologie', '0677453688', 1, 'mathildemoumi@gmail.com', '$2y$12$SZTqKfUEYm1rIEUsaWq5g.QRFOn1Ciw4GzVljW.NICgx0FqVAkwM2', 'disponible', NULL, '2026-05-26 15:50:45'),
(5, 'KOUAM', 'Daris', 'Cardiologie', '659429067', 1, 'dariskouam@gmail.com', '$2y$12$XJ5gFSjiBz/PBaoVCB4IMuKkVCwprcvtqb65rY/sQkNpzFgIAANWK', 'disponible', 'public/uploads/medecins/medecin_6a1ad5727cd39.jpg', '2026-05-26 15:56:33');

-- --------------------------------------------------------

--
-- Structure de la table `medecin_sous_service`
--

DROP TABLE IF EXISTS `medecin_sous_service`;
CREATE TABLE IF NOT EXISTS `medecin_sous_service` (
  `medecin_id` int UNSIGNED NOT NULL,
  `sous_service_id` int UNSIGNED NOT NULL,
  `date_affectation` date DEFAULT NULL,
  PRIMARY KEY (`medecin_id`,`sous_service_id`),
  KEY `idx_mss_ss` (`sous_service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Affectation mÃ©decin â†” sous-service (est_chef = mÃ©decin chef)';

--
-- DÃ©chargement des donnÃ©es de la table `medecin_sous_service`
--

INSERT INTO `medecin_sous_service` (`medecin_id`, `sous_service_id`, `date_affectation`) VALUES
(5, 1, '2026-05-26');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `consultation_id` int UNSIGNED DEFAULT NULL,
  `type` enum('CONFIRMATION','RAPPEL_J1','RAPPEL_15MIN','APPEL_IMMEDIAT','AVANCEMENT','DECALAGE','ANNULATION','CLOTURE_ABSENT','MAJ_HEURE','URGENCE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` enum('FCM','SMS') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FCM',
  `statut` enum('en_attente','envoye','echec','lu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_patient` (`patient_id`),
  KEY `idx_notif_consultation` (`consultation_id`),
  KEY `idx_notif_statut` (`statut`),
  KEY `idx_notif_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications envoyÃ©es aux patients (FCM ou SMS)';

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

DROP TABLE IF EXISTS `patients`;
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL si inscription via QR Code uniquement (anonyme)',
  `token_fcm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token Firebase Cloud Messaging pour les push',
  `statut` enum('actif','suspendu','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_inscription` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patients_email` (`email`),
  UNIQUE KEY `uq_patients_telephone` (`telephone`),
  KEY `idx_patients_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Patients / usagers du systÃ¨me';

--
-- DÃ©chargement des donnÃ©es de la table `patients`
--

INSERT INTO `patients` (`id`, `nom`, `prenom`, `telephone`, `email`, `password`, `token_fcm`, `statut`, `date_inscription`) VALUES
(1, 'kengne', 'marole', '+2375780564', 'marolekengne@gmail.com', NULL, NULL, 'actif', '2026-05-04 10:30:30'),
(2, 'Kengne', 'Marole', '641237897', '641237897@noemail.local', NULL, NULL, 'actif', '2026-05-26 15:58:14'),
(3, 'Nsang', 'Daniel', '654098723', 'danielnsang@gmail.com', NULL, NULL, 'actif', '2026-05-27 14:41:58'),
(4, 'BOULA', 'AICHA', '651995688', 'boulaaicha@gmail.com', NULL, NULL, 'actif', '2026-05-27 19:21:05'),
(5, 'BILOA', 'Harol', '696945237', 'harolbiloa@gmail.com', NULL, NULL, 'actif', '2026-05-28 21:59:12'),
(6, 'KAMENI', 'AUREL', '698805553', 'aurelkameni@gmail.com', NULL, NULL, 'actif', '2026-05-29 12:12:44'),
(7, 'KEITA', 'FLORA', '675430978', 'keitaflora@gmail.com', NULL, NULL, 'actif', '2026-05-29 12:13:49'),
(8, 'KALLU', 'ANGE', '657428938', 'angekallu@gmail.com', NULL, NULL, 'actif', '2026-05-29 12:30:59'),
(9, 'BOBOLO', 'CEDRIC', '678451209', 'cedricbobolo@gmail.com', NULL, NULL, 'actif', '2026-05-29 12:45:12'),
(10, 'MAMI', 'WATER', '612987645', 'mamiwater@gmail.com', NULL, NULL, 'actif', '2026-05-29 13:12:17'),
(11, 'ABANA', 'ALANA', '698776534', 'alanaabana@gmail.com', NULL, NULL, 'actif', '2026-05-29 13:16:15'),
(12, 'ZUTCHI', 'EMMA', '653210000', '653210000@noemail.local', NULL, NULL, 'actif', '2026-05-30 13:27:37'),
(13, 'ZUTCHI', 'COCO', '678665544', 'coco@gmail.com', NULL, NULL, 'actif', '2026-05-30 13:28:30'),
(14, 'ZUTCHI GRACE', 'GRACE', '677889900', 'grace@gmail.com', NULL, NULL, 'actif', '2026-05-30 13:29:13');

-- --------------------------------------------------------

--
-- Structure de la table `qr_codes`
--

DROP TABLE IF EXISTS `qr_codes`;
CREATE TABLE IF NOT EXISTS `qr_codes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL COMMENT 'Sous-service associÃ© au QR code',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token unique pour l''URL du QR code',
  `qr_code_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Chemin vers l''image du QR code',
  `expire_at` datetime NOT NULL COMMENT 'Date d''expiration du QR code',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contenu encodÃ© dans le QR code (URL)',
  `scan_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Nombre de fois que le QR code a Ã©tÃ© scannÃ©',
  `statut` enum('actif','inactif','expire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif' COMMENT 'Statut du QR code',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de crÃ©ation',
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'ID du gestionnaire qui a gÃ©nÃ©rÃ© le QR code',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qrcodes_token` (`token`),
  KEY `idx_qrcodes_ss` (`sous_service_id`),
  KEY `idx_qrcodes_token_idx` (`token`),
  KEY `idx_qrcodes_statut` (`statut`),
  KEY `idx_qrcodes_expire_at` (`expire_at`),
  KEY `idx_qrcodes_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QR codes gÃ©nÃ©rÃ©s pour les sous-services';

--
-- DÃ©chargement des donnÃ©es de la table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `sous_service_id`, `token`, `qr_code_path`, `expire_at`, `content`, `scan_count`, `statut`, `created_at`, `created_by`) VALUES
(1, 1, '88e62349e60757ec2fc5381455d6fb5cf70b86c20905c1f5a8aa33f4977c9018', 'public/qrcodes/qrcode_1_1779897287.png', '2026-05-27 17:14:47', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=88e62349e60757ec2fc5381455d6fb5cf70b86c20905c1f5a8aa33f4977c9018', 0, 'inactif', '2026-05-27 16:54:48', 3),
(2, 1, 'b415c3226a69da302a2d9f7b851ade3f39b6e044e366b6d7cf820c1ce6ade636', 'public/qrcodes/qrcode_1_1779897410.png', '2026-05-27 17:16:50', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=b415c3226a69da302a2d9f7b851ade3f39b6e044e366b6d7cf820c1ce6ade636', 0, 'inactif', '2026-05-27 16:56:50', 3),
(3, 1, '09eb2b29ae471e4010676c99a984039f8960d01ba0356b7bd34fa6754f440886', 'public/qrcodes/qrcode_1_1779897419.png', '2026-05-27 17:16:59', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=09eb2b29ae471e4010676c99a984039f8960d01ba0356b7bd34fa6754f440886', 0, 'inactif', '2026-05-27 16:57:00', 3),
(4, 1, 'b856f9c3e7a297cb74589addfb33ff99145665f9e923f3b23693d60e71137048', 'public/qrcodes/qrcode_1_1779897942.png', '2026-05-27 17:25:42', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=b856f9c3e7a297cb74589addfb33ff99145665f9e923f3b23693d60e71137048', 0, 'expire', '2026-05-27 17:05:43', 3),
(5, 1, '774b8fe48a0d1b33169da5ea8e9e9e864b05ea0b0a395ce827919af1a19deab3', 'public/qrcodes/qrcode_1_1779905987.png', '2026-05-27 19:39:47', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=774b8fe48a0d1b33169da5ea8e9e9e864b05ea0b0a395ce827919af1a19deab3', 0, 'inactif', '2026-05-27 19:19:47', 3),
(6, 1, '7722acd16ada59173d7895a21684ecb38bf964c26f156685858871fdf03a57b2', 'public/qrcodes/qrcode_1_1779908986.png', '2026-05-27 20:29:46', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=7722acd16ada59173d7895a21684ecb38bf964c26f156685858871fdf03a57b2', 0, 'inactif', '2026-05-27 20:09:46', 3),
(7, 1, 'd0292cc33e7c6671376d82a1d6de52b97852132a31d9828cf4c508c62c8ff7db', 'public/qrcodes/qrcode_1_1779910767.png', '2026-05-27 20:59:27', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=d0292cc33e7c6671376d82a1d6de52b97852132a31d9828cf4c508c62c8ff7db', 0, 'expire', '2026-05-27 20:39:27', 3),
(8, 1, '2dfaf9fa18c0c31da0d781e95c430a224a187e9484ca31798c7f3f424f99df76', 'public/qrcodes/qrcode_1_1780005446.png', '2026-05-28 23:17:26', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=2dfaf9fa18c0c31da0d781e95c430a224a187e9484ca31798c7f3f424f99df76', 0, 'inactif', '2026-05-28 22:57:27', 3),
(9, 1, '2270fa95dad27dd21704ef4cbfc724d9bbbb24a705c8a0df076de17b2859c0ff', 'public/qrcodes/qrcode_1_1780006654.png', '2026-05-28 23:37:34', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=2270fa95dad27dd21704ef4cbfc724d9bbbb24a705c8a0df076de17b2859c0ff', 0, 'inactif', '2026-05-28 23:17:34', 3),
(10, 1, '114fb7b749dfc19d7955d454fbcb3bd67c43d3f7449f03922808aaa4bda82c5c', 'public/qrcodes/qrcode_1_1780007855.png', '2026-05-28 23:57:35', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=114fb7b749dfc19d7955d454fbcb3bd67c43d3f7449f03922808aaa4bda82c5c', 0, 'expire', '2026-05-28 23:37:35', 3),
(11, 1, 'e3f5a6d02e9f046b883859cce1d05e156a7484bad1c5359869dc4e8fa7b3286f', 'public/qrcodes/qrcode_1_1780054181.png', '2026-05-29 12:49:41', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=e3f5a6d02e9f046b883859cce1d05e156a7484bad1c5359869dc4e8fa7b3286f', 0, 'expire', '2026-05-29 12:29:41', 3),
(12, 1, 'd03833b887a7411bae9ff94d5b1d4d458ad509c5e8b0561b443f0b5282aa1927', 'public/qrcodes/qrcode_1_1780147520.png', '2026-05-30 14:45:20', 'http://localhost/fil-attente2/index.php?action=prise_rdv&token=d03833b887a7411bae9ff94d5b1d4d458ad509c5e8b0561b443f0b5282aa1927', 0, 'actif', '2026-05-30 14:25:21', 3);

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

DROP TABLE IF EXISTS `services`;
CREATE TABLE IF NOT EXISTS `services` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de l''hÃ´pital / Ã©tablissement',
  `description` text COLLATE utf8mb4_unicode_ci,
  `adresse` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `horaires` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex : Lun-Ven 07h00-17h00',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_services_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='HÃ´pitaux et Ã©tablissements de santÃ©';

--
-- DÃ©chargement des donnÃ©es de la table `services`
--

INSERT INTO `services` (`id`, `nom`, `description`, `adresse`, `horaires`, `statut`, `created_at`) VALUES
(1, 'CMA Tyo de Baleng', '', 'PMI, entrÃ©e Ã©cole normale', 'Lun-Ven 7h00-19h00', 'actif', '2026-04-28 22:00:13');

-- --------------------------------------------------------

--
-- Structure de la table `session_service`
--

DROP TABLE IF EXISTS `session_service`;
CREATE TABLE IF NOT EXISTS `session_service` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `gestionnaire_id` int UNSIGNED NOT NULL,
  `sous_service_id` int UNSIGNED NOT NULL,
  `heure_debut` datetime NOT NULL,
  `heure_fin` datetime DEFAULT NULL,
  `nb_traites` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Nombre de consultations traitÃ©es durant cette session',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sess_gestionnaire` (`gestionnaire_id`),
  KEY `idx_sess_ss` (`sous_service_id`),
  KEY `idx_sess_date` (`heure_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessions de travail des gestionnaires';

-- --------------------------------------------------------

--
-- Structure de la table `sous_services`
--

DROP TABLE IF EXISTS `sous_services`;
CREATE TABLE IF NOT EXISTS `sous_services` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int UNSIGNED NOT NULL COMMENT 'HÃ´pital auquel appartient ce dÃ©partement',
  `nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex : HÃ©matologie, Oncologie',
  `description` text COLLATE utf8mb4_unicode_ci,
  `duree_rdv_defaut` int UNSIGNED NOT NULL DEFAULT '1800' COMMENT 'DurÃ©e par dÃ©faut en secondes (30 min)',
  `duree_estimee` int UNSIGNED NOT NULL DEFAULT '1800' COMMENT 'DurÃ©e estimÃ©e recalculÃ©e chaque nuit par moyenne pondÃ©rÃ©e (secondes)',
  `capacite_horaire` int UNSIGNED NOT NULL DEFAULT '10' COMMENT 'Nombre max de consultations par heure',
  `qr_code` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Contenu / token du QR Code dynamique propre Ã  ce sous-service',
  `qr_expire_at` datetime DEFAULT NULL COMMENT 'Date d''expiration du QR Code (rÃ©gÃ©nÃ©rÃ© pÃ©riodiquement)',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ss_service` (`service_id`),
  KEY `idx_ss_statut` (`statut`),
  KEY `idx_ss_qrcode` (`qr_code`(191))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DÃ©partements mÃ©dicaux (HÃ©matologie, Oncologieâ€¦) â€” niveau QR Code';

--
-- DÃ©chargement des donnÃ©es de la table `sous_services`
--

INSERT INTO `sous_services` (`id`, `service_id`, `nom`, `description`, `duree_rdv_defaut`, `duree_estimee`, `capacite_horaire`, `qr_code`, `qr_expire_at`, `statut`, `created_at`) VALUES
(1, 1, 'Cardiologie', 'Pour tous les problÃ¨mes de coeur', 1800, 1800, 10, NULL, NULL, 'actif', '2026-04-30 07:09:25'),
(2, 1, 'PÃ©diatrie', 'Pour les soins de santÃ© des enfants de moins de 12 ans', 1800, 1800, 10, NULL, NULL, 'actif', '2026-04-30 07:10:24');

-- --------------------------------------------------------

--
-- Structure de la table `urgences`
--

DROP TABLE IF EXISTS `urgences`;
CREATE TABLE IF NOT EXISTS `urgences` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int UNSIGNED NOT NULL COMMENT 'HÃ´pital qui dÃ©clare l''urgence',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `priorite` tinyint UNSIGNED NOT NULL DEFAULT '1' COMMENT '1=haute 2=moyenne 3=basse',
  `statut` enum('ouverte','en_cours','cloturee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_urgences_service` (`service_id`),
  KEY `idx_urgences_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Urgences dÃ©clarÃ©es par les hÃ´pitaux';

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_file_attente`
-- (Voir ci-dessous la vue rÃ©elle)
--
DROP VIEW IF EXISTS `v_file_attente`;
CREATE TABLE IF NOT EXISTS `v_file_attente` (
`consultation_id` int unsigned
,`rang` int unsigned
,`statut` enum('en_attente','confirme','en_cours','traite','annule','absent')
,`mode_prise` enum('LIGNE','PLACE','MANUEL','QR_CODE')
,`heure_passage_estimee` datetime
,`motif` text
,`patient_nom` varchar(100)
,`patient_prenom` varchar(100)
,`patient_telephone` varchar(20)
,`sous_service_nom` varchar(200)
,`duree_estimee_sec` int unsigned
,`service_nom` varchar(200)
,`medecin_nom` varchar(201)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_qrcodes_actifs`
-- (Voir ci-dessous la vue rÃ©elle)
--
DROP VIEW IF EXISTS `v_qrcodes_actifs`;
CREATE TABLE IF NOT EXISTS `v_qrcodes_actifs` (
`id` int unsigned
,`sous_service_id` int unsigned
,`token` varchar(255)
,`qr_code_path` varchar(500)
,`expire_at` datetime
,`content` text
,`scan_count` int unsigned
,`statut` enum('actif','inactif','expire')
,`created_at` datetime
,`created_by` int unsigned
,`sous_service_nom` varchar(200)
,`service_nom` varchar(200)
,`gestionnaire_nom` varchar(150)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_sous_services_complet`
-- (Voir ci-dessous la vue rÃ©elle)
--
DROP VIEW IF EXISTS `v_sous_services_complet`;
CREATE TABLE IF NOT EXISTS `v_sous_services_complet` (
`ss_id` int unsigned
,`ss_nom` varchar(200)
,`duree_estimee` int unsigned
,`capacite_horaire` int unsigned
,`qr_code` varchar(500)
,`ss_statut` enum('actif','inactif')
,`service_id` int unsigned
,`service_nom` varchar(200)
,`service_adresse` varchar(300)
,`gestionnaire_id` int unsigned
,`gestionnaire_nom` varchar(150)
,`gestionnaire_telephone` varchar(20)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_stats_jour`
-- (Voir ci-dessous la vue rÃ©elle)
--
DROP VIEW IF EXISTS `v_stats_jour`;
CREATE TABLE IF NOT EXISTS `v_stats_jour` (
`sous_service_id` int unsigned
,`sous_service_nom` varchar(200)
,`service_id` int unsigned
,`service_nom` varchar(200)
,`jour` date
,`total_consultations` bigint
,`traitees` decimal(23,0)
,`en_attente` decimal(23,0)
,`absentes` decimal(23,0)
,`annulees` decimal(23,0)
,`prises_en_ligne` decimal(23,0)
,`prises_sur_place` decimal(23,0)
,`duree_reelle_moy_sec` decimal(21,0)
);

-- --------------------------------------------------------

--
-- Structure de la vue `v_file_attente`
--
DROP TABLE IF EXISTS `v_file_attente`;

DROP VIEW IF EXISTS `v_file_attente`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_file_attente`  AS SELECT `c`.`id` AS `consultation_id`, `c`.`rang` AS `rang`, `c`.`statut` AS `statut`, `c`.`mode_prise` AS `mode_prise`, `c`.`heure_passage_estimee` AS `heure_passage_estimee`, `c`.`motif` AS `motif`, `p`.`nom` AS `patient_nom`, `p`.`prenom` AS `patient_prenom`, `p`.`telephone` AS `patient_telephone`, `ss`.`nom` AS `sous_service_nom`, `ss`.`duree_estimee` AS `duree_estimee_sec`, `s`.`nom` AS `service_nom`, concat(`m`.`prenom`,' ',`m`.`nom`) AS `medecin_nom` FROM ((((`consultations` `c` join `patients` `p` on((`p`.`id` = `c`.`patient_id`))) join `sous_services` `ss` on((`ss`.`id` = `c`.`sous_service_id`))) join `services` `s` on((`s`.`id` = `ss`.`service_id`))) left join `medecins` `m` on((`m`.`id` = `c`.`medecin_id`))) WHERE ((`c`.`statut` in ('en_attente','confirme','en_cours')) AND (cast(`c`.`heure_emission` as date) = curdate())) ORDER BY `ss`.`id` ASC, `c`.`rang` ASC  ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_qrcodes_actifs`
--
DROP TABLE IF EXISTS `v_qrcodes_actifs`;

DROP VIEW IF EXISTS `v_qrcodes_actifs`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_qrcodes_actifs`  AS SELECT `qc`.`id` AS `id`, `qc`.`sous_service_id` AS `sous_service_id`, `qc`.`token` AS `token`, `qc`.`qr_code_path` AS `qr_code_path`, `qc`.`expire_at` AS `expire_at`, `qc`.`content` AS `content`, `qc`.`scan_count` AS `scan_count`, `qc`.`statut` AS `statut`, `qc`.`created_at` AS `created_at`, `qc`.`created_by` AS `created_by`, `ss`.`nom` AS `sous_service_nom`, `s`.`nom` AS `service_nom`, `g`.`nom` AS `gestionnaire_nom` FROM (((`qr_codes` `qc` join `sous_services` `ss` on((`ss`.`id` = `qc`.`sous_service_id`))) join `services` `s` on((`s`.`id` = `ss`.`service_id`))) left join `gestionnaires` `g` on((`g`.`id` = `qc`.`created_by`))) WHERE ((`qc`.`statut` = 'actif') AND (`qc`.`expire_at` > now())) ORDER BY `qc`.`created_at` AS `DESCdesc` ASC  ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_sous_services_complet`
--
DROP TABLE IF EXISTS `v_sous_services_complet`;

DROP VIEW IF EXISTS `v_sous_services_complet`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_sous_services_complet`  AS SELECT `ss`.`id` AS `ss_id`, `ss`.`nom` AS `ss_nom`, `ss`.`duree_estimee` AS `duree_estimee`, `ss`.`capacite_horaire` AS `capacite_horaire`, `ss`.`qr_code` AS `qr_code`, `ss`.`statut` AS `ss_statut`, `s`.`id` AS `service_id`, `s`.`nom` AS `service_nom`, `s`.`adresse` AS `service_adresse`, `g`.`id` AS `gestionnaire_id`, `g`.`nom` AS `gestionnaire_nom`, `g`.`telephone` AS `gestionnaire_telephone` FROM ((`sous_services` `ss` join `services` `s` on((`s`.`id` = `ss`.`service_id`))) left join `gestionnaires` `g` on((`g`.`sous_service_id` = `ss`.`id`)))  ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_stats_jour`
--
DROP TABLE IF EXISTS `v_stats_jour`;

DROP VIEW IF EXISTS `v_stats_jour`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_stats_jour`  AS SELECT `ss`.`id` AS `sous_service_id`, `ss`.`nom` AS `sous_service_nom`, `s`.`id` AS `service_id`, `s`.`nom` AS `service_nom`, cast(`c`.`heure_emission` as date) AS `jour`, count(`c`.`id`) AS `total_consultations`, sum((`c`.`statut` = 'traite')) AS `traitees`, sum((`c`.`statut` in ('en_attente','confirme'))) AS `en_attente`, sum((`c`.`statut` = 'absent')) AS `absentes`, sum((`c`.`statut` = 'annule')) AS `annulees`, sum((`c`.`mode_prise` = 'LIGNE')) AS `prises_en_ligne`, sum((`c`.`mode_prise` = 'PLACE')) AS `prises_sur_place`, round(avg((case when ((`c`.`heure_debut_reelle` is not null) and (`c`.`heure_fin_reelle` is not null)) then timestampdiff(SECOND,`c`.`heure_debut_reelle`,`c`.`heure_fin_reelle`) end)),0) AS `duree_reelle_moy_sec` FROM ((`consultations` `c` join `sous_services` `ss` on((`ss`.`id` = `c`.`sous_service_id`))) join `services` `s` on((`s`.`id` = `ss`.`service_id`))) GROUP BY `ss`.`id`, `ss`.`nom`, `s`.`id`, `s`.`nom`, cast(`c`.`heure_emission` as date)  ;

--
-- Contraintes pour les tables dÃ©chargÃ©es
--

--
-- Contraintes pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consult_edt` FOREIGN KEY (`emploi_temps_id`) REFERENCES `emplois_du_temps` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_qrcode` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `emplois_du_temps`
--
ALTER TABLE `emplois_du_temps`
  ADD CONSTRAINT `fk_edt_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_edt_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `gestionnaires`
--
ALTER TABLE `gestionnaires`
  ADD CONSTRAINT `fk_gestionnaires_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `historique_durees`
--
ALTER TABLE `historique_durees`
  ADD CONSTRAINT `fk_hist_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `logs_estimation`
--
ALTER TABLE `logs_estimation`
  ADD CONSTRAINT `fk_logs_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `medecins`
--
ALTER TABLE `medecins`
  ADD CONSTRAINT `fk_medecins_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `medecin_sous_service`
--
ALTER TABLE `medecin_sous_service`
  ADD CONSTRAINT `fk_mss_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mss_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `fk_qrcodes_created_by` FOREIGN KEY (`created_by`) REFERENCES `gestionnaires` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qrcodes_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `session_service`
--
ALTER TABLE `session_service`
  ADD CONSTRAINT `fk_sess_gestionnaire` FOREIGN KEY (`gestionnaire_id`) REFERENCES `gestionnaires` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sess_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `sous_services`
--
ALTER TABLE `sous_services`
  ADD CONSTRAINT `fk_ss_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `urgences`
--
ALTER TABLE `urgences`
  ADD CONSTRAINT `fk_urgences_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

DELIMITER $$
--
-- Ã‰vÃ¨nements
--
DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

