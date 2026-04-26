<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_favorite_toilette_table_exists(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS user_favorite_toilette (
            user_id INT NOT NULL,
            toilette_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, toilette_id),
            CONSTRAINT fk_user_favorite_toilette_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_user_favorite_toilette_toilette
                FOREIGN KEY (toilette_id) REFERENCES toilette(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
}

function ensure_toilette_rating_table_exists(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS toilette_rating (
            user_id INT NOT NULL,
            toilette_id INT NOT NULL,
            note TINYINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, toilette_id),
            CONSTRAINT fk_toilette_rating_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_toilette_rating_toilette
                FOREIGN KEY (toilette_id) REFERENCES toilette(id)
                ON DELETE CASCADE,
            CONSTRAINT chk_toilette_rating_note
                CHECK (note BETWEEN 1 AND 5)
        ) ENGINE=InnoDB'
    );
}

function rating_summary_subquery_sql(): string
{
    ensure_toilette_rating_table_exists();

    return '(SELECT toilette_id, ROUND(AVG(note), 1) AS avg_note, COUNT(*) AS avis_count FROM toilette_rating GROUP BY toilette_id) rs';
}

function build_toilette_list_sql(bool $includeUserRating): string
{
    $userNoteColumn = $includeUserRating ? 'ur.note AS note_utilisateur' : 'NULL AS note_utilisateur';
    $userRatingJoin = $includeUserRating
        ? 'LEFT JOIN toilette_rating ur ON ur.toilette_id = t.id AND ur.user_id = :user_id'
        : '';

    return sprintf(
        'SELECT t.id, t.batiment_id, t.nom, t.photo, t.description, t.note AS note_initiale, '
        . 'COALESCE(rs.avg_note, t.note) AS note_moyenne, COALESCE(rs.avis_count, 0) AS avis_count, '
        . '%s, t.statut, b.nom AS batiment_nom '
        . 'FROM toilette t '
        . 'LEFT JOIN %s ON rs.toilette_id = t.id '
        . '%s '
        . 'INNER JOIN batiment b ON b.id = t.batiment_id',
        $userNoteColumn,
        rating_summary_subquery_sql(),
        $userRatingJoin
    );
}

function save_toilette_rating(int $userId, int $toiletteId, int $note): void
{
    ensure_toilette_rating_table_exists();

    if ($note < 1 || $note > 5) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO toilette_rating (user_id, toilette_id, note)
         VALUES (:user_id, :toilette_id, :note)
         ON DUPLICATE KEY UPDATE note = VALUES(note), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'user_id' => $userId,
        'toilette_id' => $toiletteId,
        'note' => $note,
    ]);
}

function get_toilette_user_rating(int $userId, int $toiletteId): ?int
{
    ensure_toilette_rating_table_exists();

    $stmt = db()->prepare(
        'SELECT note FROM toilette_rating WHERE user_id = :user_id AND toilette_id = :toilette_id LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'toilette_id' => $toiletteId,
    ]);

    $rating = $stmt->fetchColumn();

    return $rating === false ? null : (int) $rating;
}

function get_all_batiments(): array
{
    $stmt = db()->query('SELECT id, nom, photo, description FROM batiment ORDER BY nom ASC');
    return $stmt->fetchAll();
}

function get_batiment_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, nom, photo, description FROM batiment WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $batiment = $stmt->fetch();

    return $batiment ?: null;
}

function create_batiment(string $nom, string $photo, string $description): void
{
    $nom = trim($nom);
    $photo = trim($photo);
    $description = trim($description);

    if ($nom === '' || $photo === '' || $description === '') {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO batiment (nom, photo, description)
         VALUES (:nom, :photo, :description)'
    );
    $stmt->execute([
        'nom' => $nom,
        'photo' => $photo,
        'description' => $description,
    ]);
}

