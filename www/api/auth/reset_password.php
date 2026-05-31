<?php
$body         = getBody();
$contact      = trim($body['contact']      ?? '');
$otp_code     = trim($body['otp_code']     ?? '');
$new_password = trim($body['new_password'] ?? '');

if (!$contact || !$otp_code || !$new_password) {
    jsonError('Tous les champs sont requis', 'MISSING_FIELDS');
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id FROM patients WHERE email = ? OR telephone = ? LIMIT 1"
);
$stmt->execute([$contact, $contact]);
$patient = $stmt->fetch();

if (!$patient) jsonError('Contact introuvable', 'NOT_FOUND', 404);

$stmt = $db->prepare(
    "SELECT id FROM password_resets
     WHERE patient_id = ? AND otp_code = ? AND expires_at > NOW() LIMIT 1"
);
$stmt->execute([$patient['id'], $otp_code]);
if (!$stmt->fetch()) {
    jsonError('Code OTP invalide ou expiré', 'INVALID_OTP', 401);
}

$db->prepare("UPDATE patients SET password = ? WHERE id = ?")
   ->execute([password_hash($new_password, PASSWORD_BCRYPT), $patient['id']]);

$db->prepare("DELETE FROM password_resets WHERE patient_id = ?")
   ->execute([$patient['id']]);

jsonSuccess(null, 'Mot de passe réinitialisé avec succès');
