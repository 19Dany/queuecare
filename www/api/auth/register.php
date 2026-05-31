<?php
$body      = getBody();
$nom       = trim($body['nom']       ?? '');
$prenom    = trim($body['prenom']    ?? '');
$telephone = trim($body['telephone'] ?? '');
$email     = trim($body['email']     ?? '');
$password  = trim($body['password']  ?? '');
$token_fcm = trim($body['token_fcm'] ?? '');

if (!$nom || !$prenom || !$telephone || !$email || !$password) {
    jsonError('Tous les champs sont obligatoires', 'MISSING_FIELDS');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Email invalide', 'INVALID_EMAIL');
}

if (strlen($password) < 6) {
    jsonError('Le mot de passe doit contenir au moins 6 caractères', 'WEAK_PASSWORD');
}

$db = getDB();

// Vérifier email unique
$stmt = $db->prepare("SELECT id FROM patients WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonError('Cet email est déjà utilisé', 'EMAIL_TAKEN', 409);
}

// Vérifier téléphone unique
$stmt = $db->prepare("SELECT id FROM patients WHERE telephone = ? LIMIT 1");
$stmt->execute([$telephone]);
if ($stmt->fetch()) {
    jsonError('Ce numéro de téléphone est déjà utilisé', 'PHONE_TAKEN', 409);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare(
    "INSERT INTO patients (nom, prenom, telephone, email, password, token_fcm, statut, date_inscription)
     VALUES (?, ?, ?, ?, ?, ?, 'actif', NOW())"
);
$stmt->execute([$nom, $prenom, $telephone, $email, $hash, $token_fcm ?: null]);
$newId = (int) $db->lastInsertId();

$token = jwtEncode([
    'sub'   => $newId,
    'email' => $email,
    'role'  => 'patient',
    'exp'   => time() + 60 * 60 * 24 * 7,
]);

jsonSuccess([
    'token' => $token,
    'patient' => [
        'id'        => $newId,
        'nom'       => $nom,
        'prenom'    => $prenom,
        'email'     => $email,
        'telephone' => $telephone,
        'statut'    => 'actif',
    ],
], 'Compte créé avec succès', 201);
