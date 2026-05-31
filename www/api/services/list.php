<?php
$db   = getDB();
$stmt = $db->query(
    "SELECT id, nom, description, adresse, horaires, statut
     FROM services WHERE statut = 'actif' ORDER BY nom"
);

$services = array_map(fn($r) => [
    'id'          => (int) $r['id'],
    'nom'         => $r['nom'],
    'name'        => $r['nom'],
    'description' => $r['description'],
    'adresse'     => $r['adresse'],
    'horaires'    => $r['horaires'],
], $stmt->fetchAll());

jsonSuccess($services);
