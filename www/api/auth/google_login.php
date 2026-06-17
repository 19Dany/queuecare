<?php
$body = getBody();
$email = trim($body['email'] ?? '');
$nom = trim($body['nom'] ?? '');
$prenom = trim($body['prenom'] ?? '');
$tokenFcm = trim($body['token_fcm'] ?? '');

if (!$email) {
    jsonError('Email Google requis', 'MISSING_FIELDS');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Email Google invalide', 'INVALID_EMAIL');
}

if ($nom === '' || $prenom === '') {
    $emailParts = explode('@', $email, 2);
    $localPart = preg_replace('/[^a-zA-ZÀ-ÿ\' -]+/u', ' ', $emailParts[0] ?? '');
    $localPart = trim(preg_replace('/\s+/', ' ', $localPart));
    if ($prenom === '' && $localPart !== '') {
        $prenom = ucfirst(strtok($localPart, ' ')) ?: 'Patient';
    }
    if ($nom === '' && $localPart !== '') {
        $pieces = preg_split('/\s+/', $localPart);
        $nom = count($pieces) > 1 ? implode(' ', array_slice($pieces, 1)) : 'Google';
    }
    if ($prenom === '') {
        $prenom = 'Patient';
    }
    if ($nom === '') {
        $nom = 'Google';
    }
}

$db = getDB();
$stmt = $db->prepare(
    "SELECT id, nom, prenom, email, telephone, statut
     FROM patients
     WHERE email = ?
     LIMIT 1"
);
$stmt->execute([$email]);
$patient = $stmt->fetch();

if ($patient) {
    if ($patient['statut'] !== 'actif') {
        jsonError('Compte suspendu ou inactif', 'ACCOUNT_INACTIVE', 403);
    }

    if ($tokenFcm !== '') {
        $update = $db->prepare("UPDATE patients SET token_fcm = ? WHERE id = ?");
        $update->execute([$tokenFcm, $patient['id']]);
    }

    $token = jwtEncode([
        'sub'   => (int) $patient['id'],
        'email' => $patient['email'],
        'role'  => 'patient',
        'exp'   => time() + 60 * 60 * 24 * 7,
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
    ], 'Connexion Google reussie');
}

$telephone = 'g_' . substr(sha1($email), 0, 15);
$checkPhone = $db->prepare("SELECT id FROM patients WHERE telephone = ? LIMIT 1");
$checkPhone->execute([$telephone]);
if ($checkPhone->fetch()) {
    $telephone = 'g_' . substr(sha1($email . microtime(true)), 0, 15);
}

$insert = $db->prepare(
    "INSERT INTO patients (nom, prenom, telephone, email, password, token_fcm, statut, date_inscription)
     VALUES (?, ?, ?, ?, NULL, ?, 'actif', NOW())"
);
$insert->execute([$nom, $prenom, $telephone, $email, $tokenFcm ?: null]);
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
], 'Compte Google lie avec succes', 201);
