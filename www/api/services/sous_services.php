<?php
$serviceId = (int) ($_GET['service_id'] ?? 0);
if (!$serviceId) jsonError('service_id invalide', 'INVALID_PARAM');

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id, nom, description, duree_estimee, duree_rdv_defaut, capacite_horaire
     FROM sous_services
     WHERE service_id = ? AND statut = 'actif'
     ORDER BY nom"
);
$stmt->execute([$serviceId]);

$result = array_map(fn($r) => [
    'id'               => (int) $r['id'],
    'nom'              => $r['nom'],
    'name'             => $r['nom'],
    'description'      => $r['description'],
    // duree_estimee est en secondes dans ta BDD
    'duree_estimee'    => (int) $r['duree_estimee'],
    'dureeRDV'         => (int) $r['duree_rdv_defaut'],
    'duree_rdv'        => (int) $r['duree_rdv_defaut'],
    'capacite_horaire' => (int) $r['capacite_horaire'],
], $stmt->fetchAll());

jsonSuccess($result);
