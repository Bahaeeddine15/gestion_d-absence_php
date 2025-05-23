<?php
require_once __DIR__ . '/../includes/auth.php';
CheckAdmin();
$title = 'Gestion des modules';
include __DIR__ . '/../includes/header.php';

$message = "";
$error = "";

// Add new absence
if (isset($_POST['add'])) {
    $id_etudiant = $_POST['id_etudiant'];
    $id_module = $_POST['id_module'];
    $date = $_POST['date'];
    $justifiee = isset($_POST['justifiee']) ? 1 : 0;
    $commentaire = trim($_POST['commentaire']);

    if (empty($id_etudiant) || empty($id_module) || empty($date)) {
        $error = "L'étudiant, le module et la date sont obligatoires";
    } else {
        try {
            // Check if absence already exists for this student on this date for this module
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE id_etudiant = ? AND id_module = ? AND date = ?");
            $stmt->execute([$id_etudiant, $id_module, $date]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Cette absence existe déjà pour cet étudiant à cette date et dans ce module";
            } else {
                // Insert new absence
                $stmt = $pdo->prepare("INSERT INTO absences (id_etudiant, id_module, date, justifiee, commentaire) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_etudiant, $id_module, $date, $justifiee, $commentaire]);
                $message = "Absence ajoutée avec succès";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'ajout de l'absence: " . $e->getMessage();
        }
    }
}

// Update absence
if (isset($_POST['update'])) {
    $id = $_POST['id_absence'];
    $id_etudiant = $_POST['id_etudiant'];
    $id_module = $_POST['id_module'];
    $date = $_POST['date'];
    $justifiee = isset($_POST['justifiee']) ? 1 : 0;
    $commentaire = trim($_POST['commentaire']);

    if (empty($id_etudiant) || empty($id_module) || empty($date)) {
        $error = "L'étudiant, le module et la date sont obligatoires";
    } else {
        try {
            // Check if another absence exists for this student on this date for this module
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE id_etudiant = ? AND id_module = ? AND date = ? AND id_absence != ?");
            $stmt->execute([$id_etudiant, $id_module, $date, $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Une autre absence existe déjà pour cet étudiant à cette date et dans ce module";
            } else {
                // Update absence
                $stmt = $pdo->prepare("UPDATE absences SET id_etudiant = ?, id_module = ?, date = ?, justifiee = ?, commentaire = ? WHERE id_absence = ?");
                $stmt->execute([$id_etudiant, $id_module, $date, $justifiee, $commentaire, $id]);
                $message = "Absence mise à jour avec succès";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour de l'absence: " . $e->getMessage();
        }
    }
}

// Delete absence
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    try {
        $stmt = $pdo->prepare("DELETE FROM absences WHERE id_absence = ?");
        $stmt->execute([$id]);
        $message = "Absence supprimée avec succès";
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression de l'absence: " . $e->getMessage();
    }
}

// Get absence to edit
$absence_to_edit = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM absences WHERE id_absence = ?");
        $stmt->execute([$id]);
        $absence_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$absence_to_edit) {
            $error = "Absence non trouvée";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la récupération de l'absence: " . $e->getMessage();
    }
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$etudiant_filter = isset($_GET['etudiant']) ? $_GET['etudiant'] : '';
$module_filter = isset($_GET['module']) ? $_GET['module'] : '';
$filiere_filter = isset($_GET['filiere']) ? $_GET['filiere'] : '';
$justifiee_filter = isset($_GET['justifiee']) ? $_GET['justifiee'] : '';

// Get all students for dropdown
try {
    $stmt = $pdo->query("SELECT id_etudiant, nom, prenom FROM etudiants ORDER BY nom, prenom");
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des étudiants: " . $e->getMessage();
    $etudiants = [];
}

// Get all modules for dropdown
try {
    $stmt = $pdo->query("SELECT id_module, nom FROM modules ORDER BY nom");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des modules: " . $e->getMessage();
    $modules = [];
}

// Get all filières for dropdown
try {
    $stmt = $pdo->query("SELECT id_filiere, nom FROM filieres ORDER BY nom");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des filières: " . $e->getMessage();
    $filieres = [];
}

// Get all absences with student and module names, with optional filtering
try {
    $query = "
        SELECT a.*, 
               e.nom AS etudiant_nom, e.prenom AS etudiant_prenom, e.id_filiere,
               m.nom AS module_nom,
               f.nom AS filiere_nom
        FROM absences a
        JOIN etudiants e ON a.id_etudiant = e.id_etudiant
        JOIN modules m ON a.id_module = m.id_module
        JOIN filieres f ON e.id_filiere = f.id_filiere
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR m.nom LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }

    if (!empty($etudiant_filter)) {
        $query .= " AND a.id_etudiant = ?";
        $params[] = $etudiant_filter;
    }

    if (!empty($module_filter)) {
        $query .= " AND a.id_module = ?";
        $params[] = $module_filter;
    }

    if (!empty($filiere_filter)) {
        $query .= " AND e.id_filiere = ?";
        $params[] = $filiere_filter;
    }

    if ($justifiee_filter !== '') {
        $query .= " AND a.justifiee = ?";
        $params[] = $justifiee_filter;
    }

    $query .= " ORDER BY a.date DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des absences: " . $e->getMessage();
    $absences = [];
}

?>

<div class="admin-container">
    <h1>Gestion des Absences</h1>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="success"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="admin-content">
        <div class="form-section">
            <h2><?php echo $absence_to_edit ? 'Modifier l\'absence' : 'Ajouter une absence'; ?></h2>
            <form method="post">
                <?php if ($absence_to_edit): ?>
                    <input type="hidden" name="id_absence" value="<?php echo $absence_to_edit['id_absence']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="id_etudiant">Étudiant</label>
                    <select id="id_etudiant" name="id_etudiant" required>
                        <option value="">Sélectionnez un étudiant</option>
                        <?php foreach ($etudiants as $etudiant): ?>
                            <option value="<?php echo $etudiant['id_etudiant']; ?>" <?php echo ($absence_to_edit && $absence_to_edit['id_etudiant'] == $etudiant['id_etudiant']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_module">Module</label>
                    <select id="id_module" name="id_module" required>
                        <option value="">Sélectionnez un module</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module['id_module']; ?>" <?php echo ($absence_to_edit && $absence_to_edit['id_module'] == $module['id_module']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($module['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo $absence_to_edit ? $absence_to_edit['date'] : date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="justifiee" name="justifiee" <?php echo ($absence_to_edit && $absence_to_edit['justifiee']) ? 'checked' : ''; ?>>
                    <label for="justifiee">Absence justifiée</label>
                </div>

                <div class="form-group">
                    <label for="commentaire">Commentaire</label>
                    <textarea id="commentaire" name="commentaire" rows="3"><?php echo $absence_to_edit ? htmlspecialchars($absence_to_edit['commentaire']) : ''; ?></textarea>
                </div>

                <button type="submit" name="<?php echo $absence_to_edit ? 'update' : 'add'; ?>">
                    <?php echo $absence_to_edit ? 'Mettre à jour' : 'Ajouter'; ?>
                </button>

                <?php if ($absence_to_edit): ?>
                    <a href="gestion_absences.php" class="btn btn-secondary">Annuler</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="list-section">
            <h2>Liste des absences</h2>

            <div class="search-filters">
                <form class="absence-filters" method="get">

                    <select name="etudiant">
                        <option value="">Tous les étudiants</option>
                        <?php foreach ($etudiants as $etudiant): ?>
                            <option value="<?php echo $etudiant['id_etudiant']; ?>" <?php echo ($etudiant_filter == $etudiant['id_etudiant']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="module">
                        <option value="">Tous les modules</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module['id_module']; ?>" <?php echo ($module_filter == $module['id_module']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($module['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filiere">
                        <option value="">Toutes les filières</option>
                        <?php foreach ($filieres as $filiere): ?>
                            <option value="<?php echo $filiere['id_filiere']; ?>" <?php echo ($filiere_filter == $filiere['id_filiere']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($filiere['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="justifiee">
                        <option value="">Toutes les absences</option>
                        <option value="1" <?php echo ($justifiee_filter === '1') ? 'selected' : ''; ?>>Justifiées</option>
                        <option value="0" <?php echo ($justifiee_filter === '0') ? 'selected' : ''; ?>>Non justifiées</option>
                    </select>

                    <div class="filter-actions">
                        <button type="submit">Filtrer</button>
                        <button type="reset" class="btn-secondary">Réinitialiser</button>
                    </div>
                </form>
            </div>

            <?php if (empty($absences)): ?>
                <p>Aucune absence trouvée.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Étudiant</th>
                            <th>Module</th>
                            <th>Filière</th>
                            <th>Date</th>
                            <th>Justifiée</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): ?>
                            <tr>
                                <td><?php echo $absence['id_absence']; ?></td>
                                <td><?php echo htmlspecialchars($absence['etudiant_nom'] . ' ' . $absence['etudiant_prenom']); ?></td>
                                <td><?php echo htmlspecialchars($absence['module_nom']); ?></td>
                                <td><?php echo htmlspecialchars($absence['filiere_nom']); ?></td>
                                <td>
                                  <?php
                                    // Supposons que $a['date'] est au format 'YYYY-MM-DD'
                                    $date = DateTime::createFromFormat('Y-m-d', $absence['date']);
                                    echo $date ? $date->format('d/m/Y') : htmlspecialchars($absence['date']);
                                  ?>
                                </td>
                                <td><?php echo $absence['justifiee'] ? 'Oui' : 'Non'; ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="?edit=<?php echo $absence['id_absence']; ?>" class="btn-edit">Modifier</a>
                                        <a href="?delete=<?php echo $absence['id_absence']; ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr ?')">Supprimer</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>