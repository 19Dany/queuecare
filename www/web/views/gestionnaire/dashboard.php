<?php
// views/gestionnaire/dashboard.php

// ── Timeout inactivité 15 min ──
const SESSION_TIMEOUT_G = 900;
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_G) {
        session_unset();
        session_destroy();
        header('Location: gestionnaire.php?action=connexion&timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

$initiale = mb_strtoupper(mb_substr($gestionnaireNom, 0, 1));
$dureeMin = isset($sousService['duree_estimee']) ? round($sousService['duree_estimee'] / 60) : 30;
$openQR = isset($_GET['open_qr']) ? true : false;

// Récupérer les messages flash
$messageAction = $_SESSION['message_action'] ?? '';
$typeMessage = $_SESSION['type_message'] ?? 'success';
unset($_SESSION['message_action']);
unset($_SESSION['type_message']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Tableau de bord — QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/gestionnaire.css">
    <style>
        .planning-wrapper { width: 100%; overflow-x: auto; border-radius: 16px; background: white; }
        .planning-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; min-width: 600px; }
        .planning-table th { background: linear-gradient(135deg, #0a2b5e, #1a4db5); color: white; padding: 12px 8px; text-align: center; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .planning-table td { border: 1px solid #e2e8f0; vertical-align: top; background: white; }
        .planning-hour { background: #f8fafc; font-weight: 600; text-align: center; white-space: nowrap; position: sticky; left: 0; background-color: #f8fafc; z-index: 5; }
        .consultation-card { background: #dbeafe; border-left: 4px solid #1a4db5; padding: 8px; margin: 2px 0; border-radius: 8px; font-size: 0.75rem; cursor: pointer; }
        .consultation-card.current { background: #d1fae5; border-left-color: #10b981; }
        .consultation-card.passed { background: #f1f5f9; border-left-color: #94a3b8; opacity: 0.8; }
        .empty-slot { text-align: center; padding: 16px 8px; color: #94a3b8; font-size: 0.7rem; }
        .semaine-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .semaine-nav-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .semaine-label { background: #dbeafe; color: #1e40af; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; white-space: nowrap; }
        .planning-loading { text-align: center; padding: 60px 20px; }
        .planning-loading i { font-size: 2rem; color: #1a4db5; animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .refresh-indicator { position: fixed; bottom: 20px; right: 20px; background: #1a4db5; color: white; padding: 8px 16px; border-radius: 30px; font-size: 0.75rem; display: none; align-items: center; gap: 8px; z-index: 1000; }
        .refresh-indicator.show { display: flex; }
        .refresh-indicator i { animation: spin 1s linear infinite; }
        .action-msg-success { background: #d1fae5; color: #065f46; }
        .action-msg-error { background: #fee2e2; color: #991b1b; }
        .action-msg { padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .btn-sm { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 600; margin: 0 2px; }
        .btn-sm-green { background: #10b981; color: white; }
        .btn-sm-orange { background: #f59e0b; color: white; }
        .btn-sm-red { background: #ef4444; color: white; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-grey { background: #f1f5f9; color: #475569; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .rang-badge { width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .rang-badge.actif { background: #4ade80; color: white; }
        .file-table { width: 100%; border-collapse: collapse; }
        .file-table th { text-align: left; padding: 12px; background: #f8fafc; font-weight: 600; font-size: 0.8rem; color: #475569; }
        .file-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-value { font-size: 2rem; font-weight: 700; color: #0a2b5e; }
        .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 8px; }
        .section { background: white; border-radius: 20px; padding: 24px; margin-bottom: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #0a2b5e; }
        .empty-state { text-align: center; padding: 40px; color: #64748b; }
        .btn-icon { padding: 8px 16px; background: #f0f4f8; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-left: 8px; }
        .btn-primary { background: linear-gradient(135deg, #1a4db5, #2563eb); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .topbar { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 99; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: #0a2b5e; }
        .dashboard-content { padding: 32px; }
        .profil-section { max-width: 600px; margin: 0 auto; }
        .profil-section .form-group { margin-bottom: 20px; }
        .profil-section .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #0a2b5e; }
        .profil-section .form-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .profil-section .btn-save { background: linear-gradient(135deg, #1a4db5, #2563eb); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; }
        .profil-section .separator { border-top: 1px solid #e2e8f0; margin: 25px 0; }
        .profil-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .profil-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .modal-choix, .modal-consultation, .modal-qr { display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(13,29,67,0.55); backdrop-filter: blur(3px); align-items: center; justify-content: center; padding: 16px; }
        .modal-choix.active, .modal-consultation.active, .modal-qr.active { display: flex; }
        .modal-choix-content, .modal-consultation-content, .modal-qr-content { background: white; border-radius: 20px; width: 100%; max-width: 500px; overflow: hidden; }
        .modal-choix-header, .modal-consultation-header, .modal-qr-header { background: linear-gradient(135deg, #1a4db5, #2563eb); padding: 22px 28px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .modal-choix-body, .modal-consultation-body, .modal-qr-body { padding: 28px; }
        .choix-btn { width: 100%; padding: 20px; border: 2px solid #c8dff2; border-radius: 12px; background: #f8faff; cursor: pointer; margin-bottom: 16px; display: flex; align-items: center; gap: 15px; }
        .choix-btn-icon { width: 48px; height: 48px; background: #e0edfc; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; color: #0d2d6b; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1.5px solid #c8dff2; border-radius: 8px; font-family: inherit; font-size: 0.875rem; }
        .btn-cancel { flex: 1; padding: 12px; border: 1.5px solid #c8dff2; border-radius: 9px; background: #f0f4fa; cursor: pointer; }
        .btn-submit { flex: 2; padding: 12px; border: none; border-radius: 9px; background: linear-gradient(135deg, #1a4db5, #2563eb); color: white; font-weight: 700; cursor: pointer; }
        .qr-image-container { text-align: center; padding: 20px; }
        .qr-image-container img { max-width: 200px; }
        .qr-timer { text-align: center; margin-top: 20px; font-size: 1.2rem; font-weight: bold; color: #1a4db5; }
        .hidden { display: none !important; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .semaine-nav { flex-direction: column; } }
    </style>
</head>
<body>
<div class="app-container">
    
    <div id="refreshIndicator" class="refresh-indicator">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <span>Mise à jour...</span>
    </div>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><i class="fa-solid fa-list-check"></i><span>QueueCare</span></div>
            <div class="sidebar-user"><div class="sidebar-avatar"><?= $initiale ?></div><div class="sidebar-user-info"><div class="sidebar-user-name"><?= htmlspecialchars(explode(' ', $gestionnaireNom)[0]) ?></div><div class="sidebar-user-service"><?= htmlspecialchars($sousService['nom']) ?></div></div></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item active" onclick="showSection('file')"><i class="fa-solid fa-list-ol"></i><span>File d'attente</span></div>
            <div class="nav-item" onclick="showSection('consultations')"><i class="fa-solid fa-calendar-day"></i><span>Consultations du jour</span></div>
            <div class="nav-item" onclick="showSection('stats')"><i class="fa-solid fa-chart-simple"></i><span>Statistiques</span></div>
            <div class="nav-item" onclick="showSection('planning')"><i class="fa-solid fa-calendar-week"></i><span>Emploi du temps</span></div>
            <div class="nav-item" onclick="showSection('profil')"><i class="fa-solid fa-user"></i><span>Mon profil</span></div>
        </nav>
        <div class="sidebar-footer">
            <a href="accueil.php" class="logout-btn" style="margin-bottom:8px;"><i class="fa-solid fa-house"></i><span>Accueil</span></a>
            <a href="gestionnaire.php?action=deconnexion" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i><span>Déconnexion</span></a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="topbar">
            <h1 class="page-title">Tableau de bord</h1>
            <div><button class="btn-icon" onclick="window.location.reload()"><i class="fa-solid fa-rotate-right"></i> Actualiser</button><button class="btn-primary" onclick="ouvrirModalChoix()"><i class="fa-solid fa-user-plus"></i> Nouvelle consultation</button></div>
        </div>
        
        <div class="dashboard-content">
            
            <div id="timeoutBanner" style="display:none;position:fixed;top:0;left:0;right:0;z-index:9999;background:#c2410c;color:#fff;padding:12px 24px;align-items:center;justify-content:space-between;gap:16px;font-size:.9rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.3);">
                <span><i class="fa-solid fa-triangle-exclamation"></i> Votre session expire dans moins d'1 minute.</span>
                <button onclick="document.getElementById('timeoutBanner').style.display='none';resetIdleTimer();">Rester connecté</button>
            </div>
            
            <div id="messageContainer">
                <?php if (!empty($messageAction)): ?>
                <div class="action-msg <?= $typeMessage === 'error' ? 'action-msg-error' : 'action-msg-success' ?>"><i class="fa-solid <?= $typeMessage === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i><?= htmlspecialchars($messageAction) ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Section File d'attente -->
            <div id="section-file" class="section">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-list-ol"></i> File d'attente</span><span id="fileCount" class="badge badge-green"><?= count($file) ?> patient(s)</span></div>
                <div id="fileContent">
                    <?php if (empty($file)): ?>
                    <div class="empty-state">Aucun patient en attente.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="file-table"><thead><tr><th>Rang</th><th>Patient</th><th>Téléphone</th><th>Heure</th><th>Mode</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
                        <?php foreach ($file as $c): ?>
                        <tr><td><div class="rang-badge <?= $c['statut'] === 'en_cours' ? 'actif' : '' ?>"><?= (int)$c['rang'] ?></div></td>
                        <td><strong><?= htmlspecialchars($c['patient_nom'] . ' ' . $c['patient_prenom']) ?></strong></td>
                        <td><?= htmlspecialchars($c['telephone']) ?></td>
                        <td><?= $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—' ?></td>
                        <td><?= $c['mode_prise'] === 'LIGNE' ? '<span class="badge badge-blue">En ligne</span>' : '<span class="badge badge-green">Sur place</span>' ?></td>
                        <td><span class="badge badge-grey"><?= ucfirst($c['statut'] ?? 'en attente') ?></span></td>
                        <td><form class="status-form" style="display:flex;gap:5px;"><input type="hidden" name="action" value="maj_statut"><input type="hidden" name="consultation_id" value="<?= (int)$c['id'] ?>"><button type="submit" name="statut" value="traite" class="btn-sm btn-sm-green"><i class="fa-solid fa-check"></i> Traité</button><button type="submit" name="statut" value="absent" class="btn-sm btn-sm-orange"><i class="fa-solid fa-user-slash"></i> Absent</button><button type="submit" name="statut" value="annule" class="btn-sm btn-sm-red"><i class="fa-solid fa-xmark"></i> Annuler</button></form></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section Consultations -->
            <div id="section-consultations" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-calendar-day"></i> Consultations du jour</span><span id="consultationsCount" class="badge badge-blue"><?= count($consultations) ?></span></div>
                <div id="consultationsContent">
                    <?php if (empty($consultations)): ?>
                    <div class="empty-state">Aucune consultation.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="file-table"><thead><tr><th>#</th><th>Patient</th><th>Médecin</th><th>Heure</th><th>Statut</th></tr></thead><tbody>
                        <?php foreach ($consultations as $c): ?>
                        <tr><td><?= (int)$c['rang'] ?></td><td><strong><?= htmlspecialchars($c['patient_nom'] . ' ' . $c['patient_prenom']) ?></strong></td><td><?= $c['medecin_nom'] ? htmlspecialchars($c['medecin_nom']) : '—' ?></td><td><?= $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—' ?></td><td><?php $bc = match($c['statut']) { 'traite' => 'badge-green', 'en_cours' => 'badge-blue', 'annule','absent' => 'badge-red', default => 'badge-grey' }; $lb = match($c['statut']) { 'traite' => 'Traitée', 'en_cours' => 'En cours', 'annule' => 'Annulée', 'absent' => 'Absent', 'en_attente' => 'En attente', 'confirme' => 'Confirmé', default => $c['statut'] }; ?><span class="badge <?= $bc ?>"><?= $lb ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section Statistiques -->
            <div id="section-stats" class="stats-grid" style="display:none;">
                <div class="stat-card"><div class="stat-value" id="statTotal"><?= (int)($stats['total'] ?? 0) ?></div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-value" id="statTraitees"><?= (int)($stats['traitees'] ?? 0) ?></div><div class="stat-label">Traitées</div></div>
                <div class="stat-card"><div class="stat-value" id="statEnAttente"><?= (int)($stats['en_attente'] ?? 0) ?></div><div class="stat-label">En attente</div></div>
                <div class="stat-card"><div class="stat-value" id="statAbsentes"><?= (int)($stats['absentes'] ?? 0) ?></div><div class="stat-label">Absents</div></div>
                <div class="stat-card"><div class="stat-value" id="statAnnulees"><?= (int)($stats['annulees'] ?? 0) ?></div><div class="stat-label">Annulées</div></div>
                <div class="stat-card"><div class="stat-value" id="statEnLigne"><?= (int)($stats['en_ligne'] ?? 0) ?></div><div class="stat-label">En ligne</div></div>
                <div class="stat-card"><div class="stat-value" id="statSurPlace"><?= (int)($stats['sur_place'] ?? 0) ?></div><div class="stat-label">Sur place</div></div>
            </div>
            
            <!-- Section Emploi du temps -->
            <div id="section-planning" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-calendar-week"></i> Emploi du temps</span><button class="btn-icon" onclick="chargerEmploiTemps()"><i class="fa-solid fa-rotate-right"></i> Rafraîchir</button></div>
                <div class="semaine-nav"><div class="semaine-nav-buttons"><button class="btn-icon" onclick="changerSemaine(-1)"><i class="fa-solid fa-chevron-left"></i> Semaine préc.</button><button class="btn-icon" onclick="changerSemaine(0)"><i class="fa-solid fa-calendar-day"></i> Aujourd'hui</button><button class="btn-icon" onclick="changerSemaine(1)">Semaine suiv. <i class="fa-solid fa-chevron-right"></i></button></div><div id="semaineLabel" class="semaine-label">Chargement...</div></div>
                <div id="planningContainer"><div id="planningLoading" class="planning-loading"><i class="fa-solid fa-spinner fa-spin"></i><p>Chargement...</p></div><div id="planningTable" style="display:none;"></div></div>
            </div>
            
            <!-- Section Profil -->
            <div id="section-profil" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-user"></i> Mon profil</span></div>
                <div id="profilContent"><div class="empty-state">Chargement...</div></div>
            </div>
        </div>
    </main>
</div>

<!-- MODALS -->
<div id="modalChoix" class="modal-choix"><div class="modal-choix-content"><div class="modal-choix-header"><h2>Choisir le mode</h2><button onclick="fermerModalChoix()" style="background:rgba(255,255,255,0.15);border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;color:white;">✕</button></div><div class="modal-choix-body"><div class="choix-btn" onclick="choisirMode('manuel')"><div class="choix-btn-icon"><i class="fa-solid fa-pen-to-square" style="font-size:1.4rem;color:#1a4db5;"></i></div><div><div class="choix-btn-title">Saisie manuelle</div><div class="choix-btn-desc">Remplir le formulaire</div></div></div><div class="choix-btn" onclick="choisirMode('qr')"><div class="choix-btn-icon"><i class="fa-solid fa-qrcode" style="font-size:1.4rem;color:#1a4db5;"></i></div><div><div class="choix-btn-title">QR Code</div><div class="choix-btn-desc">Générer un QR code</div></div></div></div></div></div>

<div id="modalConsultation" class="modal-consultation"><div class="modal-consultation-content"><div class="modal-consultation-header"><div><div style="font-weight:700;font-size:1.2rem;">Enregistrer une consultation</div><div style="font-size:0.8rem;opacity:0.7;"><?= htmlspecialchars($sousService['nom']) ?></div></div><button onclick="fermerModalConsultation()" style="background:rgba(255,255,255,0.15);border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;color:white;">✕</button></div><div class="modal-consultation-body"><form id="formConsultManuelle" onsubmit="return false;"><input type="hidden" name="action" value="consultation_manuelle"><div class="form-group"><label>Téléphone *</label><input type="tel" id="m_telephone" name="patient_telephone" placeholder="+237699123456" oninput="rechercherPatient(this.value)"></div><div class="form-row"><div class="form-group"><label>Nom *</label><input type="text" id="m_nom" name="patient_nom" required></div><div class="form-group"><label>Prénom *</label><input type="text" id="m_prenom" name="patient_prenom" required></div></div><div class="form-group"><label>Email</label><input type="email" id="m_email" name="patient_email" placeholder="patient@email.cm"></div><div class="form-group"><label>Statut initial</label><select name="statut"><option value="en_attente">En attente</option><option value="confirme">Confirmé</option></select></div><div class="form-group"><label>Motif</label><textarea name="motif" rows="3"></textarea></div><div style="display:flex;gap:10px;margin-top:20px;"><button type="button" class="btn-cancel" onclick="fermerModalConsultation()">Annuler</button><button type="submit" class="btn-submit" onclick="soumettreConsultationManuelle()">Enregistrer</button></div></form></div></div></div>

<div id="modalQR" class="modal-qr"><div class="modal-qr-content"><div class="modal-qr-header"><h2>QR Code</h2><button onclick="fermerModalQR()" style="background:rgba(255,255,255,0.15);border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;color:white;">✕</button></div><div class="modal-qr-body"><div id="qrLoadingState" class="qr-loading"></div><div id="qrContentState" class="hidden"><div class="qr-image-container"><img id="qrCodeImage" src="" alt="QR Code"></div><div class="qr-timer" id="qrTimer">20:00</div><div id="qrMessage" class="hidden"></div><div class="qr-actions"><button class="btn-sm btn-sm-green" onclick="telechargerQRCode()"><i class="fa-solid fa-download"></i> Télécharger</button><button class="btn-sm btn-sm-blue" onclick="imprimerQRCode()"><i class="fa-solid fa-print"></i> Imprimer</button><button class="btn-primary" onclick="regenererQRCode()">Régénérer</button></div></div></div></div></div>

<script>
    let semaineOffset = 0, currentQRPath = '', currentExpireAt = null, qrTimerInterval = null, autoRegenerateInterval = null, searchTimer, currentSection = 'file', isRefreshing = false;
    
    function escapeHtml(t) { if(!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    
    function afficherMessage(msg, type) {
        const c = document.getElementById('messageContainer'); if(!c) return;
        const m = document.createElement('div');
        m.className = `action-msg ${type === 'error' ? 'action-msg-error' : 'action-msg-success'}`;
        m.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check'}"></i> ${escapeHtml(msg)}`;
        c.innerHTML = ''; c.appendChild(m);
        setTimeout(() => { if(m) { m.style.opacity = '0'; setTimeout(() => m.remove(), 500); } }, 5000);
    }
    
    function showRefreshIndicator(show) { const ind = document.getElementById('refreshIndicator'); if(ind) show ? ind.classList.add('show') : ind.classList.remove('show'); }
    
    function rafraichirDonnees() {
        if(isRefreshing) return;
        isRefreshing = true; showRefreshIndicator(true);
        fetch('gestionnaire.php?action=get_dashboard_data')
            .then(r=>r.json()).then(d=>{
                if(d.success){
                    if(currentSection==='file'){ mettreAJourFileAttente(d.file); const fc=document.getElementById('fileCount'); if(fc) fc.innerHTML=`${d.file.length} patient(s)`; }
                    if(currentSection==='consultations'){ mettreAJourConsultations(d.consultations); const cc=document.getElementById('consultationsCount'); if(cc) cc.innerHTML=d.consultations.length; }
                    mettreAJourStatistiques(d.stats);
                }
            }).catch(e=>console.error('Erreur refresh:',e))
            .finally(()=>{ isRefreshing=false; showRefreshIndicator(false); });
    }
    
    function mettreAJourFileAttente(file) {
        const c = document.getElementById('fileContent'); if(!c) return;
        if(file.length===0){ c.innerHTML='<div class="empty-state">Aucun patient en attente.</div>'; return; }
        let h='<div style="overflow-x:auto;"><table class="file-table"><thead><tr><th>Rang</th><th>Patient</th><th>Téléphone</th><th>Heure</th><th>Mode</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        for(const f of file){
            h+=`<tr><td><div class="rang-badge ${f.statut==='en_cours'?'actif':''}">${f.rang}</div></td><td><strong>${escapeHtml(f.patient_nom)} ${escapeHtml(f.patient_prenom)}</strong></td><td>${escapeHtml(f.telephone)}</td><td>${f.heure_passage_estimee||'—'}</td><td>${f.mode_prise==='LIGNE'?'<span class="badge badge-blue">En ligne</span>':'<span class="badge badge-green">Sur place</span>'}</td><td><span class="badge badge-grey">${f.statut||'en attente'}</span></td><td><form class="status-form" style="display:flex;gap:5px;"><input type="hidden" name="action" value="maj_statut"><input type="hidden" name="consultation_id" value="${f.id}"><button type="submit" name="statut" value="traite" class="btn-sm btn-sm-green"><i class="fa-solid fa-check"></i> Traité</button><button type="submit" name="statut" value="absent" class="btn-sm btn-sm-orange"><i class="fa-solid fa-user-slash"></i> Absent</button><button type="submit" name="statut" value="annule" class="btn-sm btn-sm-red"><i class="fa-solid fa-xmark"></i> Annuler</button></form></td></tr>`;
        }
        h+='</tbody></table></div>';
        c.innerHTML = h;
        document.querySelectorAll('.status-form').forEach(f=>f.addEventListener('submit',function(e){e.preventDefault();soumettreFormulaireStatut(this);}));
    }
    
    function mettreAJourConsultations(consultations) {
        const c = document.getElementById('consultationsContent'); if(!c) return;
        if(consultations.length===0){ c.innerHTML='<div class="empty-state">Aucune consultation.</div>'; return; }
        let h='<div style="overflow-x:auto;"><table class="file-table"><thead><tr><th>#</th><th>Patient</th><th>Médecin</th><th>Heure</th><th>Statut</th></tr></thead><tbody>';
        for(const ct of consultations){
            let bc='badge-grey',lb=ct.statut;
            if(ct.statut==='traite'){ bc='badge-green'; lb='Traitée'; }
            else if(ct.statut==='en_cours'){ bc='badge-blue'; lb='En cours'; }
            else if(ct.statut==='annule'){ bc='badge-red'; lb='Annulée'; }
            else if(ct.statut==='absent'){ bc='badge-red'; lb='Absent'; }
            else if(ct.statut==='en_attente'){ lb='En attente'; }
            else if(ct.statut==='confirme'){ lb='Confirmé'; }
            h+=`<tr><td>${ct.rang}</td><td><strong>${escapeHtml(ct.patient_nom)} ${escapeHtml(ct.patient_prenom)}</strong></td><td>${ct.medecin_nom?escapeHtml(ct.medecin_nom):'—'}</td><td>${ct.heure_passage_estimee||'—'}</td><td><span class="badge ${bc}">${lb}</span></td></tr>`;
        }
        h+='</tbody></table></div>';
        c.innerHTML = h;
    }
    
    function mettreAJourStatistiques(stats) {
        const st=document.getElementById('statTotal'), stt=document.getElementById('statTraitees'), sea=document.getElementById('statEnAttente'), sa=document.getElementById('statAbsentes'), san=document.getElementById('statAnnulees'), sel=document.getElementById('statEnLigne'), ssp=document.getElementById('statSurPlace');
        if(st) st.innerHTML=stats.total||0; if(stt) stt.innerHTML=stats.traitees||0; if(sea) sea.innerHTML=stats.en_attente||0;
        if(sa) sa.innerHTML=stats.absentes||0; if(san) san.innerHTML=stats.annulees||0; if(sel) sel.innerHTML=stats.en_ligne||0; if(ssp) ssp.innerHTML=stats.sur_place||0;
    }
    
    function soumettreFormulaireStatut(form) {
        fetch('gestionnaire.php?action=traiter_action_ajax',{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(d=>{if(d.success){afficherMessage(d.message,'success');rafraichirDonnees();}else afficherMessage(d.message||'Erreur','error');})
            .catch(()=>afficherMessage('Erreur','error'));
    }
    
    function soumettreConsultationManuelle() {
        const f=document.getElementById('formConsultManuelle'), fd=new FormData(f), btn=document.querySelector('#modalConsultation .btn-submit'), ot=btn?btn.innerHTML:'';
        if(btn){btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';btn.disabled=true;}
        fetch('gestionnaire.php?action=traiter_action_ajax',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(d=>{
                if(btn){btn.innerHTML=ot;btn.disabled=false;}
                if(d.success){fermerModalConsultation();afficherMessage(d.message,'success');rafraichirDonnees();}
                else afficherMessage(d.message||'Erreur','error');
            }).catch(()=>{if(btn){btn.innerHTML=ot;btn.disabled=false;}afficherMessage('Erreur','error');});
    }
    
    function showSection(section) {
        currentSection = section;
        const sf=document.getElementById('section-file'), sc=document.getElementById('section-consultations'), sst=document.getElementById('section-stats'), sp=document.getElementById('section-planning'), spr=document.getElementById('section-profil');
        if(sf) sf.style.display='none'; if(sc) sc.style.display='none'; if(sst) sst.style.display='none'; if(sp) sp.style.display='none'; if(spr) spr.style.display='none';
        if(section==='file' && sf) sf.style.display='block';
        else if(section==='consultations' && sc) sc.style.display='block';
        else if(section==='stats' && sst) sst.style.display='grid';
        else if(section==='planning' && sp){ sp.style.display='block'; chargerEmploiTemps(); }
        else if(section==='profil' && spr){ spr.style.display='block'; chargerProfil(); }
        document.querySelectorAll('.nav-item').forEach(i=>i.classList.remove('active'));
        if(event && event.currentTarget) event.currentTarget.classList.add('active');
    }
    
    function ouvrirModalChoix(){ const m=document.getElementById('modalChoix'); if(m) m.classList.add('active'); }
    function fermerModalChoix(){ const m=document.getElementById('modalChoix'); if(m) m.classList.remove('active'); }
    function fermerModalConsultation(){ const m=document.getElementById('modalConsultation'); if(m) m.classList.remove('active'); const f=document.getElementById('formConsultManuelle'); if(f) f.reset(); }
    function choisirMode(mode){ fermerModalChoix(); if(mode==='manuel'){ const f=document.getElementById('formConsultManuelle'); if(f) f.reset(); const m=document.getElementById('modalConsultation'); if(m) m.classList.add('active'); } else if(mode==='qr') ouvrirModalQR(); }
    
    function ouvrirModalQR(){ const m=document.getElementById('modalQR'); if(m) m.classList.add('active'); chargerQRCode(); }
    function fermerModalQR(){ const m=document.getElementById('modalQR'); if(m) m.classList.remove('active'); if(qrTimerInterval) clearInterval(qrTimerInterval); }
    function chargerQRCode(){ showQRLoading(true); fetch('gestionnaire.php?action=get_qrcode_actif').then(r=>r.json()).then(d=>{if(d.success && d.qr_code_path) afficherQRCode(d.qr_code_path, d.expire_at); else genererNouveauQRCode();}).catch(()=>genererNouveauQRCode()); }
    function genererNouveauQRCode(){ showQRLoading(true); const fd=new FormData(); fd.append('sous_service_id','<?= $sousService['id'] ?? 0 ?>'); fetch('gestionnaire.php?action=generer_qrcode',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success) afficherQRCode(d.qr_code_path,d.expire_at); else {showQRMessage('Erreur','error'); showQRLoading(false);}}).catch(()=>{showQRMessage('Erreur','error');showQRLoading(false);}); }
    function regenererQRCode(){ if(confirm('Générer un nouveau QR code ?')) genererNouveauQRCode(); }
    function afficherQRCode(path,expireAt){ const qi=document.getElementById('qrCodeImage'); if(qi) qi.src=path; currentQRPath=path; currentExpireAt=new Date(expireAt); showQRLoading(false); const qc=document.getElementById('qrContentState'); if(qc) qc.classList.remove('hidden'); demarrerTimerQR(); if(autoRegenerateInterval) clearInterval(autoRegenerateInterval); autoRegenerateInterval=setInterval(genererNouveauQRCode,20*60*1000); }
    function demarrerTimerQR(){ if(qrTimerInterval) clearInterval(qrTimerInterval); qrTimerInterval=setInterval(()=>{if(!currentExpireAt) return; const diff=currentExpireAt-new Date(), te=document.getElementById('qrTimer'); if(diff<=0){clearInterval(qrTimerInterval);if(te)te.textContent='Expiré';genererNouveauQRCode();}else{const m=Math.floor(diff/60000),s=Math.floor((diff%60000)/1000);if(te)te.textContent=`${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;}},1000); }
    function telechargerQRCode(){ if(currentQRPath) window.location.href='gestionnaire.php?action=telecharger_qrcode&path='+encodeURIComponent(currentQRPath); }
    function imprimerQRCode(){ if(currentQRPath){ const w=window.open('','_blank'); if(w){ w.document.write(`<html><head><title>QR Code</title><style>body{text-align:center;padding:50px;}img{width:250px;}</style></head><body><img src="${currentQRPath}"><h2>QueueCare</h2><p><?= htmlspecialchars($sousService['service_nom']??'') ?> - <?= htmlspecialchars($sousService['nom']??'') ?></p><script>window.print();<\/script></body></html>`); w.document.close(); } } }
    function showQRLoading(show){ const l=document.getElementById('qrLoadingState'), c=document.getElementById('qrContentState'); if(show){if(l)l.classList.remove('hidden');if(c)c.classList.add('hidden');}else if(l)l.classList.add('hidden'); }
    function showQRMessage(msg,type){ const d=document.getElementById('qrMessage'); if(!d) return; d.textContent=msg; d.classList.remove('hidden','qr-success','qr-error'); d.classList.add(type==='success'?'qr-success':'qr-error'); setTimeout(()=>{if(d)d.classList.add('hidden');},3000); }
    
    // Emploi du temps
    function getLundiSemaine(offset=0){ const t=new Date(), dow=t.getDay(), diff=dow===0?-6:-(dow-1), l=new Date(t); l.setDate(t.getDate()+diff+(offset*7)); return l; }
    function formatDateISO(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
    function formatDateFr(d){ return d.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'}); }
    function getNomJour(d){ return d.toLocaleDateString('fr-FR',{weekday:'long'}); }
    function changerSemaine(delta){ if(delta===0) semaineOffset=0; else semaineOffset+=delta; chargerEmploiTemps(); }
    function chargerEmploiTemps(){ const l=getLundiSemaine(semaineOffset), dd=formatDateISO(l), dim=new Date(l); dim.setDate(l.getDate()+6); const sl=document.getElementById('semaineLabel'); if(sl) sl.innerHTML=`${formatDateFr(l)} - ${formatDateFr(dim)}`; const pl=document.getElementById('planningLoading'), pt=document.getElementById('planningTable'); if(pl) pl.style.display='block'; if(pt) pt.style.display='none'; fetch(`gestionnaire.php?action=get_emploi_temps&date=${dd}`).then(r=>r.json()).then(d=>{if(pl)pl.style.display='none'; if(d.error){if(pt){pt.innerHTML=`<div class="empty-state">${escapeHtml(d.error)}</div>`;pt.style.display='block';}return;}afficherEmploiTempsResponsive(d);}).catch(()=>{if(pl)pl.style.display='none';if(pt){pt.innerHTML='<div class="empty-state">Erreur</div>';pt.style.display='block';}}); }
    function afficherEmploiTempsResponsive(data){ if(!data.medecins||data.medecins.length===0){const pt=document.getElementById('planningTable');if(pt){pt.innerHTML='<div class="empty-state">Aucun médecin</div>';pt.style.display='block';}return;} const l=getLundiSemaine(semaineOffset), jours=[]; for(let i=0;i<7;i++){const j=new Date(l);j.setDate(l.getDate()+i);jours.push(j);} let h='<div class="planning-wrapper"><table class="planning-table"><thead><tr><th class="planning-hour">Horaire</th>'; for(let i=0;i<jours.length;i++){const j=jours[i],nj=getNomJour(j),dj=formatDateFr(j),estAjd=formatDateISO(j)===formatDateISO(new Date());h+=`<th style="${estAjd?'background:#10b981;':''}">${nj}<br><small>${dj}</small></th>`;} h+='</tr></thead><tbody>'; const heures=[]; for(let hh=8;hh<=18;hh++){for(let mm=0;mm<60;mm+=30){if(hh===18&&mm===30)break;heures.push(`${String(hh).padStart(2,'0')}:${String(mm).padStart(2,'0')}`);}} for(const heure of heures){ const[hh,mm]=heure.split(':').map(Number); let nh=hh,nm=mm+30; if(nm>=60){nh++;nm-=60;} const hs=`${String(nh).padStart(2,'0')}:${String(nm).padStart(2,'0')}`; h+=`<tr><td class="planning-hour">${heure} - ${hs} </td>`; for(let i=0;i<jours.length;i++){ const dj=formatDateISO(jours[i]), consDuJour=data.consultations.filter(c=>c.date_consultation===dj), cons=consDuJour.find(c=>{const hd=c.heure_debut?c.heure_debut.substring(0,5):'';return hd===heure;}); if(cons){ const sc=cons.statut==='en_cours'?'current':(cons.statut==='traite'?'passed':''), st=cons.statut==='en_cours'?'En cours':(cons.statut==='traite'?'Traité':'En attente'), bc=cons.statut==='en_cours'?'badge-green':(cons.statut==='traite'?'badge-blue':'badge-orange'); h+=`<td><div class="consultation-card ${sc}"><div class="patient-name"><i class="fa-solid fa-user"></i> ${escapeHtml(cons.patient_nom)} ${escapeHtml(cons.patient_prenom)}</div><div class="consultation-time"><i class="fa-regular fa-clock"></i> ${cons.heure_debut?cons.heure_debut.substring(0,5):heure}</div><div class="consultation-status"><span class="badge ${bc}">${st}</span></div></div></td>`; }else{ let dp=false; if(data.plages_horaire){ for(const med of data.medecins){ const pl=data.plages_horaire[med.id]||[]; for(const pg of pl){ if(pg.jour===dj && heure>=pg.heure_debut && heure<pg.heure_fin){dp=true;break;} } if(dp)break; } } h+=`<td style="background:${dp?'#faf5ff':'#f8fafc'}"><div class="empty-slot">${dp?'<i class="fa-regular fa-clock"></i> Disponible':'<i class="fa-regular fa-calendar-xmark"></i> Hors plage'}</div></td>`; } } h+='</tr>'; } h+='</tbody></table></div>'; if(data.consultations.length===0) h+='<div class="empty-state">Aucune consultation cette semaine</div>'; const pt=document.getElementById('planningTable'); if(pt){pt.innerHTML=h;pt.style.display='block';} }
    
    // Profil
    function chargerProfil(){ const c=document.getElementById('profilContent'); if(!c) return; c.innerHTML='<div class="empty-state">Chargement...</div>'; fetch('gestionnaire.php?action=get_profil_data').then(r=>r.json()).then(d=>{if(d.success)afficherFormulaireProfil(d.profil);else c.innerHTML=`<div class="profil-error">${d.message||'Erreur'}</div>`;}).catch(()=>c.innerHTML='<div class="profil-error">Erreur</div>'); }
    function afficherFormulaireProfil(profil){ const h=`<div class="profil-section"><form id="profilForm"><div class="form-group"><label><i class="fa-solid fa-user"></i> Nom complet</label><input type="text" name="nom" id="profil_nom" value="${escapeHtml(profil.nom)}" required></div><div class="form-group"><label><i class="fa-solid fa-phone"></i> Téléphone</label><input type="tel" name="telephone" id="profil_telephone" value="${escapeHtml(profil.telephone)}" required></div><div class="form-group"><label><i class="fa-solid fa-envelope"></i> Email</label><input type="email" name="email" id="profil_email" value="${escapeHtml(profil.email)}" required></div><div class="separator"></div><h3>Changer le mot de passe</h3><div class="form-group"><label><i class="fa-solid fa-lock"></i> Mot de passe actuel</label><input type="password" name="password_actuel" id="profil_password_actuel" placeholder="Mot de passe actuel"></div><div class="form-group"><label><i class="fa-solid fa-key"></i> Nouveau mot de passe</label><input type="password" name="nouveau_password" id="profil_nouveau_password" placeholder="Min 8 car., 1 maj., 1 chiffre"></div><div class="form-group"><label><i class="fa-solid fa-check"></i> Confirmer</label><input type="password" name="confirmer_password" id="profil_confirmer_password" placeholder="Répéter"></div><button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button></form><div id="profilMessage"></div></div>`; document.getElementById('profilContent').innerHTML=h; const pf=document.getElementById('profilForm'); if(pf) pf.addEventListener('submit',function(e){e.preventDefault();enregistrerProfil();}); }
    function enregistrerProfil(){ const fd=new FormData(); fd.append('nom',document.getElementById('profil_nom').value); fd.append('telephone',document.getElementById('profil_telephone').value); fd.append('email',document.getElementById('profil_email').value); fd.append('password_actuel',document.getElementById('profil_password_actuel').value); fd.append('nouveau_password',document.getElementById('profil_nouveau_password').value); fd.append('confirmer_password',document.getElementById('profil_confirmer_password').value); const btn=document.querySelector('#profilForm button[type="submit"]'), ot=btn?btn.innerHTML:''; if(btn){btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';btn.disabled=true;} fetch('gestionnaire.php?action=mettre_a_jour_profil',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>{if(btn){btn.innerHTML=ot;btn.disabled=false;} const md=document.getElementById('profilMessage'); if(d.success){if(md)md.innerHTML=`<div class="profil-success">${d.message}</div>`;setTimeout(()=>window.location.reload(),1500);}else{let err='';if(d.errors)for(const[k,v]of Object.entries(d.errors))err+=`<div class="error-msg">- ${v}</div>`;else err=d.message||'Erreur';if(md)md.innerHTML=`<div class="profil-error">${err}</div>`;}}).catch(()=>{if(btn){btn.innerHTML=ot;btn.disabled=false;}const md=document.getElementById('profilMessage');if(md)md.innerHTML='<div class="profil-error">Erreur</div>';}); }
    
    function rechercherPatient(tel){ const cl=tel.replace(/[^0-9+]/g,''); if(cl.length<8) return; clearTimeout(searchTimer); searchTimer=setTimeout(async()=>{try{const r=await fetch('gestionnaire.php?action=dashboard&api=patient&tel='+encodeURIComponent(cl)); const p=await r.json(); if(p){const nf=document.getElementById('m_nom'), pf=document.getElementById('m_prenom'), ef=document.getElementById('m_email'); if(nf) nf.value=p.nom||''; if(pf) pf.value=p.prenom||''; if(ef) ef.value=p.email||''; }}catch(e){}},500); }
    
    let idleTimer, warnTimer;
    function resetIdleTimer(){ if(idleTimer) clearTimeout(idleTimer); if(warnTimer) clearTimeout(warnTimer); warnTimer=setTimeout(()=>{const b=document.getElementById('timeoutBanner');if(b)b.style.display='flex';},14*60*1000); idleTimer=setTimeout(()=>{window.location.href='gestionnaire.php?action=deconnexion&timeout=1';},15*60*1000); }
    
    window.onload = function(){ resetIdleTimer(); setInterval(()=>{ if(currentSection==='file'||currentSection==='consultations') rafraichirDonnees(); },30000); };
    document.onmousemove = resetIdleTimer; document.onkeydown = resetIdleTimer; document.onclick = resetIdleTimer; document.onscroll = resetIdleTimer;
    
    <?php if ($openQR): ?>
    setTimeout(ouvrirModalQR, 500);
    <?php endif; ?>
</script>
</body>
</html>