function delete_batiment(int $id): void
{
    if ($id <= 0) {
        return;
    }

    $stmt = db()->prepare('DELETE FROM batiment WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function update_batiment_data(int $id, string $nom, string $photo, string $description): void
{
    $nom = trim($nom);
    $photo = trim($photo);
    $description = trim($description);

    if ($id <= 0 || $nom === '' || $photo === '' || $description === '') {
        return;
    }

    $stmt = db()->prepare(
        'UPDATE batiment
         SET nom = :nom,
             photo = :photo,
             description = :description
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'nom' => $nom,
        'photo' => $photo,
        'description' => $description,
    ]);
}

function get_toilettes_by_batiment_id(int $batimentId, ?int $userId = null): array
{
    ensure_toilette_rating_table_exists();

    $sql = build_toilette_list_sql($userId !== null) . ' WHERE t.batiment_id = :batiment_id ORDER BY t.nom ASC';
    $stmt = db()->prepare($sql);

    $params = ['batiment_id' => $batimentId];
    if ($userId !== null) {
        $params['user_id'] = $userId;
    }

    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_all_toilettes_for_admin(): array
{
    ensure_toilette_rating_table_exists();

    $stmt = db()->query(
        build_toilette_list_sql(false) . ' ORDER BY b.nom ASC, t.nom ASC'
    );

    return $stmt->fetchAll();
}

function update_toilette_status(int $toiletteId, string $statut): void
{
    $statut = strtolower(trim($statut));
    if (!in_array($statut, ['ouvert', 'ferme'], true)) {
        return;
    }

    $stmt = db()->prepare('UPDATE toilette SET statut = :statut WHERE id = :id');
    $stmt->execute([
        'statut' => $statut,
        'id' => $toiletteId,
    ]);
}

function update_toilette_data(int $id, int $batimentId, string $nom, string $photo, string $description, string $statut): void
{
    $nom = trim($nom);
    $photo = trim($photo);
    $description = trim($description);
    $statut = strtolower(trim($statut));

    if ($id <= 0 || $batimentId <= 0 || $nom === '' || $photo === '' || $description === '') {
        return;
    }

    if (!in_array($statut, ['ouvert', 'ferme'], true)) {
        return;
    }

    $stmt = db()->prepare(
        'UPDATE toilette
         SET batiment_id = :batiment_id,
             nom = :nom,
             photo = :photo,
             description = :description,
             statut = :statut
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'batiment_id' => $batimentId,
        'nom' => $nom,
        'photo' => $photo,
        'description' => $description,
        'statut' => $statut,
    ]);
}

function create_toilette(int $batimentId, string $nom, string $photo, string $description, string $statut): void
{
    $nom = trim($nom);
    $photo = trim($photo);
    $description = trim($description);
    $statut = strtolower(trim($statut));

    if ($batimentId <= 0 || $nom === '' || $photo === '' || $description === '') {
        return;
    }

    if (!in_array($statut, ['ouvert', 'ferme'], true)) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO toilette (batiment_id, nom, photo, description, statut)
         VALUES (:batiment_id, :nom, :photo, :description, :statut)'
    );
    $stmt->execute([
        'batiment_id' => $batimentId,
        'nom' => $nom,
        'photo' => $photo,
        'description' => $description,
        'statut' => $statut,
    ]);
}

function delete_toilette(int $id): void
{
    if ($id <= 0) {
        return;
    }

    $stmt = db()->prepare('DELETE FROM toilette WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function get_toilette_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, batiment_id, nom, photo, description, note, statut
         FROM toilette
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $toilette = $stmt->fetch();

    return $toilette ?: null;
}

function add_favorite_toilette(int $userId, int $toiletteId): void
{
    ensure_favorite_toilette_table_exists();

    $stmt = db()->prepare(
        'INSERT IGNORE INTO user_favorite_toilette (user_id, toilette_id) VALUES (:user_id, :toilette_id)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'toilette_id' => $toiletteId,
    ]);
}

