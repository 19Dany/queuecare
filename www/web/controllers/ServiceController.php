<?php
/**
 * controllers/ServiceController.php
 * Contrôleur MVC — Gestion des Services (hôpitaux)
 * Actions : liste, creer, modifier, basculer_statut
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/ServiceModel.php';

class ServiceController
{
    private ServiceModel $model;

    public function __construct()
    {
        $this->model = new ServiceModel();
    }

    /* ══════════════════════════════════════════════════
       LISTE DES SERVICES
    ══════════════════════════════════════════════════ */

    public function afficherListe(): void
    {
        $services      = $this->model->getAll();
        $erreurs       = [];
        $anciens       = [];
        $messageAction = $_SESSION['message_action'] ?? '';
        $typeMessage   = $_SESSION['type_message']   ?? 'success';
        unset($_SESSION['message_action'], $_SESSION['type_message']);

        require __DIR__ . '/../views/service/liste.php';
    }

    /* ══════════════════════════════════════════════════
       CRÉER UN SERVICE
    ══════════════════════════════════════════════════ */

    public function afficherCreer(): void
    {
        $erreurs = [];
        $anciens = [];
        require __DIR__ . '/../views/service/creer.php';
    }

    public function traiterCreer(): void
    {
        $erreurs = [];

        $nom         = trim($_POST['nom']         ?? '');
        $description = trim($_POST['description'] ?? '');
        $adresse     = trim($_POST['adresse']     ?? '');
        $horaires    = trim($_POST['horaires']    ?? '');
        $statut      = trim($_POST['statut']      ?? 'actif');

        $anciens = compact('nom', 'description', 'adresse', 'horaires', 'statut');

        /* ── Validation ── */
        if (mb_strlen($nom) < 3)
            $erreurs['nom'] = 'Le nom doit contenir au moins 3 caractères.';
        elseif ($this->model->nomExiste($nom))
            $erreurs['nom'] = 'Un service avec ce nom existe déjà.';

        if (mb_strlen($adresse) < 5)
            $erreurs['adresse'] = 'L\'adresse doit contenir au moins 5 caractères.';

        if (!in_array($statut, ['actif', 'inactif']))
            $statut = 'actif';

        if (!empty($erreurs)) {
            require __DIR__ . '/../views/service/creer.php';
            return;
        }

        $ok = $this->model->creer(compact('nom', 'description', 'adresse', 'horaires', 'statut'));

        if ($ok) {
            $_SESSION['message_action'] = "Service « {$nom} » créé avec succès. Vous pouvez maintenant vous inscrire.";
            $_SESSION['type_message']   = 'success';
            header('Location: service.php?action=liste');
            exit;
        }

        $erreurs['global'] = 'Une erreur est survenue. Veuillez réessayer.';
        require __DIR__ . '/../views/service/creer.php';
    }

    /* ══════════════════════════════════════════════════
       MODIFIER UN SERVICE
    ══════════════════════════════════════════════════ */

    public function afficherModifier(): void
    {
        $id      = (int)($_GET['id'] ?? 0);
        $service = $id > 0 ? $this->model->trouverParId($id) : false;
        if (!$service) {
            header('Location: service.php?action=liste');
            exit;
        }
        $erreurs = [];
        $anciens = $service;
        require __DIR__ . '/../views/service/modifier.php';
    }

    public function traiterModifier(): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $service = $id > 0 ? $this->model->trouverParId($id) : false;
        if (!$service) {
            header('Location: service.php?action=liste');
            exit;
        }

        $erreurs = [];

        $nom         = trim($_POST['nom']         ?? '');
        $description = trim($_POST['description'] ?? '');
        $adresse     = trim($_POST['adresse']     ?? '');
        $horaires    = trim($_POST['horaires']    ?? '');
        $statut      = trim($_POST['statut']      ?? 'actif');

        $anciens = compact('nom', 'description', 'adresse', 'horaires', 'statut') + ['id' => $id];

        if (mb_strlen($nom) < 3)
            $erreurs['nom'] = 'Le nom doit contenir au moins 3 caractères.';
        elseif ($this->model->nomExiste($nom, $id))
            $erreurs['nom'] = 'Un autre service avec ce nom existe déjà.';

        if (mb_strlen($adresse) < 5)
            $erreurs['adresse'] = 'L\'adresse doit contenir au moins 5 caractères.';

        if (!empty($erreurs)) {
            require __DIR__ . '/../views/service/modifier.php';
            return;
        }

        $ok = $this->model->modifier($id, compact('nom', 'description', 'adresse', 'horaires', 'statut'));

        if ($ok) {
            $_SESSION['message_action'] = "Service « {$nom} » modifié avec succès.";
            $_SESSION['type_message']   = 'success';
            header('Location: service.php?action=liste');
            exit;
        }

        $erreurs['global'] = 'Une erreur est survenue. Veuillez réessayer.';
        require __DIR__ . '/../views/service/modifier.php';
    }

    /* ══════════════════════════════════════════════════
       BASCULER STATUT ACTIF / INACTIF
    ══════════════════════════════════════════════════ */

    public function basculerStatut(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $this->model->basculerStatut($id);
            $_SESSION['message_action'] = 'Statut du service mis à jour.';
            $_SESSION['type_message']   = 'info';
        }
        header('Location: service.php?action=liste');
        exit;
    }

    /* ══════════════════════════════════════════════════
       CRÉER UN SOUS-SERVICE
    ══════════════════════════════════════════════════ */

    public function traiterCreerSousService(): void
    {
        $serviceId   = (int)($_POST['service_id']    ?? 0);
        $nom         = trim($_POST['ss_nom']          ?? '');
        $description = trim($_POST['ss_description']  ?? '');
        $duree       = (int)($_POST['ss_duree']       ?? 1800);
        $capacite    = (int)($_POST['ss_capacite']    ?? 10);

        if ($serviceId === 0 || mb_strlen($nom) < 2) {
            $_SESSION['message_action'] = 'Données invalides pour le sous-service.';
            $_SESSION['type_message']   = 'error';
            header("Location: service.php?action=liste#svc-{$serviceId}");
            exit;
        }

        if ($this->model->nomSsExiste($nom, $serviceId)) {
            $_SESSION['message_action'] = "Un sous-service « {$nom} » existe déjà dans cet hôpital.";
            $_SESSION['type_message']   = 'error';
            header("Location: service.php?action=liste#svc-{$serviceId}");
            exit;
        }

        $ok = $this->model->creerSousService([
            'service_id'      => $serviceId,
            'nom'             => $nom,
            'description'     => $description,
            'duree_rdv_defaut'=> $duree,
            'capacite_horaire'=> $capacite,
        ]);

        if ($ok) {
            $_SESSION['message_action'] = "Sous-service « {$nom} » créé avec succès.";
            $_SESSION['type_message']   = 'success';
        } else {
            $_SESSION['message_action'] = 'Erreur lors de la création du sous-service.';
            $_SESSION['type_message']   = 'error';
        }
        header("Location: service.php?action=liste#svc-{$serviceId}");
        exit;
    }

    /* ══════════════════════════════════════════════════
       MODIFIER UN SOUS-SERVICE
    ══════════════════════════════════════════════════ */

    public function traiterModifierSousService(): void
    {
        $ssId        = (int)($_POST['ss_id']         ?? 0);
        $serviceId   = (int)($_POST['service_id']    ?? 0);
        $nom         = trim($_POST['ss_nom']         ?? '');
        $description = trim($_POST['ss_description'] ?? '');
        $duree       = (int)($_POST['ss_duree']      ?? 1800);
        $capacite    = (int)($_POST['ss_capacite']   ?? 10);
        $statut      = trim($_POST['ss_statut']      ?? 'actif');

        if ($ssId === 0 || mb_strlen($nom) < 2) {
            $_SESSION['message_action'] = 'Données invalides.';
            $_SESSION['type_message']   = 'error';
            header("Location: service.php?action=liste#svc-{$serviceId}");
            exit;
        }

        if ($this->model->nomSsExiste($nom, $serviceId, $ssId)) {
            $_SESSION['message_action'] = "Un autre sous-service « {$nom} » existe déjà dans cet hôpital.";
            $_SESSION['type_message']   = 'error';
            header("Location: service.php?action=liste#svc-{$serviceId}");
            exit;
        }

        $ok = $this->model->modifierSousService($ssId, compact('nom', 'description', 'duree', 'capacite', 'statut') + ['duree_rdv_defaut' => $duree]);

        if ($ok) {
            $_SESSION['message_action'] = "Sous-service « {$nom} » modifié avec succès.";
            $_SESSION['type_message']   = 'success';
        } else {
            $_SESSION['message_action'] = 'Erreur lors de la modification.';
            $_SESSION['type_message']   = 'error';
        }
        header("Location: service.php?action=liste#svc-{$serviceId}");
        exit;
    }

    /* ══════════════════════════════════════════════════
       BASCULER STATUT SOUS-SERVICE
    ══════════════════════════════════════════════════ */

    public function basculerStatutSousService(): void
    {
        $ssId      = (int)($_GET['ss_id']      ?? 0);
        $serviceId = (int)($_GET['service_id'] ?? 0);
        if ($ssId > 0) {
            $this->model->basculerStatutSs($ssId);
            $_SESSION['message_action'] = 'Statut du sous-service mis à jour.';
            $_SESSION['type_message']   = 'info';
        }
        header("Location: service.php?action=liste#svc-{$serviceId}");
        exit;
    }

    public function supprimerSousService(): void
    {
        $ssId      = (int)($_GET['ss_id']      ?? 0);
        $serviceId = (int)($_GET['service_id'] ?? 0);

        if ($ssId > 0) {
            $res = $this->model->supprimerSousService($ssId);
            $_SESSION['message_action'] = $res['msg'];
            $_SESSION['type_message']   = $res['ok'] ? 'success' : 'error';
        }
        header("Location: service.php?action=liste#svc-{$serviceId}");
        exit;
    }
}

/* ── Dispatch ───────────────────────────────────────────── */
$action = $_GET['action'] ?? 'liste';
$ok     = ['liste', 'creer', 'modifier', 'basculer_statut',
           'creer_ss', 'modifier_ss', 'basculer_statut_ss', 'supprimer_ss'];

if (!in_array($action, $ok)) {
    header('Location: service.php?action=liste');
    exit;
}

$ctrl = new ServiceController();

match ($action) {
    'liste'              => $ctrl->afficherListe(),
    'creer'              => $_SERVER['REQUEST_METHOD'] === 'POST'
                               ? $ctrl->traiterCreer()
                               : $ctrl->afficherCreer(),
    'modifier'           => $_SERVER['REQUEST_METHOD'] === 'POST'
                               ? $ctrl->traiterModifier()
                               : $ctrl->afficherModifier(),
    'basculer_statut'    => $ctrl->basculerStatut(),
    'creer_ss'           => $ctrl->traiterCreerSousService(),
    'modifier_ss'        => $ctrl->traiterModifierSousService(),
    'basculer_statut_ss' => $ctrl->basculerStatutSousService(),
    'supprimer_ss'       => $ctrl->supprimerSousService(),
    default              => $ctrl->afficherListe(),
};
