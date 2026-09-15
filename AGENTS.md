# eloquage/vector

In-process vector math for PHP: cosine, dot, L2, top-k, and batch similarity.

- Composer: `eloquage/vector`
- Entrypoint: `Eloquage\Vector\Vector`
- This package is framework-agnostic. The Laravel app at the monorepo root is a local test bench only.

## Layout

- `src/` — public PHP API (source of truth)
- `native/` — optional TypePHP AOT sources (empty today)
- `tests/` — Pest 5
- `TYPEPHP.md` — extension build contract
- `project.yml.example` — TypePHP project config (copy to gitignored `project.yml`)

## Setup and commands

```bash
composer install
composer test
composer format
vendor/bin/pest --coverage --min=90
```

Run commands from `packages/vector`. Keep coverage at or above 90% for `src/`.

## Conventions

- No Illuminate / Laravel service providers.
- Always ship a pure-PHP fallback. Never `require` `swoole/typephp`.
- Consumers: PHP 8.3+. Package CI: PHP 8.4. TypePHP compile: PHP 8.5 syntax.

## TypePHP

Extension mode only (`mode: ext`). Build in Docker, not on the host:

```bash
# harness (default: ghcr.io/eloquage/typephp-builder)
docker/typephp/build-package.sh vector

# this repo
docker run --rm -v "$PWD":/src -w /src \
  "${ELOQUAGE_TYPEPHP_IMAGE:-ghcr.io/eloquage/typephp-builder:latest}" \
  sh -c 'test -f project.yml || cp project.yml.example project.yml; tpc.php project.yml'
```

See `TYPEPHP.md`. Linux containers only for the shared builder.

## Debugging

- Reproduce behavior through `Eloquage\Vector\Vector`; do not call private
  helpers from tests or consumers.
- Start with `composer test`, then run the smallest failing Pest filter. Use
  `php -l src/Vector.php` for syntax-only diagnosis.
- The root Laravel application is only a harness. Verify its `/` panel with
  `php artisan test --filter=EloquageWelcomeTest` from the repository root.

## Security and scope

- Keep this package free of HTTP, persistence, database, Illuminate, and other
  application integrations.
- Do not log or commit input data that may contain sensitive embeddings.
- Do not add Composer dependencies for TypePHP; use the documented Docker
  builder only.

## Harness demo

Public behavior must be exercisable from the laravel-x welcome page (`/` → `resources/views/welcome.blade.php`) with a Feature test.

## Document ownership

- README — install and usage for consumers
- This file — agent setup, development, debugging, and security context
- TYPEPHP.md — AOT, Docker, and release contract
