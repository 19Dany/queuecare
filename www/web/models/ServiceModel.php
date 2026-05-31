<?php
/**
 * models/ServiceModel.php
 * Modèle MVC — Service (hôpital / établissement de santé)
 * Table : services (id, nom, description, adresse, horaires, statut, created_at)
 */
require_once __DIR__ . '/../config/database.php';

class ServiceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ══════════════════════════════════════════════════
       LISTE & LECTURE
    ══════════════════════════════════════════════════ */

    /** Retourne tous les services avec stats associées */
    public function getAll(): array
    {
        // Essayer d'abord via service_id (colonne directe sur medecins)
        // Si la colonne n'existe pas encore en BD, fallback via medecin_sous_service
        try {
            $stmt = $this->db->query(
                'SELECT s.*,
                   (SELECT COUNT(*) FROM sous_services ss
                    WHERE ss.service_id = s.id) AS nb_sous_services,
                   (SELECT COUNT(DISTINCT g.id) FROM gestionnaires g
                    JOIN sous_services ss2 ON ss2.id = g.sous_service_id
                    WHERE ss2.service_id = s.id) AS nb_gestionnaires,
                   (SELECT COUNT(DISTINCT m.id) FROM medecins m
                    WHERE m.service_id = s.id) AS nb_medecins
                 FROM services s
                 ORDER BY s.nom'
            );
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Fallback : service_id absent en BD → compter via medecin_sous_service
            $stmt = $this->db->query(
                'SELECT s.*,
                   (SELECT COUNT(*) FROM sous_services ss
                    WHERE ss.service_id = s.id) AS nb_sous_services,
                   (SELECT COUNT(DISTINCT g.id) FROM gestionnaires g
                    JOIN sous_services ss2 ON ss2.id = g.sous_service_id
                    WHERE ss2.service_id = s.id) AS nb_gestionnaires,
                   (SELECT COUNT(DISTINCT mss.medecin_id) FROM medecin_sous_service mss
                    JOIN sous_services ss3 ON ss3.id = mss.sous_service_id
                    WHERE ss3.service_id = s.id) AS nb_medecins
                 FROM services s
                 ORDER BY s.nom'
            );
            return $stmt->fetchAll();
        }
    }

    /** Retourne un service par ID */
    public function trouverParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM services WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /** Vérifie si un service avec ce nom existe déjà */
    public function nomExiste(string $nom, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM services WHERE LOWER(TRIM(nom)) = LOWER(TRIM(:nom)) AND id != :eid'
        );
        $stmt->execute([':nom' => $nom, ':eid' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /* ══════════════════════════════════════════════════
       CRÉATION
    ══════════════════════════════════════════════════ */

    /**
     * Crée un service hospitalier
     * Colonnes : nom, description, adresse, horaires, statut
     */
    public function creer(array $d): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO services (nom, description, adresse, horaires, statut)
             VALUES (:nom, :description, :adresse, :horaires, :statut)'
        );
        return $stmt->execute([
            ':nom'         => htmlspecialchars(trim($d['nom'])),
            ':description' => htmlspecialchars(trim($d['description'] ?? '')),
            ':adresse'     => htmlspecialchars(trim($d['adresse'])),
            ':horaires'    => htmlspecialchars(trim($d['horaires'] ?? '')),
            ':statut'      => in_array($d['statut'] ?? 'actif', ['actif','inactif']) ? $d['statut'] : 'actif',
        ]);
    }

    public function dernierID(): int
    {
        return (int)$this->db->lastInsertId();
    }

    /* ══════════════════════════════════════════════════
       MODIFICATION / SUPPRESSION
    ══════════════════════════════════════════════════ */

    public function modifier(int $id, array $d): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE services
             SET nom = :nom, description = :description, adresse = :adresse,
                 horaires = :horaires, statut = :statut
             WHERE id = :id'
        );
        return $stmt->execute([
            ':id'          => $id,
            ':nom'         => htmlspecialchars(trim($d['nom'])),
            ':description' => htmlspecialchars(trim($d['description'] ?? '')),
            ':adresse'     => htmlspecialchars(trim($d['adresse'])),
            ':horaires'    => htmlspecialchars(trim($d['horaires'] ?? '')),
            ':statut'      => in_array($d['statut'] ?? 'actif', ['actif','inactif']) ? $d['statut'] : 'actif',
        ]);
    }

    public function basculerStatut(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE services
             SET statut = IF(statut = "actif", "inactif", "actif")
             WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]);
    }

    /* ══════════════════════════════════════════════════
       SOUS-SERVICES D'UN SERVICE
    ══════════════════════════════════════════════════ */

    /** Retourne tous les sous-services d'un service avec stats */
    public function getSousServicesParService(int $serviceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*,
               (SELECT COUNT(*) FROM gestionnaires g WHERE g.sous_service_id = ss.id) AS nb_gestionnaires,
               (SELECT COUNT(*) FROM medecin_sous_service mss WHERE mss.sous_service_id = ss.id) AS nb_medecins
             FROM sous_services ss
             WHERE ss.service_id = :sid
             ORDER BY ss.nom'
        );
        $stmt->execute([':sid' => $serviceId]);
        return $stmt->fetchAll();
    }

    /** Retourne un sous-service par ID */
    public function trouverSsParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM sous_services WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /** Vérifie si un nom de sous-service existe déjà dans un service */
    public function nomSsExiste(string $nom, int $serviceId, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM sous_services
             WHERE LOWER(TRIM(nom)) = LOWER(TRIM(:nom))
               AND service_id = :sid AND id != :eid'
        );
        $stmt->execute([':nom' => $nom, ':sid' => $serviceId, ':eid' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Crée un sous-service
     * Colonnes : service_id, nom, description, duree_rdv_defaut, duree_estimee, capacite_horaire
     */
    public function creerSousService(array $d): bool
    {
        $duree = max(60, (int)($d['duree_rdv_defaut'] ?? 1800));
        $capa  = max(1,  (int)($d['capacite_horaire'] ?? 10));
        $stmt = $this->db->prepare(
            'INSERT INTO sous_services
               (service_id, nom, description, duree_rdv_defaut, duree_estimee, capacite_horaire, statut)
             VALUES (:sid, :nom, :desc, :duree_rdv, :duree_est, :capa, "actif")'
        );
        return $stmt->execute([
            ':sid'       => (int)$d['service_id'],
            ':nom'       => htmlspecialchars(trim($d['nom'])),
            ':desc'      => htmlspecialchars(trim($d['description'] ?? '')),
            ':duree_rdv' => $duree,
            ':duree_est' => $duree,
            ':capa'      => $capa,
        ]);
    }

    /** Modifie un sous-service */
    public function modifierSousService(int $id, array $d): bool
    {
        $duree = max(60, (int)($d['duree_rdv_defaut'] ?? 1800));
        $capa  = max(1,  (int)($d['capacite_horaire'] ?? 10));
        $stmt = $this->db->prepare(
            'UPDATE sous_services
             SET nom = :nom, description = :desc, duree_rdv_defaut = :duree,
                 capacite_horaire = :capa, statut = :statut
             WHERE id = :id'
        );
        return $stmt->execute([
            ':id'    => $id,
            ':nom'   => htmlspecialchars(trim($d['nom'])),
            ':desc'  => htmlspecialchars(trim($d['description'] ?? '')),
            ':duree' => $duree,
            ':capa'  => $capa,
            ':statut'=> in_array($d['statut'] ?? 'actif', ['actif','inactif']) ? $d['statut'] : 'actif',
        ]);
    }

    /** Bascule le statut d'un sous-service */
    public function basculerStatutSs(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services
             SET statut = IF(statut = "actif", "inactif", "actif")
             WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]);
    }

    /** Supprime un sous-service (vérifie qu'il n'a aucune dépendance) */
    public function supprimerSousService(int $id): array
    {
        // Vérifier les dépendances avant suppression
        $stmt = $this->db->prepare(
            'SELECT
               (SELECT COUNT(*) FROM gestionnaires WHERE sous_service_id = :id)         AS nb_gest,
               (SELECT COUNT(*) FROM medecin_sous_service WHERE sous_service_id = :id2) AS nb_med,
               (SELECT COUNT(*) FROM consultations WHERE sous_service_id = :id3)        AS nb_consult'
        );
        $stmt->execute([':id' => $id, ':id2' => $id, ':id3' => $id]);
        $deps = $stmt->fetch();

        if ((int)$deps['nb_gest'] > 0)
            return ['ok' => false, 'msg' => "Impossible : {$deps['nb_gest']} gestionnaire(s) sont affectés à ce sous-service."];
        if ((int)$deps['nb_med'] > 0)
            return ['ok' => false, 'msg' => "Impossible : {$deps['nb_med']} médecin(s) sont affectés à ce sous-service."];
        if ((int)$deps['nb_consult'] > 0)
            return ['ok' => false, 'msg' => "Impossible : {$deps['nb_consult']} consultation(s) existent dans ce sous-service."];

        $stmt2 = $this->db->prepare('DELETE FROM sous_services WHERE id = :id');
        $ok    = $stmt2->execute([':id' => $id]);
        return ['ok' => $ok, 'msg' => $ok ? 'Sous-service supprimé.' : 'Erreur lors de la suppression.'];
    }

    public function dernierIdSs(): int
    {
        return (int)$this->db->lastInsertId();
    }
}
