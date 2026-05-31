<?php
// views/medecin/dashboard.php

// ── Timeout inactivité 15 min ──
const SESSION_TIMEOUT = 900;
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: medecin.php?action=connexion&timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

$initiale = mb_strtoupper(mb_substr($medecinNom, 0, 1));
$dureeMin = isset($affectation['duree_estimee']) ? round($affectation['duree_estimee'] / 60) : 30;
$hasService = $affectation && !empty($affectation['ss_id']);
$serviceNom = $affectation['service_nom'] ?? '';
$ssNom = $affectation['ss_nom'] ?? '';

// Récupérer la photo du médecin
$medecinPhoto = isset($medecin['photo']) && !empty($medecin['photo']) ? $medecin['photo'] : null;
$photoPath = $medecinPhoto ? $medecinPhoto : 'public/images/default-avatar.png';

if ($medecinPhoto && !file_exists(__DIR__ . '/../' . $medecinPhoto)) {
    $photoPath = 'public/images/default-avatar.png';
}

// Récupérer les messages flash
$messageAction = $_SESSION['message_action'] ?? '';
$erreurAction = $_SESSION['erreur_action'] ?? '';
unset($_SESSION['message_action']);
unset($_SESSION['erreur_action']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dashboard Médecin — QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/medecin.css">
    <style>
        .planning-wrapper {
            overflow-x: auto;
            border-radius: 16px;
        }
        .planning-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        .planning-table th {
            background: linear-gradient(135deg, #0052a0, #003d7a);
            color: white;
            padding: 12px;
            text-align: center;
            position: sticky;
            top: 0;
        }
        .planning-table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            vertical-align: top;
        }
        .planning-hour {
            background: #f8fafc;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }
        .planning-slot { min-height: 60px; }
        .planning-slot.empty { text-align: center; padding: 20px; color: #94a3b8; font-size: 0.75rem; }
        .refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0052a0;
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.75rem;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 1000;
        }
        .refresh-indicator.show { display: flex; }
        .refresh-indicator i { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .semaine-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .semaine-label {
            background: #dbeafe;
            color: #1e40af;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 10px;
            display: block;
            border: 3px solid #0052a0;
            background: #f0f4f8;
        }
        .photo-upload-area {
            text-align: center;
            margin-bottom: 20px;
        }
        .photo-upload-label {
            display: inline-block;
            padding: 6px 12px;
            background: #0052a0;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
        }
        .sidebar-avatar-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        .sidebar-avatar {
            width: 48px;
            height: 48px;
            background: #00a86b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            color: #0052a0;
            overflow: hidden;
        }
        .consultation-card {
            background: #dbeafe;
            border-left: 4px solid #0052a0;
            padding: 8px;
            margin: 2px 0;
            border-radius: 8px;
        }
        .consultation-card.current {
            background: #d1fae5;
            border-left-color: #10b981;
        }
        .consultation-card.passed {
            background: #f1f5f9;
            border-left-color: #94a3b8;
            opacity: 0.8;
        }
        .profil-form { max-width: 600px; margin: 0 auto; }
        .profil-form .form-group { margin-bottom: 20px; }
        .profil-form .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #1a2a3a; }
        .profil-form .form-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .profil-form .form-group input:focus { outline: none; border-color: #0052a0; }
        .profil-separator { border-top: 1px solid #e2e8f0; margin: 25px 0; }
        .profil-title { font-size: 1.2rem; font-weight: 700; color: #0052a0; margin-bottom: 20px; }
        .btn-save { background: linear-gradient(135deg, #0052a0, #003d7a); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; }
        .profil-success { background: #e6f7f0; color: #00a86b; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .profil-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .rang-badge {
            width: 32px;
            height: 32px;
            background: #e2e8f0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .rang-badge.current {
            background: #10b981;
            color: white;
            animation: pulse 1s infinite;
        }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .med-table { width: 100%; border-collapse: collapse; }
        .med-table th { text-align: left; padding: 12px; background: #f8fafc; font-weight: 600; font-size: 0.8rem; }
        .med-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-icon { font-size: 2rem; color: #0052a0; margin-bottom: 8px; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #0052a0; }
        .stat-label { font-size: 0.8rem; color: #64748b; }
        .empty-state { text-align: center; padding: 40px; color: #64748b; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fed7aa; color: #9a3412; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .btn-sm { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 600; margin: 0 2px; }
        .btn-sm-success { background: #10b981; color: white; }
        .btn-sm-info { background: #3b82f6; color: white; }
        .btn-sm-warning { background: #f59e0b; color: white; }
        .btn-sm-danger { background: #ef4444; color: white; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .alert-danger { background: #fee2e2; color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .section { background: white; border-radius: 20px; padding: 24px; margin-bottom: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #0052a0; }
        .btn-icon { padding: 8px 16px; background: #f0f4f8; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-left: 8px; }
        .topbar { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 99; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: #0052a0; }
        .dashboard-content { padding: 32px; }
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
            <div class="sidebar-logo">
                <i class="fa-solid fa-user-doctor"></i>
                <span>QueueCare</span>
            </div>
            <div class="sidebar-user">
                <?php if ($photoPath && file_exists(__DIR__ . '/../' . $photoPath) && $photoPath !== 'public/images/default-avatar.png'): ?>
                <img src="<?= $photoPath ?>" class="sidebar-avatar-img" alt="Photo de profil">
                <?php else: ?>
                <div class="sidebar-avatar"><?= $initiale ?></div>
                <?php endif; ?>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">Dr. <?= htmlspecialchars($medecinNom) ?></div>
                    <?php if ($hasService): ?>
                    <div class="sidebar-user-service"><?= htmlspecialchars($ssNom) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item active" data-section="consultations" onclick="showSection('consultations')">
                <i class="fa-solid fa-calendar-day"></i><span>Consultations du jour</span>
            </div>
            <div class="nav-item" data-section="planning" onclick="showSection('planning')">
                <i class="fa-solid fa-calendar-week"></i><span>Mon planning</span>
            </div>
            <div class="nav-item" data-section="stats" onclick="showSection('stats')">
                <i class="fa-solid fa-chart-line"></i><span>Mes statistiques</span>
            </div>
            <div class="nav-item" data-section="profil" onclick="showSection('profil')">
                <i class="fa-solid fa-user"></i><span>Mon profil</span>
            </div>
        </nav>
        
        <div class="sidebar-footer">
            <a href="accueil.php" class="logout-btn" style="margin-bottom: 8px;"><i class="fa-solid fa-house"></i><span>Accueil</span></a>
            <a href="medecin.php?action=deconnexion" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i><span>Déconnexion</span></a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="topbar">
            <h1 class="page-title">Tableau de bord</h1>
            <div>
                <button class="btn-icon" onclick="window.location.reload()"><i class="fa-solid fa-rotate-right"></i> Actualiser</button>
            </div>
        </div>
        
        <div class="dashboard-content">
            
            <div id="timeoutBanner" style="display:none;position:fixed;top:0;left:0;right:0;z-index:9999;background:#c2410c;color:#fff;padding:12px 24px;align-items:center;justify-content:space-between;gap:16px;font-size:.9rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.3);">
                <span><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i>Votre session expire dans moins d'1 minute.</span>
                <button onclick="document.getElementById('timeoutBanner').style.display='none';resetIdleTimer();">Rester connecté</button>
            </div>
            
            <div id="messageContainer">
                <?php if (!empty($messageAction)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($messageAction) ?></div>
                <?php endif; ?>
                <?php if (!empty($erreurAction)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($erreurAction) ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Section Consultations du jour -->
            <div id="section-consultations" class="section">
                <div class="section-header">
                    <span class="section-title"><i class="fa-solid fa-calendar-day"></i> Consultations du jour</span>
                    <div>
                        <?php if (!empty($consultations)): ?>
                        <button type="button" class="btn-sm btn-sm-danger" onclick="annulerToutesConsultations()"><i class="fa-solid fa-trash"></i> Annuler toutes</button>
                        <?php endif; ?>
                        <span id="consultationsCount" class="badge badge-info"><?= count($consultations) ?> consultation(s)</span>
                    </div>
                </div>
                <div id="consultationsContent">
                    <?php if (empty($consultations)): ?>
                    <div class="empty-state">Aucune consultation programmée pour aujourd'hui.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="med-table">
                            <thead><tr><th>#</th><th>Patient</th><th>Téléphone</th><th>Heure prévue</th><th>Heure début</th><th>Heure fin</th><th>Statut</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($consultations as $c): ?>
                                <tr data-id="<?= $c['id'] ?>">
                                    <td><div class="rang-badge <?= $c['statut'] === 'en_cours' ? 'current' : '' ?>"><?= (int)$c['rang'] ?></div></td>
                                    <td><strong><?= htmlspecialchars($c['patient_nom'] . ' ' . $c['patient_prenom']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['telephone']) ?></td>
                                    <td><?= $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—' ?></td>
                                    <td><?= $c['heure_debut_reelle'] ? date('H:i', strtotime($c['heure_debut_reelle'])) : '—' ?></td>
                                    <td><?= $c['heure_fin_reelle'] ? date('H:i', strtotime($c['heure_fin_reelle'])) : '—' ?></td>
                                    <td><?php $bc = match($c['statut']) { 'traite' => 'badge-success', 'en_cours' => 'badge-info', 'annule' => 'badge-danger', 'absent' => 'badge-warning', default => 'badge-secondary' }; $lb = match($c['statut']) { 'traite' => 'Traitée', 'en_cours' => 'En cours', 'annule' => 'Annulée', 'absent' => 'Absent', 'en_attente' => 'En attente', 'confirme' => 'Confirmé', default => $c['statut'] }; ?><span class="badge <?= $bc ?>"><?= $lb ?></span></td>
                                    <td><div style="display:flex; gap:5px;"><?php if ($c['statut'] === 'en_cours'): ?><button class="btn-sm btn-sm-success" onclick="terminerConsultation(<?= $c['id'] ?>)"><i class="fa-solid fa-check"></i> Terminer</button><?php elseif (in_array($c['statut'], ['en_attente', 'confirme'])): ?><button class="btn-sm btn-sm-info" onclick="demarrerConsultation(<?= $c['id'] ?>)"><i class="fa-solid fa-play"></i> Démarrer</button><?php endif; if (!in_array($c['statut'], ['traite', 'annule', 'absent'])): ?><button class="btn-sm btn-sm-warning" onclick="marquerAbsent(<?= $c['id'] ?>)"><i class="fa-solid fa-user-slash"></i> Absent</button><?php endif; ?></div></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section Planning -->
            <div id="section-planning" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-calendar-week"></i> Mon emploi du temps</span><button class="btn-icon" onclick="chargerPlanning()"><i class="fa-solid fa-rotate-right"></i> Rafraîchir</button></div>
                <div class="semaine-nav"><div style="display:flex;gap:10px;"><button class="btn-icon" onclick="changerSemaine(-1)"><i class="fa-solid fa-chevron-left"></i> Semaine précédente</button><button class="btn-icon" onclick="changerSemaine(0)"><i class="fa-solid fa-calendar-day"></i> Semaine en cours</button><button class="btn-icon" onclick="changerSemaine(1)">Semaine suivante <i class="fa-solid fa-chevron-right"></i></button></div><div id="semaineLabel" class="semaine-label">Chargement...</div></div>
                <div id="planningContainer"><div id="planningLoading" style="text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;color:#0052a0;"></i><p>Chargement...</p></div><div id="planningTable" style="display:none;"></div></div>
            </div>
            
            <!-- Section Statistiques -->
            <div id="section-stats" style="display:none;">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div class="stat-value" id="statTotal"><?= (int)($stats['total'] ?? 0) ?></div><div class="stat-label">Total consultations</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div class="stat-value" id="statTraitees"><?= (int)($stats['traitees'] ?? 0) ?></div><div class="stat-label">Consultations traitées</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div class="stat-value" id="statEnAttente"><?= (int)($stats['en_attente'] ?? 0) ?></div><div class="stat-label">En attente</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-user-slash"></i></div><div class="stat-value" id="statAbsentes"><?= (int)($stats['absentes'] ?? 0) ?></div><div class="stat-label">Patients absents</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-ban"></i></div><div class="stat-value" id="statAnnulees"><?= (int)($stats['annulees'] ?? 0) ?></div><div class="stat-label">Consultations annulées</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-clock"></i></div><div class="stat-value" id="statDureeMoy"><?php $dm = (int)($stats['duree_moy_sec'] ?? 0); echo $dm > 0 ? round($dm / 60) . ' min' : '—'; ?></div><div class="stat-label">Durée moyenne</div></div>
                </div>
            </div>
            
            <!-- Section Profil -->
            <div id="section-profil" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-user"></i> Mon profil</span></div>
                <div id="profilContent"><div class="empty-state">Chargement...</div></div>
            </div>
        </div>
    </main>
</div>

<script>
    let autoRefreshInterval = null, currentSection = 'consultations', isRefreshing = false, planningOffset = 0;
    
    function afficherMessage(msg, type) {
        const c = document.getElementById('messageContainer');
        if (!c) return;
        const d = document.createElement('div');
        d.className = type === 'error' ? 'alert alert-danger' : 'alert alert-success';
        d.innerHTML = msg;
        c.innerHTML = '';
        c.appendChild(d);
        setTimeout(() => d.remove(), 4000);
    }
    
    function showRefreshIndicator(show) {
        const ind = document.getElementById('refreshIndicator');
        if (ind) show ? ind.classList.add('show') : ind.classList.remove('show');
    }
    
    function rafraichirConsultations() {
        if (isRefreshing) return;
        isRefreshing = true;
        showRefreshIndicator(true);
        fetch('medecin.php?action=get_consultations_data')
            .then(r => r.json()).then(d => { if (d.success) { mettreAJourConsultations(d.consultations); mettreAJourStatistiques(d.stats); } })
            .catch(e => console.error(e))
            .finally(() => { isRefreshing = false; showRefreshIndicator(false); });
    }
    
    function rafraichirStatistiques() {
        if (isRefreshing) return;
        isRefreshing = true;
        showRefreshIndicator(true);
        fetch('medecin.php?action=get_stats_data')
            .then(r => r.json()).then(d => { if (d.success) mettreAJourStatistiques(d.stats); })
            .catch(e => console.error(e))
            .finally(() => { isRefreshing = false; showRefreshIndicator(false); });
    }
    
    function mettreAJourConsultations(cons) {
        const c = document.getElementById('consultationsContent');
        const cs = document.getElementById('consultationsCount');
        if (!c) return;
        if (!cons || cons.length === 0) { c.innerHTML = '<div class="empty-state">Aucune consultation programmée.</div>'; if(cs) cs.innerHTML = '0 consultation'; return; }
        if(cs) cs.innerHTML = cons.length + ' consultation(s)';
        let h = '<div style="overflow-x:auto;"><table class="med-table"><thead><tr><th>#</th><th>Patient</th><th>Téléphone</th><th>Heure prévue</th><th>Heure début</th><th>Heure fin</th><th>Statut</th><th>Action</th></tr></thead><tbody>';
        for(const v of cons) {
            const bc = v.statut === 'traite' ? 'badge-success' : (v.statut === 'en_cours' ? 'badge-info' : (v.statut === 'annule' ? 'badge-danger' : (v.statut === 'absent' ? 'badge-warning' : 'badge-secondary')));
            const lb = v.statut === 'traite' ? 'Traitée' : (v.statut === 'en_cours' ? 'En cours' : (v.statut === 'annule' ? 'Annulée' : (v.statut === 'absent' ? 'Absent' : (v.statut === 'en_attente' ? 'En attente' : 'Confirmé'))));
            h += `<tr><td><div class="rang-badge ${v.statut === 'en_cours' ? 'current' : ''}">${v.rang}</div></td><td><strong>${escapeHtml(v.patient_nom)} ${escapeHtml(v.patient_prenom)}</strong></td><td>${escapeHtml(v.telephone)}</td><td>${v.heure_passage_estimee || '—'}</td><td>${v.heure_debut_reelle || '—'}</td><td>${v.heure_fin_reelle || '—'}</td><td><span class="badge ${bc}">${lb}</span></td><td><div style="display:flex;gap:5px;">`;
            if(v.statut === 'en_cours') h += `<button class="btn-sm btn-sm-success" onclick="terminerConsultation(${v.id})"><i class="fa-solid fa-check"></i> Terminer</button>`;
            else if(v.statut === 'en_attente' || v.statut === 'confirme') h += `<button class="btn-sm btn-sm-info" onclick="demarrerConsultation(${v.id})"><i class="fa-solid fa-play"></i> Démarrer</button>`;
            if(!['traite','annule','absent'].includes(v.statut)) h += `<button class="btn-sm btn-sm-warning" onclick="marquerAbsent(${v.id})"><i class="fa-solid fa-user-slash"></i> Absent</button>`;
            h += `</div></td></tr>`;
        }
        h += '</tbody></table></div>';
        c.innerHTML = h;
    }
    
    function mettreAJourStatistiques(stats) {
        const st = document.getElementById('statTotal'), stt = document.getElementById('statTraitees'), sea = document.getElementById('statEnAttente'), sa = document.getElementById('statAbsentes'), san = document.getElementById('statAnnulees'), sdm = document.getElementById('statDureeMoy');
        if(st) st.innerHTML = stats.total || 0;
        if(stt) stt.innerHTML = stats.traitees || 0;
        if(sea) sea.innerHTML = stats.en_attente || 0;
        if(sa) sa.innerHTML = stats.absentes || 0;
        if(san) san.innerHTML = stats.annulees || 0;
        if(sdm) sdm.innerHTML = (stats.duree_moy_sec || 0) > 0 ? Math.round((stats.duree_moy_sec || 0) / 60) + ' min' : '—';
    }
    
    function escapeHtml(t) { if(!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    
    function demarrerConsultation(id) {
        fetch('medecin.php?action=demarrer_consultation_ajax', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'consultation_id=' + id })
            .then(r => r.json()).then(d => { if(d.success) { afficherMessage('Consultation démarrée', 'success'); rafraichirConsultations(); } else afficherMessage(d.message || 'Erreur', 'error'); })
            .catch(() => afficherMessage('Erreur', 'error'));
    }
    
    function terminerConsultation(id) {
        fetch('medecin.php?action=terminer_consultation_ajax', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'consultation_id=' + id })
            .then(r => r.json()).then(d => { if(d.success) { afficherMessage('Consultation terminée', 'success'); rafraichirConsultations(); } else afficherMessage(d.message || 'Erreur', 'error'); })
            .catch(() => afficherMessage('Erreur', 'error'));
    }
    
    function marquerAbsent(id) {
        fetch('medecin.php?action=marquer_absent_ajax', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'consultation_id=' + id })
            .then(r => r.json()).then(d => { if(d.success) { afficherMessage('Patient marqué absent', 'success'); rafraichirConsultations(); } else afficherMessage(d.message || 'Erreur', 'error'); })
            .catch(() => afficherMessage('Erreur', 'error'));
    }
    
    function annulerToutesConsultations() {
        if(confirm('⚠️ Annuler TOUTES vos consultations ?')) {
            fetch('medecin.php?action=annuler_toutes_ajax', { method: 'POST' })
                .then(r => r.json()).then(d => { if(d.success) { afficherMessage('Toutes annulées', 'success'); rafraichirConsultations(); } else afficherMessage('Erreur', 'error'); })
                .catch(() => afficherMessage('Erreur', 'error'));
        }
    }
    
    function showSection(section) {
        currentSection = section;
        document.getElementById('section-consultations').style.display = 'none';
        document.getElementById('section-planning').style.display = 'none';
        document.getElementById('section-stats').style.display = 'none';
        document.getElementById('section-profil').style.display = 'none';
        if(section === 'consultations') { document.getElementById('section-consultations').style.display = 'block'; rafraichirConsultations(); }
        else if(section === 'planning') { document.getElementById('section-planning').style.display = 'block'; chargerPlanning(); }
        else if(section === 'stats') { document.getElementById('section-stats').style.display = 'grid'; rafraichirStatistiques(); }
        else if(section === 'profil') { document.getElementById('section-profil').style.display = 'block'; chargerProfil(); }
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        if(event && event.currentTarget) event.currentTarget.classList.add('active');
    }
    
    // Planning
    function getLundiSemaine(offset) { offset = offset || 0; const t = new Date(), dow = t.getDay(), diff = dow === 0 ? -6 : -(dow - 1), l = new Date(t); l.setDate(t.getDate() + diff + (offset * 7)); return l; }
    function formatDateISO(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
    function formatDateFr(d) { return d.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'}); }
    function getNomJour(d) { return d.toLocaleDateString('fr-FR',{weekday:'long'}); }
    function changerSemaine(delta) { if(delta===0) planningOffset=0; else planningOffset+=delta; chargerPlanning(); }
    
    function chargerPlanning() {
        const l = getLundiSemaine(planningOffset), dd = formatDateISO(l), dim = new Date(l);
        dim.setDate(l.getDate()+6);
        document.getElementById('semaineLabel').innerHTML = `${formatDateFr(l)} - ${formatDateFr(dim)}`;
        document.getElementById('planningLoading').style.display = 'block';
        document.getElementById('planningTable').style.display = 'none';
        fetch(`medecin.php?action=get_planning_medecin&date=${dd}`)
            .then(r => r.json()).then(d => {
                document.getElementById('planningLoading').style.display = 'none';
                if(d.error) { document.getElementById('planningTable').innerHTML = `<div class="empty-state">${d.error}</div>`; document.getElementById('planningTable').style.display = 'block'; return; }
                afficherPlanning(d);
            }).catch(() => { document.getElementById('planningLoading').style.display = 'none'; document.getElementById('planningTable').innerHTML = '<div class="empty-state">Erreur</div>'; document.getElementById('planningTable').style.display = 'block'; });
    }
    
    function afficherPlanning(data) {
        const l = getLundiSemaine(planningOffset), jours = [];
        for(let i=0;i<7;i++){const j=new Date(l);j.setDate(l.getDate()+i);jours.push(j);}
        let h = '<div class="planning-wrapper"><table class="planning-table"><thead><tr><th>Horaire</th>';
        for(let i=0;i<jours.length;i++){const j=jours[i],nj=getNomJour(j),dj=formatDateFr(j),estAjd=formatDateISO(j)===formatDateISO(new Date());h+=`<th style="${estAjd?'background:#00a86b;':''}">${nj}<br><small>${dj}</small></th>`;}
        h+='</tr></thead><tbody>';
        const heures=[];for(let hh=8;hh<=18;hh++){for(let mm=0;mm<60;mm+=30){if(hh===18&&mm===30)break;heures.push(`${String(hh).padStart(2,'0')}:${String(mm).padStart(2,'0')}`);}}
        for(const heure of heures){
            const[hh,mm]=heure.split(':').map(Number);let nh=hh,nm=mm+30;if(nm>=60){nh++;nm-=60;}const hs=`${String(nh).padStart(2,'0')}:${String(nm).padStart(2,'0')}`;
            h+=`<tr><td class="planning-hour">${heure} - ${hs} </td>`;
            for(let i=0;i<jours.length;i++){
                const dj=formatDateISO(jours[i]),cons=data.consultations?.find(c=>c.date_consultation===dj&&c.heure_debut?.substring(0,5)===heure);
                if(cons){
                    const sc=cons.statut==='traite'?'passed':(cons.statut==='en_cours'?'current':''),bc=cons.statut==='traite'?'badge-success':(cons.statut==='en_cours'?'badge-info':'badge-secondary'),st=cons.statut==='traite'?'Traité':(cons.statut==='en_cours'?'En cours':'En attente');
                    h+=`<td><div class="consultation-card ${sc}" style="background:#dbeafe;border-left:4px solid #0052a0;padding:8px;border-radius:8px;"><div class="patient-name" style="font-weight:700;">${escapeHtml(cons.patient_nom)} ${escapeHtml(cons.patient_prenom)}</div><div class="consultation-time" style="font-size:0.7rem;color:#475569;">${heure}</div><div class="consultation-status"><span class="badge ${bc}" style="font-size:0.6rem;">${st}</span></div></div></td>`;
                }else{
                    h+=`<td><div class="empty-slot" style="text-align:center;padding:16px;color:#94a3b8;">— Libre —</div></td>`;
                }
            }
            h+='</tr>';
        }
        h+='</tbody></table></div>';
        if(!data.consultations||data.consultations.length===0)h+='<div class="empty-state">Aucune consultation cette semaine</div>';
        document.getElementById('planningTable').innerHTML = h;
        document.getElementById('planningTable').style.display = 'block';
    }
    
    // Profil
    function chargerProfil() {
        const c = document.getElementById('profilContent');
        c.innerHTML = '<div class="empty-state">Chargement...</div>';
        fetch('medecin.php?action=get_profil_data')
            .then(r=>r.json()).then(d=>{if(d.success)afficherFormulaireProfil(d.profil);else c.innerHTML=`<div class="profil-error">${d.message||'Erreur'}</div>`;})
            .catch(()=>c.innerHTML='<div class="profil-error">Erreur</div>');
    }
    
    function afficherFormulaireProfil(profil) {
        const pp = profil.photo && profil.photo !== 'null' && profil.photo !== '' ? profil.photo : 'public/images/default-avatar.png';
        const html = `<div class="profil-form"><form id="profilForm" enctype="multipart/form-data"><div class="photo-upload-area"><img id="photoPreview" class="photo-preview" src="${pp}"><div><label class="photo-upload-label"><i class="fa-solid fa-camera"></i> Changer la photo<input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/jpg" style="display:none;"></label></div><small>Formats: JPG, PNG (max 2MB)</small></div><div class="form-group"><label><i class="fa-solid fa-user"></i> Nom</label><input type="text" name="nom" id="profil_nom" value="${escapeHtml(profil.nom)}" required></div><div class="form-group"><label><i class="fa-solid fa-user"></i> Prénom</label><input type="text" name="prenom" id="profil_prenom" value="${escapeHtml(profil.prenom)}" required></div><div class="form-group"><label><i class="fa-solid fa-phone"></i> Téléphone</label><input type="tel" name="telephone" id="profil_telephone" value="${escapeHtml(profil.telephone)}" required></div><div class="form-group"><label><i class="fa-solid fa-envelope"></i> Email</label><input type="email" name="email" id="profil_email" value="${escapeHtml(profil.email)}" required></div><div class="profil-separator"></div><div class="profil-title">Changer le mot de passe</div><div class="form-group"><label><i class="fa-solid fa-lock"></i> Mot de passe actuel</label><input type="password" name="password_actuel" id="profil_password_actuel" placeholder="Mot de passe actuel"></div><div class="form-group"><label><i class="fa-solid fa-key"></i> Nouveau mot de passe</label><input type="password" name="nouveau_password" id="profil_nouveau_password" placeholder="Min 8 car., 1 maj., 1 chiffre"></div><div class="form-group"><label><i class="fa-solid fa-check"></i> Confirmer</label><input type="password" name="confirmer_password" id="profil_confirmer_password" placeholder="Répéter"></div><button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button></form><div id="profilMessage"></div></div>`;
        document.getElementById('profilContent').innerHTML = html;
        const pi = document.getElementById('photoInput'), ppv = document.getElementById('photoPreview');
        if(pi) pi.addEventListener('change',function(e){const f=e.target.files[0];if(f){const r=new FileReader();r.onload=function(ev){ppv.src=ev.target.result};r.readAsDataURL(f);}});
        const pf = document.getElementById('profilForm');
        if(pf) pf.addEventListener('submit',function(e){e.preventDefault();enregistrerProfil();});
    }
    
    function enregistrerProfil() {
        const fd = new FormData();
        fd.append('nom', document.getElementById('profil_nom').value);
        fd.append('prenom', document.getElementById('profil_prenom').value);
        fd.append('telephone', document.getElementById('profil_telephone').value);
        fd.append('email', document.getElementById('profil_email').value);
        fd.append('password_actuel', document.getElementById('profil_password_actuel').value);
        fd.append('nouveau_password', document.getElementById('profil_nouveau_password').value);
        fd.append('confirmer_password', document.getElementById('profil_confirmer_password').value);
        const pf = document.getElementById('photoInput')?.files[0];
        if(pf) fd.append('photo', pf);
        const btn = document.querySelector('#profilForm button[type="submit"]'), ot = btn ? btn.innerHTML : '';
        if(btn){btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';btn.disabled=true;}
        fetch('medecin.php?action=mettre_a_jour_profil',{method:'POST',body:fd})
            .then(r=>r.json()).then(d=>{
                if(btn){btn.innerHTML=ot;btn.disabled=false;}
                const md=document.getElementById('profilMessage');
                if(d.success){if(md)md.innerHTML=`<div class="profil-success">${d.message}</div>`;setTimeout(()=>window.location.reload(),1500);}
                else{let err='';if(d.errors)for(const[k,v]of Object.entries(d.errors))err+=`<div class="error-msg">- ${v}</div>`;else err=d.message||'Erreur';if(md)md.innerHTML=`<div class="profil-error">${err}</div>`;}
            }).catch(()=>{if(btn){btn.innerHTML=ot;btn.disabled=false;}const md=document.getElementById('profilMessage');if(md)md.innerHTML='<div class="profil-error">Erreur</div>';});
    }
    
    // Timer inactivité simplifié
    let idleTimer, warnTimer;
    function resetIdleTimer() {
        if(idleTimer) clearTimeout(idleTimer);
        if(warnTimer) clearTimeout(warnTimer);
        warnTimer = setTimeout(() => { const b = document.getElementById('timeoutBanner'); if(b) b.style.display = 'flex'; }, 14 * 60 * 1000);
        idleTimer = setTimeout(() => { window.location.href = 'medecin.php?action=deconnexion&timeout=1'; }, 15 * 60 * 1000);
    }
    
    window.onload = function() {
        resetIdleTimer();
        setInterval(() => { if(currentSection === 'consultations') rafraichirConsultations(); }, 30000);
    };
    
    document.onmousemove = resetIdleTimer;
    document.onkeydown = resetIdleTimer;
    document.onclick = resetIdleTimer;
    document.onscroll = resetIdleTimer;
</script>
</body>
</html>