---
paths:
  - package.json
---

# General

## Vite 8 / rolldown needs Node >= 20.19 or the build breaks silently
Vite 8 uses rolldown, whose native binary `@rolldown/binding-win32-x64-msvc` declares `engines.node: ^20.19.0 || >=22.12.0`. On older Node (e.g. 20.18.3) npm silently SKIPS it because it is an optional dependency, and `npm run build` / `npm run dev` then dies with "Cannot find module '@rolldown/binding-win32-x64-msvc'". The lockfile looks correct, so this is easy to misdiagnose.

Fix: run Node >= 20.19 (22 LTS or newer preferred). Stopgap only: `npm install @rolldown/binding-win32-x64-msvc --no-save --force`, which any later `npm ci` will undo.

Also note: a bad global registry in ~/.npmrc (`https://registry.npmjs` instead of `https://registry.npmjs.org`) breaks every npm/npx call in this project with ENOTFOUND.
