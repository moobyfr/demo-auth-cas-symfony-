# Demo Symfony + CAS (minimal)

Cette application montre un flux CAS 2.0 minimal dans Symfony, avec login et logout CAS. L'application *doit* etre servie en https

## Prerequis

- PHP 8.4+
- Composer 2+

## Installation rapide

```bash
composer install
cp .env.local.example .env.local
```

Puis adapte `.env.local`:

```dotenv
CAS_URL=https://cas.example.edu/cas
CAS_LOGIN_PATH=/login
CAS_VALIDATE_PATH=/serviceValidate
CAS_LOGOUT_PATH=/logout
```

## Lancement local

```bash
make run
```

Ou sans `make`:

```bash
symfony server:start
```

Pages utiles:
- `https://127.0.0.1:8000/` (publique)
- `https://127.0.0.1:8000/private` (protegee, declenche CAS)
- `https://127.0.0.1:8000/logout` (logout local + CAS)

## Verifications

```bash
make lint
```

## Fonctionnement

### Login CAS

- L'acces a `/private` redirige vers `CAS_LOGIN_PATH` avec `service`.
- CAS renvoie sur l'app avec un `ticket`.
- L'authenticator appelle `CAS_VALIDATE_PATH` (`serviceValidate`).
- L'authenticator extrait les attributs et les roles (`extractRoles()`).
- Le provider construit ensuite l'utilisateur applicatif.

### Logout CAS

- L'appel a `/logout` invalide la session Symfony.
- Le listener de logout redirige vers `CAS_LOGOUT_PATH?service=<url_home>`.
- Tu obtiens une deconnexion locale et SSO.

## Integration avec une classe utilisateur existante

La classe `src/Security/User.php` est une classe de demo. Si ton projet a deja une entite utilisateur (`App\Entity\User`, `Personne`, etc.), garde le flux CAS et adapte `src/Security/UserProvider.php`.

Checklist:
1. Adapte `loadFromCas(string $identifier, array $attributes, array $roles = ['ROLE_USER'])`.
2. Recharge le meme type d'utilisateur dans `loadUserByIdentifier()`.
3. Retourne `true` dans `supportsClass()` pour ta classe metier.
4. Mappe `identifier`, attributs CAS et roles sur ton entite.
5. Si Doctrine est utilise, injecte repository + EntityManager et fais `persist/flush`.

`UserProvider` contient deja un commentaire avec un exemple Doctrine pret a reprendre.

## Fichiers cles

- `src/Security/CasAuthenticator.php`: login CAS, validation ticket, extraction des roles.
- `src/Security/UserProvider.php`: mapping CAS vers utilisateur applicatif.
- `src/Security/CasLogoutListener.php`: redirection CAS au logout.
- `config/packages/security.yaml`: firewall, route protegee, logout.
- `config/services.yaml`: URLs CAS et wiring des services.
- `.env.local.example`: template de configuration locale.
- `Makefile`: commandes de dev (`install`, `run`, `lint`).
- `.github/workflows/ci.yml`: verification automatique en CI.
