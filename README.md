# Cabinet de psychologie — API

Backend Laravel 13 du dashboard et de la prise de rendez-vous publique. Les patients ne possèdent pas de compte.

## Développement

```bash
cp .env.example .env
composer install
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

Le démarrage quotidien depuis ce dépôt est une seule commande :

```bash
composer dev:spa
```

Elle démarre Sail (Laravel et MySQL) sur `http://localhost:8000`, puis le SPA React voisin (`../cabinetPsy`) sur `http://localhost:5174`. Arrêtez Vite avec `Ctrl+C`; Sail reste disponible pour le prochain démarrage. Vérifiez les trois surfaces sans vous connecter avec :

```bash
./scripts/dev-health.sh
```

Le frontend SPA doit appeler `/sanctum/csrf-cookie`, puis `/api/login`, avec `Accept: application/json` et `credentials: include`. Configurez `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` et les cookies sécurisés selon le domaine de production.

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
vendor/bin/pint --test
```

La suite de concurrence utilise exclusivement la base MySQL dédiée `cabinetpsy_testing_mysql` et l’utilisateur restreint `cabinetpsy_concurrency_test`. Définissez uniquement `MYSQL_CONCURRENCY_TEST_PASSWORD` dans votre `.env` local, avec le mot de passe de cet utilisateur (jamais dans Git). Lorsqu’elle est activée, la configuration de test sélectionne explicitement cette variable au lieu de `DB_PASSWORD` :

```sql
CREATE DATABASE cabinetpsy_testing_mysql CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cabinetpsy_concurrency_test'@'%' IDENTIFIED BY '<mot-de-passe-de-test-dédié>';
GRANT ALL PRIVILEGES ON cabinetpsy_testing_mysql.* TO 'cabinetpsy_concurrency_test'@'%';
```

Ces droits sont nécessaires aux migrations isolées de la suite, et ne s’appliquent à aucune autre base.

```bash
vendor/bin/phpunit -c phpunit.mysql-concurrency.xml
```

## Architecture

Les contrôleurs délèguent des données validées par Form Requests via des DTO aux services. Les services dépendent des contrats de repositories, les Policies contrôlent l’ownership, et les API Resources limitent les réponses JSON.

La source de vérité des créneaux est `AvailabilityService` : horaires actifs moins absences moins rendez-vous `pending`/`confirmed`, avec la durée du profil et le fuseau `APP_TIMEZONE`.

## Protection des doubles réservations

`AppointmentService::create()` ouvre une transaction, verrouille la ligne du psychologue avec `SELECT ... FOR UPDATE`, recalcule la fin côté serveur, puis revalide horaires, absences et chevauchements avant l’insertion. Toutes les réservations concurrentes d’un même psychologue sont ainsi sérialisées sous MySQL/InnoDB. La transaction est retentée jusqu’à trois fois en cas de deadlock. Une contrainte unique classique ne suffit pas pour interdire le chevauchement de deux intervalles arbitraires.

En production : MySQL/InnoDB, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, CORS limité au frontend réel, worker de queue supervisé et secrets uniquement dans l’environnement.
