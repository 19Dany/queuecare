<?php
$auth    = requireAuth();
$body    = getBody();
$token   = trim($body['qr_code'] ?? $body['token'] ?? '');
$motif   = trim($body['motif']   ?? '');

if (!$token) jsonError('qr_code requis', 'MISSING_FIELDS');

$db = getDB();

// 1. Trouver le QR code actif via son token
$stmt = $db->prepare(
    "SELECT qr.id AS qr_id, qr.sous_service_id,
            ss.nom AS ss_nom, ss.duree_estimee, ss.duree_rdv_defaut,
            s.id  AS s_id,   s.nom AS s_nom
     FROM qr_codes qr
     JOIN sous_services ss ON qr.sous_service_id = ss.id
     JOIN services s       ON ss.service_id = s.id
     WHERE qr.token = ?
       AND qr.statut = 'actif'
       AND qr.expire_at > NOW()
     LIMIT 1"
);
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) jsonError('QR code invalide ou expiré', 'INVALID_QR', 404);

// 2. Vérifier qu'il n'a pas déjà un ticket actif aujourd'hui
$stmt = $db->prepare(
    "SELECT id FROM consultations
     WHERE patient_id = ? AND sous_service_id = ?
       AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('traite', 'annule', 'absent')
     LIMIT 1"
);
$stmt->execute([$auth['sub'], $qr['sous_service_id']]);
if ($stmt->fetch()) {
    jsonError('Vous avez déjà un ticket actif pour ce service aujourd\'hui', 'DUPLICATE_TICKET', 409);
}

// 3. Calculer le rang (nombre de tickets actifs aujourd'hui)
$stmt = $db->prepare(
    "SELECT COUNT(*) AS nb FROM consultations
     WHERE sous_service_id = ?
       AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('annule', 'absent')"
);
$stmt->execute([$qr['sous_service_id']]);
$rang = (int) $stmt->fetch()['nb'] + 1;

// 4. Calculer l'heure de passage estimée
// duree_estimee est en secondes
$dureeSecondes    = (int) $qr['duree_estimee'] ?: (int) $qr['duree_rdv_defaut'];
$tempsAttenteSecondes = ($rang - 1) * $dureeSecondes;
$heurePassage     = date('Y-m-d H:i:s', time() + $tempsAttenteSecondes);

// 5. Créer la consultation
$stmt = $db->prepare(
    "INSERT INTO consultations
       (patient_id, sous_service_id, qr_code_id, statut, rang,
        mode_prise, heure_emission, heure_passage_estimee, duree_estimee, motif)
     VALUES (?, ?, ?, 'en_attente', ?, 'QR_CODE', NOW(), ?, ?, ?)"
);
$stmt->execute([
    $auth['sub'],
    $qr['sous_service_id'],
    $qr['qr_id'],
    $rang,
    $heurePassage,
    $dureeSecondes,
    $motif ?: null,
]);
$consultationId = (int) $db->lastInsertId();

// 6. Incrémenter le scan_count du QR
$db->prepare("UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = ?")
   ->execute([$qr['qr_id']]);

// 7. Créer une notification de confirmation
$db->prepare(
    "INSERT INTO notifications (patient_id, consultation_id, type, contenu, canal, statut)
     VALUES (?, ?, 'CONFIRMATION', ?, 'FCM', 'en_attente')"
)->execute([
    $auth['sub'],
    $consultationId,
    "Ticket #{$rang} créé pour {$qr['ss_nom']}. Passage estimé à " . date('H:i', strtotime($heurePassage)),
]);

jsonSuccess([
    'consultation_id'       => $consultationId,
    'rang'                  => $rang,
    'heure_estimee'         => $heurePassage,
    'heure_passage_estimee' => $heurePassage,
    'message'               => "Ticket #{$rang} créé. Passage estimé à " . date('H:i', strtotime($heurePassage)),
    'consultation' => [
        'id'                    => $consultationId,
        'statut'                => 'en_attente',
        'rang'                  => $rang,
        'heure_passage_estimee' => $heurePassage,
        'heure_emission'        => date('Y-m-d H:i:s'),
        'mode_prise'            => 'QR_CODE',
        'motif'                 => $motif ?: null,
        'service'               => ['id' => (int)$qr['s_id'],           'nom' => $qr['s_nom']],
        'sousService'           => ['id' => (int)$qr['sous_service_id'], 'nom' => $qr['ss_nom']],
        'sous_service'          => ['id' => (int)$qr['sous_service_id'], 'nom' => $qr['ss_nom']],
    ],
]);
