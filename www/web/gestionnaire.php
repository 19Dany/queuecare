<?php
/**
 * gestionnaire.php — Point d'entrée espace gestionnaire
 * Routeur principal pour toutes les actions des gestionnaires
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_GET['action'] ?? 'connexion';

$allowedActions = [
    // Authentification
    'inscription', 'connexion', 'dashboard', 'deconnexion',
    // QR Code
    'generer_qrcode', 'get_qrcode_actif', 'telecharger_qrcode',
    // Planning
    'get_planning', 'get_emploi_temps',
    // Actions AJAX
    'get_dashboard_data', 'traiter_action_ajax',
    // Profil
    'get_profil_data', 'mettre_a_jour_profil'
];

if (!in_array($action, $allowedActions)) {
    http_response_code(404);
    die('Page introuvable.');
}

require_once __DIR__ . '/controllers/GestionnaireController.php';
require_once __DIR__ . '/controllers/QRCodeController.php';

$ctrl   = new GestionnaireController();
$qrCtrl = new QRCodeController();

switch ($action) {
    // Authentification
    case 'inscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ctrl->traiterInscription();
        } else {
            $ctrl->afficherInscription();
        }
        break;
    
    case 'connexion':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ctrl->traiterConnexion();
        } else {
            $ctrl->afficherConnexion();
        }
        break;
    
    case 'dashboard':
        $ctrl->afficherDashboard();
        break;
    
    case 'deconnexion':
        $ctrl->deconnecter();
        break;
    
    // QR Code
    case 'generer_qrcode':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $qrCtrl->genererQRCode();
        } else {
            $qrCtrl->afficherGenerateur();
        }
        break;
    
    case 'get_qrcode_actif':
        $qrCtrl->getQRCodeActif();
        break;
    
    case 'telecharger_qrcode':
        $qrCtrl->telechargerQRCode();
        break;
    
    // Planning
    case 'get_planning':
        $ctrl->getPlanning();
        break;
    
    case 'get_emploi_temps':
        $ctrl->getEmploiTemps();
        break;
    
    // Actions AJAX
    case 'get_dashboard_data':
        $ctrl->getDashboardData();
        break;
    
    case 'traiter_action_ajax':
        $ctrl->traiterActionAjax();
        break;
    
    // Profil
    case 'get_profil_data':
        $ctrl->getProfilData();
        break;
    
    case 'mettre_a_jour_profil':
        $ctrl->mettreAJourProfil();
        break;
    
    default:
        $ctrl->afficherConnexion();
        break;
}