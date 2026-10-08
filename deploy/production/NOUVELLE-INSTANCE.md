# Installer une deuxième instance (exemple : « crij »)

Une instance indépendante de l'application, sur le même VPS : son propre dossier, sa propre
base de données, ses propres utilisateurs, son propre worker d'e-mails et son propre hub temps réel.

| Élément              | Valeur dans ce document                 |
|----------------------|-----------------------------------------|
| Dossier              | `/var/www/crij`                         |
| Base de données      | `crij`                                  |
| Utilisateur MariaDB  | `crij`                                  |
| Adresse du site      | `crij.addcayenne.com` (à adapter)       |
| IP du VPS            | `151.80.60.253`                         |
| Worker e-mails       | service `crij-worker`                   |
| Hub Mercure          | port `3001` (le premier utilise `3000`) |

## 0. DNS

Dans la zone DNS de `addcayenne.com`, ajouter : **A** `crij` → `151.80.60.253`.
Vérifier (quelques minutes à quelques heures) : `dig +short crij.addcayenne.com`.

## 1. Base de données et utilisateur

```bash
CRIJ_DB_PASSWORD=$(openssl rand -hex 24); echo "Mot de passe MariaDB crij : $CRIJ_DB_PASSWORD"
```
```bash
sudo mariadb -e "CREATE DATABASE crij CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'crij'@'localhost' IDENTIFIED BY '$CRIJ_DB_PASSWORD'; GRANT ALL PRIVILEGES ON crij.* TO 'crij'@'localhost'; FLUSH PRIVILEGES;"
```
Garder ce mot de passe (gestionnaire de mots de passe) : il sert à l'étape 3.
Un mot de passe uniquement hexadécimal évite les problèmes d'encodage dans `DATABASE_URL`.

## 2. Code

```bash
sudo mkdir /var/www/crij && sudo chown debian:debian /var/www/crij
```
```bash
git clone git@github.com:VESA973/kanbanVesa.git /var/www/crij
```

## 3. Configuration (`.env.local`)

```bash
cd /var/www/crij && cat > .env.local <<EOF
APP_ENV=prod
APP_SECRET=$(openssl rand -hex 32)
DATABASE_URL="mysql://crij:${CRIJ_DB_PASSWORD}@127.0.0.1:3306/crij?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
DEFAULT_URI=https://crij.addcayenne.com
MAILER_FROM="CRIJ <noreply@addcayenne.com>"
MERCURE_URL=http://127.0.0.1:3001/.well-known/mercure
MERCURE_PUBLIC_URL=https://crij.addcayenne.com/.well-known/mercure
MERCURE_JWT_SECRET="$(openssl rand -hex 32)"
EOF
chmod 640 .env.local && sudo chgrp www-data .env.local
```
(Ces commandes s'exécutent dans le même terminal que l'étape 1, pour que `$CRIJ_DB_PASSWORD` soit connu.)

Vérifier la connexion à la base :
```bash
php bin/console dbal:run-sql "SELECT 1" --env=prod
```

## 4. Installation

```bash
composer install --no-dev --optimize-autoloader
```
```bash
php bin/console doctrine:migrations:migrate -n --env=prod
```
```bash
php bin/console tailwind:build --minify --env=prod && php bin/console asset-map:compile --env=prod
```
```bash
sudo setfacl -R -m u:www-data:rwX -m u:debian:rwX var && sudo setfacl -dR -m u:www-data:rwX -m u:debian:rwX var
```
```bash
sudo -u www-data php bin/console cache:clear --env=prod
```

## 5. Apache + HTTPS

```bash
sudo tee /etc/apache2/sites-available/crij.conf > /dev/null <<'EOF'
<VirtualHost *:80>
    ServerName crij.addcayenne.com
    DocumentRoot /var/www/crij/public

    <Directory /var/www/crij/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>

    <Location "/.well-known/mercure">
        ProxyPass "http://127.0.0.1:3001/.well-known/mercure" flushpackets=on timeout=3600
        ProxyPassReverse "http://127.0.0.1:3001/.well-known/mercure"
    </Location>

    ErrorLog ${APACHE_LOG_DIR}/crij_error.log
    CustomLog ${APACHE_LOG_DIR}/crij_access.log combined
</VirtualHost>
EOF
```
```bash
sudo a2ensite crij && sudo apache2ctl configtest && sudo systemctl reload apache2
```
```bash
sudo certbot --apache -d crij.addcayenne.com
```

## 6. Worker des e-mails (indispensable : sans lui, aucun e-mail ne part)

```bash
sed -e 's#/var/www/kanban#/var/www/crij#' -e 's#TaskBoard Messenger worker#CRIJ Messenger worker#' deploy/production/taskboard-worker.service | sudo tee /etc/systemd/system/crij-worker.service > /dev/null
```
```bash
sudo systemctl daemon-reload && sudo systemctl enable --now crij-worker && sudo systemctl status crij-worker --no-pager
```

## 7. Temps réel (Mercure, facultatif)

Un hub séparé sur le port 3001 (le binaire `/usr/local/bin/mercure` est déjà installé
si l'instance principale a le temps réel ; sinon, lancer d'abord `deploy/mercure/install.sh`).
Sans cette étape l'application fonctionne : les tableaux ne se rafraîchissent simplement pas tout seuls.

```bash
sudo mkdir -p /etc/mercure-crij && sed 's/^:3000 {/:3001 {/' deploy/mercure/Caddyfile | sudo tee /etc/mercure-crij/Caddyfile > /dev/null
```
```bash
SECRET=$(grep -oE '[0-9a-f]{64}' <(grep MERCURE_JWT_SECRET .env.local)); printf 'MERCURE_PUBLISHER_JWT_KEY=%s\nMERCURE_SUBSCRIBER_JWT_KEY=%s\nMERCURE_DB_PATH=/var/lib/mercure-crij/mercure.db\nMERCURE_RESOURCE_IDENTIFIER=https://crij.addcayenne.com/.well-known/mercure\n' "$SECRET" "$SECRET" | sudo tee /etc/mercure-crij/mercure.env > /dev/null && sudo chmod 600 /etc/mercure-crij/mercure.env
```
```bash
sed -e 's#/etc/mercure/#/etc/mercure-crij/#g' -e 's#StateDirectory=mercure#StateDirectory=mercure-crij#' -e 's#(TaskBoard real time)#(CRIJ real time)#' deploy/mercure/mercure.service | sudo tee /etc/systemd/system/mercure-crij.service > /dev/null
```
```bash
sudo systemctl daemon-reload && sudo systemctl enable --now mercure-crij && sudo systemctl status mercure-crij --no-pager
```

## 8. Premier administrateur

1. Ouvrir https://crij.addcayenne.com et créer son compte.
2. Le passer administrateur :
```bash
cd /var/www/crij && php bin/console app:user:promote ton@email.fr --env=prod
```
3. Se déconnecter / reconnecter, puis **Administration → Serveur e-mail (SMTP)** (voir `EMAIL.md`).

## Mettre à jour cette instance plus tard

```bash
cd /var/www/crij && git pull origin main && composer install --no-dev --optimize-autoloader && php bin/console doctrine:migrations:migrate -n --env=prod && php bin/console tailwind:build --minify --env=prod && php bin/console asset-map:compile --env=prod && sudo -u www-data php bin/console cache:clear --env=prod && sudo systemctl restart crij-worker
```
