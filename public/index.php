<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/repository.php';

function handle_admin_image_upload(string $fieldName, string $prefix): array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return ['success' => true, 'path' => null, 'error' => ''];
    }

    $file = $_FILES[$fieldName];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'error' => ''];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'Erreur lors du televersement de l\'image.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        return ['success' => false, 'path' => null, 'error' => 'L\'image doit faire moins de 5 Mo.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'path' => null, 'error' => 'Fichier image invalide.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        return ['success' => false, 'path' => null, 'error' => 'Format image non supporte (jpg, png, webp, gif).'];
    }

    $uploadDir = __DIR__ . '/assets/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['success' => false, 'path' => null, 'error' => 'Impossible de creer le dossier d\'upload.'];
    }

    $extension = $allowed[$mime];
    try {
        $uniquePart = bin2hex(random_bytes(6));
    } catch (Throwable $exception) {
        $uniquePart = str_replace('.', '', uniqid('', true));
    }
    $filename = $prefix . '-' . date('YmdHis') . '-' . $uniquePart . '.' . $extension;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        return ['success' => false, 'path' => null, 'error' => 'Impossible d\'enregistrer l\'image sur le serveur.'];
    }

    return ['success' => true, 'path' => 'assets/uploads/' . $filename, 'error' => ''];
}

