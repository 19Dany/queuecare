<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QueueCare — Plateforme Santé</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,600,700,800|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="public/css/style.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      font-family: 'Outfit', sans-serif;
      background: linear-gradient(135deg, #0a2b5e 0%, #1a4db5 50%, #0a5c36 100%);
      position: relative;
      overflow-x: hidden;
    }

    /* ========== ANIMATIONS DE FOND ========== */
    .medical-bg {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      z-index: 0;
    }

    .medical-bg svg {
      position: absolute;
      opacity: 0.08;
    }

    /* Icônes flottantes */
    .floating-icon {
      position: absolute;
      font-size: 2rem;
      color: white;
      opacity: 0.12;
      animation: float 8s ease-in-out infinite;
      pointer-events: none;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(5deg); }
    }

    @keyframes floatReverse {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(20px) rotate(-5deg); }
    }

    .floating-icon:nth-child(1) { top: 10%; left: 5%; font-size: 4rem; animation-duration: 10s; }
    .floating-icon:nth-child(2) { top: 20%; right: 8%; font-size: 3rem; animation-duration: 12s; animation-name: floatReverse; }
    .floating-icon:nth-child(3) { bottom: 15%; left: 10%; font-size: 3.5rem; animation-duration: 14s; }
    .floating-icon:nth-child(4) { bottom: 25%; right: 15%; font-size: 2.8rem; animation-duration: 9s; animation-name: floatReverse; }
    .floating-icon:nth-child(5) { top: 50%; left: 15%; font-size: 2.5rem; animation-duration: 11s; }
    .floating-icon:nth-child(6) { top: 60%; right: 20%; font-size: 3.2rem; animation-duration: 13s; animation-name: floatReverse; }
    .floating-icon:nth-child(7) { top: 75%; left: 20%; font-size: 2rem; animation-duration: 7s; }
    .floating-icon:nth-child(8) { top: 30%; left: 30%; font-size: 2.2rem; animation-duration: 15s; }
    .floating-icon:nth-child(9) { bottom: 40%; right: 35%; font-size: 3rem; animation-duration: 11s; }
    .floating-icon:nth-child(10) { top: 80%; right: 45%; font-size: 2.5rem; animation-duration: 9s; }

    /* Cercles décoratifs */
    .deco-circle {
      position: absolute;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255,255,255,0.1), transparent);
      pointer-events: none;
    }

    .deco-circle-1 { width: 600px; height: 600px; top: -200px; right: -200px; }
    .deco-circle-2 { width: 400px; height: 400px; bottom: -150px; left: -150px; }
    .deco-circle-3 { width: 200px; height: 200px; top: 50%; left: 50%; transform: translate(-50%, -50%); }

    /* ========== BOUTON PARAMÈTRES ========== */
    .settings-btn {
      position: fixed;
      top: 25px;
      left: 25px;
      width: 52px;
      height: 52px;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 100;
      border: none;
      backdrop-filter: blur(4px);
    }

    .settings-btn:hover {
      transform: rotate(90deg) scale(1.05);
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
      background: white;
    }

    .settings-btn i {
      font-size: 1.5rem;
      color: #1a4db5;
    }

    /* ========== MENU LATÉRAL ========== */
    .services-menu {
      position: fixed;
      top: 0;
      left: -380px;
      width: 380px;
      height: 100vh;
      background: white;
      box-shadow: 4px 0 30px rgba(0, 0, 0, 0.15);
      transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 200;
      overflow-y: auto;
      border-radius: 0 20px 20px 0;
    }

    .services-menu.open {
      left: 0;
    }

    .services-menu-header {
      background: linear-gradient(135deg, #0a2b5e, #1a4db5);
      padding: 28px 24px;
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .services-menu-header h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem;
      font-weight: 700;
    }

    .services-menu-header h3 i {
      margin-right: 10px;
      color: #4ade80;
    }

    .close-menu {
      background: rgba(255, 255, 255, 0.15);
      border: none;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      cursor: pointer;
      color: white;
      font-size: 1.1rem;
      transition: all 0.2s;
    }

    .close-menu:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: rotate(90deg);
    }

    .services-list {
      padding: 24px;
    }

    .service-item {
      background: #f8faff;
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 16px;
      border: 1px solid #e2e8f0;
      transition: all 0.25s;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 16px;
      text-decoration: none;
    }

    .service-item:hover {
      border-color: #1a4db5;
      background: #eff6ff;
      transform: translateX(8px);
    }

    .service-icon {
      width: 52px;
      height: 52px;
      background: linear-gradient(135deg, #1a4db5, #2563eb);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.3rem;
    }

    .service-info h4 {
      color: #0a2b5e;
      margin-bottom: 6px;
      font-size: 1rem;
      font-weight: 700;
    }

    .service-info p {
      color: #6b83a8;
      font-size: 0.75rem;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(3px);
      z-index: 150;
      display: none;
    }

    .overlay.active {
      display: block;
    }

    /* ========== CONTENEUR PRINCIPAL ========== */
    .main-container {
      position: relative;
      z-index: 10;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }

    /* ========== CARTE DE CONNEXION ========== */
    .login-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 40px;
      box-shadow: 0 30px 70px rgba(0, 0, 0, 0.25);
      width: 100%;
      max-width: 500px;
      overflow: hidden;
      animation: fadeInUp 0.5s ease-out;
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(40px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* En-tête de la carte */
    .login-header {
      background: linear-gradient(135deg, #0a2b5e, #1a4db5);
      padding: 36px 32px;
      text-align: center;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .login-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
      background-size: 30px 30px;
      pointer-events: none;
    }

    .login-logo {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      font-size: 1.8rem;
      font-weight: 800;
      font-family: 'Playfair Display', serif;
      margin-bottom: 20px;
    }

    .login-logo i {
      font-size: 2.2rem;
      color: #4ade80;
    }

    .login-header h1 {
      font-size: 1.6rem;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .login-header p {
      font-size: 0.9rem;
      opacity: 0.85;
    }

    /* Corps de la carte */
    .login-body {
      padding: 36px 32px;
    }

    /* Badge de rôle */
    .role-badge {
      text-align: center;
      margin-bottom: 28px;
    }

    .badge-role {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      color: #0a5c36;
      padding: 8px 20px;
      border-radius: 40px;
      font-size: 0.8rem;
      font-weight: 700;
    }

    .badge-role i {
      font-size: 1rem;
    }

    /* Champs de formulaire */
    .form-group {
      margin-bottom: 24px;
    }

    .form-group label {
      display: block;
      font-size: 0.75rem;
      font-weight: 700;
      color: #0d2d6b;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group label i {
      margin-right: 6px;
      color: #1a4db5;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #6b83a8;
      font-size: 1rem;
    }

    .form-input {
      width: 100%;
      padding: 14px 16px 14px 48px;
      border: 2px solid #e2e8f0;
      border-radius: 16px;
      font-family: 'Outfit', sans-serif;
      font-size: 0.95rem;
      transition: all 0.2s;
      background: #f8faff;
    }

    .form-input:focus {
      outline: none;
      border-color: #1a4db5;
      background: white;
      box-shadow: 0 0 0 4px rgba(26, 77, 181, 0.1);
    }

    /* Options ligne */
    .form-options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
    }

    .checkbox-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }

    .checkbox-wrapper input {
      width: 18px;
      height: 18px;
      accent-color: #1a8a52;
      cursor: pointer;
    }

    .checkbox-wrapper span {
      font-size: 0.85rem;
      color: #4a6fa5;
    }

    .forgot-link {
      font-size: 0.85rem;
      color: #1a4db5;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s;
    }

    .forgot-link:hover {
      color: #0a2b5e;
      text-decoration: underline;
    }

    /* Bouton de connexion */
    .login-btn {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #1a8a52, #0a5c36);
      color: white;
      border: none;
      border-radius: 16px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(26, 138, 82, 0.35);
    }

    /* Lien création de compte */
    .register-section {
      text-align: center;
      margin-top: 28px;
      padding-top: 24px;
      border-top: 1px solid #e2e8f0;
    }

    .register-section p {
      font-size: 0.9rem;
      color: #64748b;
    }

    .register-link {
      color: #1a4db5;
      text-decoration: none;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: gap 0.2s;
    }

    .register-link:hover {
      gap: 10px;
      text-decoration: underline;
    }

    /* Messages */
    .error-msg, .success-msg {
      padding: 14px 18px;
      border-radius: 14px;
      margin-bottom: 24px;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .error-msg {
      background: #fee2e2;
      color: #991b1b;
      border-left: 4px solid #ef4444;
    }

    .success-msg {
      background: #d1fae5;
      color: #065f46;
      border-left: 4px solid #10b981;
    }

    /* ========== ICÔNES DE NAVIGATION EN BAS ========== */
    .nav-icons {
      position: fixed;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 15px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 8px 12px;
      border-radius: 60px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      z-index: 100;
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .nav-icon {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      cursor: pointer;
      padding: 10px 28px;
      border-radius: 50px;
      transition: all 0.3s ease;
      background: transparent;
      border: none;
      font-family: 'Outfit', sans-serif;
    }

    .nav-icon i {
      font-size: 1.6rem;
      transition: all 0.3s ease;
    }

    .nav-icon span {
      font-size: 0.7rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    /* Gestionnaire */
    .nav-icon.gestionnaire i {
      color: #1a8a52;
    }
    .nav-icon.gestionnaire span {
      color: #1a8a52;
    }
    .nav-icon.gestionnaire.active {
      background: linear-gradient(135deg, #1a8a52, #0a5c36);
    }
    .nav-icon.gestionnaire.active i,
    .nav-icon.gestionnaire.active span {
      color: white;
    }

    /* Médecin */
    .nav-icon.medecin i {
      color: #1a4db5;
    }
    .nav-icon.medecin span {
      color: #1a4db5;
    }
    .nav-icon.medecin.active {
      background: linear-gradient(135deg, #1a4db5, #0a2b5e);
    }
    .nav-icon.medecin.active i,
    .nav-icon.medecin.active span {
      color: white;
    }

    /* Responsive */
    @media (max-width: 640px) {
      .nav-icons {
        bottom: 20px;
        gap: 8px;
        padding: 6px 10px;
      }
      .nav-icon {
        padding: 8px 16px;
      }
      .nav-icon i {
        font-size: 1.3rem;
      }
      .login-card {
        margin-bottom: 80px;
      }
      .login-header {
        padding: 28px 24px;
      }
      .login-body {
        padding: 28px 24px;
      }
      .services-menu {
        width: 320px;
        left: -320px;
      }
    }
  </style>
</head>
<body>

  <!-- Animations de fond médicales -->
  <div class="medical-bg">
    <div class="deco-circle deco-circle-1"></div>
    <div class="deco-circle deco-circle-2"></div>
    <div class="deco-circle deco-circle-3"></div>
    
    <!-- Icônes flottantes -->
    <div class="floating-icon"><i class="fa-solid fa-heart-pulse"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-stethoscope"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-hospital"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-syringe"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-capsules"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-microscope"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-ambulance"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-notes-medical"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-brain"></i></div>
    <div class="floating-icon"><i class="fa-solid fa-lungs"></i></div>
  </div>

  <!-- Bouton Paramètres -->
  <button class="settings-btn" onclick="toggleServicesMenu()">
    <i class="fa-solid fa-gear"></i>
  </button>

  <!-- Menu latéral des services -->
  <div id="servicesMenu" class="services-menu">
    <div class="services-menu-header">
      <h3><i class="fa-solid fa-hospital"></i> Administration</h3>
      <button class="close-menu" onclick="toggleServicesMenu()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="services-list">
      <a href="service.php?action=liste" class="service-item">
        <div class="service-icon"><i class="fa-solid fa-building"></i></div>
        <div class="service-info">
          <h4>Services Hospitaliers</h4>
          <p>Créer, modifier ou supprimer les établissements</p>
        </div>
      </a>
      <a href="service.php?action=sous_services" class="service-item">
        <div class="service-icon"><i class="fa-solid fa-sitemap"></i></div>
        <div class="service-info">
          <h4>Sous-services</h4>
          <p>Configurer les spécialités par établissement</p>
        </div>
      </a>
      <a href="service.php?action=planning" class="service-item">
        <div class="service-icon"><i class="fa-solid fa-calendar-week"></i></div>
        <div class="service-info">
          <h4>Emplois du temps</h4>
          <p>Définir les plannings des médecins</p>
        </div>
      </a>
    </div>
  </div>

  <!-- Overlay -->
  <div id="overlay" class="overlay" onclick="toggleServicesMenu()"></div>

  <!-- Icônes de navigation en bas -->
  <div class="nav-icons">
    <button class="nav-icon gestionnaire active" onclick="switchToGestionnaire()">
      <i class="fa-solid fa-user-tie"></i>
      <span>Gestionnaire</span>
    </button>
    <button class="nav-icon medecin" onclick="switchToMedecin()">
      <i class="fa-solid fa-user-doctor"></i>
      <span>Médecin</span>
    </button>
  </div>

  <!-- Conteneur principal -->
  <div class="main-container">
    
    <!-- Formulaire Gestionnaire -->
    <div id="gestionnaireForm" class="login-card">
      <div class="login-header">
        <div class="login-logo">
          <i class="fa-solid fa-list-check"></i>
          <span>QueueCare</span>
        </div>
        <h1>Espace Gestionnaire</h1>
        <p>Gérez la file d'attente et les consultations</p>
      </div>
      <div class="login-body">
        <div class="role-badge">
          <span class="badge-role">
            <i class="fa-solid fa-user-tie"></i> Connexion Gestionnaire
          </span>
        </div>

        <div id="gestionnaireMessages"></div>

        <form id="gestionnaireLoginForm" method="POST" action="gestionnaire.php?action=connexion">
          <div class="form-group">
            <label><i class="fa-solid fa-envelope"></i> Adresse email</label>
            <div class="input-wrapper">
              <span class="input-icon"><i class="fa-solid fa-envelope"></i></span>
              <input type="email" name="email" class="form-input" placeholder="exemple@hopital.cm" required>
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa-solid fa-lock"></i> Mot de passe</label>
            <div class="input-wrapper">
              <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
              <input type="password" name="password" class="form-input" placeholder="Votre mot de passe" required>
            </div>
          </div>
          <div class="form-options">
            <label class="checkbox-wrapper">
              <input type="checkbox" name="remember">
              <span>Se souvenir de moi</span>
            </label>
            <a href="#" class="forgot-link" onclick="alert('Un lien de réinitialisation vous sera envoyé par email.')">Mot de passe oublié ?</a>
          </div>
          <button type="submit" class="login-btn">
            <i class="fa-solid fa-right-to-bracket"></i> Se connecter
          </button>
          <div class="register-section">
            <p>Pas encore de compte ? <a href="gestionnaire.php?action=inscription" class="register-link">Créer un compte <i class="fa-solid fa-arrow-right"></i></a></p>
          </div>
        </form>
      </div>
    </div>

    <!-- Formulaire Médecin -->
    <div id="medecinForm" class="login-card" style="display: none;">
      <div class="login-header">
        <div class="login-logo">
          <i class="fa-solid fa-list-check"></i>
          <span>QueueCare</span>
        </div>
        <h1>Espace Médecin</h1>
        <p>Gérez vos consultations et votre planning</p>
      </div>
      <div class="login-body">
        <div class="role-badge">
          <span class="badge-role">
            <i class="fa-solid fa-user-doctor"></i> Connexion Médecin
          </span>
        </div>

        <div id="medecinMessages"></div>

        <form id="medecinLoginForm" method="POST" action="medecin.php?action=connexion">
          <div class="form-group">
            <label><i class="fa-solid fa-envelope"></i> Adresse email</label>
            <div class="input-wrapper">
              <span class="input-icon"><i class="fa-solid fa-envelope"></i></span>
              <input type="email" name="email" class="form-input" placeholder="medecin@hopital.cm" required>
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa-solid fa-lock"></i> Mot de passe</label>
            <div class="input-wrapper">
              <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
              <input type="password" name="password" class="form-input" placeholder="Votre mot de passe" required>
            </div>
          </div>
          <div class="form-options">
            <label class="checkbox-wrapper">
              <input type="checkbox" name="remember">
              <span>Se souvenir de moi</span>
            </label>
            <a href="#" class="forgot-link" onclick="alert('Un lien de réinitialisation vous sera envoyé par email.')">Mot de passe oublié ?</a>
          </div>
          <button type="submit" class="login-btn">
            <i class="fa-solid fa-right-to-bracket"></i> Se connecter
          </button>
          <div class="register-section">
            <p>Pas encore de compte ? <a href="medecin.php?action=inscription" class="register-link">Créer un compte <i class="fa-solid fa-arrow-right"></i></a></p>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Récupérer les messages éventuels depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    
    function showMessage(containerId, message, type) {
      const container = document.getElementById(containerId);
      if (!container) return;
      
      const msgDiv = document.createElement('div');
      msgDiv.className = type === 'error' ? 'error-msg' : 'success-msg';
      msgDiv.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check'}"></i> ${message}`;
      container.innerHTML = '';
      container.appendChild(msgDiv);
      
      setTimeout(() => {
        msgDiv.style.opacity = '0';
        setTimeout(() => msgDiv.remove(), 500);
      }, 5000);
    }

    // Vérifier les paramètres d'URL
    if (urlParams.has('timeout')) {
      showMessage('gestionnaireMessages', 'Session expirée. Veuillez vous reconnecter.', 'error');
    }
    if (urlParams.has('deconnecte')) {
      showMessage('gestionnaireMessages', 'Vous avez été déconnecté avec succès.', 'success');
    }
    if (urlParams.has('inscription') && urlParams.get('inscription') === 'succes') {
      showMessage('gestionnaireMessages', 'Compte créé avec succès ! Veuillez vous connecter.', 'success');
    }

    // Basculer vers l'espace Gestionnaire
    function switchToGestionnaire() {
      document.getElementById('gestionnaireForm').style.display = 'block';
      document.getElementById('medecinForm').style.display = 'none';
      
      document.querySelector('.nav-icon.gestionnaire').classList.add('active');
      document.querySelector('.nav-icon.medecin').classList.remove('active');
      
      const url = new URL(window.location.href);
      url.searchParams.delete('action');
      window.history.pushState({}, '', url);
    }

    // Basculer vers l'espace Médecin
    function switchToMedecin() {
      document.getElementById('gestionnaireForm').style.display = 'none';
      document.getElementById('medecinForm').style.display = 'block';
      
      document.querySelector('.nav-icon.medecin').classList.add('active');
      document.querySelector('.nav-icon.gestionnaire').classList.remove('active');
      
      const url = new URL(window.location.href);
      url.searchParams.set('action', 'medecin');
      window.history.pushState({}, '', url);
    }

    // Menu des services
    function toggleServicesMenu() {
      const menu = document.getElementById('servicesMenu');
      const overlay = document.getElementById('overlay');
      menu.classList.toggle('open');
      overlay.classList.toggle('active');
    }

    // Intercepter la soumission des formulaires
    document.getElementById('gestionnaireLoginForm')?.addEventListener('submit', function(e) {
      const btn = this.querySelector('button[type="submit"]');
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Connexion...';
      btn.disabled = true;
    });

    document.getElementById('medecinLoginForm')?.addEventListener('submit', function(e) {
      const btn = this.querySelector('button[type="submit"]');
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Connexion...';
      btn.disabled = true;
    });

    // Afficher le bon formulaire selon l'URL
    if (urlParams.has('action') && urlParams.get('action') === 'medecin') {
      switchToMedecin();
    } else {
      switchToGestionnaire();
    }
  </script>
</body>
</html>