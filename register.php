<?php
require_once 'includes/header.php';
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/mail_functions.php';

$pageTitle = 'Inscription - Gestion des Absences';
$error = '';
$success = '';

// Récupération des filières pour les étudiants
try {
    $stmt = $pdo->query("SELECT id_filiere, nom FROM filieres ORDER BY nom");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des filières";
}

if (isset($_POST['register'])) {
    // Récupération des données communes
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    // Validation basique
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = "Tous les champs obligatoires doivent être remplis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format d'email invalide";
    } else {
        // Hashage du mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            if ($user_type === 'admin') {
                $username = $_POST['username'];
                // Vérification que l'email n'existe pas déjà
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Cet email est déjà utilisé";
                } else {
                    // Insertion de l'administrateur
                    $stmt = $pdo->prepare("INSERT INTO admins (username, password, email) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $email]);
                    $success = "Compte administrateur créé avec succès! Votre nom d'utilisateur est: $username";
                }
            } else {
                // Récupération des données spécifiques aux étudiants
                $numero_apogee = trim($_POST['numero_apogee']);
                $id_filiere = $_POST['id_filiere'];

                // Gestion de la photo de profil
                $photo_profil_path = null;
                if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/photos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $fileTmpPath = $_FILES['photo_profil']['tmp_name'];
                    $fileName = uniqid() . '_' . basename($_FILES['photo_profil']['name']);
                    $filePath = $uploadDir . $fileName;
                    $fileType = mime_content_type($fileTmpPath);

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($fileType, $allowedTypes)) {
                        if (move_uploaded_file($fileTmpPath, $filePath)) {
                            $photo_profil_path = $filePath;
                        } else {
                            $error = "Erreur lors de l'upload de la photo de profil.";
                        }
                    } else {
                        $error = "Format de photo non supporté (JPEG, PNG, GIF, WEBP uniquement).";
                    }
                } else {
                    $error = "Veuillez sélectionner une photo de profil.";
                }

                if (empty($numero_apogee) || empty($id_filiere) || empty($photo_profil_path)) {
                    $error = "Tous les champs obligatoires doivent être remplis";
                } else if (empty($error)) {
                    // Vérification que l'email et le numéro apogée n'existent pas déjà
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE email = ? OR numero_apogee = ?");
                    $stmt->execute([$email, $numero_apogee]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Cet email ou ce numéro Apogée est déjà utilisé";
                    } else {
                        // Insertion de l'étudiant avec la photo de profil
                        $stmt = $pdo->prepare("INSERT INTO etudiants (nom, prenom, email, numero_apogee, password, id_filiere, photo_profil) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nom, $prenom, $email, $numero_apogee, $hashed_password, $id_filiere, $photo_profil_path]);

                        // Envoi de l'email de bienvenue
                        $emailSent = sendWelcomeEmailToStudent($email, $nom, $prenom, $password);

                        $success = "Compte étudiant créé avec succès! Vous pouvez maintenant vous connecter.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'inscription: " . $e->getMessage();
        }
    }
}
?>

<link href="https://cdn.lineicons.com/4.0/lineicons.css" rel="stylesheet" />

<?php if(!empty($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="container" id="container">
    <div class="form-container register-container">
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="user_type" value="student">
            <h1>Inscription Étudiant</h1>
            <input type="text" name="nom" placeholder="Nom" required>
            <input type="text" name="prenom" placeholder="Prénom" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="numero_apogee" placeholder="Numéro Apogée" required>

            <select name="id_filiere" required>
                <option value="">-- Sélectionner une filière --</option>
                <?php foreach ($filieres as $filiere): ?>
                    <option value="<?php echo $filiere['id_filiere']; ?>"><?php echo htmlspecialchars($filiere['nom']); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="password" name="password" placeholder="Mot de passe" required>
            <input type="file" name="photo_profil" accept="image/*" >

            <button type="submit" name="register" class="register-btn">S'inscrire</button>
            <div class="form-footer">
                <p>Vous avez déjà un compte?<a href="index.php">Se connecter</a></p>
            </div>
        </form>
    </div>

    <!-- Admin Registration Form -->
    <div class="form-container login-container">
        <form action="" method="post">
            <input type="hidden" name="user_type" value="admin">
            <h1>Inscription Admin</h1>
            <input type="text" name="nom" placeholder="Nom" required>
            <input type="text" name="prenom" placeholder="Prénom" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="username" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Mot de passe" required>

            <button type="submit" name="register" class="register-btn">S'inscrire</button>
            <div class="form-footer">
                <p>Vous avez déjà un compte? <a href="index.php">Se connecter</a></p>
            </div>
        </form>
    </div>

    <!-- Overlay Effect -->
    <div class="overlay-container">
        <div class="overlay">
            <div class="overlay-panel overlay-left">
                <h1 class="title">Admin</h1>
                <p>Inscrivez-vous en tant qu'administrateur pour gérer le système</p>
                <button class="ghost-btn" id="login">
                    Inscription Admin
                    <i class="lni lni-arrow-left login"></i>
                </button>
            </div>
            <div class="overlay-panel overlay-right">
                <h1 class="title">Étudiant</h1>
                <p>Inscrivez-vous en tant qu'étudiant pour accéder à votre compte</p>
                <button class="ghost-btn" id="register">
                    Inscription Étudiant
                    <i class="lni lni-arrow-right register"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const registrationButton = document.getElementById('register');
        const loginButton = document.getElementById('login');
        const container = document.getElementById('container');

        // Initially show student registration
        container.classList.remove('right-panel-active');

        registrationButton.addEventListener('click', () => {
            container.classList.add('right-panel-active');
        });

        loginButton.addEventListener('click', () => {
            container.classList.remove('right-panel-active');
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>