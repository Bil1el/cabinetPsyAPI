# Production readiness

## Code ready

- Routes privées protégées par Sanctum et politiques d’ownership.
- Authentification professionnelle par session, régénération à la connexion et invalidation à la déconnexion.
- CSRF Sanctum actif pour les requêtes SPA stateful ; les routes publiques restent explicitement publiques.
- CORS limité à `FRONTEND_URL`, avec credentials activés pour la SPA.
- Limites distinctes : login, disponibilité, liste publique, réservation publique, upload de photo et flux de compte.
- Les comptes professionnels ont un cycle `invited` / `active` / `suspended`; disponibilité et réservation publiques exigent à la fois un compte `active` et `psychologists.is_active=true`. Ce dernier reste le commutateur de visibilité du profil, distinct de l’accès au dashboard.
- Photos professionnelles stockées sur le disque public sous un nom généré, avec URL centralisée dans les Resources et suppression limitée au répertoire applicatif géré.
- Réservation, remplacement d’horaires/mode et absences sérialisés par verrou psychologue sous MySQL/InnoDB.
- Les erreurs API sont JSON et les conflits métier n’exposent pas de trace.

## Required during deployment

- Définir un `APP_KEY` unique, `APP_ENV=production`, `APP_DEBUG=false` et un `APP_URL` HTTPS.
- Définir `FRONTEND_URL` et `SANCTUM_STATEFUL_DOMAINS` avec le domaine réel de la SPA, sans joker.
- Définir les identifiants MySQL de production avec privilèges minimaux.
- Configurer `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` (ou `none` uniquement avec HTTPS et besoin cross-site), ainsi que `SESSION_DOMAIN` si nécessaire.
- Terminer HTTPS au proxy ; configurer les proxies de confiance selon l’infrastructure réelle.
- Exécuter uniquement `php artisan migrate --force` après sauvegarde et vérification. Ne jamais utiliser `migrate:fresh`, `db:wipe` ou les suites destructives sur production.
- Configurer permissions `storage/` et `bootstrap/cache/`, vérifier le lien `public/storage`, rotation/rétention des logs, sauvegardes MySQL et monitoring.
- Déployer le code source sans cache Laravel généré localement : `bootstrap/cache/*.php` est ignoré par Git et ne constitue pas une configuration de production.
- Après injection des variables réelles sur le serveur, exécuter `php artisan optimize:clear`, puis `php artisan config:cache`, `php artisan route:cache` et `php artisan view:cache` dans cet ordre. Ne jamais copier un `bootstrap/cache/config.php` issu du poste de développement.

## Backup and restore

- Effectuer un dump MySQL cohérent avant chaque déploiement et le chiffrer/conserver hors du dépôt. Pour les tables InnoDB, utiliser `mysqldump --single-transaction --routines --events --triggers`.
- Tester périodiquement la restauration dans une base temporaire nommée explicitement, jamais dans la base applicative. Le dump produit avec `--databases` doit être restauré après réécriture de son `USE` vers cette base temporaire : il ne faut jamais l’importer aveuglément.
- Vérifier au minimum le schéma, les clés étrangères, les index et les compteurs `users`, `psychologists`, `patients`, `appointments`, `psychologist_working_hours` et `psychologist_absences`, puis supprimer la base temporaire.

## Queue and scheduler

Utiliser un worker permanent supervisé. Un worker suffit pour un petit cabinet ; augmenter seulement après observation d’une file durablement non vide.

```ini
[program:cabinetpsy-queue]
command=/usr/bin/php /var/www/cabinetPsyAPI/artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600
directory=/var/www/cabinetPsyAPI
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/cabinetpsy-queue.log
stopwaitsecs=90
```

Le `timeout` doit rester inférieur au `retry_after` database (90 secondes). Après un déploiement ou une modification de configuration, exécuter `php artisan queue:restart` afin que le superviseur remplace proprement les workers.

Le scheduler doit tourner chaque minute :

```cron
* * * * * cd /var/www/cabinetPsyAPI && php artisan schedule:run >> /dev/null 2>&1
```

Il purge quotidiennement les jetons de réinitialisation expirés et les failed jobs de plus de 30 jours. Examiner d’abord les failed jobs récents ; ne jamais utiliser `queue:retry all` sans sélection des UUID pertinents.

