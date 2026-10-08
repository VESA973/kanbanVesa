# E-mails : envoi depuis le serveur et réception dans Gmail

L'application envoie ses e-mails (vérification, invitations, mot de passe oublié,
notifications) par le serveur SMTP configuré dans **Administration → Serveur e-mail**.
Ici, ce serveur est **Postfix installé sur le VPS**. Il signe chaque message avec DKIM.

Valeurs utilisées dans ce document (à adapter) :

| Élément                 | Valeur                                   |
|-------------------------|------------------------------------------|
| Domaine                 | `addcayenne.com`                         |
| Site                    | `gestion.addcayenne.com`                 |
| Nom du serveur mail     | `mail.addcayenne.com`                    |
| Adresse d'expédition    | `noreply@addcayenne.com`                 |
| IPv4 du VPS             | `151.80.60.253` (à remplacer partout dans ce document) |
| Sélecteur DKIM          | `taskboard`                              |

## 0. Diagnostic : pourquoi rien n'arrive

Vérifie ces trois points dans l'ordre :

1. **Un serveur SMTP est-il configuré ?** S'il ne l'est pas, l'application n'envoie rien.
   Ouvre **Administration → E-mails envoyés** : le statut « Non envoyé (SMTP non configuré) » le signale.
2. **Le worker tourne-t-il ?** Les e-mails passent par une file d'attente (Messenger) :
   ```bash
   sudo systemctl status taskboard-worker
   cd /var/www/kanban && sudo -u www-data php bin/console messenger:stats
   sudo -u www-data php bin/console messenger:failed:show
   ```
   Si des messages attendent et que le worker est arrêté, installe-le (voir `taskboard-worker.service`).
3. **Le message est-il « Envoyé » dans Administration → E-mails envoyés ?**
   Oui : le serveur l'a accepté. Cherche son Message-ID dans `/var/log/mail.log`
   pour savoir ce que Gmail a répondu (voir la section 6).

## 1. Nom du serveur et reverse DNS (PTR)

Gmail refuse ou classe en spam les messages venant d'une IP sans reverse DNS cohérent.

1. **DNS (zone `addcayenne.com`)** : `mail.addcayenne.com.  A  151.80.60.253`
2. **Reverse DNS** chez l'hébergeur du VPS (OVH : *Bare Metal Cloud → IP → ⋯ → Modifier le reverse*) :
   `151.80.60.253 → mail.addcayenne.com`
3. Vérification (les deux doivent se répondre) :
   ```bash
   dig +short mail.addcayenne.com
   dig +short -x 151.80.60.253
   ```

On envoie en IPv4 seulement. Sinon, l'IPv6 du VPS aurait aussi besoin de son propre PTR.

## 2. Postfix (envoi uniquement, non accessible depuis l'extérieur)

