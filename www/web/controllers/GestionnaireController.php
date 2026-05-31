<?php
/**
 * controllers/GestionnaireController.php
 * Contrôleur pour l'espace Gestionnaire
 */

require_once __DIR__ . '/../models/GestionnaireModel.php';

class GestionnaireController
{
    private GestionnaireModel $model;

    public function __construct()
    {
        $this->model = new GestionnaireModel();
    }

    /* ════════════════════════════════════════════════════════
       INSCRIPTION
    ════════════════════════════════════════════════════════ */

    public function afficherInscription(): void
    {
        $erreurs      = [];
        $anciens      = [];
        $sousServices = $this->model->getSousServices();
        require __DIR__ . '/../views/gestionnaire/inscription.php';
    }

    public function traiterInscription(): void
    {
        $erreurs      = [];
        $sousServices = $this->model->getSousServices();

        $nom           = trim($_POST['nom']               ?? '');
        $telephone     = trim($_POST['telephone']         ?? '');
        $email         = trim($_POST['email']             ?? '');
        $password      = $_POST['password']               ?? '';
        $confirm       = $_POST['confirm']                ?? '';
        $sousServiceId = (int)($_POST['sous_service_id']  ?? 0);

        $anciens = [
            'nom'             => $nom,
            'telephone'       => $telephone,
            'email'           => $email,
            'sous_service_id' => $sousServiceId,
        ];

        if (mb_strlen($nom) < 2) {
            $erreurs['nom'] = 'Le nom doit contenir au moins 2 caractères.';
        }

        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) {
            $erreurs['telephone'] = 'Numéro invalide (chiffres et + uniquement).';
        } elseif ($this->model->telephoneExiste($telephone)) {
            $erreurs['telephone'] = 'Ce numéro est déjà utilisé.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Adresse email invalide.';
        } elseif ($this->model->emailExiste($email)) {
            $erreurs['email'] = 'Cette adresse email est déjà utilisée.';
        }

        if ($sousServiceId === 0) {
            $erreurs['sous_service_id'] = 'Veuillez sélectionner un sous-service.';
        }

        if (strlen($password) < 8) {
            $erreurs['password'] = 'Minimum 8 caractères.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $erreurs['password'] = 'Au moins une majuscule requise.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $erreurs['password'] = 'Au moins un chiffre requis.';
        }

        if ($password !== $confirm) {
            $erreurs['confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($erreurs)) {
            require __DIR__ . '/../views/gestionnaire/inscription.php';
            return;
        }

        $ok = $this->model->creer([
            'nom'             => $nom,
            'telephone'       => $telephone,
            'email'           => $email,
            'password'        => $password,
            'sous_service_id' => $sousServiceId,
        ]);

        if ($ok) {
            header('Location: gestionnaire.php?action=connexion&inscription=succes');
            exit;
        }

        $erreurs['global'] = 'Une erreur est survenue. Veuillez réessayer.';
        require __DIR__ . '/../views/gestionnaire/inscription.php';
    }

    /* ════════════════════════════════════════════════════════
       CONNEXION
    ════════════════════════════════════════════════════════ */

    public function afficherConnexion(): void
    {
        $erreurs      = [];
        $ancien_email = '';
        require __DIR__ . '/../views/gestionnaire/connexion.php';
    }

