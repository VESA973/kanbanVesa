CLAUDE.md — TaskBoard (gestion de projet type Trello)

Ce fichier guide Claude Code sur ce projet. Lis-le en entier avant toute modification.

1. Le projet en bref

Application web de gestion de projet inspirée de Trello, centrée sur le tableau Kanban :

Les utilisateurs créent un compte et se connectent.
Un utilisateur crée des projets (tableaux) composés de colonnes et de cartes/tâches.
Le propriétaire partage un projet avec d'autres utilisateurs (invitation par e-mail).
Chaque tâche peut être assignée à un membre ; le propriétaire voit qui a fait quoi et ce qui reste à faire.
On ne reproduit PAS tout Trello (pas de power-ups, automatisations, templates publics…). Priorité : un Kanban simple, rapide et fiable.
2. Stack technique
Domaine	Choix
Langage	PHP 8.4 (declare(strict_types=1); partout)
Framework	Symfony 7.4 LTS
Base de données	MariaDB 11 (LTS) + Doctrine ORM 3 + Doctrine Migrations
Front	Twig + Tailwind CSS (symfonycasts/tailwind-bundle) + AssetMapper (pas de Webpack, pas de Node obligatoire)
Interactivité	Symfony UX : Stimulus, Turbo, Twig Components, Live Components
Drag & drop	SortableJS piloté par un contrôleur Stimulus
Temps réel (phase 2)	Mercure + Turbo Streams
Auth	Symfony Security (form login, remember me, symfonycasts/verify-email-bundle, symfonycasts/reset-password-bundle)
Permissions	Voters Symfony uniquement
E-mails	Symfony Mailer + Messenger (envoi asynchrone)
Tests	PHPUnit, zenstruck/foundry (fixtures/factories), WebTestCase
Qualité	PHPStan (niveau max), PHP-CS-Fixer (règles @Symfony), Rector
Environnement	Docker Compose (PHP/FrankenPHP, MariaDB, Mailpit) + Symfony CLI
3. Commandes utiles
bash
# Démarrage
docker compose up -d
symfony serve -d
php bin/console tailwind:build --watch

# Base de données
php bin/console make:migration
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n

# Qualité (à lancer avant chaque commit)
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse
vendor/bin/rector process --dry-run
php bin/phpunit
4. Modèle de données
User ──< ProjectMember >── Project ──< BoardColumn ──< Task
                              │                         ├──< Comment
                              ├──< Category ──────────< ├──< ChecklistItem
                              │                         └──>< Label
                              ├──< Invitation
                              └──< ActivityLog
EmailLog (journal des envois, indépendant)

Hiérarchie fonctionnelle : le Project est le « projet global » ; il contient des catégories
(Category) ; chaque tâche appartient à une catégorie (obligatoire) ET à une colonne (statut).
Le tableau affiche une bande horizontale par catégorie, découpée en cases colonne × catégorie.
User : email (unique), password, firstName, lastName, isVerified, createdAt.
Project : name, description, color, owner (User), archivedAt, createdAt.
ProjectMember : project, user, role (ProjectRole enum : OWNER, EDITOR, VIEWER), joinedAt.
BoardColumn : project, name, position (int).
Category : project, name, position (int). Un projet garde toujours au moins une catégorie (« Général » à la création).
Task : column, category, title, description, assignee (User, nullable), dueDate, position, priority (enum), completedAt, createdBy.
ChecklistItem : task, label, isDone, position.
Comment : task, author, content, createdAt.
Label : project, name, color.
Invitation : project, email, role, tokenHash, expiresAt (+7 jours), acceptedAt, task (nullable) ; statut calculé (InvitationStatus : PENDING, ACCEPTED, EXPIRED).
EmailLog : recipients, subject, status (EmailStatus : SENT, FAILED, NOT_CONFIGURED), messageId, error, createdAt.
ActivityLog : project, user, action (enum), subject, payload (json), createdAt → permet au propriétaire de suivre qui a fait quoi.

Règles :

Utiliser des enums PHP natives (backed enums) pour les rôles, priorités, actions.
Utiliser DateTimeImmutable partout.
Le champ position gère l'ordre des colonnes, des catégories et des cartes ; le réordonnancement passe par un service dédié (ColumnMover, CategoryMover, TaskMover).
La position d'une tâche est comptée dans sa case (colonne + catégorie), pas dans toute la colonne.
Avancement (CategoryProgress) : tâches terminées (completedAt) / total, par catégorie et pour le projet (pondéré par le nombre de tâches) ; une catégorie vide affiche « 0 tâche ».

Spécificités MariaDB :

