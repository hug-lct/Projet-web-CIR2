# Projet-web-CIR2

## La description du projet

Classiqu'ISEN est une application web PHP/MySQL qui centralise les informations des toilettes par bâtiment sur le campus JUNIA Lille.
L'objectif est de proposer une interface simple pour consulter les toilettes disponibles, leur statut (ouvert/fermé), les ajouter en favoris, et permettre aux utilisateurs de laisser une note sur 5.
Le projet inclut également un espace administrateur pour gérer les bâtiments et les toilettes, ainsi qu'un espace profil utilisateur.

## Les fonctionnalités principales (détail complet)

### Côté utilisateur connecté / non connecté

- Inscription utilisateur avec validation des champs (nom, email, mot de passe) et hachage du mot de passe.
- Connexion utilisateur avec vérification email/mot de passe.
- Déconnexion avec destruction de session.
- Affichage de la liste des bâtiments avec photo, description et accès au détail.
- Affichage de la liste des toilettes par bâtiment avec:
	- nom,
	- photo,
	- description,
	- statut (Ouvert/Fermé),
	- note moyenne,
	- nombre d'avis.
- Ajout/Suppression des favoris par utilisateur connecté.
- Page Favoris avec liste personnelle des toilettes favorites.
- Notation des toilettes par utilisateur connecté (de 1 à 5), avec mise à jour de la note moyenne globale.

### Onglet Profil & stats

- Accès à un onglet Profil & stats depuis le menu quand l'utilisateur est connecté.
- Modification du compte:
	- nom,
	- email,
	- mot de passe (optionnel),
	- contrôle du mot de passe actuel obligatoire.
- Suppression du compte avec confirmation et mot de passe actuel obligatoire.
- Statistiques personnelles associées au compte:
	- nombre de favoris,
	- nombre de favoris actuellement ouverts,
	- nombre de toilettes notées,
	- moyenne des notes données par l'utilisateur.

### Roulette (accès connecté uniquement)

- Bouton Roulette visible uniquement pour les utilisateurs connectés.
- Sélection d'un bâtiment puis tirage aléatoire d'une toilette de ce bâtiment.
- Affichage du résultat avec photo, nom, description, note moyenne et statut.
- Gestion des cas sans toilettes dans le bâtiment sélectionné.

### Espace administrateur (admin = 1)

- Accès restreint aux comptes administrateurs (champ `admin = 1`).
- Gestion des bâtiments:
	- ajout,
	- modification,
	- suppression (avec suppression en cascade des toilettes liées).
- Gestion des toilettes:
	- ajout,
	- modification (nom, photo, description, bâtiment, statut),
	- suppression.


### Fonctionnalités dynamiques JavaScript

- Recherche instantanée des bâtiments via la barre de recherche avec filtrage des cartes et compteur de résultats: (JS)
- Recherche instantanée des toilettes (page bâtiment et page favoris) avec filtrage des cartes et compteur de résultats: (JS)
- Affichage/Masquage d'un état vide quand aucun résultat ne correspond au filtre: (JS)
- Normalisation de la recherche (minuscule + retrait des accents) pour améliorer la tolérance de saisie: (JS)
- Animation d'apparition progressive des cartes au scroll (IntersectionObserver): (JS)
- Bouton "retour en haut" affiché selon la position de scroll + scroll fluide: (JS)
- Modal Roulette (ouverture, fermeture, gestion touche Echap, fermeture au clic hors modal): (JS)
- Animation de tirage de la roulette puis affichage du résultat final: (JS)

## Les instructions d’installation et d’exécution

1. Copier le projet dans le répertoire web de MAMP.
2. Ouvrir phpMyAdmin depuis MAMP.
3. Importer le fichier `database/schema.sql`.
4. Vérifier la configuration de la base dans `src/config.php`:
	- `db_host`: `localhost`
	- `db_port`: `8889` (adapter selon votre config MAMP)
	- `db_user`: `root`
	- `db_pass`: `root`
	- `db_name`: `junia_toilettes`
	- Vous pouvez utiliser n'importe quel port MySQL en modifiant `db_port`, ou en définissant les variables d'environnement `DB_HOST` / `DB_PORT` (option avancée: `DB_PORTS=3306,3307,8889`).
5. Démarrer Apache et MySQL dans MAMP.
6. Ouvrir l'application dans le navigateur via le dossier `public`.
7. (Optionnel) Promouvoir un utilisateur admin:
	- `UPDATE users SET admin = 1 WHERE email = 'votre-email@exemple.com';`
8. Voici des comptes déjà prêts:
	- Compte User:
	  - Mail: User@junia.com
	  - Mot de passe: User2026!

	- Compte Admin:
	  - Mail: Admin@junia.com
	  - Mot de passe: Admin2026!

	Vous pouvez aussi créer un compte via la page inscription, mais il ne sera pas administrateur par défaut.

## Les noms des membres du groupe

- Hugo Lecompte
- Antonin Bonami
- Léonard Chanterelle
- Adrien Allienne