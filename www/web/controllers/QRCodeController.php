<?php
/**
 * controllers/QRCodeController.php
 * Contrôleur pour la gestion des QR codes
 */

require_once __DIR__ . '/../models/QRCodeModel.php';

// Inclure la librairie PHP QR Code
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';


class QRCodeController
{
    private QRCodeModel $model;
    private string $qrcodeDir;

    public function __construct()
    {
        $this->model = new QRCodeModel();
        $this->qrcodeDir = __DIR__ . '/../public/qrcodes/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($this->qrcodeDir)) {
            mkdir($this->qrcodeDir, 0777, true);
        }
    }

    /**
     * Vérifie si le gestionnaire est connecté
     */
    private function checkAuth(): bool
    {
        if (!isset($_SESSION['gestionnaire_id'])) {
            return false;
        }
        return true;
    }

    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /**
     * Affiche l'interface de génération de QR code
     */
    public function afficherGenerateur(): void
    {
        if (!$this->checkAuth()) {
            if ($this->isAjaxRequest()) {
                echo json_encode(['error' => 'Non authentifié']);
                exit;
            }
            header('Location: gestionnaire.php?action=connexion');
            exit;
        }
        
        $gestionnaireId = $_SESSION['gestionnaire_id'];
        $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
        
        if (!$sousService) {
            die('Aucun sous-service trouvé pour ce gestionnaire.');
        }
        
        require __DIR__ . '/../views/qrcode/generate.php';
    }

    /**
     * Génère un nouveau QR code (via AJAX)
     */
    public function genererQRCode(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->checkAuth()) {
            echo json_encode(['error' => 'Non authentifié', 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }

        $sousServiceId = $_POST['sous_service_id'] ?? $_SESSION['sous_service_id'] ?? null;
        
        if (!$sousServiceId) {
            $gestionnaireId = $_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
            $sousServiceId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $sousServiceId;
        }
        
        if (!$sousServiceId) {
            echo json_encode(['error' => 'Sous-service non spécifié']);
            exit;
        }
        
        // Générer un token unique
        $token = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+20 minutes'));
        
        // Créer l'URL du QR code
        $baseUrl = $this->getBaseUrl();
        $qrContent = $baseUrl . '/index.php?action=prise_rdv&token=' . $token;
        
        // Générer l'image QR code
        $filename = 'qrcode_' . $sousServiceId . '_' . time() . '.png';
        $filepath = $this->qrcodeDir . $filename;
        
        // Utiliser la librairie PHP QR Code
        \QRcode::png($qrContent, $filepath, QR_ECLEVEL_L, 10);
        
        // Sauvegarder en base de données
        $qrCodeId = $this->model->saveQRCode([
            'sous_service_id' => $sousServiceId,
            'token' => $token,
            'qr_code_path' => 'public/qrcodes/' . $filename,
            'expire_at' => $expireAt,
            'content' => $qrContent,
            'created_by' => $_SESSION['gestionnaire_id']
        ]);
        
        if ($qrCodeId) {
            // Nettoyer les anciens fichiers
            $this->cleanOldQRCodeFiles($sousServiceId);
            
            echo json_encode([
                'success' => true,
                'qr_code_path' => 'qrcodes/' . $filename,
                'token' => $token,
                'expire_at' => $expireAt,
                'content' => $qrContent
            ]);
        } else {
            echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
        }
    }

    private function cleanOldQRCodeFiles(int $sousServiceId): void
    {
        $files = glob($this->qrcodeDir . 'qrcode_' . $sousServiceId . '_*.png');
        if (count($files) > 10) {
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $toDelete = array_slice($files, 10);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Récupère le QR code actif (via AJAX)
     */
    public function getQRCodeActif(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->checkAuth()) {
            echo json_encode(['error' => 'Non authentifié', 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }

        $sousServiceId = $_GET['sous_service_id'] ?? $_SESSION['sous_service_id'] ?? null;
        
        if (!$sousServiceId) {
            $gestionnaireId = $_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
            $sousServiceId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $sousServiceId;
        }
        
        if (!$sousServiceId) {
            echo json_encode(['error' => 'Sous-service non spécifié']);
            exit;
        }

        $qrCodeInfo = $this->model->getActiveQRCode($sousServiceId);
        
        if ($qrCodeInfo) {
            echo json_encode([
                'success' => true,
                'qr_code_path' => $qrCodeInfo['qr_code_path'],
                'token' => $qrCodeInfo['token'],
                'expire_at' => $qrCodeInfo['expire_at']
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    /**
     * Télécharge le QR code
     */
    public function telechargerQRCode(): void
    {
        if (!$this->checkAuth()) {
            header('Location: gestionnaire.php?action=connexion');
            exit;
        }
        
        $path = $_GET['path'] ?? '';
        if (!$path) {
            die('Chemin non spécifié');
        }
        
        $fullPath = __DIR__ . '/../' . $path;
        if (!file_exists($fullPath)) {
            die('Fichier non trouvé');
        }
        
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qrcode_consultation.png"');
        readfile($fullPath);
    }

    private function getBaseUrl(): string
    {
        $configured = getenv('QUEUECARE_PUBLIC_URL')
            ?: getenv('APP_PUBLIC_URL')
            ?: getenv('PUBLIC_BASE_URL')
            ?: '';

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/');

        return $protocol . '://' . $host . ($script !== '/' ? $script : '');
    }
}
