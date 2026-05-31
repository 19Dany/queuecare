<?php
/**
 * medecin.php — Point d'entrée espace médecin
 * Routeur principal pour toutes les actions des médecins
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
    // API
    'api_sous_services',
    // Actions AJAX consultations
    'get_consultations_data', 'get_stats_data',
    'demarrer_consultation_ajax', 'terminer_consultation_ajax',
    'marquer_absent_ajax', 'annuler_toutes_ajax',
    // Planning
    'get_planning_medecin',
    // Profil
    'get_profil_data', 'mettre_a_jour_profil'
];

if (!in_array($action, $allowedActions)) {
    http_response_code(404);
    die('Page introuvable.');
}

require_once __DIR__ . '/controllers/MedecinController.php';

$ctrl = new MedecinController();

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
    
    // API
    case 'api_sous_services':
        $ctrl->apiSousServices();
        break;
    
    // Actions AJAX consultations
    case 'get_consultations_data':
        $ctrl->getConsultationsData();
        break;
    
    case 'get_stats_data':
        $ctrl->getStatsData();
        break;
    
    case 'demarrer_consultation_ajax':
        $ctrl->demarrerConsultationAjax();
        break;
    
    case 'terminer_consultation_ajax':
        $ctrl->terminerConsultationAjax();
        break;
    
    case 'marquer_absent_ajax':
        $ctrl->marquerAbsentAjax();
        break;
    
    case 'annuler_toutes_ajax':
        $ctrl->annulerToutesAjax();
        break;
    
    // Planning
    case 'get_planning_medecin':
        $ctrl->getPlanningMedecin();
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