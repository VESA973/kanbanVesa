# Hub Mercure (temps réel)

Le tableau Kanban se met à jour tout seul quand un autre membre le modifie : après chaque
changement, Symfony publie une mise à jour privée `<turbo-stream action="refresh">` sur le
sujet `project-{id}`, et Turbo recharge la page en *morph* (le défilement et la modale
ouverte sont conservés).

## Installation (Debian + Apache)

```bash
sudo deploy/mercure/install.sh
```

Le script :

1. télécharge le hub Mercure (binaire unique) dans `/usr/local/bin/mercure` ;
2. copie `Caddyfile` dans `/etc/mercure/` et crée `/etc/mercure/mercure.env` avec le même
   secret que `MERCURE_JWT_SECRET` de Symfony ;
3. installe et démarre le service systemd `mercure` (écoute uniquement sur `127.0.0.1:3000`) ;
4. active `mod_proxy_http` et ajoute dans le VirtualHost `kanban.conf` un proxy
   `/.well-known/mercure` → hub (une copie de sauvegarde du vhost est faite) ;
5. vérifie que tout répond.

## Variables Symfony

| Variable | Valeur |
| --- | --- |
| `MERCURE_URL` | `http://127.0.0.1:3000/.well-known/mercure` (publication, interne) |
| `MERCURE_PUBLIC_URL` | `http://kanban.lan/.well-known/mercure` (abonnement, navigateur) |
| `MERCURE_JWT_SECRET` | secret partagé avec le hub (dans `.env.dev.local` / `.env.prod.local`, jamais commité) |

## Passage en HTTPS

Mettre `https://` dans `MERCURE_PUBLIC_URL` et `MERCURE_RESOURCE_IDENTIFIER` (variable
d'environnement du hub, à ajouter dans `/etc/mercure/mercure.env`), puis redémarrer :
`sudo systemctl restart mercure`.

## Si le hub est arrêté

L'application continue de fonctionner normalement : seule la mise à jour automatique est
perdue (un avertissement est écrit dans les logs Symfony).

## Diagnostic

```bash
systemctl status mercure
journalctl -u mercure -f
```
