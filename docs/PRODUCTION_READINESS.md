# Production readiness

## Code ready

- Routes privées protégées par Sanctum et politiques d’ownership.
- Authentification professionnelle par session, régénération à la connexion et invalidation à la déconnexion.
- CSRF Sanctum actif pour les requêtes SPA stateful ; les routes publiques restent explicitement publiques.
- CORS limité à `FRONTEND_URL`, avec credentials activés pour la SPA.
- Limites distinctes : login, disponibilité, liste publique, réservation publique, upload de photo et flux de compte.
- Les comptes professionnels ont un cycle `invited` / `active` / `suspended`; ce statut contrôle le dashboard tandis que `psychologists.is_active` contrôle séparément la visibilité et la réservation publiques.
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

## Out of scope

Les emails de compte (invitation, réinitialisation et confirmation de changement d’email) utilisent les Notifications Laravel et `FRONTEND_URL`. Configurer `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME` dans l’environnement; aucun secret ne doit être commité. Les emails de rendez-vous restent hors périmètre.
