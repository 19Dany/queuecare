<?php
$auth      = requireAuth();
$body      = getBody();
$token_fcm = trim($body['token_fcm'] ?? '');

if (!$token_fcm) jsonError('token_fcm requis', 'MISSING_FIELDS');

getDB()->prepare("UPDATE patients SET token_fcm = ? WHERE id = ?")
       ->execute([$token_fcm, $auth['sub']]);

jsonSuccess(null, 'Token FCM mis à jour');
