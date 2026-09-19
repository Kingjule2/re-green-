---
paths:
  - package.json
  - composer.json
  - phpunit.xml
---

# General

## Vite 8 / rolldown needs Node >= 20.19 or the build breaks silently
Vite 8 uses rolldown, whose native binary `@rolldown/binding-win32-x64-msvc` declares `engines.node: ^20.19.0 || >=22.12.0`. On older Node (e.g. 20.18.3) npm silently SKIPS it because it is an optional dependency, and `npm run build` / `npm run dev` then dies with "Cannot find module '@rolldown/binding-win32-x64-msvc'". The lockfile looks correct, so this is easy to misdiagnose.

Fix: run Node >= 20.19 (22 LTS or newer preferred). Stopgap only: `npm install @rolldown/binding-win32-x64-msvc --no-save --force`, which any later `npm ci` will undo.

Also note: a bad global registry in ~/.npmrc (`https://registry.npmjs` instead of `https://registry.npmjs.org`) breaks every npm/npx call in this project with ENOTFOUND.

## Run artisan and PHPUnit with PHP 8.3, not the 8.2 on PATH
The default `php` on PATH is 8.2.32, but this project requires PHP ^8.3 and the dev deps (PHPUnit 12 / sebastian/environment use typed class constants) fail to parse on 8.2 with "unexpected identifier STDIN". Use the Laragon 8.3 binary for any artisan/test/pint run, e.g. `& 'D:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe' artisan test`. The 8.3 build has pdo_sqlite, pdo_pgsql, gd, and fileinfo enabled. App code itself stays 8.2-compatible.

## phpunit.xml must force its env values
Every <env> in phpunit.xml carries force="true". Without it, PHPUnit does not override env vars that already exist in the shell, and this machine exports APP_ENV=local / DB_CONNECTION=pgsql / DB_DATABASE=regreen. A plain `php artisan test` then boots the local Postgres database and RefreshDatabase runs migrate:fresh on it, wiping the dev data. Keep force="true" on every entry, and keep tests on sqlite :memory:.
