<?php
/**
 * service.php — Point d'entrée de la gestion des services hospitaliers
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$action = $_GET['action'] ?? 'liste';
$ok     = ['liste', 'creer', 'modifier', 'basculer_statut',
           'creer_ss', 'modifier_ss', 'basculer_statut_ss', 'supprimer_ss'];

if (!in_array($action, $ok)) {
    http_response_code(404);
    die('Page introuvable.');
}

require __DIR__ . '/controllers/ServiceController.php';
