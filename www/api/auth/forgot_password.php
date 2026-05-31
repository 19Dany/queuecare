<?php
$body    = getBody();
$contact = trim($body['contact'] ?? '');

if (!$contact) jsonError('Email ou téléphone requis', 'MISSING_FIELDS');

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id FROM patients WHERE email = ? OR telephone = ? AND statut = 'actif' LIMIT 1"
);
$stmt->execute([$contact, $contact]);
$patient = $stmt->fetch();

// Sécurité : on ne révèle pas si l'utilisateur existe
if (!$patient) {
    jsonSuccess(null, 'Si ce contact existe, un code a été envoyé.');
}

$otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 900); // 15 min

// Stocker dans une table temporaire (créée si elle n'existe pas)
$db->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$db->prepare("DELETE FROM password_resets WHERE patient_id = ?")
   ->execute([$patient['id']]);

$db->prepare("INSERT INTO password_resets (patient_id, otp_code, expires_at) VALUES (?, ?, ?)")
   ->execute([$patient['id'], $otp, $expires]);

// En DEV : on retourne le code. En PROD : envoyer par SMS/email
jsonSuccess(['otp_code' => $otp], 'Code envoyé. (DEV: voir otp_code)');
