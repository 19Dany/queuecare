<?php
/**
 * helpers/NotificationHelper.php
 * Helper pour l'envoi de notifications push via Firebase Cloud Messaging (FCM)
 */

require_once __DIR__ . '/../config/firebase.php';

class NotificationHelper
{
    private static ?NotificationHelper $instance = null;
    private string $serverKey;
    private string $apiUrl;

    private function __construct()
    {
        $this->serverKey = defined('FCM_SERVER_KEY') ? FCM_SERVER_KEY : '';
        $this->apiUrl = defined('FCM_API_URL') ? FCM_API_URL : 'https://fcm.googleapis.com/fcm/send';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Envoie une notification à un seul appareil
     */
    public function sendToDevice(string $token, string $title, string $body, array $data = []): array
    {
        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'message' => 'Clé serveur FCM non configurée. Veuillez configurer FCM_SERVER_KEY dans config/firebase.php'
            ];
        }

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token FCM manquant'
            ];
        }

        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => 1,
                'icon' => '/public/images/logo.png'
            ],
            'data' => array_merge($data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default'
            ]),
            'priority' => 'high'
        ];

        return $this->sendRequest($payload);
    }

    /**
     * Envoie une notification à plusieurs appareils
     */
    public function sendToMultipleDevices(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return ['success' => false, 'message' => 'Aucun token fourni'];
        }

        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'message' => 'Clé serveur FCM non configurée'
            ];
        }

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => 1,
                'icon' => '/public/images/logo.png'
            ],
            'data' => array_merge($data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default'
            ]),
            'priority' => 'high'
        ];

        return $this->sendRequest($payload);
    }

    /**
     * Envoie une notification à un topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'message' => 'Clé serveur FCM non configurée'
            ];
        }

        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => 1,
                'icon' => '/public/images/logo.png'
            ],
            'data' => array_merge($data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default'
            ]),
            'priority' => 'high'
        ];

        return $this->sendRequest($payload);
    }

    /**
     * Envoie la requête à l'API FCM
     */
    private function sendRequest(array $payload): array
    {
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Erreur cURL: ' . $error
            ];
        }

        $result = json_decode($response, true);

        return [
            'success' => $httpCode === 200 && isset($result['success']) && $result['success'] > 0,
            'http_code' => $httpCode,
            'response' => $result,
            'message' => $this->getResponseMessage($result)
        ];
    }

    /**
     * Interprète la réponse FCM
     */
    private function getResponseMessage(array $response): string
    {
        if (isset($response['success']) && $response['success'] > 0) {
            return 'Notification envoyée avec succès';
        }
        
        if (isset($response['results'][0]['error'])) {
            $error = $response['results'][0]['error'];
            switch ($error) {
                case 'NotRegistered':
                    return 'Le token n\'est plus valide (appareil désinstallé)';
                case 'InvalidRegistration':
                    return 'Token invalide';
                case 'MismatchSenderId':
                    return 'Sender ID ne correspond pas';
                default:
                    return 'Erreur FCM: ' . $error;
            }
        }
        
        if (isset($response['error'])) {
            return 'Erreur: ' . $response['error'];
        }
        
        return 'Erreur inconnue';
    }

    /* ═══════════════════════════════════════════════════════════
       NOTIFICATIONS SPÉCIFIQUES À L'APPLICATION
    ═══════════════════════════════════════════════════════════ */

    /**
     * Envoie une notification de rappel de consultation
     */
    public function notifyConsultationReminder(string $token, array $consultation): array
    {
        $patientName = ($consultation['patient_nom'] ?? '') . ' ' . ($consultation['patient_prenom'] ?? '');
        $heure = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? 'now'));
        $date = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'now'));
        
        return $this->sendToDevice($token, 
            '🩺 Rappel de consultation', 
            "Consultation avec {$patientName} le {$date} à {$heure}",
            [
                'type' => 'consultation_reminder',
                'consultation_id' => $consultation['id'] ?? 0,
                'heure' => $heure,
                'date' => $date,
                'url' => '/patient/dashboard.php'
            ]
        );
    }

    /**
     * Envoie une notification lorsque le patient est appelé
     */
    public function notifyPatientCalled(string $token, array $consultation): array
    {
        $medecinName = $consultation['medecin_nom'] ?? 'le médecin';
        
        return $this->sendToDevice($token,
            '🔔 Vous êtes appelé(e) !',
            "{$medecinName} est prêt(e) à vous recevoir. Veuillez vous présenter au cabinet.",
            [
                'type' => 'patient_called',
                'consultation_id' => $consultation['id'] ?? 0,
                'url' => '/patient/consultation.php?id=' . ($consultation['id'] ?? 0)
            ]
        );
    }

    /**
     * Envoie une notification de rappel J-1
     */
    public function notifyDayBeforeReminder(string $token, array $consultation): array
    {
        $date = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'tomorrow'));
        $heure = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? '09:00'));
        
        return $this->sendToDevice($token,
            '📅 Rappel : Consultation demain',
            "Vous avez une consultation le {$date} à {$heure}. Merci de votre ponctualité.",
            [
                'type' => 'day_before_reminder',
                'consultation_id' => $consultation['id'] ?? 0,
                'url' => '/patient/dashboard.php'
            ]
        );
    }

    /**
     * Envoie une notification de confirmation de rendez-vous
     */
    public function notifyAppointmentConfirmed(string $token, array $consultation): array
    {
        $date = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'now'));
        $heure = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? '09:00'));
        $sousService = $consultation['sous_service_nom'] ?? 'consultation';
        
        return $this->sendToDevice($token,
            '✅ Rendez-vous confirmé',
            "Votre {$sousService} est confirmée pour le {$date} à {$heure}.",
            [
                'type' => 'appointment_confirmed',
                'consultation_id' => $consultation['id'] ?? 0,
                'url' => '/patient/dashboard.php'
            ]
        );
    }

    /**
     * Envoie une notification d'annulation
     */
    public function notifyCancellation(string $token, array $consultation, string $reason = ''): array
    {
        $message = "Votre consultation a été annulée.";
        if ($reason) {
            $message .= " Motif : {$reason}";
        }
        
        return $this->sendToDevice($token,
            '❌ Consultation annulée',
            $message,
            [
                'type' => 'cancellation',
                'consultation_id' => $consultation['id'] ?? 0,
                'url' => '/patient/dashboard.php'
            ]
        );
    }

    /**
     * Envoie une notification de modification de rendez-vous
     */
    public function notifyAppointmentUpdated(string $token, array $consultation, string $oldDate = '', string $oldTime = ''): array
    {
        $newDate = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'now'));
        $newTime = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? '09:00'));
        
        $message = "Votre consultation a été modifiée.";
        if ($oldDate && $oldTime) {
            $message .= " Ancien créneau: {$oldDate} à {$oldTime}. Nouveau créneau: {$newDate} à {$newTime}.";
        } else {
            $message .= " Nouveau créneau: {$newDate} à {$newTime}.";
        }
        
        return $this->sendToDevice($token,
            '✏️ Consultation modifiée',
            $message,
            [
                'type' => 'appointment_updated',
                'consultation_id' => $consultation['id'] ?? 0,
                'url' => '/patient/dashboard.php'
            ]
        );
    }

    /**
     * Envoie une notification de bienvenue
     */
    public function notifyWelcome(string $token, array $patient): array
    {
        return $this->sendToDevice($token,
            '👋 Bienvenue sur QueueCare !',
            "Bonjour {$patient['prenom']} {$patient['nom']}, vous recevrez désormais vos rappels de consultation par notification.",
            [
                'type' => 'welcome',
                'url' => '/patient/dashboard.php'
            ]
        );
    }

    /**
     * Envoie une notification de test
     */
    public function sendTestNotification(string $token): array
    {
        return $this->sendToDevice($token,
            '🔔 Test de notification',
            'Vos notifications fonctionnent correctement ! 🎉',
            [
                'type' => 'test',
                'url' => '/'
            ]
        );
    }

    /**
     * Envoie des rappels groupés pour toutes les consultations du jour suivant
     * (À exécuter via cron job tous les jours à 18h)
     */
    public function sendBulkDayBeforeReminders(array $consultations, callable $getTokenCallback): array
    {
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($consultations as $consultation) {
            $token = $getTokenCallback($consultation['patient_id']);
            
            if ($token) {
                $result = $this->notifyDayBeforeReminder($token, $consultation);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = [
                        'consultation_id' => $consultation['id'],
                        'error' => $result['message']
                    ];
                }
            } else {
                $failCount++;
                $errors[] = [
                    'consultation_id' => $consultation['id'],
                    'error' => 'Patient non abonné aux notifications'
                ];
            }
        }

        return [
            'success' => true,
            'sent' => $successCount,
            'failed' => $failCount,
            'errors' => $errors
        ];
    }

    /**
     * Vérifie si la configuration FCM est valide
     */
    public function isConfigured(): bool
    {
        return !empty($this->serverKey);
    }
}