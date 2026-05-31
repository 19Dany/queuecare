<?php
/**
 * controllers/MedecinController.php
 * Contrôleur pour l'espace Médecin
 */

require_once __DIR__ . '/../models/MedecinModel.php';

class MedecinController
{
    private MedecinModel $model;

    public function __construct()
    {
        $this->model = new MedecinModel();
    }

    /* ════════════════════════════════════════════════════════
       INSCRIPTION
    ════════════════════════════════════════════════════════ */

    public function afficherInscription(): void
    {
        $erreurs    = [];
        $anciens    = [];
        $services   = $this->model->getServices();
        $medecin = null;
        require __DIR__ . '/../views/medecin/inscription.php';
    }

    public function traiterInscription(): void
    {
        $erreurs  = [];
        $services = $this->model->getServices();

        $nom       = trim($_POST['nom']        ?? '');
        $prenom    = trim($_POST['prenom']      ?? '');
        $telephone = trim($_POST['telephone']   ?? '');
        $email     = trim($_POST['email']       ?? '');
        $password  = $_POST['password']         ?? '';
        $confirm   = $_POST['confirm']          ?? '';
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $specialite= trim($_POST['specialite']  ?? '');

        $anciens = compact('nom', 'prenom', 'telephone', 'email', 'serviceId', 'specialite');

        // Gestion de la photo
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../public/uploads/medecins/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            if (in_array($extension, $allowed)) {
                $filename = uniqid('medecin_') . '.' . $extension;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    $photoPath = 'public/uploads/medecins/' . $filename;
                } else {
                    $erreurs['photo'] = 'Erreur lors de l\'upload de la photo.';
                }
            } else {
                $erreurs['photo'] = 'Format non autorisé. Utilisez JPG ou PNG.';
            }
        }

        if (mb_strlen($nom) < 2) $erreurs['nom'] = 'Nom trop court (min. 2 caractères).';
        if (mb_strlen($prenom) < 2) $erreurs['prenom'] = 'Prénom trop court (min. 2 caractères).';

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

        if ($serviceId === 0) $erreurs['service_id'] = 'Veuillez sélectionner un hôpital.';
        if (empty($specialite)) $erreurs['specialite'] = 'Veuillez sélectionner votre sous-service.';

        $sousServiceId = 0;
        if ($serviceId > 0 && !empty($specialite)) {
            $sousServiceId = $this->model->trouverSousServiceParNom($specialite, $serviceId);
            if (!$sousServiceId) $erreurs['specialite'] = 'Sous-service introuvable. Veuillez resélectionner.';
        }

        if (strlen($password) < 8) {
            $erreurs['password'] = 'Minimum 8 caractères.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $erreurs['password'] = 'Au moins une majuscule requise.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $erreurs['password'] = 'Au moins un chiffre requis.';
        }

        if ($password !== $confirm) $erreurs['confirm'] = 'Les mots de passe ne correspondent pas.';

        if (!empty($erreurs)) {
            require __DIR__ . '/../views/medecin/inscription.php';
            return;
        }

        $ok = $this->model->creer([
            'nom'        => $nom,
            'prenom'     => $prenom,
            'specialite' => $specialite,
            'telephone'  => $telephone,
            'email'      => $email,
            'password'   => $password,
            'service_id' => $serviceId,
            'photo'      => $photoPath,
        ]);

        if (!$ok) {
            $erreurs['global'] = 'Erreur lors de la création du compte. Veuillez réessayer.';
            require __DIR__ . '/../views/medecin/inscription.php';
            return;
        }

        $medecinId = $this->model->dernierID();
        $this->model->affecterSousService($medecinId, $sousServiceId);

        header('Location: medecin.php?action=connexion&inscription=succes');
        exit;
    }

    /* ════════════════════════════════════════════════════════
       CONNEXION
    ════════════════════════════════════════════════════════ */

    public function afficherConnexion(): void
    {
        $erreurs      = [];
        $ancien_email = '';
        require __DIR__ . '/../views/medecin/connexion.php';
    }

    public function traiterConnexion(): void
    {
        $erreurs      = [];
        $ancien_email = trim($_POST['email']  ?? '');
        $password     = $_POST['password']    ?? '';

        if (empty($ancien_email) || empty($password)) {
            $erreurs['global'] = 'Veuillez remplir tous les champs.';
            require __DIR__ . '/../views/medecin/connexion.php';
            return;
        }

        $medecin = $this->model->trouverParEmail($ancien_email);

        if (!$medecin || !password_verify($password, $medecin['password'])) {
            $erreurs['global'] = 'Email ou mot de passe incorrect.';
            require __DIR__ . '/../views/medecin/connexion.php';
            return;
        }

        session_regenerate_id(true);
        $_SESSION['medecin_id']     = $medecin['id'];
        $_SESSION['medecin_nom']    = $medecin['nom'] . ' ' . $medecin['prenom'];
        $_SESSION['medecin_prenom'] = $medecin['prenom'];
        $_SESSION['last_activity']  = time();

        header('Location: medecin.php?action=dashboard');
        exit;
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD
    ════════════════════════════════════════════════════════ */

    public function afficherDashboard(): void
    {
        if (!isset($_SESSION['medecin_id'])) {
            header('Location: medecin.php?action=connexion');
            exit;
        }

        $medecinId  = (int)$_SESSION['medecin_id'];
        $medecinNom = $_SESSION['medecin_nom'];

        $affectation  = $this->model->getSousServiceMedecin($medecinId);
        $stats        = [];
        $consultations= [];
        $planning     = [];
        $medecin      = $this->model->trouverParId($medecinId);

        if ($affectation) {
            $ssId = (int)$affectation['ss_id'];
            $stats = $this->model->statsJour($ssId);
            $consultations = $this->model->consultationsDuJour($ssId, $medecinId);
            $planning = $this->model->planningMedecin($medecinId);
        }

        require __DIR__ . '/../views/medecin/dashboard.php';
    }

    /* ════════════════════════════════════════════════════════
       RÉCUPÉRATION DES DONNÉES AJAX
    ════════════════════════════════════════════════════════ */

    public function getConsultationsData(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        $medecinId = $_SESSION['medecin_id'];
        $affectation = $this->model->getSousServiceMedecin($medecinId);
        
        if (!$affectation) {
            echo json_encode(['success' => true, 'consultations' => [], 'stats' => []]);
            exit;
        }
        
        $ssId = $affectation['ss_id'];
        $consultations = $this->model->consultationsDuJour($ssId, $medecinId);
        $stats = $this->model->statsJour($ssId);
        
        foreach ($consultations as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—';
            $c['heure_debut_reelle'] = $c['heure_debut_reelle'] ? date('H:i', strtotime($c['heure_debut_reelle'])) : '—';
            $c['heure_fin_reelle'] = $c['heure_fin_reelle'] ? date('H:i', strtotime($c['heure_fin_reelle'])) : '—';
        }
        
        echo json_encode(['success' => true, 'consultations' => $consultations, 'stats' => $stats]);
        exit;
    }

    public function getStatsData(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        $medecinId = $_SESSION['medecin_id'];
        $affectation = $this->model->getSousServiceMedecin($medecinId);
        
        if (!$affectation) {
            echo json_encode(['success' => true, 'stats' => []]);
            exit;
        }
        
        $stats = $this->model->statsJour($affectation['ss_id']);
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       ACTIONS SUR LES CONSULTATIONS (AJAX)
    ════════════════════════════════════════════════════════ */

    public function demarrerConsultationAjax(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        if ($consultationId && $this->model->demarrerConsultation($consultationId)) {
            echo json_encode(['success' => true, 'message' => 'Consultation démarrée']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de démarrer cette consultation']);
        }
        exit;
    }

    public function terminerConsultationAjax(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        if ($consultationId && $this->model->terminerConsultation($consultationId)) {
            echo json_encode(['success' => true, 'message' => 'Consultation terminée']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de terminer cette consultation']);
        }
        exit;
    }

    public function marquerAbsentAjax(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        if ($consultationId && $this->model->marquerAbsent($consultationId)) {
            echo json_encode(['success' => true, 'message' => 'Patient marqué comme absent']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de marquer ce patient comme absent']);
        }
        exit;
    }

    public function annulerToutesAjax(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $medecinId = $_SESSION['medecin_id'];
        if ($this->model->annulerToutesConsultations($medecinId)) {
            echo json_encode(['success' => true, 'message' => 'Toutes les consultations ont été annulées']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'annulation']);
        }
        exit;
    }

    /* ════════════════════════════════════════════════════════
       PLANNING DU MÉDECIN
    ════════════════════════════════════════════════════════ */

    public function getPlanningMedecin(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        $medecinId = $_SESSION['medecin_id'];
        $affectation = $this->model->getSousServiceMedecin($medecinId);
        
        if (!$affectation) {
            echo json_encode(['success' => true, 'consultations' => []]);
            exit;
        }
        
        $ssId = $affectation['ss_id'];
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $consultations = [];
        for ($i = 0; $i <= 6; $i++) {
            $dateCourante = date('Y-m-d', strtotime($date . " +{$i} days"));
            $consultationsJour = $this->model->consultationsDuJour($ssId, $medecinId);
            foreach ($consultationsJour as $c) {
                $cDate = date('Y-m-d', strtotime($c['heure_passage_estimee']));
                if ($cDate === $dateCourante) {
                    $consultations[] = [
                        'id' => $c['id'],
                        'patient_nom' => $c['patient_nom'],
                        'patient_prenom' => $c['patient_prenom'],
                        'statut' => $c['statut'],
                        'heure_debut' => date('H:i', strtotime($c['heure_passage_estimee'])),
                        'date_consultation' => $dateCourante
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'consultations' => $consultations,
            'date_debut' => $date
        ]);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       API AJAX
    ════════════════════════════════════════════════════════ */

    public function apiSousServices(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $serviceId = (int)($_GET['service_id'] ?? 0);
        if ($serviceId <= 0) {
            echo json_encode([]);
            exit;
        }
        echo json_encode($this->model->getSousServicesActifsParService($serviceId));
        exit;
    }

    /* ════════════════════════════════════════════════════════
       GESTION DU PROFIL
    ════════════════════════════════════════════════════════ */

    public function getProfilData(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $medecinId = $_SESSION['medecin_id'];
        $medecin = $this->model->trouverParId($medecinId);
        
        if (!$medecin) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'profil' => [
                'nom' => $medecin['nom'],
                'prenom' => $medecin['prenom'],
                'telephone' => $medecin['telephone'],
                'email' => $medecin['email'],
                'photo' => $medecin['photo']
            ]
        ]);
        exit;
    }

    public function mettreAJourProfil(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['medecin_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $id = $_SESSION['medecin_id'];
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $passwordActuel = $_POST['password_actuel'] ?? '';
        $nouveauPassword = $_POST['nouveau_password'] ?? '';
        $confirmerPassword = $_POST['confirmer_password'] ?? '';

        $erreurs = [];

        if (mb_strlen($nom) < 2) $erreurs['nom'] = 'Nom trop court.';
        if (mb_strlen($prenom) < 2) $erreurs['prenom'] = 'Prénom trop court.';
        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) $erreurs['telephone'] = 'Téléphone invalide.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Email invalide.';

        $medecin = $this->model->trouverParId($id);
        if ($email !== $medecin['email'] && $this->model->emailExiste($email)) {
            $erreurs['email'] = 'Cet email est déjà utilisé.';
        }
        if ($telephone !== $medecin['telephone'] && $this->model->telephoneExiste($telephone)) {
            $erreurs['telephone'] = 'Ce numéro est déjà utilisé.';
        }

        // Gestion de la photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../public/uploads/medecins/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            if (in_array($extension, $allowed)) {
                $filename = uniqid('medecin_') . '.' . $extension;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    // Supprimer l'ancienne photo
                    if ($medecin['photo'] && file_exists(__DIR__ . '/../' . $medecin['photo'])) {
                        unlink(__DIR__ . '/../' . $medecin['photo']);
                    }
                    $this->model->mettreAJourPhoto($id, 'public/uploads/medecins/' . $filename);
                } else {
                    $erreurs['photo'] = 'Erreur lors de l\'upload.';
                }
            } else {
                $erreurs['photo'] = 'Format non autorisé. Utilisez JPG ou PNG.';
            }
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

        $this->model->mettreAJourProfil($id, $nom, $prenom, $telephone);
        $this->model->mettreAJourEmail($id, $email);
        
        if (!empty($nouveauPassword)) {
            $this->model->mettreAJourMotDePasse($id, $nouveauPassword);
        }

        $_SESSION['medecin_nom'] = $nom . ' ' . $prenom;

        echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       DÉCONNEXION
    ════════════════════════════════════════════════════════ */

    public function deconnecter(): void
    {
        session_unset();
        session_destroy();
        header('Location: medecin.php?action=connexion&deconnecte=1');
        exit;
    }
}