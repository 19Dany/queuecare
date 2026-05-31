<?php
$auth      = requireAuth();
$patientId = (int) ($_GET['patient_id'] ?? 0);

if ($patientId && $patientId !== (int) $auth['sub']) {
    jsonError('Accès non autorisé', 'FORBIDDEN', 403);
}

$targetId = $patientId ?: (int) $auth['sub'];
$db       = getDB();

$stmt = $db->prepare(
    "SELECT id, type, contenu, canal, statut, sent_at, created_at, consultation_id
     FROM notifications
     WHERE patient_id = ?
     ORDER BY created_at DESC
     LIMIT 50"
);
$stmt->execute([$targetId]);

$notifications = array_map(fn($r) => [
    'id'              => (int) $r['id'],
    'type'            => $r['type'],
    'contenu'         => $r['contenu'],
    'message'         => $r['contenu'],
    'canal'           => $r['canal'],
    'statut'          => $r['statut'],
    'status'          => $r['statut'],
    'sent_at'         => $r['sent_at'],
    'created_at'      => $r['created_at'],
    'consultation_id' => $r['consultation_id'] ? (int) $r['consultation_id'] : null,
], $stmt->fetchAll());

// Marquer comme lues
$db->prepare(
    "UPDATE notifications SET statut = 'lu' WHERE patient_id = ? AND statut = 'envoye'"
)->execute([$targetId]);

jsonSuccess($notifications);
