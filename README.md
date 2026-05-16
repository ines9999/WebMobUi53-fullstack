# Application de Sondage – WebMobUi53

## Installation

```bash
git clone <url-du-repo>
cd WebMobUi53-fullstack
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan storage:link
touch database/database.sqlite
php artisan migrate
composer run dev
```

Accéder à l'app sur `http://127.0.0.1:8000`.

## Choix techniques

### Architecture
Deux applications Vue.js distinctes :
- **Dashboard** (`/polls/dashboard-integrated`) : gestion des sondages
- **Page de vote** (`/polls/{token}`) : accessible via lien de partage

### Pourquoi deux apps Vue séparées ?
Le dashboard nécessite d'être connecté, la page de vote est accessible à tous. Les séparer rend le code plus clair.

### Apps Vue
- `AppPollDashboardIntegrated.vue` : app du dashboard (modifiée)
- `AppPollVote.vue` : page de vote (créée)

### Composants créés
- `PollCard` : carte d'un sondage dans le dashboard
- `PollForm` : formulaire de création et d'édition
- `PollResults` : graphique à barres des résultats