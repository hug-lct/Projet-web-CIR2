-- Base de données : `junia_toilettes`
--

-- --------------------------------------------------------

--
-- Structure de la table `batiment`
--

CREATE TABLE `batiment` (
  `id` int NOT NULL,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `batiment`
--

INSERT INTO `batiment` (`id`, `nom`, `photo`, `description`) VALUES
(1, 'IC1', 'assets/uploads/batiment-20260426114139-f68e19ce75ec.jpg', 'Nouveau bâtiment sans wifi'),
(2, 'IC2', 'assets/uploads/batiment-20260426114154-83b98605614c.jpg', 'Bâtiment principal de JUNIA ISEN'),
(3, 'Palais Rameau', 'assets/uploads/batiment-20260426114214-2997dac5349d.jpg', 'Jolie palais');

-- --------------------------------------------------------

--
-- Structure de la table `toilette`
--

CREATE TABLE `toilette` (
  `id` int NOT NULL,
  `batiment_id` int NOT NULL,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` decimal(2,1) NOT NULL DEFAULT '0.0',
  `statut` enum('ouvert','ferme') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `toilette`
--

INSERT INTO `toilette` (`id`, `batiment_id`, `nom`, `photo`, `description`, `note`, `statut`) VALUES
(5, 2, 'A800', 'assets/uploads/toilette-20260426150841-87176dfcd1cf.jpg', 'Un peu petit mais parfait pour les envies pressantes', 0.0, 'ouvert'),
(6, 2, 'Atrium', 'assets/uploads/toilette-20260426150925-f5aabb4487a6.jpg', 'À la vue de tout le monde', 0.0, 'ouvert'),
(7, 2, 'B800', 'assets/uploads/toilette-20260426150945-f84a535f4c69.jpg', 'Caché dans les hauteurs', 0.0, 'ouvert'),
(8, 2, 'C200', 'assets/uploads/toilette-20260426151034-28cb926847d4.jpg', 'Le nouveau bassin acoustique', 0.0, 'ouvert'),
(9, 2, 'C602', 'assets/uploads/toilette-20260426151104-6cba164b3d7d.jpg', 'Petit mais suffisant', 0.0, 'ouvert'),
(10, 2, 'C800', 'assets/uploads/toilette-20260426151130-9557bd061091.jpg', 'Parfait en plein partiel', 0.0, 'ouvert');

-- --------------------------------------------------------

--
-- Structure de la table `toilette_rating`
--

CREATE TABLE `toilette_rating` (
  `user_id` int NOT NULL,
  `toilette_id` int NOT NULL,
  `note` tinyint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `admin` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `password_hash`, `created_at`, `admin`) VALUES
(2, 'User', 'User@junia.com', '$2y$10$MoYheSoe8LkA8rxQQ40pt.9iGPIJFcTxaa/jaa2qyaWUrxsdafSRS', '2026-04-26 15:20:48', 0),
(3, 'Admin', 'Admin@junia.com', '$2y$10$ycq/pOyb6XsvgNPbQHD18ePI6LmGr4seYwaDXpvfigOi83MUWGcGa', '2026-04-26 15:21:43', 1);

-- --------------------------------------------------------

--
-- Structure de la table `user_favorite_toilette`
--

CREATE TABLE `user_favorite_toilette` (
  `user_id` int NOT NULL,
  `toilette_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `batiment`
--
ALTER TABLE `batiment`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `toilette`
--
ALTER TABLE `toilette`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_toilette_batiment` (`batiment_id`);

--
-- Index pour la table `toilette_rating`
--
ALTER TABLE `toilette_rating`
  ADD PRIMARY KEY (`user_id`,`toilette_id`),
  ADD KEY `fk_toilette_rating_toilette` (`toilette_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `user_favorite_toilette`
--
ALTER TABLE `user_favorite_toilette`
  ADD PRIMARY KEY (`user_id`,`toilette_id`),
  ADD KEY `fk_user_favorite_toilette_toilette` (`toilette_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `batiment`
--
ALTER TABLE `batiment`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `toilette`
--
ALTER TABLE `toilette`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `toilette`
--
ALTER TABLE `toilette`
  ADD CONSTRAINT `fk_toilette_batiment` FOREIGN KEY (`batiment_id`) REFERENCES `batiment` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `toilette_rating`
--
ALTER TABLE `toilette_rating`
  ADD CONSTRAINT `fk_toilette_rating_toilette` FOREIGN KEY (`toilette_id`) REFERENCES `toilette` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_toilette_rating_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_favorite_toilette`
--
ALTER TABLE `user_favorite_toilette`
  ADD CONSTRAINT `fk_user_favorite_toilette_toilette` FOREIGN KEY (`toilette_id`) REFERENCES `toilette` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_favorite_toilette_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