function remove_favorite_toilette(int $userId, int $toiletteId): void
{
    ensure_favorite_toilette_table_exists();

    $stmt = db()->prepare(
        'DELETE FROM user_favorite_toilette WHERE user_id = :user_id AND toilette_id = :toilette_id'
    );
    $stmt->execute([
        'user_id' => $userId,
        'toilette_id' => $toiletteId,
    ]);
}

function is_toilette_favorite(int $userId, int $toiletteId): bool
{
    ensure_favorite_toilette_table_exists();

    $stmt = db()->prepare(
        'SELECT 1 FROM user_favorite_toilette WHERE user_id = :user_id AND toilette_id = :toilette_id LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'toilette_id' => $toiletteId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function get_favorite_toilette_ids_by_user_id(int $userId): array
{
    ensure_favorite_toilette_table_exists();

    $stmt = db()->prepare(
        'SELECT toilette_id FROM user_favorite_toilette WHERE user_id = :user_id ORDER BY created_at DESC'
    );
    $stmt->execute(['user_id' => $userId]);
    $rows = $stmt->fetchAll();

    return array_map(static fn (array $row): int => (int) $row['toilette_id'], $rows);
}

function get_favorite_toilettes_by_user_id(int $userId): array
{
    ensure_favorite_toilette_table_exists();
    ensure_toilette_rating_table_exists();

    $stmt = db()->prepare(
        'SELECT t.id, t.batiment_id, t.nom, t.photo, t.description, t.note AS note_initiale,
                COALESCE(rs.avg_note, t.note) AS note_moyenne,
                COALESCE(rs.avis_count, 0) AS avis_count,
                ur.note AS note_utilisateur,
                t.statut, b.nom AS batiment_nom
         FROM user_favorite_toilette uf
         INNER JOIN toilette t ON t.id = uf.toilette_id
         INNER JOIN batiment b ON b.id = t.batiment_id
         LEFT JOIN (SELECT toilette_id, ROUND(AVG(note), 1) AS avg_note, COUNT(*) AS avis_count FROM toilette_rating GROUP BY toilette_id) rs
            ON rs.toilette_id = t.id
         LEFT JOIN toilette_rating ur ON ur.toilette_id = t.id AND ur.user_id = :user_id
         WHERE uf.user_id = :user_id
         ORDER BY uf.created_at DESC'
    );
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function get_user_account_stats(int $userId): array
{
    ensure_favorite_toilette_table_exists();
    ensure_toilette_rating_table_exists();

    $favoriteCountStmt = db()->prepare(
        'SELECT COUNT(*) FROM user_favorite_toilette WHERE user_id = :user_id'
    );
    $favoriteCountStmt->execute(['user_id' => $userId]);

    $ratingStatsStmt = db()->prepare(
        'SELECT COUNT(*) AS ratings_count, ROUND(AVG(note), 1) AS ratings_avg
         FROM toilette_rating
         WHERE user_id = :user_id'
    );
    $ratingStatsStmt->execute(['user_id' => $userId]);
    $ratingStats = $ratingStatsStmt->fetch() ?: ['ratings_count' => 0, 'ratings_avg' => null];

    $favoriteOpenStmt = db()->prepare(
        "SELECT COUNT(*)
         FROM user_favorite_toilette uf
         INNER JOIN toilette t ON t.id = uf.toilette_id
         WHERE uf.user_id = :user_id AND t.statut = 'ouvert'"
    );
    $favoriteOpenStmt->execute(['user_id' => $userId]);

    return [
        'favorites_count' => (int) $favoriteCountStmt->fetchColumn(),
        'ratings_count' => (int) ($ratingStats['ratings_count'] ?? 0),
        'ratings_avg' => $ratingStats['ratings_avg'] !== null ? (float) $ratingStats['ratings_avg'] : null,
        'favorites_open_count' => (int) $favoriteOpenStmt->fetchColumn(),
    ];
}
