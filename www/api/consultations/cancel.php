<?php
$auth           = requireAuth();
$consultationId = (int) ($_GET['consultation_id'] ?? 0);
if (!$consultationId) jsonError('consultation_id invalide', 'INVALID_PARAM');

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id, statut, patient_id, sous_service_id, rang
     FROM consultations WHERE id = ? AND patient_id = ? LIMIT 1"
);
$stmt->execute([$consultationId, $auth['sub']]);
$consultation = $stmt->fetch();

if (!$consultation) jsonError('Consultation introuvable', 'NOT_FOUND', 404);

if (in_array($consultation['statut'], ['traite', 'annule', 'absent'])) {
    jsonError('Cette consultation ne peut plus être annulée', 'CANNOT_CANCEL', 400);
}

// Annuler la consultation
$db->prepare("UPDATE consultations SET statut = 'annule' WHERE id = ?")
   ->execute([$consultationId]);

// Notifier le patient
$db->prepare(
    "INSERT INTO notifications (patient_id, consultation_id, type, contenu, canal, statut)
     VALUES (?, ?, 'ANNULATION', 'Votre ticket a été annulé avec succès.', 'FCM', 'en_attente')"
)->execute([$auth['sub'], $consultationId]);

jsonSuccess(null, 'Consultation annulée avec succès');
