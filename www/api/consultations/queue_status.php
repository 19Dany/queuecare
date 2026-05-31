<?php
$auth           = requireAuth();
$consultationId = (int) ($_GET['consultation_id'] ?? 0);
if (!$consultationId) jsonError('consultation_id invalide', 'INVALID_PARAM');

$db   = getDB();
$stmt = $db->prepare(
    "SELECT c.*, ss.duree_estimee, ss.nom AS ss_nom, s.nom AS s_nom
     FROM consultations c
     JOIN sous_services ss ON c.sous_service_id = ss.id
     JOIN services s       ON ss.service_id = s.id
     WHERE c.id = ? AND c.patient_id = ?
     LIMIT 1"
);
$stmt->execute([$consultationId, $auth['sub']]);
$consultation = $stmt->fetch();

if (!$consultation) jsonError('Consultation introuvable', 'NOT_FOUND', 404);

// Compter combien de patients sont avant ce patient dans la file
$stmt = $db->prepare(
    "SELECT COUNT(*) AS nb FROM consultations
     WHERE sous_service_id = ?
       AND DATE(heure_emission) = DATE(?)
       AND statut IN ('en_attente', 'confirme', 'en_cours')
       AND rang < ?"
);
$stmt->execute([
    $consultation['sous_service_id'],
    $consultation['heure_emission'],
    $consultation['rang'],
]);
$patientsAvant = (int) $stmt->fetch()['nb'];

// duree_estimee est en secondes
$dureeSecondes   = (int) $consultation['duree_estimee'];
$tempsAttenteSec = $patientsAvant * $dureeSecondes;
$heurePassage    = date('Y-m-d H:i:s', time() + $tempsAttenteSec);

jsonSuccess([
    'consultation_id'       => $consultationId,
    'rang'                  => (int) $consultation['rang'],
    'patients_avant'        => $patientsAvant,
    'temps_attente_sec'     => $tempsAttenteSec,
    'temps_attente_min'     => (int) round($tempsAttenteSec / 60),
    'heure_passage_estimee' => $heurePassage,
    'statut'                => $consultation['statut'],
    'service'               => $consultation['s_nom'],
    'sous_service'          => $consultation['ss_nom'],
]);
