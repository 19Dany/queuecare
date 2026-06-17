# Configuration SMTP QueueCare

Ajoute ces variables d'environnement sur le service `QueueCare` Railway pour activer l'envoi des codes OTP par email :

- `SMTP_HOST`
- `SMTP_PORT` (souvent `587`)
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_FROM_EMAIL`
- `SMTP_FROM_NAME`
- `SMTP_ENCRYPTION` (`tls` ou `ssl`)
- `DEBUG_OTP` (`true` pour voir le code dans la réponse API en mode test)

Comportement :

- Si SMTP est correctement configuré, `/api/auth/password/forgot` envoie le code OTP par email.
- Si SMTP n'est pas configuré et que `DEBUG_OTP=true`, le code est renvoyé dans la réponse JSON pour les tests.
- Si SMTP n'est pas configuré et `DEBUG_OTP=false`, l'API renvoie une erreur d'envoi.
