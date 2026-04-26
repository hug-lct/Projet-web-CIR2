<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_user_admin_column_exists(): void
{
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'admin'"
    );
    $stmt->execute();

    if ((int) $stmt->fetchColumn() === 0) {
        db()->exec('ALTER TABLE users ADD COLUMN admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    ensure_user_admin_column_exists();

    $stmt = db()->prepare('SELECT id, nom, email, admin FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function register_user(string $nom, string $email, string $password): array
{
    ensure_user_admin_column_exists();

    $nom = trim($nom);
    $email = trim($email);

    if ($nom === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'Tous les champs sont obligatoires.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Adresse email invalide.'];
    }

    $check = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = db()->prepare('INSERT INTO users (nom, email, password_hash, admin) VALUES (:nom, :email, :password_hash, 0)');
    $insert->execute([
        'nom' => $nom,
        'email' => $email,
        'password_hash' => $hash,
    ]);

    $_SESSION['user_id'] = (int) db()->lastInsertId();

    return ['success' => true, 'message' => 'Inscription réussie.'];
}

function login_user(string $email, string $password): array
{
    ensure_user_admin_column_exists();

    $email = trim($email);

    if ($email === '' || $password === '') {
        return ['success' => false, 'message' => 'Email et mot de passe requis.'];
    }

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Identifiants invalides.'];
    }

    $_SESSION['user_id'] = (int) $user['id'];

    return ['success' => true, 'message' => 'Connexion réussie.'];
}

function update_user_account(int $userId, string $nom, string $email, string $currentPassword, string $newPassword = ''): array
{
    ensure_user_admin_column_exists();

    $nom = trim($nom);
    $email = trim($email);
    $newPassword = trim($newPassword);

    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Utilisateur invalide.'];
    }

    if ($nom === '' || $email === '' || $currentPassword === '') {
        return ['success' => false, 'message' => 'Nom, email et mot de passe actuel sont obligatoires.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Adresse email invalide.'];
    }

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Mot de passe actuel incorrect.'];
    }

    $check = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $check->execute([
        'email' => $email,
        'id' => $userId,
    ]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    if ($newPassword !== '' && strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'];
    }

    if ($newPassword !== '') {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = db()->prepare(
            'UPDATE users
             SET nom = :nom,
                 email = :email,
                 password_hash = :password_hash
             WHERE id = :id'
        );
        $update->execute([
            'id' => $userId,
            'nom' => $nom,
            'email' => $email,
            'password_hash' => $newHash,
        ]);
    } else {
        $update = db()->prepare(
            'UPDATE users
             SET nom = :nom,
                 email = :email
             WHERE id = :id'
        );
        $update->execute([
            'id' => $userId,
            'nom' => $nom,
            'email' => $email,
        ]);
    }

    return ['success' => true, 'message' => 'Profil mis à jour.'];
}

function delete_user_account(int $userId, string $currentPassword): array
{
    if ($userId <= 0 || trim($currentPassword) === '') {
        return ['success' => false, 'message' => 'Mot de passe actuel requis.'];
    }

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Mot de passe actuel incorrect.'];
    }

    $delete = db()->prepare('DELETE FROM users WHERE id = :id');
    $delete->execute(['id' => $userId]);

    return ['success' => true, 'message' => 'Compte supprimé.'];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