    public function traiterConnexion(): void
    {
        $erreurs      = [];
        $ancien_email = trim($_POST['email']    ?? '');
        $password     = $_POST['password']      ?? '';

        if (empty($ancien_email) || empty($password)) {
            $erreurs['global'] = 'Veuillez remplir tous les champs.';
            require __DIR__ . '/../views/gestionnaire/connexion.php';
            return;
        }

        $gestionnaire = $this->model->trouverParEmail($ancien_email);

        if (!$gestionnaire || !password_verify($password, $gestionnaire['password'])) {
            $erreurs['global'] = 'Email ou mot de passe incorrect.';
            require __DIR__ . '/../views/gestionnaire/connexion.php';
            return;
        }

        session_regenerate_id(true);
        $_SESSION['gestionnaire_id']  = $gestionnaire['id'];
        $_SESSION['gestionnaire_nom'] = $gestionnaire['nom'];
        $_SESSION['last_activity']    = time();

        $sousService = $this->model->getSousServiceByGestionnaire($gestionnaire['id']);
        if ($sousService) {
            $_SESSION['sous_service_id'] = $sousService['id'];
        }

        header('Location: gestionnaire.php?action=dashboard');
        exit;
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD
    ════════════════════════════════════════════════════════ */

    public function afficherDashboard(): void
    {
        if (!isset($_SESSION['gestionnaire_id'])) {
            header('Location: gestionnaire.php?action=connexion');
            exit;
        }

        $gestionnaireId  = (int)$_SESSION['gestionnaire_id'];
        $gestionnaireNom = $_SESSION['gestionnaire_nom'];

        $sousService = $this->model->getSousServiceGestionnaire($gestionnaireId);
        if (!$sousService) {
            die('Aucun sous-service affecté à ce compte. Contactez l\'administrateur.');
        }

        $ssId          = (int)$sousService['id'];
        $stats         = $this->model->statsJour($ssId);
        $file          = $this->model->fileAttente($ssId);
        $consultations = $this->model->consultationsJour($ssId);
        $urgences      = $this->model->urgencesOuvertes($ssId);
        $messageAction = '';
        $typeMessage   = 'success';
        $medecins      = $this->model->getMedecinsDisponibles($ssId);
        $horairesJour  = $this->model->getHorairesJour($ssId);

        $_SESSION['sous_service_id'] = $ssId;

        // ── Endpoint AJAX : chercher un patient par téléphone ──
        if (isset($_GET['api']) && $_GET['api'] === 'patient') {
            header('Content-Type: application/json; charset=utf-8');
            $tel    = trim($_GET['tel'] ?? '');
            $result = $tel ? $this->model->chercherPatientParTel($tel) : false;
            echo json_encode($result ?: null);
            exit;
        }

        $openQR = isset($_GET['open_qr']) ? true : false;
        require __DIR__ . '/../views/gestionnaire/dashboard.php';
    }

    /* ════════════════════════════════════════════════════════
       TRAITEMENT DES ACTIONS AJAX
    ════════════════════════════════════════════════════════ */

    public function traiterActionAjax(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['gestionnaire_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        $ssId = $_SESSION['sous_service_id'] ?? null;
        
        if (!$ssId) {
            $gestionnaireId = (int)$_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceGestionnaire($gestionnaireId);
            $ssId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $ssId;
        }
        
        if (!$ssId) {
            echo json_encode(['success' => false, 'message' => 'Sous-service non trouvé']);
            exit;
        }
        
        // Action: Mise à jour du statut
        if ($action === 'maj_statut') {
            $cId = (int)($_POST['consultation_id'] ?? 0);
            $statut = $_POST['statut'] ?? '';
            $autorises = ['traite', 'annule', 'absent'];
            
            if ($cId > 0 && in_array($statut, $autorises, true)) {
                $this->model->majStatutConsultation($cId, $statut);
                echo json_encode(['success' => true, 'message' => 'Statut mis à jour.']);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Action invalide.']);
            exit;
        }
        
        // Action: Consultation manuelle - sans sélection de médecin
        if ($action === 'consultation_manuelle') {
            $patNom = trim($_POST['patient_nom'] ?? '');
            $patPrenom = trim($_POST['patient_prenom'] ?? '');
            $patTel = trim($_POST['patient_telephone'] ?? '');
            $patEmail = trim($_POST['patient_email'] ?? '');
            $statut = trim($_POST['statut'] ?? 'en_attente');
            $motif = trim($_POST['motif'] ?? '');

            $errC = [];
            if (mb_strlen($patNom) < 2) $errC[] = 'Nom requis.';
            if (mb_strlen($patPrenom) < 2) $errC[] = 'Prénom requis.';
            if (!preg_match('/^\+?[0-9]{8,20}$/', $patTel)) $errC[] = 'Téléphone invalide.';

            if (empty($errC)) {
                try {
                    $patientId = $this->model->rechercherOuCreerPatient([
                        'nom' => $patNom, 
                        'prenom' => $patPrenom,
                        'telephone' => $patTel, 
                        'email' => $patEmail
                    ]);

                    // Le système choisit automatiquement le médecin le moins chargé
                    $consultId = $this->model->enregistrerConsultationManuelle([
                        'patient_id' => $patientId,
                        'sous_service_id' => $ssId,
                        'mode_prise' => 'MANUEL',
                        'statut' => $statut,
                        'motif' => $motif
                    ]);

                    if ($consultId > 0) {
                        echo json_encode(['success' => true, 'message' => "Consultation enregistrée — {$patPrenom} {$patNom}."]);
                        exit;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement. Vérifiez les données.']);
                        exit;
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => implode(' | ', $errC)]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       RÉCUPÉRATION DES DONNÉES DASHBOARD
    ════════════════════════════════════════════════════════ */

    public function getDashboardData(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['gestionnaire_id'])) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        $gestionnaireId = (int)$_SESSION['gestionnaire_id'];
        $sousService = $this->model->getSousServiceGestionnaire($gestionnaireId);
        
        if (!$sousService) {
            echo json_encode(['error' => 'Sous-service non trouvé']);
            exit;
        }
        
        $ssId = (int)$sousService['id'];
        $stats = $this->model->statsJour($ssId);
        $file = $this->model->fileAttente($ssId);
        $consultations = $this->model->consultationsJour($ssId);
        
        foreach ($file as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—';
        }
        foreach ($consultations as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—';
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'file' => $file,
            'consultations' => $consultations
        ]);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       GESTION DU PROFIL
    ════════════════════════════════════════════════════════ */

    public function getProfilData(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['gestionnaire_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $gestionnaireId = $_SESSION['gestionnaire_id'];
        $gestionnaire = $this->model->trouverParId($gestionnaireId);
        
        if (!$gestionnaire) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'profil' => [
                'nom' => $gestionnaire['nom'],
                'telephone' => $gestionnaire['telephone'],
                'email' => $gestionnaire['email']
            ]
        ]);
        exit;
    }

    public function mettreAJourProfil(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['gestionnaire_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $id = $_SESSION['gestionnaire_id'];
        $nom = trim($_POST['nom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $passwordActuel = $_POST['password_actuel'] ?? '';
        $nouveauPassword = $_POST['nouveau_password'] ?? '';
        $confirmerPassword = $_POST['confirmer_password'] ?? '';

        $erreurs = [];

        if (mb_strlen($nom) < 2) $erreurs['nom'] = 'Nom trop court.';
        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) $erreurs['telephone'] = 'Téléphone invalide.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Email invalide.';

        $gestionnaire = $this->model->trouverParId($id);
        if ($email !== $gestionnaire['email'] && $this->model->emailExiste($email)) {
            $erreurs['email'] = 'Cet email est déjà utilisé.';
        }
        if ($telephone !== $gestionnaire['telephone'] && $this->model->telephoneExiste($telephone)) {
            $erreurs['telephone'] = 'Ce numéro est déjà utilisé.';
        }

        if (!empty($nouveauPassword)) {
            if (empty($passwordActuel)) {
                $erreurs['password_actuel'] = 'Veuillez entrer votre mot de passe actuel.';
            } elseif (!$this->model->verifierMotDePasse($id, $passwordActuel)) {
                $erreurs['password_actuel'] = 'Mot de passe actuel incorrect.';
            } elseif (strlen($nouveauPassword) < 8) {
                $erreurs['nouveau_password'] = 'Minimum 8 caractères.';
            } elseif (!preg_match('/[A-Z]/', $nouveauPassword)) {
                $erreurs['nouveau_password'] = 'Au moins une majuscule.';
            } elseif (!preg_match('/[0-9]/', $nouveauPassword)) {
                $erreurs['nouveau_password'] = 'Au moins un chiffre.';
            } elseif ($nouveauPassword !== $confirmerPassword) {
                $erreurs['confirmer_password'] = 'Les mots de passe ne correspondent pas.';
            }
        }

        if (!empty($erreurs)) {
            echo json_encode(['success' => false, 'errors' => $erreurs]);
            exit;
        }

        $this->model->mettreAJourProfil($id, $nom, $telephone);
        $this->model->mettreAJourEmail($id, $email);
        
        if (!empty($nouveauPassword)) {
            $this->model->mettreAJourMotDePasse($id, $nouveauPassword);
        }

        $_SESSION['gestionnaire_nom'] = $nom;

        echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       EMPLOI DU TEMPS
    ════════════════════════════════════════════════════════ */

    public function getEmploiTemps(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['gestionnaire_id'])) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }

        $gestionnaireId = $_SESSION['gestionnaire_id'];
        $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
        
        if (!$sousService) {
            echo json_encode(['error' => 'Sous-service non trouvé']);
            exit;
        }
        
        $ssId = $sousService['id'];
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $medecins = $this->model->getMedecinsDisponibles($ssId);
        $consultations = [];
        
        // Récupérer toutes les consultations de la semaine
        for ($i = 0; $i <= 6; $i++) {
            $dateCourante = date('Y-m-d', strtotime($date . " +{$i} days"));
            $consultationsJour = $this->model->consultationsJour($ssId);
            foreach ($consultationsJour as $c) {
                $cDate = date('Y-m-d', strtotime($c['heure_passage_estimee']));
                if ($cDate === $dateCourante) {
                    $consultations[] = [
                        'id' => $c['id'],
                        'patient_nom' => $c['patient_nom'],
                        'patient_prenom' => $c['patient_prenom'],
                        'statut' => $c['statut'],
                        'heure_debut' => date('H:i', strtotime($c['heure_passage_estimee'])),
                        'date_consultation' => $dateCourante,
                        'medecin_nom' => $c['medecin_nom'] ?? 'Non assigné'
                    ];
                }
            }
        }
        
        // Récupérer les plages horaires
        $plagesHoraire = [];
        foreach ($medecins as $medecin) {
            $plages = $this->model->getHorairesJour($ssId, $date);
            $plagesHoraire[$medecin['id']] = [];
            foreach ($plages as $plage) {
                if ($plage['medecin_id'] == $medecin['id']) {
                    $plagesHoraire[$medecin['id']][] = [
                        'jour' => $plage['jour'],
                        'heure_debut' => $plage['heure_debut'],
                        'heure_fin' => $plage['heure_fin']
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'medecins' => $medecins,
            'consultations' => $consultations,
            'plages_horaire' => $plagesHoraire,
            'date_debut' => $date
        ]);
        exit;
    }

    public function getPlanning(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['gestionnaire_id'])) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        $date = $_GET['date'] ?? date('Y-m-d');
        $sousServiceId = $_SESSION['sous_service_id'] ?? null;
        
        if (!$sousServiceId) {
            $gestionnaireId = $_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
            $sousServiceId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $sousServiceId;
        }
        
        if (!$sousServiceId) {
            echo json_encode(['error' => 'Sous-service non trouvé']);
            exit;
        }
        
        $horaires = $this->model->getHorairesJour($sousServiceId, $date);
        $medecins = $this->model->getMedecinsDisponibles($sousServiceId);
        
        echo json_encode([
            'success' => true,
            'horaires' => $horaires,
            'medecins' => $medecins,
            'date' => $date
        ]);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       DÉCONNEXION
    ════════════════════════════════════════════════════════ */

    public function deconnecter(): void
    {
        session_unset();
        session_destroy();
        header('Location: gestionnaire.php?action=connexion&deconnecte=1');
        exit;
    }
}