<?php
/**
 * models/MedecinModel.php
 * Modèle MVC — Médecin
 */

require_once __DIR__ . '/../config/database.php';

class MedecinModel
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
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM medecins WHERE email = :e');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function telephoneExiste(string $tel): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM medecins WHERE telephone = :t');
        $stmt->execute([':t' => trim($tel)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function creer(array $d): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medecins (nom, prenom, specialite, telephone, email, password, service_id, statut, photo)
             VALUES (:nom, :prenom, :specialite, :telephone, :email, :password, :service_id, "disponible", :photo)'
        );
        return $stmt->execute([
            ':nom'        => htmlspecialchars(trim($d['nom'])),
            ':prenom'     => htmlspecialchars(trim($d['prenom'])),
            ':specialite' => htmlspecialchars(trim($d['specialite'])),
            ':telephone'  => trim($d['telephone']),
            ':email'      => strtolower(trim($d['email'])),
            ':password'   => password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            ':service_id' => !empty($d['service_id']) ? (int)$d['service_id'] : null,
            ':photo'      => $d['photo'] ?? null,
        ]);
    }

    public function dernierID(): int
    {
        return (int)$this->db->lastInsertId();
    }

    public function affecterSousService(int $medecinId, int $ssId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medecin_sous_service (medecin_id, sous_service_id, date_affectation)
             VALUES (:mid, :ssid, CURDATE())
             ON DUPLICATE KEY UPDATE date_affectation = date_affectation'
        );
        return $stmt->execute([':mid' => $medecinId, ':ssid' => $ssId]);
    }

    public function trouverParEmail(string $email)
    {
        $stmt = $this->db->prepare('SELECT * FROM medecins WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return $stmt->fetch();
    }

    public function trouverParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM medecins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getSousServiceMedecin(int $medecinId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id AS ss_id, ss.nom AS ss_nom, ss.duree_estimee,
                    ss.capacite_horaire, ss.qr_code,
                    s.id AS service_id, s.nom AS service_nom, s.adresse
             FROM medecin_sous_service mss
             JOIN sous_services ss ON ss.id = mss.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE mss.medecin_id = :mid
             LIMIT 1'
        );
        $stmt->execute([':mid' => $medecinId]);
        return $stmt->fetch();
    }

    /* ════════════════════════════════════════════════════════
       GESTION DU PROFIL
    ════════════════════════════════════════════════════════ */

    public function mettreAJourProfil(int $id, string $nom, string $prenom, string $telephone): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE medecins SET nom = :nom, prenom = :prenom, telephone = :telephone WHERE id = :id'
        );
        return $stmt->execute([
            ':nom' => htmlspecialchars(trim($nom)),
            ':prenom' => htmlspecialchars(trim($prenom)),
            ':telephone' => trim($telephone),
            ':id' => $id
        ]);
    }

    public function mettreAJourEmail(int $id, string $email): bool
    {
        $stmt = $this->db->prepare('UPDATE medecins SET email = :email WHERE id = :id');
        return $stmt->execute([
            ':email' => strtolower(trim($email)),
            ':id' => $id
        ]);
    }

    public function mettreAJourMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('UPDATE medecins SET password = :password WHERE id = :id');
        return $stmt->execute([
            ':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ':id' => $id
        ]);
    }

    public function mettreAJourPhoto(int $id, ?string $photoPath): bool
    {
        $stmt = $this->db->prepare('UPDATE medecins SET photo = :photo WHERE id = :id');
        return $stmt->execute([
            ':photo' => $photoPath,
            ':id' => $id
        ]);
    }

    public function verifierMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT password FROM medecins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $hash = $stmt->fetchColumn();
        return password_verify($password, $hash);
    }

    /* ════════════════════════════════════════════════════════
       LISTES POUR LES SELECTS
    ════════════════════════════════════════════════════════ */

    public function getServices(): array
    {
        $stmt = $this->db->query(
            'SELECT id, nom, adresse FROM services WHERE statut = "actif" ORDER BY nom'
        );
        return $stmt->fetchAll();
    }

    public function getSousServices(): array
    {
        $stmt = $this->db->query(
            'SELECT ss.id, ss.nom, s.nom AS service_nom, s.id AS service_id
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.statut = "actif" AND s.statut = "actif"
             ORDER BY s.nom, ss.nom'
        );
        return $stmt->fetchAll();
    }

    public function getSousServicesActifsParService(int $serviceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id, ss.nom, ss.capacite_horaire, ss.duree_estimee
             FROM sous_services ss
             WHERE ss.service_id = :sid AND ss.statut = "actif"
             ORDER BY ss.nom'
        );
        $stmt->execute([':sid' => $serviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function trouverSousServiceParNom(string $nom, int $serviceId): int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM sous_services
             WHERE TRIM(nom) = TRIM(:nom) AND service_id = :sid AND statut = "actif"
             LIMIT 1'
        );
        $stmt->execute([':nom' => $nom, ':sid' => $serviceId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD MÉDECIN
    ════════════════════════════════════════════════════════ */

    public function statsJour(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
               COUNT(*) AS total,
               SUM(statut = "traite") AS traitees,
               SUM(statut IN ("en_attente","confirme")) AS en_attente,
               SUM(statut = "absent") AS absentes,
               SUM(statut = "annule") AS annulees,
               COALESCE(ROUND(AVG(
                 CASE WHEN heure_debut_reelle IS NOT NULL
                       AND heure_fin_reelle IS NOT NULL
                      THEN TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)
                 END
               )), 0) AS duree_moy_sec
             FROM consultations
             WHERE sous_service_id = :ss
               AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetch() ?: [];
    }

    public function consultationsDuJour(int $ssId, int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.rang, c.statut, c.mode_prise,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss
               AND c.medecin_id = :mid
               AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang'
        );
        $stmt->execute([':ss' => $ssId, ':mid' => $medecinId]);
        return $stmt->fetchAll();
    }

    public function planningMedecin(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT edt.id, edt.jour, edt.heure_debut, edt.heure_fin,
                    edt.nb_creneaux, ss.nom AS ss_nom, s.nom AS service_nom
             FROM emplois_du_temps edt
             JOIN sous_services ss ON ss.id = edt.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE edt.medecin_id = :mid
               AND edt.jour BETWEEN
                   DATE(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY))
                   AND DATE(DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY))
             ORDER BY edt.jour, edt.heure_debut'
        );
        $stmt->execute([':mid' => $medecinId]);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════════════════
       ACTIONS SUR LES CONSULTATIONS
    ════════════════════════════════════════════════════════ */

    public function demarrerConsultation(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "en_cours", heure_debut_reelle = NOW() 
             WHERE id = :id AND statut IN ("en_attente", "confirme")'
        );
        return $stmt->execute([':id' => $consultationId]);
    }

    public function terminerConsultation(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "traite", heure_fin_reelle = NOW() 
             WHERE id = :id AND statut = "en_cours"'
        );
        return $stmt->execute([':id' => $consultationId]);
    }

    public function marquerAbsent(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "absent" 
             WHERE id = :id AND statut IN ("en_attente", "confirme", "en_cours")'
        );
        return $stmt->execute([':id' => $consultationId]);
    }

    public function annulerConsultation(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "annule" 
             WHERE id = :id AND statut NOT IN ("traite")'
        );
        return $stmt->execute([':id' => $consultationId]);
    }

    public function annulerToutesConsultations(int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "annule" 
             WHERE medecin_id = :mid 
               AND DATE(heure_emission) = CURDATE() 
               AND statut NOT IN ("traite", "annule", "absent")'
        );
        return $stmt->execute([':mid' => $medecinId]);
    }

    public function majStatutConsultation(int $id, string $statut): bool
    {
        $allowed = ['en_attente', 'confirme', 'en_cours', 'traite', 'annule', 'absent'];
        if (!in_array($statut, $allowed)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE consultations SET statut = :s WHERE id = :id');
        return $stmt->execute([':s' => $statut, ':id' => $id]);
    }

    /**
     * Appelle le patient suivant - CORRIGÉ
     * Le patient ne démarre pas automatiquement, reste en attente
     */
    public function appelerPatientSuivant(int $consultationId): void
    {
        $stmt = $this->db->prepare(
            'SELECT sous_service_id, medecin_id FROM consultations WHERE id = :id'
        );
        $stmt->execute([':id' => $consultationId]);
        $consult = $stmt->fetch();
        
        if (!$consult) return;
        
        $ssId = $consult['sous_service_id'];
        $medecinId = $consult['medecin_id'];
        
        // Ne pas démarrer automatiquement, seulement marquer comme "en attente"
        // Le médecin devra cliquer sur "Démarrer" manuellement
    }

    /* ════════════════════════════════════════════════════════
       CHEF DE SERVICE — GESTION DES SOUS-SERVICES
    ════════════════════════════════════════════════════════ */

    public function creerSousService(array $d): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sous_services (service_id, nom, description, duree_rdv_defaut, duree_estimee, capacite_horaire)
             VALUES (:sid, :nom, :desc, :duree, :duree, :capa)'
        );
        return $stmt->execute([
            ':sid'   => (int)$d['service_id'],
            ':nom'   => htmlspecialchars(trim($d['nom'])),
            ':desc'  => htmlspecialchars(trim($d['description'] ?? '')),
            ':duree' => (int)($d['duree_rdv_defaut'] ?? 1800),
            ':capa'  => (int)($d['capacite_horaire'] ?? 10),
        ]);
    }

    public function getSousServicesDeService(int $serviceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id, ss.nom, ss.description, ss.duree_estimee,
                    ss.capacite_horaire, ss.statut,
                    (SELECT COUNT(*) FROM gestionnaires g WHERE g.sous_service_id = ss.id) AS nb_gestionnaires,
                    (SELECT COUNT(*) FROM medecin_sous_service mss2 WHERE mss2.sous_service_id = ss.id) AS nb_medecins
             FROM sous_services ss
             WHERE ss.service_id = :sid
             ORDER BY ss.nom'
        );
        $stmt->execute([':sid' => $serviceId]);
        return $stmt->fetchAll();
    }
}