```bash
sudo apt install postfix opendkim opendkim-tools
```
(À l'installation, choisis « Site Internet » et `addcayenne.com` comme nom de courrier.)

`/etc/postfix/main.cf`, lignes à définir :

```
myhostname = mail.addcayenne.com
myorigin = addcayenne.com
mydestination = localhost
inet_interfaces = loopback-only
inet_protocols = ipv4
mynetworks = 127.0.0.0/8
smtp_tls_security_level = may
smtpd_milters = inet:127.0.0.1:8891
non_smtpd_milters = $smtpd_milters
milter_default_action = accept
```

Avec `inet_interfaces = loopback-only`, Postfix n'accepte que les messages du VPS lui-même.
Ce n'est donc pas un relais ouvert.

## 3. DKIM (OpenDKIM)

```bash
sudo mkdir -p /etc/opendkim/keys/addcayenne.com
sudo opendkim-genkey -b 2048 -d addcayenne.com -s taskboard -D /etc/opendkim/keys/addcayenne.com
sudo chown -R opendkim:opendkim /etc/opendkim/keys
```

`/etc/opendkim.conf`, lignes à définir :

```
Domain                  addcayenne.com
Selector                taskboard
KeyFile                 /etc/opendkim/keys/addcayenne.com/taskboard.private
Socket                  inet:8891@127.0.0.1
Canonicalization        relaxed/simple
Mode                    s
```

```bash
sudo systemctl restart opendkim postfix
sudo cat /etc/opendkim/keys/addcayenne.com/taskboard.txt   # contenu du TXT DKIM ci-dessous
```

## 4. Enregistrements DNS à ajouter

| Type | Nom (sous-domaine)              | Valeur |
|------|---------------------------------|--------|
| A    | `mail`                          | `151.80.60.253` |
| TXT  | `@` (addcayenne.com)            | `v=spf1 ip4:151.80.60.253 ~all` |
| TXT  | `taskboard._domainkey`          | `v=DKIM1; k=rsa; p=…` (la clé publique de `taskboard.txt`, en une seule chaîne) |
| TXT  | `_dmarc`                        | `v=DMARC1; p=none; rua=mailto:dmarc@addcayenne.com; adkim=r; aspf=r` |
| PTR  | (chez l'hébergeur)              | `151.80.60.253 → mail.addcayenne.com` |

Points d'attention :

- **Un seul enregistrement SPF par domaine.** Si `addcayenne.com` reçoit ou envoie déjà des
  e-mails ailleurs (boîtes OVH, Google Workspace…), **fusionne** au lieu d'ajouter un deuxième
  enregistrement. Exemple avec OVH : `v=spf1 ip4:151.80.60.253 include:mx.ovh.com ~all`.
- `rua=` doit être une boîte qui existe : elle recevra les rapports DMARC quotidiens.
  Après quelques semaines de rapports propres, passe à `p=quarantine`.
- La propagation DNS prend de quelques minutes à quelques heures.

Vérification :
```bash
dig +short TXT addcayenne.com
dig +short TXT taskboard._domainkey.addcayenne.com
dig +short TXT _dmarc.addcayenne.com
sudo opendkim-testkey -d addcayenne.com -s taskboard -vvv   # doit afficher « key OK »
```

## 5. Configuration dans l'application

Dans **Administration → Serveur e-mail (SMTP)** :

| Champ                 | Valeur |
|-----------------------|--------|
| Serveur               | `127.0.0.1` |
| Port                  | `25` |
| Chiffrement           | Aucun (serveur local uniquement) |
| Identifiant / mot de passe | vides |
| Adresse d'expédition  | `noreply@addcayenne.com` |
| Nom d'expéditeur      | `Tableau de bord` |

L'adresse d'expédition sert aussi d'expéditeur d'enveloppe (Return-Path). Les domaines du
`From`, de SPF et de DKIM sont donc tous `addcayenne.com`, ce qu'exige l'alignement DMARC.
L'application refuse une adresse d'expédition Gmail, Outlook, Orange…
Le DMARC de ces domaines fait échouer tout message envoyé par un autre serveur qu'eux.

Les invitations ont un `Reply-To` vers la personne qui invite, une partie texte et une partie
HTML, et uniquement des liens vers `gestion.addcayenne.com` (aucun raccourcisseur d'URL).

## 6. Procédure de test

1. **Administration → Serveur e-mail → Envoyer un e-mail de test** vers une adresse Gmail.
2. Dans Gmail, ouvre le message, puis **⋮ → Afficher l'original**. Il faut lire :
   `SPF : PASS`, `DKIM : PASS`, `DMARC : PASS`.
3. Va sur **https://www.mail-tester.com**, copie l'adresse proposée, invite-la dans un projet
   (ou envoie-lui un e-mail de test), puis vérifie le score. Vise **9/10 ou plus**.
4. **Administration → E-mails envoyés** : chaque envoi y apparaît avec son statut et son Message-ID.
5. Côté serveur, suis un message précis :
   ```bash
   sudo grep "Message-ID-copié-depuis-l-admin" /var/log/mail.log
   sudo postqueue -p          # messages encore en attente
   sudo tail -f /var/log/mail.log
   ```
   `status=sent (250 2.0.0 OK …gsmtp)` : Gmail a accepté le message.
   Un code `550 5.7.26` ou `5.7.1` indique un problème SPF, DKIM ou DMARC : revois les sections 3 et 4.
6. Optionnel : déclare le domaine dans **Google Postmaster Tools** pour suivre sa réputation.