## Logs and storage

- En production, définir `LOG_CHANNEL=stack`, `LOG_STACK=daily`, `LOG_LEVEL=info` et `LOG_DAILY_DAYS=14`. Les valeurs de repli du code sont également `daily` et `info` ; le `.env` local peut conserver son niveau debug.
- Surveiller `storage/logs`, le journal du superviseur et l’espace disque. Ne pas journaliser de mots de passe, tokens ou payloads patients complets.
- Les photos sont stockées sur le disque `public`, soit `storage/app/public`, et exposées uniquement via le lien `public/storage`. Monter ce répertoire sur un volume persistant du VPS, avec ownership du processus PHP, permissions minimales nécessaires et sauvegarde incluse avec les uploads.
- Les photos gérées sont des JPEG, PNG ou WebP jusqu’à 5 Mo. Le remplacement supprime l’ancienne photo gérée après succès de la mise à jour en base.

## Database growth and cleanup

- Les collections patients, absences et rendez-vous sont paginées. Les rendez-vous chargent explicitement patient et psychologue, évitant le N+1 de la liste privée.
- Les index actuels couvrent les requêtes principales : rendez-vous par psychologue/date/statut, identité patient normalisée, horaires/absences par psychologue et date, ainsi que les tables de queue/session/cache.
- Pour le volume d’un petit cabinet, MySQL local/InnoDB et la queue database sont suffisants. Réévaluer Redis, une base managée et plusieurs workers si les jobs restent durablement en attente, si les sessions/cache grossissent fortement, ou si les listes opérationnelles approchent plusieurs centaines de milliers de lignes.
- Ne supprimer des comptes, patients ou rendez-vous que s’ils sont prouvés orphelins et sans historique. Pour les comptes avec historique, préférer suspension ou masquage. Les invitations expirées, tokens de reset expirés, cache expiré et failed jobs anciens peuvent être purgés selon les rétentions prévues.

## Security configuration

- Définir `APP_ENV=production`, `APP_DEBUG=false`, un `APP_KEY` unique et `APP_URL` en HTTPS. Ne jamais publier le fichier `.env`, les dumps SQL ou les répertoires `storage/`.
- Définir `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_DOMAIN` et `SANCTUM_STATEFUL_DOMAINS` pour les domaines réellement servis. Maintenir CORS limité à `FRONTEND_URL` avec credentials ; aucun joker pour une SPA authentifiée.
- Configurer le proxy TLS et les proxies de confiance selon l’infrastructure VPS/proxy inverse réellement retenue, afin que Laravel reconnaisse correctement HTTPS et l’adresse cliente utilisée par les limites de débit.
- Utiliser des identifiants MySQL et SMTP propres à la production, à privilèges minimaux, et injectés par le gestionnaire de secrets ou l’environnement de déploiement.

## Lightweight load validation

- Limiter les tests de charge de préproduction à des GET publics et exécuter les écritures seulement sur une base dédiée. Les endpoints de disponibilité sont intentionnellement limités par IP ; un benchmark depuis une seule IP doit distinguer les `429` de la saturation applicative.
- Mesurer à nouveau depuis le VPS, après HTTPS et cache de configuration, les endpoints `/api/public/psychologists` et `/api/psychologists/{id}/availability`. Conserver les résultats, le nombre de requêtes, la concurrence, le p95 et le taux d’erreur comme baseline de déploiement.

## Email delivery

Les emails de compte et de rendez-vous utilisent les Notifications Laravel, la queue database et la configuration SMTP standard. Configurer `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` ou `MAIL_SCHEME`, `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME` dans l’environnement; aucun secret ne doit être commité.

Si le blocage des IP inconnues est activé pour les clés SMTP Brevo, autoriser l’IP publique de sortie stable du VPS dans **Brevo > Settings > Security > Authorized IPs** avant d’activer le worker. Ne pas désactiver cette protection pour contourner une erreur `525 5.7.1 Unauthorized IP address`. Après tout changement de configuration mail, exécuter `php artisan optimize:clear`, puis `php artisan queue:restart`, et valider d’abord un envoi SMTP minimal avant de réessayer des jobs échoués.
