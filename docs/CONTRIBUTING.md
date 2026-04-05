# Contributing to WB Listora

Thank you for your interest in contributing to WB Listora. This guide will help you get started.

## Prerequisites

- PHP 7.4 or higher
- Node.js 18+ and npm
- Composer 2+
- WordPress 6.9+ development environment
- Git

## Getting Started

### 1. Clone and Install

```bash
git clone git@github.com:wbcomdesigns/wb-listora.git
cd wb-listora

# Install PHP dependencies
composer install

# Install JS dependencies and build
npm install
npm run build
```

### 2. Local Development

The plugin requires a working WordPress installation. Symlink or copy the plugin into `wp-content/plugins/`.

For local development with hot reloading:

```bash
npm run start
```

## Branch Workflow

1. **Create a feature branch** from `main`:
   ```bash
   git checkout -b feature/short-description
   ```

2. **Make your changes** following the code standards below.

3. **Push and create a Pull Request** against `main`:
   ```bash
   git push -u origin feature/short-description
   ```

4. **PR Review:** All pull requests require at least one approval before merging.

5. **Merge:** PRs are squash-merged into `main`.

### Branch Naming

| Type | Pattern | Example |
|---|---|---|
| Feature | `feature/description` | `feature/add-event-filters` |
| Bug fix | `fix/description` | `fix/search-pagination` |
| Refactor | `refactor/description` | `refactor/search-engine` |
| Docs | `docs/description` | `docs/api-reference` |

## Code Standards

### PHP

- **Standard:** WordPress Coding Standards (WPCS) via PHP_CodeSniffer
- **Static Analysis:** PHPStan Level 5
- **Namespace:** `WBListora\*`
- **File naming:** PSR-4 autoloading (`class-my-class.php` maps to `WBListora\My_Class`)

Run checks:

```bash
# PHPCS (coding standards)
vendor/bin/phpcs

# Auto-fix what can be fixed
vendor/bin/phpcbf

# PHPStan (static analysis)
vendor/bin/phpstan analyse
```

### JavaScript

- **Standard:** `@wordpress/scripts` ESLint config
- **Modules:** ES modules for Interactivity API blocks
- **Build:** webpack via `@wordpress/scripts`

### CSS

- **Tool:** PostCSS via `@wordpress/scripts`
- **Design tokens:** Use `--wcb-space-*`, `--wcb-radius-*` variables
- **Responsive:** Every layout must include `@media` breakpoints for mobile (<=640px)

## Running Tests

### PHP Lint (Syntax Check)

```bash
find includes -name "*.php" -exec php -l {} \;
```

### PHPCS (Coding Standards)

```bash
vendor/bin/phpcs
```

The configuration is in `phpcs.xml`. Key rules:
- WordPress coding standards with short array syntax allowed
- PSR-4 file naming (WordPress filename sniff excluded)
- Plugin-specific prefixes: `wb_listora`, `listora`, `WBListora`, `WB_LISTORA`
- Text domain: `wb-listora`

### PHPStan (Static Analysis)

```bash
vendor/bin/phpstan analyse
```

Configuration is in `phpstan.neon` with a baseline in `phpstan-baseline.neon`. Target level is 5.

### JavaScript Build

```bash
npm run build
```

Build must complete without errors before submitting a PR.

## CI Pipeline

Pull requests are automatically checked by GitHub Actions. The following checks must pass:

| Check | Command | Blocks PR? |
|---|---|---|
| PHP Lint | `php -l` on all PHP files | Yes |
| WPCS | `vendor/bin/phpcs` | Yes |
| PHPStan | `vendor/bin/phpstan analyse` | Yes |
| PHPUnit | `vendor/bin/phpunit` | Yes |
| JS Build | `npm run build` | Yes |

## Commit Message Format

Use conventional commit prefixes:

```
type: short description

Optional longer description.
```

### Types

| Type | When to Use |
|---|---|
| `feat` | New feature or capability |
| `fix` | Bug fix |
| `refactor` | Code restructuring without behavior change |
| `perf` | Performance improvement |
| `test` | Adding or updating tests |
| `docs` | Documentation changes |
| `chore` | Build, CI, or tooling changes |
| `style` | Code formatting (no logic change) |

### Examples

```
feat: add date range filter to event search
fix: prevent duplicate reviews from same user
refactor: extract geo query into dedicated class
docs: add REST API endpoint reference
```

## Plugin Architecture Quick Reference

- **Entry:** `wb-listora.php` -- constants, autoloader, requirement checks
- **Core:** `includes/core/` -- post types, taxonomies, field system, meta handler
- **REST:** `includes/rest/` -- 9 controllers, 36+ endpoints under `listora/v1`
- **Search:** `includes/search/` -- fulltext, facets, geo (Haversine), denormalized index
- **Blocks:** `blocks/` -- 11 Interactivity API blocks sharing `listora/directory` store
- **Database:** 10 custom tables prefixed `listora_` (InnoDB)
- **Admin:** `includes/admin/` -- settings, setup wizard, type editor
- **Workflow:** `includes/workflow/` -- cron jobs, notifications, status management

See `docs/ARCHITECTURE.md` for the full architecture reference.

## Key Conventions

1. **REST-first:** All data operations go through REST API endpoints, never PHP form POST with `wp_redirect`.
2. **Transactions:** Multi-step writes (submission, migration) are wrapped in database transactions.
3. **Pagination:** All paginated endpoints return `has_more` computed as `(offset + count) < total`.
4. **Escaping:** All output is escaped. All input is sanitized. All database queries use `$wpdb->prepare()`.
5. **Internationalization:** All user-facing strings use `__()` or `esc_html__()` with the `wb-listora` text domain.
6. **Capabilities:** Custom capabilities are used for all permission checks (e.g., `submit_listora_listing`, `moderate_listora_reviews`).
7. **Caching:** Object cache is used for expensive queries with appropriate invalidation on writes.
8. **Hooks:** Actions and filters are provided at key extension points for Pro addons (see `ARCHITECTURE.md`).

## Reporting Issues

- Use GitHub Issues for bug reports and feature requests
- Include WordPress version, PHP version, and steps to reproduce
- Check existing issues before creating duplicates