$page = $_GET['page'] ?? 'home';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $result = register_user($_POST['nom'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: ?page=home');
            exit;
        }
        $error = $result['message'];
        $page = 'register';
    }

    if ($action === 'login') {
        $result = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: ?page=home');
            exit;
        }
        $error = $result['message'];
        $page = 'login';
    }

    if ($action === 'account_update') {
        $current = current_user();
        if (!$current) {
            $error = 'Connecte-toi pour modifier ton compte.';
            $page = 'login';
        } else {
            $result = update_user_account(
                (int) $current['id'],
                (string) ($_POST['nom'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? '')
            );

            if ($result['success']) {
                header('Location: ?page=profil');
                exit;
            }

            $error = $result['message'];
            $page = 'profil';
        }
    }

    if ($action === 'account_delete') {
        $current = current_user();
        if (!$current) {
            $error = 'Connecte-toi pour supprimer ton compte.';
            $page = 'login';
        } else {
            $result = delete_user_account(
                (int) $current['id'],
                (string) ($_POST['current_password'] ?? '')
            );

            if ($result['success']) {
                logout_user();
                header('Location: ?page=home');
                exit;
            }

            $error = $result['message'];
            $page = 'profil';
        }
    }

    if ($action === 'favorite_toggle') {
        $current = current_user();
        if (!$current) {
            $error = 'Connecte-toi pour gérer tes favoris.';
            $page = 'login';
        } else {
            $toiletteId = (int) ($_POST['toilette_id'] ?? 0);
            if ($toiletteId <= 0 || !get_toilette_by_id($toiletteId)) {
                $error = 'Toilette invalide.';
            } else {
                if (is_toilette_favorite((int) $current['id'], $toiletteId)) {
                    remove_favorite_toilette((int) $current['id'], $toiletteId);
                    $message = 'Favori retiré.';
                } else {
                    add_favorite_toilette((int) $current['id'], $toiletteId);
                    $message = 'Ajouté aux favoris.';
                }
            }

            $redirectPage = (string) ($_POST['redirect_page'] ?? 'home');
            $redirectId = (int) ($_POST['redirect_id'] ?? 0);
            if (!in_array($redirectPage, ['home', 'favoris', 'batiment'], true)) {
                $redirectPage = 'home';
            }

            $location = '?page=' . rawurlencode($redirectPage);
            if ($redirectPage === 'batiment' && $redirectId > 0) {
                $location .= '&id=' . $redirectId;
            }

            header('Location: ' . $location);
            exit;
        }
    }

    if ($action === 'admin_update_toilette_status') {
        $current = current_user();
        if (!$current || (int) ($current['admin'] ?? 0) !== 1) {
            $error = 'Accès refusé.';
            $page = 'home';
        } else {
            $toiletteId = (int) ($_POST['toilette_id'] ?? 0);
            $statut = (string) ($_POST['statut'] ?? '');

            if ($toiletteId > 0 && get_toilette_by_id($toiletteId)) {
                update_toilette_status($toiletteId, $statut);
                $message = 'Statut mis à jour.';
            } else {
                $error = 'Toilette invalide.';
            }

            header('Location: ?page=admin');
            exit;
        }
    }

    if ($action === 'admin_update_batiment_data') {
        $current = current_user();
        if (!$current || (int) ($current['admin'] ?? 0) !== 1) {
            $error = 'Accès refusé.';
            $page = 'home';
        } else {
            $batimentId = (int) ($_POST['batiment_id'] ?? 0);
            $nom = (string) ($_POST['nom'] ?? '');
            $photo = (string) ($_POST['photo'] ?? '');
            $description = (string) ($_POST['description'] ?? '');
            $batiment = get_batiment_by_id($batimentId);

            if ($batimentId > 0 && $batiment) {
                $upload = handle_admin_image_upload('photo_file', 'batiment');
                if (!$upload['success']) {
                    $error = $upload['error'];
                    $page = 'admin';
                } else {
                    $finalPhoto = (string) ($upload['path'] ?? '');
                    if ($finalPhoto === '') {
                        $finalPhoto = trim($photo);
                    }
                    if ($finalPhoto === '') {
                        $finalPhoto = (string) $batiment['photo'];
                    }

                    update_batiment_data($batimentId, $nom, $finalPhoto, $description);
                    $message = 'Bâtiment mis à jour.';
                    header('Location: ?page=admin');
                    exit;
                }
            } else {
                $error = 'Bâtiment invalide.';
                $page = 'admin';
            }
        }
    }

    if ($action === 'admin_update_toilette_data') {
        $current = current_user();
        if (!$current || (int) ($current['admin'] ?? 0) !== 1) {
            $error = 'Accès refusé.';
            $page = 'home';
        } else {
            $toiletteId = (int) ($_POST['toilette_id'] ?? 0);
            $batimentId = (int) ($_POST['batiment_id'] ?? 0);
            $nom = (string) ($_POST['nom'] ?? '');
            $photo = (string) ($_POST['photo'] ?? '');
            $description = (string) ($_POST['description'] ?? '');
            $statut = (string) ($_POST['statut'] ?? '');
            $toilette = get_toilette_by_id($toiletteId);

            if ($toiletteId > 0 && $toilette && get_batiment_by_id($batimentId)) {
                $upload = handle_admin_image_upload('photo_file', 'toilette');
                if (!$upload['success']) {
                    $error = $upload['error'];
                    $page = 'admin';
                } else {
                    $finalPhoto = (string) ($upload['path'] ?? '');
                    if ($finalPhoto === '') {
                        $finalPhoto = trim($photo);
                    }
                    if ($finalPhoto === '') {
                        $finalPhoto = (string) $toilette['photo'];
                    }

                    update_toilette_data($toiletteId, $batimentId, $nom, $finalPhoto, $description, $statut);
                    $message = 'Toilette mise à jour.';
                    header('Location: ?page=admin');
                    exit;
                }
            } else {
                $error = 'Toilette ou bâtiment invalide.';
                $page = 'admin';
            }
        }
    }

    if ($action === 'admin_create_batiment') {
        $current = current_user();
        if (!$current || (int) ($current['admin'] ?? 0) !== 1) {
            $error = 'Accès refusé.';
            $page = 'home';
        } else {
            $nom = (string) ($_POST['nom'] ?? '');
            $photo = (string) ($_POST['photo'] ?? '');
            $description = (string) ($_POST['description'] ?? '');

            $upload = handle_admin_image_upload('photo_file', 'batiment');
            if (!$upload['success']) {
                $error = $upload['error'];
                $page = 'admin';
            } else {
                $finalPhoto = (string) ($upload['path'] ?? '');
                if ($finalPhoto === '') {
                    $finalPhoto = trim($photo);
                }

                if (trim($nom) === '' || $finalPhoto === '' || trim($description) === '') {
                    $error = 'Nom, photo et description sont obligatoires.';
                    $page = 'admin';
                } else {
                    create_batiment($nom, $finalPhoto, $description);
                    $message = 'Nouveau bâtiment ajouté.';
                    header('Location: ?page=admin');
                    exit;
                }
            }
        }
    }

    if ($action === 'admin_create_toilette') {
        $current = current_user();
        if (!$current || (int) ($current['admin'] ?? 0) !== 1) {
            $error = 'Accès refusé.';
            $page = 'home';
        } else {
            $batimentId = (int) ($_POST['batiment_id'] ?? 0);
            $nom = (string) ($_POST['nom'] ?? '');
            $photo = (string) ($_POST['photo'] ?? '');
            $description = (string) ($_POST['description'] ?? '');
            $statut = (string) ($_POST['statut'] ?? 'ouvert');

            $upload = handle_admin_image_upload('photo_file', 'toilette');
            if (!$upload['success']) {
                $error = $upload['error'];
                $page = 'admin';
            } else {
                $finalPhoto = (string) ($upload['path'] ?? '');
                if ($finalPhoto === '') {
                    $finalPhoto = trim($photo);
                }

                if (!get_batiment_by_id($batimentId)) {
                    $error = 'Bâtiment invalide.';
                    $page = 'admin';
                } elseif (trim($nom) === '' || $finalPhoto === '' || trim($description) === '') {
                    $error = 'Nom, photo et description sont obligatoires.';
                    $page = 'admin';
                } elseif (!in_array(strtolower(trim($statut)), ['ouvert', 'ferme'], true)) {
                    $error = 'Statut invalide.';
                    $page = 'admin';
                } else {
                    create_toilette($batimentId, $nom, $finalPhoto, $description, $statut);
                    $message = 'Nouvelle toilette ajoutée.';
                    header('Location: ?page=admin');
                    exit;
                }
            }
        }
    }

    if ($action === 'admin_delete_batiment') {
        $current = current_user();
        if (!$current || (int) ($current['admin'] ?? 0) !== 1) {
            $error = 'Accès refusé.';
            $page = 'home';
        } else {
            $batimentId = (int) ($_POST['batiment_id'] ?? 0);
            if ($batimentId > 0 && get_batiment_by_id($batimentId)) {
                delete_batiment($batimentId);
                $message = 'Bâtiment supprimé.';
            } else {
                $error = 'Bâtiment invalide.';
            }

            header('Location: ?page=admin');
            exit;
        }
    }

    if ($action === 'admin_delete_toilette') {
        $current = current_user();
        if (!$current || (int) ($current['admin'] ?? 0) !== 1) {
            $error = 'Accès refusé.';
            $page = 'home';
        } else {
            $toiletteId = (int) ($_POST['toilette_id'] ?? 0);
            if ($toiletteId > 0 && get_toilette_by_id($toiletteId)) {
                delete_toilette($toiletteId);
                $message = 'Toilette supprimée.';
            } else {
                $error = 'Toilette invalide.';
            }

            header('Location: ?page=admin');
            exit;
        }
    }

    if ($action === 'toilette_rate') {
        $current = current_user();
        if (!$current) {
            $error = 'Connecte-toi pour donner une note.';
            $page = 'login';
        } else {
            $toiletteId = (int) ($_POST['toilette_id'] ?? 0);
            $note = (int) ($_POST['note'] ?? 0);

            if ($toiletteId <= 0 || !get_toilette_by_id($toiletteId)) {
                $error = 'Toilette invalide.';
            } elseif ($note < 1 || $note > 5) {
                $error = 'La note doit être comprise entre 1 et 5.';
            } else {
                save_toilette_rating((int) $current['id'], $toiletteId, $note);
                $message = 'Note enregistrée.';
            }

            $redirectPage = (string) ($_POST['redirect_page'] ?? 'home');
            $redirectId = (int) ($_POST['redirect_id'] ?? 0);
            if (!in_array($redirectPage, ['home', 'favoris', 'batiment', 'admin'], true)) {
                $redirectPage = 'home';
            }

            $location = '?page=' . rawurlencode($redirectPage);
            if (in_array($redirectPage, ['batiment', 'admin'], true) && $redirectId > 0) {
                $location .= '&id=' . $redirectId;
            }

            header('Location: ' . $location);
            exit;
        }
    }
}

