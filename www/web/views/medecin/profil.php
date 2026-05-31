<?php
// views/medecin/profil.php
$medecin = $this->model->trouverParId($_SESSION['medecin_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil — QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/medecin.css">
    <style>
        .profil-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .profil-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .profil-header {
            background: linear-gradient(135deg, #0052a0, #003d7a);
            padding: 30px;
            color: white;
            text-align: center;
        }
        .profil-avatar {
            width: 80px;
            height: 80px;
            background: #00a86b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            font-weight: 700;
            color: #0052a0;
        }
        .profil-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a2a3a;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: #0052a0;
        }
        .btn-save {
            background: linear-gradient(135deg, #0052a0, #003d7a);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-back {
            background: #f1f5f9;
            color: #1e293b;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            border-radius: 8px;
        }
        .separator {
            border-top: 1px solid #e2e8f0;
            margin: 25px 0;
        }
        .error-msg {
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        .success-msg {
            background: #e6f7f0;
            color: #00a86b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body style="background: #f5f7fa;">
<div class="profil-container">
    <a href="medecin.php?action=dashboard" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
    </a>
    
    <div class="profil-card">
        <div class="profil-header">
            <div class="profil-avatar">
                <?= strtoupper(substr($medecin['prenom'], 0, 1)) ?>
            </div>
            <h2>Mon profil</h2>
            <p>Gérez vos informations personnelles</p>
        </div>
        
        <div class="profil-body">
            <div id="messageContainer"></div>
            
            <form id="profilForm">
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Nom</label>
                    <input type="text" name="nom" id="nom" value="<?= htmlspecialchars($medecin['nom']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Prénom</label>
                    <input type="text" name="prenom" id="prenom" value="<?= htmlspecialchars($medecin['prenom']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fa-solid fa-phone"></i> Téléphone</label>
                    <input type="tel" name="telephone" id="telephone" value="<?= htmlspecialchars($medecin['telephone']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fa-solid fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($medecin['email']) ?>" required>
                </div>
                
                <div class="separator"></div>
                
                <h3 style="margin-bottom: 20px;">Changer le mot de passe</h3>
                
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Mot de passe actuel</label>
                    <input type="password" name="password_actuel" id="password_actuel" placeholder="Entrez votre mot de passe actuel">
                </div>
                
                <div class="form-group">
                    <label><i class="fa-solid fa-key"></i> Nouveau mot de passe</label>
                    <input type="password" name="nouveau_password" id="nouveau_password" placeholder="Minimum 8 caractères, 1 majuscule, 1 chiffre">
                </div>
                
                <div class="form-group">
                    <label><i class="fa-solid fa-check"></i> Confirmer le mot de passe</label>
                    <input type="password" name="confirmer_password" id="confirmer_password" placeholder="Répétez le nouveau mot de passe">
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('profilForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';
    btn.disabled = true;
    
    try {
        const response = await fetch('medecin.php?action=mettre_a_jour_profil', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('messageContainer').innerHTML = 
                '<div class="success-msg"><i class="fa-solid fa-circle-check"></i> ' + data.message + '</div>';
            setTimeout(() => {
                window.location.href = 'medecin.php?action=dashboard';
            }, 1500);
        } else {
            let errors = '';
            for (const [key, value] of Object.entries(data.errors)) {
                errors += `<div class="error-msg">- ${value}</div>`;
            }
            document.getElementById('messageContainer').innerHTML = 
                '<div class="error-msg" style="background:#fee2e2;padding:12px;border-radius:8px;margin-bottom:20px;">' + errors + '</div>';
        }
    } catch (error) {
        document.getElementById('messageContainer').innerHTML = 
            '<div class="error-msg" style="background:#fee2e2;padding:12px;border-radius:8px;">Erreur lors de l\'enregistrement</div>';
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});

// Validation téléphone
document.getElementById('telephone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9+]/g, '');
});
</script>
</body>
</html>