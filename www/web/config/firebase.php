<?php
/**
 * config/firebase.php
 * Configuration Firebase pour les notifications push
 */

// Configuration Firebase (vos valeurs)
define('FIREBASE_API_KEY', 'AIzaSyDSWyPMr_cak10gNUYNU94khLZe7Mrge6s');
define('FIREBASE_AUTH_DOMAIN', 'smartqueue-37328.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'smartqueue-37328');
define('FIREBASE_STORAGE_BUCKET', 'smartqueue-37328.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', '87084283644');
define('FIREBASE_APP_ID', '1:87084283644:web:0b7160836195a66a050979');

// ⚠️ IMPORTANT - À récupérer dans Firebase Console
// Aller dans: Paramètres du projet > Cloud Messaging > Clé du serveur (Server Key)
define('FCM_SERVER_KEY', 'VOTRE_SERVER_KEY_A_RECUPERER');

// ⚠️ IMPORTANT - Générer avec: web-push generate-vapid-keys
define('VAPID_PUBLIC_KEY', 'VOTRE_VAPID_PUBLIC_KEY');
define('VAPID_PRIVATE_KEY', 'VOTRE_VAPID_PRIVATE_KEY');

// URL de l'API FCM
define('FCM_API_URL', 'https://fcm.googleapis.com/fcm/send');