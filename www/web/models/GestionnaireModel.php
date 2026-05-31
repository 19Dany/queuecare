<?php
/**
 * models/GestionnaireModel.php
 * Modèle MVC — Gestionnaire
 */

require_once __DIR__ . '/../config/database.php';

class GestionnaireModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ════════════════════════════════════════════════════════
       AUTHENTIFICATION & COMPTE
    ════════════════════════════════════════════════════════ */

    public function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gestionnaires WHERE email = :e');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function telephoneExiste(string $telephone): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gestionnaires WHERE telephone = :t');
        $stmt->execute([':t' => trim($telephone)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function creer(array $d): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gestionnaires (nom, telephone, email, password, sous_service_id)
             VALUES (:nom, :telephone, :email, :password, :sous_service_id)'
        );
        return $stmt->execute([
            ':nom'             => htmlspecialchars(trim($d['nom'])),
            ':telephone'       => trim($d['telephone']),
            ':email'           => strtolower(trim($d['email'])),
            ':password'        => password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            ':sous_service_id' => (int)$d['sous_service_id'],
        ]);
    }

    public function trouverParEmail(string $email)
    {
        $stmt = $this->db->prepare('SELECT * FROM gestionnaires WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return $stmt->fetch();
    }

    public function trouverParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT id, nom, telephone, email, sous_service_id FROM gestionnaires WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function dernierID(): int
    {
        return (int)$this->db->lastInsertId();
    }

    /* ════════════════════════════════════════════════════════
       GESTION DU PROFIL
    ════════════════════════════════════════════════════════ */

    public function mettreAJourProfil(int $id, string $nom, string $telephone): bool
    {
        $stmt = $this->db->prepare('UPDATE gestionnaires SET nom = :nom, telephone = :telephone WHERE id = :id');
        return $stmt->execute([
            ':nom' => htmlspecialchars(trim($nom)),
            ':telephone' => trim($telephone),
            ':id' => $id
        ]);
    }

    public function mettreAJourEmail(int $id, string $email): bool
    {
        $stmt = $this->db->prepare('UPDATE gestionnaires SET email = :email WHERE id = :id');
        return $stmt->execute([
            ':email' => strtolower(trim($email)),
            ':id' => $id
        ]);
    }

    public function mettreAJourMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('UPDATE gestionnaires SET password = :password WHERE id = :id');
        return $stmt->execute([
            ':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ':id' => $id
        ]);
    }

    public function verifierMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT password FROM gestionnaires WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $hash = $stmt->fetchColumn();
        return password_verify($password, $hash);
    }

    /* ════════════════════════════════════════════════════════
       SOUS-SERVICES
    ════════════════════════════════════════════════════════ */

    public function getSousServices(): array
    {
        $stmt = $this->db->query(
            'SELECT ss.id, ss.nom, s.nom AS service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.statut = "actif" AND s.statut = "actif"
             ORDER BY s.nom, ss.nom'
        );
        return $stmt->fetchAll();
    }

    public function getSousServiceGestionnaire(int $gestionnaireId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id, ss.nom, ss.duree_estimee, ss.duree_rdv_defaut,
                    ss.capacite_horaire, ss.qr_code,
                    s.nom AS service_nom, s.adresse AS service_adresse
             FROM gestionnaires g
             JOIN sous_services ss ON ss.id = g.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE g.id = :gid LIMIT 1'
        );
        $stmt->execute([':gid' => $gestionnaireId]);
        return $stmt->fetch();
    }

    public function getSousServiceByGestionnaire(int $gestionnaireId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id, ss.nom, ss.service_id, s.nom as service_nom, s.adresse
             FROM gestionnaires g
             JOIN sous_services ss ON ss.id = g.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE g.id = :gid'
        );
        $stmt->execute([':gid' => $gestionnaireId]);
        return $stmt->fetch();
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD — STATISTIQUES
    ════════════════════════════════════════════════════════ */

    public function statsJour(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
               COUNT(*) AS total,
               SUM(statut = "traite") AS traitees,
               SUM(statut IN ("en_attente", "confirme")) AS en_attente,
               SUM(statut = "absent") AS absentes,
               SUM(statut = "annule") AS annulees,
               SUM(mode_prise = "LIGNE") AS en_ligne,
               SUM(mode_prise = "PLACE") AS sur_place
             FROM consultations
             WHERE sous_service_id = :ss AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetch() ?: [];
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD — FILE D'ATTENTE
    ════════════════════════════════════════════════════════ */

    public function fileAttente(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.rang, c.statut, c.mode_prise, c.heure_passage_estimee, c.motif,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone,
                    CONCAT(m.prenom, " ", m.nom) AS medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.sous_service_id = :ss
               AND c.statut IN ("en_attente", "confirme", "en_cours")
               AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll();
    }

    public function consultationsJour(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.rang, c.statut, c.mode_prise, c.heure_passage_estimee,
                    c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone,
                    CONCAT(m.prenom, " ", m.nom) AS medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.sous_service_id = :ss AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════════════════
       ACTIONS GESTIONNAIRE
    ════════════════════════════════════════════════════════ */

    public function majStatutConsultation(int $consultationId, string $statut): bool
    {
        $stmt = $this->db->prepare('UPDATE consultations SET statut = :s WHERE id = :id');
        return $stmt->execute([':s' => $statut, ':id' => $consultationId]);
    }

    public function appelerSuivant(int $ssId)
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.rang, p.nom, p.prenom, p.telephone
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss
               AND c.statut = "en_attente"
               AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang LIMIT 1'
        );
        $stmt->execute([':ss' => $ssId]);
        $suivant = $stmt->fetch();
        if ($suivant) {
            $this->majStatutConsultation($suivant['id'], 'en_cours');
        }
        return $suivant;
    }

    /* ════════════════════════════════════════════════════════
       URGENCES
    ════════════════════════════════════════════════════════ */

    public function urgencesOuvertes(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.description, u.priorite, u.statut, u.created_at
             FROM urgences u
             JOIN sous_services ss ON ss.service_id = u.service_id
             WHERE ss.id = :ss AND u.statut != "cloturee"
             ORDER BY u.priorite ASC, u.created_at DESC'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════════════════
       CONSULTATION MANUELLE - CORRIGÉE
       Le système assigne automatiquement le médecin le moins chargé
    ════════════════════════════════════════════════════════ */

    public function patientADejaConsultationAujourdhui(int $patientId, int $ssId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM consultations 
             WHERE patient_id = :patient_id 
               AND sous_service_id = :ss_id 
               AND DATE(heure_emission) = CURDATE()
               AND statut NOT IN ("annule", "absent", "traite")'
        );
        $stmt->execute([
            ':patient_id' => $patientId,
            ':ss_id' => $ssId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function rechercherOuCreerPatient(array $d): int
    {
        $telephone = trim($d['telephone']);
        $email     = trim($d['email']);
        $nom       = htmlspecialchars(trim($d['nom']));
        $prenom    = htmlspecialchars(trim($d['prenom']));

        $stmt = $this->db->prepare('SELECT id FROM patients WHERE telephone = :tel LIMIT 1');
        $stmt->execute([':tel' => $telephone]);
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];

        if (!empty($email)) {
            $stmt = $this->db->prepare('SELECT id FROM patients WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch();
            if ($row) return (int)$row['id'];
        }

        $emailFinal = !empty($email) ? $email : $telephone . '@noemail.local';
        $stmt = $this->db->prepare(
            'INSERT INTO patients (nom, prenom, telephone, email, statut)
             VALUES (:nom, :prenom, :tel, :email, "actif")'
        );
        $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':tel'    => $telephone,
            ':email'  => $emailFinal,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function prochainRang(int $ssId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(rang), 0) + 1
             FROM consultations
             WHERE sous_service_id = :ss AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':ss' => $ssId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Enregistre une consultation manuelle - CORRIGÉE
     * Le système assigne automatiquement le médecin le moins chargé
     * Pas besoin de sélectionner un médecin manuellement
     */
    public function enregistrerConsultationManuelle(array $d): int
    {
        $ssId      = (int)$d['sous_service_id'];
        $patientId = (int)$d['patient_id'];
        
        if ($this->patientADejaConsultationAujourdhui($patientId, $ssId)) {
            return 0;
        }
        
        $modePriseDB = 'PLACE';
        $statut = 'en_attente';
        if (isset($d['statut']) && in_array($d['statut'], ['en_attente', 'confirme'])) {
            $statut = $d['statut'];
        }
        
        // Assignation automatique du médecin le moins chargé
        $medecinId = $this->choisirMedecinMoinsOccupe($ssId);

        $rang = $this->prochainRang($ssId);
        $dureeEstimee = $this->getDureeEstimee($ssId);
        $heureDebut = date('Y-m-d H:i:s');
        $motif = !empty($d['motif']) ? htmlspecialchars(trim($d['motif'])) : null;

        $sql = "INSERT INTO consultations 
                (patient_id, sous_service_id, medecin_id, statut, rang, mode_prise, 
                 heure_emission, heure_passage_estimee, duree_estimee, motif)
                VALUES 
                (:patient_id, :sous_service_id, :medecin_id, :statut, :rang, :mode_prise,
                 NOW(), :heure_passage_estimee, :duree_estimee, :motif)";
        
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute([
            ':patient_id'           => $patientId,
            ':sous_service_id'      => $ssId,
            ':medecin_id'           => $medecinId,
            ':statut'               => $statut,
            ':rang'                 => $rang,
            ':mode_prise'           => $modePriseDB,
            ':heure_passage_estimee'=> $heureDebut,
            ':duree_estimee'        => $dureeEstimee,
            ':motif'                => $motif
        ]);
        
        if (!$result) {
            return 0;
        }
        
        return (int)$this->db->lastInsertId();
    }

    /**
     * Sélectionne le médecin le moins occupé du sous-service
     * Compte les consultations en cours et en attente
     */
    public function choisirMedecinMoinsOccupe(int $ssId): int
    {
        $stmt = $this->db->prepare(
            'SELECT m.id,
                    COALESCE(occ.nb, 0) AS nb_consultations
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             LEFT JOIN (
                 SELECT medecin_id, COUNT(*) AS nb
                 FROM consultations
                 WHERE sous_service_id = :ss1
                   AND DATE(heure_emission) = CURDATE()
                   AND statut IN ("en_attente","confirme","en_cours")
                 GROUP BY medecin_id
             ) occ ON occ.medecin_id = m.id
             WHERE mss.sous_service_id = :ss2 AND m.statut = "disponible"
             ORDER BY COALESCE(occ.nb, 0) ASC, m.id ASC
             LIMIT 1'
        );
        $stmt->execute([':ss1' => $ssId, ':ss2' => $ssId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : 0;
    }

    public function getDureeEstimee(int $ssId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(duree_estimee, duree_rdv_defaut, 1800) FROM sous_services WHERE id = :id');
        $stmt->execute([':id' => $ssId]);
        return (int)($stmt->fetchColumn() ?: 1800);
    }

    public function getMedecinsDisponibles(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.nom, m.prenom, m.specialite, m.statut,
                    COALESCE(occ.nb, 0) AS nb_en_attente
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             LEFT JOIN (
                 SELECT medecin_id, COUNT(*) AS nb
                 FROM consultations
                 WHERE sous_service_id = :ss2
                   AND DATE(heure_emission) = CURDATE()
                   AND statut IN ("en_attente","confirme","en_cours")
                 GROUP BY medecin_id
             ) occ ON occ.medecin_id = m.id
             WHERE mss.sous_service_id = :ss AND m.statut = "disponible"
             ORDER BY occ.nb ASC, m.nom'
        );
        $stmt->execute([':ss' => $ssId, ':ss2' => $ssId]);
        return $stmt->fetchAll();
    }

    public function chercherPatientParTel(string $telephone)
    {
        $stmt = $this->db->prepare('SELECT id, nom, prenom, telephone, email FROM patients WHERE telephone = :tel LIMIT 1');
        $stmt->execute([':tel' => $telephone]);
        return $stmt->fetch();
    }

    /* ════════════════════════════════════════════════════════
       HORAIRES ET PLANNING
    ════════════════════════════════════════════════════════ */

    public function getHorairesJour(int $ssId, ?string $date = null): array
    {
        if (!$date) $date = date('Y-m-d');
        
        $stmt = $this->db->prepare(
            'SELECT edt.*, m.nom as medecin_nom, m.prenom as medecin_prenom
             FROM emplois_du_temps edt
             LEFT JOIN medecins m ON m.id = edt.medecin_id
             WHERE edt.sous_service_id = :ss AND edt.jour = :date
             ORDER BY edt.heure_debut'
        );
        $stmt->execute([':ss' => $ssId, ':date' => $date]);
        return $stmt->fetchAll();
    }
}