if ($page === 'logout') {
    logout_user();
    header('Location: ?page=home');
    exit;
}

$user = current_user();
$isAdmin = $user && (int) ($user['admin'] ?? 0) === 1;
$favoriteToiletteIds = $user ? get_favorite_toilette_ids_by_user_id((int) $user['id']) : [];

$batimentsRoulette = get_all_batiments();
$toilettesRoulette = get_all_toilettes_for_admin();
$toilettesByBatiment = [];

foreach ($toilettesRoulette as $toiletteRoulette) {
    $key = (int) $toiletteRoulette['batiment_id'];
    if (!isset($toilettesByBatiment[$key])) {
        $toilettesByBatiment[$key] = [];
    }
    $toilettesByBatiment[$key][] = $toiletteRoulette;
}

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classiqu'ISEN</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<header class="header">
    <div class="logo">Classiqu'ISEN</div>
    <nav class="menu">
        <?php if ($user): ?>
            <span class="welcome">Bonjour <?= htmlspecialchars($user['nom']) ?></span>
        <?php endif; ?>
        <a href="?page=home">Accueil</a>
        <?php if ($user): ?>
            <button type="button" class="menu-link-btn" data-js="open-roulette">Roulette</button>
        <?php endif; ?>
        <?php if ($user): ?>
            <?php if ($isAdmin): ?>
                <a href="?page=admin">Admin</a>
            <?php endif; ?>
            <a href="?page=favoris">Favoris</a>
            <a href="?page=profil">Profil & stats</a>
            <a href="?page=logout">Déconnexion</a>
        <?php else: ?>
            <a href="?page=login">Connexion</a>
            <a href="?page=register">Inscription</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">
    <?php if ($error !== ''): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($page === 'register'): ?>
        <section class="panel">
            <h1>Inscription</h1>
            <form method="post" class="form-card">
                <input type="hidden" name="action" value="register">
                <label for="nom">Nom</label>
                <input id="nom" name="nom" type="text" required>

                <label for="email-register">Email</label>
                <input id="email-register" name="email" type="email" required>

                <label for="password-register">Mot de passe</label>
                <input id="password-register" name="password" type="password" minlength="6" required>

                <button type="submit">Créer mon compte</button>
            </form>
        </section>
    <?php elseif ($page === 'login'): ?>
        <section class="panel">
            <h1>Connexion</h1>
            <form method="post" class="form-card">
                <input type="hidden" name="action" value="login">
                <label for="email-login">Email</label>
                <input id="email-login" name="email" type="email" required>

                <label for="password-login">Mot de passe</label>
                <input id="password-login" name="password" type="password" required>

                <button type="submit">Se connecter</button>
            </form>
        </section>
    <?php elseif ($page === 'batiment'): ?>
        <?php
            $batimentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $batiment = get_batiment_by_id($batimentId);
        ?>

        <?php if (!$batiment): ?>
            <div class="alert error">Bâtiment introuvable.</div>
        <?php else: ?>
            <?php $toilettes = get_toilettes_by_batiment_id($batimentId, $user ? (int) $user['id'] : null); ?>
            <section class="panel">
                <h1><?= htmlspecialchars($batiment['nom']) ?></h1>
                <p class="subtitle"><?= htmlspecialchars($batiment['description']) ?></p>
                <img class="hero" src="<?= htmlspecialchars($batiment['photo']) ?>" alt="Photo du bâtiment <?= htmlspecialchars($batiment['nom']) ?>">
            </section>

            <?php if (count($toilettes) === 0): ?>
                <div class="alert success">Nos equipes sont en pleine exploration de ce batiment, revenez plus tard.</div>
            <?php else: ?>
                <section class="search-strip" data-js="toilette-toolbar">
                    <div class="search-line">
                        <input id="toilette-search" type="search" placeholder="Nom, zone, description..." data-js="search-input">
                    </div>
                    <p class="toolbar-meta" data-js="result-count" aria-live="polite"></p>
                </section>

                <section class="cards-grid" data-js="toilette-grid">
                    <?php foreach ($toilettes as $toilette): ?>
                        <?php
                            $statutRaw = strtolower((string) $toilette['statut']);
                            $isOpen = $statutRaw === 'ouvert';
                            $noteValue = (float) $toilette['note_moyenne'];
                            $avisCount = (int) $toilette['avis_count'];
                            $currentUserNote = isset($toilette['note_utilisateur']) ? (int) $toilette['note_utilisateur'] : null;
                            $isFavoriteToilette = in_array((int) $toilette['id'], $favoriteToiletteIds, true);
                        ?>
                        <article
                            class="card reveal <?= $isFavoriteToilette ? 'favorite' : '' ?>"
                            data-search="<?= htmlspecialchars(strtolower((string) $toilette['nom']), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <img loading="lazy" decoding="async" src="<?= htmlspecialchars($toilette['photo']) ?>" alt="<?= htmlspecialchars($toilette['nom']) ?>">
                            <div class="card-content">
                                <h2><?= htmlspecialchars($toilette['nom']) ?></h2>
                                <p><?= htmlspecialchars($toilette['description']) ?></p>
                                <?php if ($avisCount === 0): ?>
                                    <p><strong>Note :</strong> il n'y a pas encore assez d'avis</p>
                                <?php else: ?>
                                    <p><strong>Note moyenne :</strong> <?= htmlspecialchars((string) $noteValue) ?>/5 <span class="toolbar-meta">(<?= $avisCount ?> avis)</span></p>
                                <?php endif; ?>
                                <?php if ($user): ?>
                                    <form method="post" class="rating-form">
                                        <input type="hidden" name="action" value="toilette_rate">
                                        <input type="hidden" name="toilette_id" value="<?= (int) $toilette['id'] ?>">
                                        <input type="hidden" name="redirect_page" value="batiment">
                                        <input type="hidden" name="redirect_id" value="<?= (int) $batimentId ?>">
                                        <label for="note-bat-<?= (int) $toilette['id'] ?>">Votre note</label>
                                        <select id="note-bat-<?= (int) $toilette['id'] ?>" name="note">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <option value="<?= $i ?>" <?= $currentUserNote === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                            <?php endfor; ?>
                                        </select>
                                        <button type="submit" class="btn secondary">Noter</button>
                                    </form>
                                <?php endif; ?>
                                <p>
                                    <strong>Statut :</strong>
                                    <span class="status <?= $isOpen ? 'open' : 'closed' ?>">
                                        <?= $isOpen ? 'Ouvert' : 'Fermé' ?>
                                    </span>
                                </p>
                                <div class="actions-row">
                                    <?php if ($user): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="action" value="favorite_toggle">
                                            <input type="hidden" name="toilette_id" value="<?= (int) $toilette['id'] ?>">
                                            <input type="hidden" name="redirect_page" value="batiment">
                                            <input type="hidden" name="redirect_id" value="<?= (int) $batimentId ?>">
                                            <button type="submit" class="btn secondary"><?= $isFavoriteToilette ? '★ Favori' : '☆ Favori' ?></button>
                                        </form>
                                    <?php else: ?>
                                        <a class="btn secondary" href="?page=login">Connexion pour ajouter à vos favoris</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
                <p class="empty-state hidden" data-js="empty-state">Aucun résultat ne correspond à la recherche.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php elseif ($page === 'favoris'): ?>
        <?php if (!$user): ?>
            <div class="alert error">Connecte-toi pour voir tes favoris.</div>
        <?php else: ?>
            <?php $toilettes = get_favorite_toilettes_by_user_id((int) $user['id']); ?>
            <section class="panel">
                <h1>Mes favoris</h1>
                <p class="subtitle">Retrouve rapidement les toilettes que tu as enregistrées.</p>
            </section>

            <section class="search-strip" data-js="toilette-toolbar">
                <div class="search-line">
                    <input id="toilette-search-fav" type="search" placeholder="Nom, bâtiment ou description" data-js="search-input">
                </div>
                <p class="toolbar-meta" data-js="result-count" aria-live="polite"></p>
            </section>

            <section class="cards-grid" data-js="toilette-grid">
                <?php foreach ($toilettes as $toilette): ?>
                    <?php
                        $statutRaw = strtolower((string) $toilette['statut']);
                        $isOpen = $statutRaw === 'ouvert';
                        $avisCount = (int) $toilette['avis_count'];
                        $currentUserNote = isset($toilette['note_utilisateur']) ? (int) $toilette['note_utilisateur'] : null;
                    ?>
                    <article
                        class="card reveal favorite"
                        data-search="<?= htmlspecialchars(strtolower((string) $toilette['nom'] . ' ' . $toilette['batiment_nom']), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <img loading="lazy" decoding="async" src="<?= htmlspecialchars($toilette['photo']) ?>" alt="Photo de <?= htmlspecialchars($toilette['nom']) ?>">
                        <div class="card-content">
                            <h2><?= htmlspecialchars($toilette['nom']) ?></h2>
                            <p><strong>Bâtiment :</strong> <?= htmlspecialchars($toilette['batiment_nom']) ?></p>
                            <p><?= htmlspecialchars($toilette['description']) ?></p>
                            <?php if ($avisCount === 0): ?>
                                <p><strong>Note :</strong> il n'y a pas encore assez d'avis</p>
                            <?php else: ?>
                                <p><strong>Note moyenne :</strong> <?= htmlspecialchars((string) $toilette['note_moyenne']) ?>/5 <span class="toolbar-meta">(<?= $avisCount ?> avis)</span></p>
                            <?php endif; ?>
                            <form method="post" class="rating-form">
                                <input type="hidden" name="action" value="toilette_rate">
                                <input type="hidden" name="toilette_id" value="<?= (int) $toilette['id'] ?>">
                                <input type="hidden" name="redirect_page" value="favoris">
                                <label for="note-fav-<?= (int) $toilette['id'] ?>">Votre note</label>
                                <select id="note-fav-<?= (int) $toilette['id'] ?>" name="note">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <option value="<?= $i ?>" <?= $currentUserNote === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                    <?php endfor; ?>
                                </select>
                                <button type="submit" class="btn secondary">Noter</button>
                            </form>
                            <p>
                                <strong>Statut :</strong>
                                <span class="status <?= $isOpen ? 'open' : 'closed' ?>">
                                    <?= $isOpen ? 'Ouvert' : 'Fermé' ?>
                                </span>
                            </p>
                            <div class="actions-row">
                                <a class="btn" href="?page=batiment&id=<?= (int) $toilette['batiment_id'] ?>">Voir le bâtiment</a>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="favorite_toggle">
                                    <input type="hidden" name="toilette_id" value="<?= (int) $toilette['id'] ?>">
                                    <input type="hidden" name="redirect_page" value="favoris">
                                    <button type="submit" class="btn secondary">Retirer</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
            <p class="empty-state hidden" data-js="empty-state">Aucun favori ne correspond à la recherche.</p>
        <?php endif; ?>
    <?php elseif ($page === 'profil'): ?>
        <?php if (!$user): ?>
            <div class="alert error">Connecte-toi pour accéder à ton profil.</div>
        <?php else: ?>
            <?php $accountStats = get_user_account_stats((int) $user['id']); ?>
            <section class="panel">
                <h1>Mon profil</h1>
                <p class="subtitle">Gère ton compte et consulte tes statistiques personnelles.</p>
            </section>

            <section class="panel">
                <h2>Modifier mes informations</h2>
                <form method="post" class="form-card">
                    <input type="hidden" name="action" value="account_update">

                    <label for="profil-nom">Nom</label>
                    <input id="profil-nom" name="nom" type="text" value="<?= htmlspecialchars($user['nom']) ?>" required>

                    <label for="profil-email">Email</label>
                    <input id="profil-email" name="email" type="email" value="<?= htmlspecialchars($user['email']) ?>" required>

                    <label for="profil-current-password">Mot de passe actuel</label>
                    <input id="profil-current-password" name="current_password" type="password" required>

                    <label for="profil-new-password">Nouveau mot de passe (optionnel)</label>
                    <input id="profil-new-password" name="new_password" type="password" minlength="6">

                    <button type="submit">Enregistrer mes modifications</button>
                </form>
            </section>

            <section class="panel">
                <h2>Mes statistiques</h2>
                <p><strong>Favoris enregistrés :</strong> <?= (int) $accountStats['favorites_count'] ?></p>
                <p><strong>Favoris actuellement ouverts :</strong> <?= (int) $accountStats['favorites_open_count'] ?></p>
                <p><strong>Toilettes notées :</strong> <?= (int) $accountStats['ratings_count'] ?></p>
                <p>
                    <strong>Moyenne de mes notes :</strong>
                    <?= $accountStats['ratings_avg'] !== null ? htmlspecialchars((string) $accountStats['ratings_avg']) . '/5' : 'Aucune note pour le moment' ?>
                </p>
            </section>

            <section class="panel">
                <h2>Supprimer mon compte</h2>
                <p class="subtitle">Cette action est définitive et supprimera aussi tes favoris et tes notes.</p>
                <form method="post" class="form-card" onsubmit="return confirm('Supprimer définitivement votre compte ?');">
                    <input type="hidden" name="action" value="account_delete">

                    <label for="profil-delete-password">Mot de passe actuel</label>
                    <input id="profil-delete-password" name="current_password" type="password" required>

                    <button type="submit">Supprimer mon compte</button>
                </form>
            </section>
        <?php endif; ?>
    <?php elseif ($page === 'admin'): ?>
        <?php if (!$isAdmin): ?>
            <div class="alert error">Accès réservé à l'administrateur.</div>
        <?php else: ?>
            <?php $batimentsAdmin = get_all_batiments(); ?>
            <?php $toilettesAdmin = get_all_toilettes_for_admin(); ?>
            <section class="panel">
                <h1 class="centered-title">Administration</h1>
                <p class="subtitle centered-subtitle">Modifier les données des bâtiments et des toilettes.</p>
            </section>

            <section class="panel">
                <h2>Ajouter un bâtiment</h2>
                <form method="post" class="admin-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="admin_create_batiment">

                    <label for="new-batiment-nom">Nom</label>
                    <input id="new-batiment-nom" name="nom" type="text" required>

                    <label for="new-batiment-photo">URL photo (optionnel)</label>
                    <input id="new-batiment-photo" name="photo" type="url">

                    <label for="new-batiment-photo-file">Ou televerser une image</label>
                    <input id="new-batiment-photo-file" name="photo_file" type="file" accept="image/*">

                    <label for="new-batiment-description">Description</label>
                    <textarea id="new-batiment-description" name="description" rows="4" required></textarea>

                    <button type="submit">Ajouter bâtiment</button>
                </form>
            </section>

            <section class="panel">
                <h2>Ajouter une toilette</h2>
                <form method="post" class="admin-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="admin_create_toilette">

                    <label for="new-toilette-nom">Nom</label>
                    <input id="new-toilette-nom" name="nom" type="text" required>

                    <label for="new-toilette-photo">URL photo (optionnel)</label>
                    <input id="new-toilette-photo" name="photo" type="url">

                    <label for="new-toilette-photo-file">Ou televerser une image</label>
                    <input id="new-toilette-photo-file" name="photo_file" type="file" accept="image/*">

                    <label for="new-toilette-description">Description</label>
                    <textarea id="new-toilette-description" name="description" rows="4" required></textarea>

                    <label for="new-toilette-batiment">Bâtiment</label>
                    <select id="new-toilette-batiment" name="batiment_id" required>
                        <option value="">Choisir un bâtiment</option>
                        <?php foreach ($batimentsAdmin as $batiment): ?>
                            <option value="<?= (int) $batiment['id'] ?>"><?= htmlspecialchars($batiment['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="new-toilette-statut">Statut</label>
                    <select id="new-toilette-statut" name="statut">
                        <option value="ouvert">Ouvert</option>
                        <option value="ferme">Fermé</option>
                    </select>

                    <button type="submit">Ajouter toilette</button>
                </form>
            </section>

            <section class="panel">
                <h2>Modifier les bâtiments</h2>
            </section>

            <section class="cards-grid admin-grid">
                <?php foreach ($batimentsAdmin as $batiment): ?>
                    <article class="card reveal admin-card">
                        <img loading="lazy" decoding="async" src="<?= htmlspecialchars($batiment['photo']) ?>" alt="Photo de <?= htmlspecialchars($batiment['nom']) ?>">
                        <div class="card-content">
                            <h2>#<?= (int) $batiment['id'] ?> - <?= htmlspecialchars($batiment['nom']) ?></h2>
                            <form method="post" class="admin-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="admin_update_batiment_data">
                                <input type="hidden" name="batiment_id" value="<?= (int) $batiment['id'] ?>">

                                <label for="batiment-nom-<?= (int) $batiment['id'] ?>">Nom</label>
                                <input id="batiment-nom-<?= (int) $batiment['id'] ?>" name="nom" type="text" value="<?= htmlspecialchars($batiment['nom']) ?>" required>

                                <label for="batiment-photo-<?= (int) $batiment['id'] ?>">URL photo (optionnel)</label>
                                <input id="batiment-photo-<?= (int) $batiment['id'] ?>" name="photo" type="url" value="<?= htmlspecialchars($batiment['photo']) ?>">

                                <label for="batiment-photo-file-<?= (int) $batiment['id'] ?>">Ou televerser une image</label>
                                <input id="batiment-photo-file-<?= (int) $batiment['id'] ?>" name="photo_file" type="file" accept="image/*">

                                <label for="batiment-description-<?= (int) $batiment['id'] ?>">Description</label>
                                <textarea id="batiment-description-<?= (int) $batiment['id'] ?>" name="description" rows="4" required><?= htmlspecialchars($batiment['description']) ?></textarea>

                                <button type="submit">Enregistrer bâtiment</button>
                            </form>
                            <form method="post" class="admin-form" onsubmit="return confirm('Supprimer ce bâtiment et toutes ses toilettes ?');">
                                <input type="hidden" name="action" value="admin_delete_batiment">
                                <input type="hidden" name="batiment_id" value="<?= (int) $batiment['id'] ?>">
                                <button type="submit">Supprimer bâtiment</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <h2>Modifier les toilettes</h2>
            </section>

            <section class="cards-grid admin-grid">
                <?php foreach ($toilettesAdmin as $toilette): ?>
                    <?php
                        $isOpen = strtolower((string) $toilette['statut']) === 'ouvert';
                        $avisCount = (int) $toilette['avis_count'];
                    ?>
                    <article class="card reveal admin-card">
                        <img loading="lazy" decoding="async" src="<?= htmlspecialchars($toilette['photo']) ?>" alt="Photo de <?= htmlspecialchars($toilette['nom']) ?>">
                        <div class="card-content">
                            <h2><?= htmlspecialchars($toilette['nom']) ?></h2>
                            <p><strong>Bâtiment :</strong> <?= htmlspecialchars($toilette['batiment_nom']) ?></p>
                            <p><?= htmlspecialchars($toilette['description']) ?></p>
                            <?php if ($avisCount === 0): ?>
                                <p><strong>Note :</strong> il n'y a pas encore assez d'avis</p>
                            <?php else: ?>
                                <p><strong>Note moyenne :</strong> <?= htmlspecialchars((string) $toilette['note_moyenne']) ?>/5 <span class="toolbar-meta">(<?= $avisCount ?> avis)</span></p>
                            <?php endif; ?>
                            <form method="post" class="admin-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="admin_update_toilette_data">
                                <input type="hidden" name="toilette_id" value="<?= (int) $toilette['id'] ?>">

                                <label for="toilette-nom-<?= (int) $toilette['id'] ?>">Nom</label>
                                <input id="toilette-nom-<?= (int) $toilette['id'] ?>" name="nom" type="text" value="<?= htmlspecialchars($toilette['nom']) ?>" required>

                                <label for="toilette-photo-<?= (int) $toilette['id'] ?>">URL photo (optionnel)</label>
                                <input id="toilette-photo-<?= (int) $toilette['id'] ?>" name="photo" type="url" value="<?= htmlspecialchars($toilette['photo']) ?>">

                                <label for="toilette-photo-file-<?= (int) $toilette['id'] ?>">Ou televerser une image</label>
                                <input id="toilette-photo-file-<?= (int) $toilette['id'] ?>" name="photo_file" type="file" accept="image/*">

                                <label for="toilette-description-<?= (int) $toilette['id'] ?>">Description</label>
                                <textarea id="toilette-description-<?= (int) $toilette['id'] ?>" name="description" rows="4" required><?= htmlspecialchars($toilette['description']) ?></textarea>

                                <label for="toilette-batiment-<?= (int) $toilette['id'] ?>">Bâtiment</label>
                                <select id="toilette-batiment-<?= (int) $toilette['id'] ?>" name="batiment_id">
                                    <?php foreach ($batimentsAdmin as $batiment): ?>
                                        <option value="<?= (int) $batiment['id'] ?>" <?= (int) $batiment['id'] === (int) $toilette['batiment_id'] ? 'selected' : '' ?>><?= htmlspecialchars($batiment['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="statut-<?= (int) $toilette['id'] ?>">Statut</label>
                                <select id="statut-<?= (int) $toilette['id'] ?>" name="statut">
                                    <option value="ouvert" <?= $isOpen ? 'selected' : '' ?>>Ouvert</option>
                                    <option value="ferme" <?= !$isOpen ? 'selected' : '' ?>>Fermé</option>
                                </select>
                                <button type="submit">Enregistrer toilette</button>
                            </form>
                            <form method="post" class="admin-form" onsubmit="return confirm('Supprimer cette toilette ?');">
                                <input type="hidden" name="action" value="admin_delete_toilette">
                                <input type="hidden" name="toilette_id" value="<?= (int) $toilette['id'] ?>">
                                <button type="submit">Supprimer toilette</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php else: ?>
    <?php $batiments = get_all_batiments(); ?>
        <section class="panel">
            <h1 class="centered-title">Bâtiments JUNIA</h1>
            <p class="subtitle centered-subtitle">Clique sur un bâtiment pour voir les toilettes.</p>
        </section>

        <section class="search-strip" data-js="batiment-toolbar">
            <div class="search-line">
                <input id="batiment-search" type="search" placeholder="Nom ou description" data-js="search-input">
            </div>
            <p class="toolbar-meta" data-js="result-count" aria-live="polite"></p>
        </section>

        <section class="cards-grid" data-js="batiment-grid">
            <?php foreach ($batiments as $batiment): ?>
                <?php $toilettesBatiment = get_toilettes_by_batiment_id((int) $batiment['id'], $user ? (int) $user['id'] : null); ?>
                <article
                    class="card reveal"
                    data-search="<?= htmlspecialchars(strtolower((string) $batiment['nom'] . ' ' . implode(' ', array_map(static fn (array $toilette): string => (string) $toilette['nom'], $toilettesBatiment))), ENT_QUOTES, 'UTF-8') ?>"
                >
                    <img loading="lazy" decoding="async" src="<?= htmlspecialchars($batiment['photo']) ?>" alt="Photo de <?= htmlspecialchars($batiment['nom']) ?>">
                    <div class="card-content">
                        <h2>#<?= htmlspecialchars((string) $batiment['id']) ?> - <?= htmlspecialchars($batiment['nom']) ?></h2>
                        <p><?= htmlspecialchars($batiment['description']) ?></p>
                        <div class="actions-row">
                            <a class="btn" href="?page=batiment&id=<?= (int) $batiment['id'] ?>">Voir les toilettes</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <p class="empty-state hidden" data-js="empty-state">Aucun bâtiment ne correspond à la recherche.</p>
    <?php endif; ?>
</main>

<?php if ($user): ?>
    <div class="roulette-modal-backdrop hidden" data-js="roulette-modal-backdrop">
        <section class="roulette-modal" data-js="roulette" data-toilettes-by-batiment='<?= htmlspecialchars(json_encode($toilettesByBatiment, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>' role="dialog" aria-modal="true" aria-label="Roulette Toilettes">
            <div class="roulette-modal-head">
                <h2>Roulette Toilettes</h2>
                <button type="button" class="roulette-close" data-js="close-roulette" aria-label="Fermer">×</button>
            </div>
            <p class="subtitle">Choisis un bâtiment, lance la roulette et découvre une toilette aléatoire.</p>

            <div class="roulette-panel">
                <div class="roulette-controls">
                    <label for="roulette-batiment">Bâtiment</label>
                    <select id="roulette-batiment" data-js="roulette-select">
                        <option value="">Choisir un bâtiment</option>
                        <?php foreach ($batimentsRoulette as $batiment): ?>
                            <option value="<?= (int) $batiment['id'] ?>"><?= htmlspecialchars($batiment['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" data-js="roulette-launch">Lancer la roulette</button>
                </div>

                <div class="roulette-roll hidden" data-js="roulette-roll" aria-live="polite"></div>
                <div class="alert error hidden" data-js="roulette-empty">Nos equipes sont en pleine exploration de ce batiment, revenez plus tard.</div>

                <article class="card roulette-result hidden" data-js="roulette-result">
                    <img data-js="roulette-photo" src="" alt="Toilette sélectionnée">
                    <div class="card-content">
                        <h2 data-js="roulette-name"></h2>
                        <p data-js="roulette-description"></p>
                        <p data-js="roulette-note"></p>
                        <p>
                            <strong>Statut :</strong>
                            <span class="status" data-js="roulette-status"></span>
                        </p>
                        <a class="btn" data-js="roulette-link" href="#">Voir ce bâtiment</a>
                    </div>
                </article>
            </div>
        </section>
    </div>
<?php endif; ?>

<button type="button" class="back-top hidden" data-js="back-top" aria-label="Remonter en haut">↑</button>
</body>
</html>
