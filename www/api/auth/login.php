<?php
$body     = getBody();
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if (!$email || !$password) {
    jsonError('Email et mot de passe requis', 'MISSING_FIELDS');
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id, nom, prenom, email, password, telephone, statut, token_fcm
     FROM patients WHERE email = ? LIMIT 1"
);
$stmt->execute([$email]);
$patient = $stmt->fetch();

if (!$patient) {
    jsonError('Email ou mot de passe incorrect', 'INVALID_CREDENTIALS', 401);
}

if ($patient['statut'] !== 'actif') {
    jsonError('Compte suspendu ou inactif', 'ACCOUNT_INACTIVE', 403);
}

// Les patients créés par QR code n'ont pas de password
if (empty($patient['password'])) {
    jsonError('Ce compte nécessite une inscription complète', 'NO_PASSWORD', 401);
}

if (!password_verify($password, $patient['password'])) {
    jsonError('Email ou mot de passe incorrect', 'INVALID_CREDENTIALS', 401);
}

$token = jwtEncode([
    'sub'   => $patient['id'],
    'email' => $patient['email'],
    'role'  => 'patient',
    'exp'   => time() + 60 * 60 * 24 * 7, // 7 jours
]);

jsonSuccess([
    'token' => $token,
    'patient' => [
        'id'        => (int) $patient['id'],
        'nom'       => $patient['nom'],
        'prenom'    => $patient['prenom'],
        'email'     => $patient['email'],
        'telephone' => $patient['telephone'],
        'statut'    => $patient['statut'],
    ],
]);
