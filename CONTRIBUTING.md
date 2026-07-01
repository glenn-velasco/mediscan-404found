# Contributing Guide

## Branch Naming

Branch names follow this pattern:

```
<type>/<short-description>
```

Examples:
- `feature/mediscan-businesslogic`
- `fix/invitation-redirect`
- `chore/upgrade-dependencies`

Always branch off `main`. Keep branches focused on a single concern.

---

## Commit Messages

This project follows [Conventional Commits](https://www.conventionalcommits.org/).

```
<type>(<scope>): <short description>
```

### Types

| Type | When to use |
|------|-------------|
| `feat` | New feature or page |
| `fix` | Bug fix |
| `chore` | Tooling, config, dependencies, cleanup |
| `refactor` | Code restructure with no behavior change |
| `test` | Adding or updating tests |
| `docs` | Documentation only |

### Scopes

Use a scope that matches the area you changed:

| Scope | Area |
|-------|------|
| `frontend` | React/Inertia pages and components |
| `admin` | Admin-specific features |
| `dashboard` | Patient dashboard |
| `auth` | Authentication and Fortify |
| `tests` | PHPUnit/Pest test files |
| `ci` | GitHub Actions workflows |
| `database` | Migrations and seeders |
| `models` | Eloquent models, enums, traits |
| `data-layer` | Repositories and services |
| `config` | App configuration |
| `cleanup` | Removing scaffold or dead code |

### Examples

```
feat(frontend): add invitation create page
fix(admin): correct invitation store redirect and enable RequirePassword middleware
chore(ci): switch workflows to pnpm and add browser test job
feat(tests): migrate PHPUnit test suite to Pest v4
```

### Rules

- Use the **imperative mood** — "add", not "added" or "adds"
- Keep the subject line under **72 characters**
- One logical change per commit — don't bundle unrelated changes

---

## Workflow

### First-Time Setup

```bash
composer run setup
```

Installs PHP and Node dependencies, copies `.env.example`, generates the app key, runs migrations, builds assets, and configures the shared git hooks. Run this once after cloning.

If you skipped `setup` or want to enable hooks manually:

```bash
composer run hooks:install
```

This sets `core.hooksPath` to `.githooks/`, activating the pre-push hook that runs `composer run ci:check` before every push.

### Starting New Work

```bash
git checkout main
git pull origin main
git checkout -b feature/<your-feature>
```

### During Development

Commit often in small, logical units:

```bash
git add <specific files>
git commit -m "feat(scope): description"
```

Avoid `git add .` — stage files explicitly to prevent committing generated or sensitive files.

### Opening a Pull Request

1. Push your branch: `git push -u origin <branch-name>`
2. Open a PR against `main` on GitHub
3. Fill in the PR description — what changed and why
4. Request a review from at least one teammate
5. Do **not** force-push after requesting review

### Code Review

- Reviewers: focus on correctness, security, and intent — not style (the linter handles that)
- Authors: respond to all comments before merging; don't resolve threads yourself
- Approved PRs are merged by the author using **Squash and Merge** unless the branch history is intentionally granular

### After Merging

Delete the branch:

```bash
git branch -d feature/<your-feature>
git push origin --delete feature/<your-feature>
```

---

## Local Development Commands

### Starting the Dev Environment

```bash
composer run dev
```

Starts all services concurrently: PHP server, queue worker, Pail log viewer, Reverb WebSocket server, and Vite. Defined in `composer.json → scripts.dev`.

---

### Code Quality

#### PHP — Laravel Pint

```bash
# Auto-fix code style
composer run lint          # → pint --parallel

# Check only (no writes) — what CI runs
composer run lint:check    # → pint --parallel --test
```

#### Frontend — ESLint + Prettier

```bash
# Auto-fix lint issues
pnpm run lint              # → eslint . --fix

# Check only
pnpm run lint:check        # → eslint .

# Auto-format
pnpm run format            # → prettier --write resources/

# Check only
pnpm run format:check      # → prettier --check resources/
```

#### TypeScript

```bash
pnpm run types:check       # → tsc --noEmit
```

#### PHPStan

```bash
composer run types:check   # → phpstan analyse
```

---

### Running All Checks at Once

```bash
composer run ci:check
```

Runs in order: frontend lint check → frontend format check → frontend type check → PHP lint check → PHPStan → feature tests. Use this before opening a PR to catch everything CI will catch. Defined in `composer.json → scripts.ci:check`.

---

### Running Tests Locally

```bash
# Feature and unit tests
php artisan test

# Browser tests (requires a running server)
php artisan serve &
./vendor/bin/pest tests/Browser/
```

CI runs both automatically on every push.

---

## What Not to Commit

- `.env` files or any credentials
- `_ide_helper.php` / `_ide_helper_models.php` (add to `.gitignore` if not already)
- `node_modules/`, `vendor/` (managed by package managers)
- Compiled assets unless the project requires them