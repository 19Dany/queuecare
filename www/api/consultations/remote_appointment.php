<?php
$auth  = requireAuth();
$body  = getBody();
$ss_id = (int)   ($body['ss_id']    ?? 0);
$motif = trim($body['motif'] ?? '');

if (!$ss_id) jsonError('ss_id requis', 'MISSING_FIELDS');

$db = getDB();

// Vérifier que le sous-service existe
$stmt = $db->prepare("SELECT id, duree_estimee, nom FROM sous_services WHERE id = ? AND statut = 'actif' LIMIT 1");
$stmt->execute([$ss_id]);
$ss = $stmt->fetch();
if (!$ss) jsonError('Sous-service introuvable', 'NOT_FOUND', 404);

// Vérifier pas de doublon aujourd'hui
$stmt = $db->prepare(
    "SELECT id FROM consultations
     WHERE patient_id = ? AND sous_service_id = ?
       AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('traite', 'annule', 'absent') LIMIT 1"
);
$stmt->execute([$auth['sub'], $ss_id]);
if ($stmt->fetch()) jsonError('Vous avez déjà une consultation active pour ce service', 'DUPLICATE_TICKET', 409);

// Calculer rang
$stmt = $db->prepare(
    "SELECT COUNT(*) AS nb FROM consultations
     WHERE sous_service_id = ? AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('annule', 'absent')"
);
$stmt->execute([$ss_id]);
$rang = (int) $stmt->fetch()['nb'] + 1;

$duree        = (int) $ss['duree_estimee'];
$heurePassage = date('Y-m-d H:i:s', time() + ($rang - 1) * $duree);

$stmt = $db->prepare(
    "INSERT INTO consultations
       (patient_id, sous_service_id, statut, rang, mode_prise,
        heure_emission, heure_passage_estimee, duree_estimee, motif)
     VALUES (?, ?, 'confirme', ?, 'LIGNE', NOW(), ?, ?, ?)"
);
$stmt->execute([$auth['sub'], $ss_id, $rang, $heurePassage, $duree, $motif ?: null]);
$newId = (int) $db->lastInsertId();

jsonSuccess([
    'id'                    => $newId,
    'rang'                  => $rang,
    'statut'                => 'confirme',
    'heure_passage_estimee' => $heurePassage,
    'message'               => "Rendez-vous confirmé. Rang #{$rang}",
], 'Rendez-vous créé', 201);
