<?php
/**
 * models/QRCodeModel.php
 * Modèle pour la gestion des QR codes
 */

require_once __DIR__ . '/../config/database.php';

class QRCodeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
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

    public function getActiveQRCode(int $sousServiceId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM qr_codes 
             WHERE sous_service_id = :ss_id 
               AND expire_at > NOW()
               AND statut = "actif"
             ORDER BY created_at DESC 
             LIMIT 1'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetch();
    }

    public function saveQRCode(array $data): int
    {
        // Désactiver les anciens QR codes
        $stmt = $this->db->prepare(
            'UPDATE qr_codes SET statut = "inactif" 
             WHERE sous_service_id = :ss_id AND statut = "actif"'
        );
        $stmt->execute([':ss_id' => $data['sous_service_id']]);

        // Insérer le nouveau QR code
        $stmt = $this->db->prepare(
            'INSERT INTO qr_codes 
             (sous_service_id, token, qr_code_path, expire_at, content, statut, created_by, created_at)
             VALUES 
             (:ss_id, :token, :path, :expire_at, :content, "actif", :created_by, NOW())'
        );
        $stmt->execute([
            ':ss_id' => $data['sous_service_id'],
            ':token' => $data['token'],
            ':path' => $data['qr_code_path'],
            ':expire_at' => $data['expire_at'],
            ':content' => $data['content'],
            ':created_by' => $data['created_by'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }

    public function verifierToken(string $token)
    {
        $stmt = $this->db->prepare(
            'SELECT qc.*, ss.nom as sous_service_nom, s.nom as service_nom
             FROM qr_codes qc
             JOIN sous_services ss ON ss.id = qc.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE qc.token = :token 
               AND qc.statut = "actif"
               AND qc.expire_at > NOW()'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }

    public function incrementerScan(int $qrCodeId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $qrCodeId]);
    }
}