DATABASE_URL doit préciser la version serveur, ex. mysql://app:app@127.0.0.1:3306/taskboard?serverVersion=11.4.2-MariaDB&charset=utf8mb4.
Encodage utf8mb4 / collation utf8mb4_unicode_ci (emojis et accents dans les tâches et commentaires).
Le champ payload (json) d'ActivityLog est stocké en LONGTEXT par MariaDB : ne pas compter sur des requêtes JSON avancées, filtrer plutôt sur les colonnes action et createdAt.
Ajouter des index sur les clés de tri et de filtre fréquentes (task.position, task.due_date, task.assignee_id, activity_log.created_at).
5. Permissions (Voters)
Action	OWNER	EDITOR	VIEWER
Voir le projet	✅	✅	✅
Créer / déplacer / modifier une tâche	✅	✅	❌
Cocher une tâche qui m'est assignée	✅	✅	✅
Gérer les colonnes	✅	✅	❌
Gérer les catégories	✅	✅	❌
Inviter / retirer des membres	✅	❌	❌
Archiver / supprimer le projet	✅	❌	❌
Toute vérification d'accès passe par ProjectVoter / TaskVoter (#[IsGranted] ou denyAccessUnlessGranted).
Jamais de vérification de rôle codée en dur dans un contrôleur ou un template.
Un utilisateur non membre reçoit une 404 (ne pas révéler l'existence du projet).
6. Architecture du code
src/
├── Controller/        # Fins : reçoivent la requête, appellent un service, rendent la réponse
├── Entity/
├── Enum/
├── Form/              # Un FormType par formulaire
├── Repository/        # Toutes les requêtes DQL / QueryBuilder ici
├── Security/Voter/
├── Service/           # Logique métier (TaskMover, CategoryManager, CategoryProgress, InvitationManager…)
├── Mailer/            # Transport SMTP réglé dans l'admin (SettingsTransport) + journal des envois (EmailJournal)
├── EventSubscriber/   # Ex : journalisation automatique des actions
├── Twig/Components/   # Twig & Live Components
└── DataFixtures/ + Factory/ (Foundry)
templates/
├── base.html.twig
├── components/        # Composants réutilisables (Button, Modal, Card…)
├── project/
├── task/
└── security/
assets/
├── controllers/       # Contrôleurs Stimulus (sortable_controller.js, modal_controller.js…)
└── styles/app.css     # Point d'entrée Tailwind
7. Règles de code (lisibilité avant tout)
Contrôleurs fins : pas plus de ~15 lignes par action. La logique va dans un service.
Un service = une responsabilité, nom explicite (TaskMover, pas TaskHelper).
Injection de dépendances par le constructeur, propriétés private readonly.
Typage strict de toutes les signatures (paramètres + retour). Pas de mixed sauf nécessité.
Attributs PHP pour les routes, l'ORM, la validation (#[Route], #[ORM\...], #[Assert\...]).
Noms en anglais dans le code ; textes de l'interface en français via les traductions (translations/messages.fr.yaml).
Pas de code mort, pas de commentaires qui répètent le code. Un commentaire explique le pourquoi.
Méthodes courtes (< 20 lignes idéalement), retours anticipés plutôt que if imbriqués.
Requêtes optimisées : éviter le N+1 (jointures + addSelect dans les repositories).
8. Front-end & Tailwind
Uniquement Tailwind pour le style. Pas de CSS custom sauf cas exceptionnel dans assets/styles/app.css via @layer components.
Les combinaisons de classes répétées deviennent des Twig Components (<twig:Button variant="primary">), pas des classes @apply partout.
Design : sobre, responsive (mobile d'abord), mode sombre via dark:.
Accessibilité : labels sur tous les champs, focus visibles, contrastes suffisants, rôles ARIA pour les modales.
Le drag & drop des cartes et colonnes envoie une requête PATCH (fetch) au serveur, qui renvoie la position confirmée. En cas d'erreur, la carte revient à sa place.
Pas de framework JS lourd (React/Vue) : Stimulus + Turbo suffisent.
9. Sécurité
Protection CSRF sur tous les formulaires et requêtes de modification.
Validation côté serveur systématique (contraintes Assert), même si le front valide aussi.
Mots de passe hashés avec l'algorithme auto.
Tokens d'invitation aléatoires (random_bytes), stockés hachés, à usage unique, avec expiration ; renvoyer une invitation régénère le jeton.
E-mails : expéditeur sur notre domaine (jamais @gmail.com…, refusé par la validation), SPF/DKIM/DMARC alignés ; procédure dans deploy/production/EMAIL.md.
Limiter les tentatives de connexion (login_throttling).
Échapper toute donnée utilisateur (Twig le fait par défaut : ne jamais utiliser |raw sur du contenu utilisateur).
10. Tests
Chaque Voter a ses tests unitaires (toutes les combinaisons rôle/action).
Chaque service métier a ses tests unitaires.
Les parcours principaux ont un test fonctionnel : inscription, création de projet, invitation, déplacement d'une tâche, accès refusé à un non-membre.
Utiliser Foundry pour créer les données de test.
11. Façon de travailler (pour Claude)
Avant de coder une fonctionnalité, propose un court plan (fichiers touchés, entités, routes).
Utilise les générateurs Symfony (make:entity, make:controller, make:voter, make:form, make:twig-component) puis adapte le code.
Après une modification d'entité : génère la migration et vérifie-la avant de la lancer.
Après chaque fonctionnalité : lance PHP-CS-Fixer, PHPStan et les tests. Corrige avant de dire que c'est terminé.
Avance par petites étapes livrables ; une fonctionnalité = un commit clair (Conventional Commits : feat:, fix:, refactor:…).
Si une demande est ambiguë ou touche la sécurité/les permissions, pose la question avant d'agir.
N'ajoute pas de dépendance sans expliquer pourquoi.
12. Feuille de route
V1 : comptes (inscription, vérification e-mail, mot de passe oublié), projets, colonnes, tâches, drag & drop, partage par invitation, rôles, assignation, échéances, tableau de bord « Mes tâches ».
V2 : commentaires, checklists, étiquettes, journal d'activité, notifications e-mail (tâche assignée, échéance proche), filtres (par membre, étiquette, échéance).
V3 : temps réel avec Mercure, recherche, archivage, statistiques d'avancement par membre.
