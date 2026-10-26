# Tati Pilates Plugin

Private WordPress plugin to manage classes, students, bookings, payments, makeup sessions, attendance, and the student portal for Tati Pilates.

This repository contains only the plugin code. It does not include credentials, sensitive documentation, local data, or generated ZIP packages.

## Main features

- Management of schedules, spots, and class formats.
- Student registration linked to native WordPress users.
- Weekly and individual plans.
- Bookings from wp-admin and from the `/mi-pilates` portal.
- Booking cancellation, absence reporting, and makeup sessions.
- Private weekly agenda and agenda copy for WhatsApp.
- Simple monthly payments, without amounts or currency.
- Attendance, absences, and automatic makeup-session generation.
- Personal achievements, medical data, allergies, birthdays, and start date.
- Internal notifications for admin and students.
- Lightweight PWA for the student portal.
- Test data for local environments.
- Restricted roles `tp_admin_pilates` and `tp_tatiana`; Tatiana gets access to
  medical data without receiving native WordPress privileges.

## Structure

```text
tatipilates.php
uninstall.php
includes/
admin/
public/
assets/
docs/
tests/
.github/workflows/
updates.json
pre-release-check.sh
```

Local files excluded from the repo:

- `CONTEXTO.md`
- `dev/`
- `release/`
- `guia-rapida-tatiana.*`
- `checklist-pruebas-tati-pilates.*`
- `mipilates.zip`
- logs and Mac files

## Local development

In LocalWP, the plugin can be mounted with a symlink to the project folder:

```zsh
ln -s "/Users/vicunav/Documents/Codex/Mi Pilates Admin" "/path/to/wordpress/wp-content/plugins/tatipilates"
```

Then activate the plugin from wp-admin.

The student portal lives at:

```text
/mi-pilates
```

## Pre-release

Before generating a ZIP for live, run:

```zsh
./pre-release-check.sh
```

To run it without interactive prompts:

```zsh
./pre-release-check.sh --yes
```

The script checks:

- PHP syntax using LocalWP's PHP.
- PHP files with a UTF-8 BOM.
- Forgotten debug code.
- That `release/tatipilates/` does not exist.
- Plugin version in `tatipilates.php`.
- WordPress version used for the local regression run.

Confirmed compatibility is saved to `release-metadata.json`, and the
workflows copy it automatically into the published channel's manifest.

If everything passes, it generates a ZIP at:

```text
release/tatipilates-VERSION-YYYYMMDD-HHMM.zip
```

The ZIP includes:

- `tatipilates.php`
- `uninstall.php`
- `includes/`
- `admin/`
- `public/`
- `assets/`

The ZIP does not include local documentation, credentials, `dev/`, `release/`, dotfiles, or auxiliary files.

## Recommended workflow

1. Create or work on a feature locally.
2. Test in LocalWP.
3. Review changes:

```zsh
git status
git diff
```

4. Registrar el cambio en `docs/releases/changelog.json`; luego regenerar y verificar `CHANGELOG.md`:

```zsh
php scripts/generate-changelog.php
php scripts/generate-changelog.php --check
```
5. If the change is going to be released, bump the version in `tatipilates.php`.
6. Run the pre-release check:

```zsh
./pre-release-check.sh --yes
```

7. If the checks pass, commit:

```zsh
git add .
git commit -m "type: short description"
git push
```

Message examples:

```text
feat: add student notification preferences
fix: prevent duplicate recovery booking
chore: update release checks
```

8. Test in LocalWP (`devtatipilates.local`, linked to this repo via a directory
   junction/symlink into `wp-content/plugins/tatipilates`), especially:

- Login and password reset at `/mi-pilates`.
- Student dashboard.
- Bookings, cancellations, and makeup sessions.
- Weekly agenda.
- Payments, attendance, and notifications.
- PWA/offline.
- Admin Pilates with limited operational permissions.
- Tatiana with the same operational permissions plus medical access.

9. If local testing looks correct, promote straight to stable (see below).
10. After the live deploy, create a version tag:

```zsh
git tag v1.0.1
git push origin v1.0.1
```

11. If a bug shows up in local testing or live, fix it locally, generate a new ZIP, and test again. Do not edit the plugin directly on live without replicating the change in Git.

## Important rules

- Do not upload credentials or `CONTEXTO.md` to the repo.
- Do not upload generated ZIPs.
- Do not upload `dev/`.
- Do not edit the plugin directly on live without replicating the change in this repo.
- If the database structure changes, update activation/migration and test locally before live.
- If security or permissions change, test with administrator, Tatiana, Admin
  Pilates, and student accounts.
- Deactivating the plugin preserves data. Deleting it also preserves data by default; data is only deleted if explicitly enabled in the Danger Zone.
- Before debugging risky changes on live, download a JSON backup from `Tati Pilates > Settings`.

## Deploy to live

There is no staging environment anymore (since 2026-10-23). The repo is
linked directly into the LocalWP site, so local testing is the validation
step before promoting to live:

```text
LocalWP (devtatipilates.local, linked via junction) -> pre-release -> commit/push -> promote stable -> live
```

The `staging` update channel and the `Publish staging release` workflow still
exist for an occasional one-off release candidate, but they are not a
required step.

## Private updater

Since `1.1.0`, the plugin includes a private channel-based updater:

- `staging`: for the test site.
- `stable`: for the live site.

The first version that includes the updater must be installed manually with a regular ZIP. After that bootstrap, WordPress can detect private updates from:

```text
Plugins > Tati Pilates > Update now
```

Configuration on each site:

```text
Tati Pilates > Settings > Private updates
```

On staging:

```text
Channel: staging
```

On live:

```text
Channel: stable
```

The GitHub token is stored only in WordPress. It is never uploaded to the repo.

Recommended flow:

```text
Local changes
-> test in devtatipilates.local
-> commit/push main
-> GitHub Action: Promote stable release 1.1.1
-> live updates from WordPress
```

Full documentation:

```text
docs/updater-workflow.md
```

## Operational guides

- `docs/README.md`: index of technical and operational documentation.
- `docs/architecture.md`: map of classes, layers, and responsibilities.
- `docs/codex-workflow-guide.md`: how to open new chats, request changes, version, and document.
- `docs/codex-workflow-guide.pdf`: printable/quick version of the Codex workflow guide.
- `docs/data-model.md`: custom tables, relationships, indexes, and data rules.
- `docs/local-development.md`: LocalWP, symlink, local PHP, demo data, and local workflow.
- `docs/product-decisions.md`: functional decisions that must be respected.
- `docs/release-runbook.md`: steps to test locally and promote directly to live.
- `docs/roadmap.md`: future improvements and technical debt.
- `docs/security-checklist.md`: security checklist for sensitive changes.
- `docs/testing-checklist.md`: tests to run before publishing a version.
- `docs/troubleshooting.md`: common issues and diagnostics.
- `docs/updater-workflow.md`: the `staging`/`stable` update channel mechanism (staging is optional, not part of the normal flow).

## Security

The plugin uses:

- Nonces on sensitive actions.
- `tp_manage_pilates` capability for administration.
- Ownership checks for student actions.
- Sanitization on save and escaping on output.
- `$wpdb->prepare()` and typed `$wpdb` methods.
- Strict `false ===` checks on database operations.
- Internal logging controlled by `WP_DEBUG`.
- Rate limiting on portal login.
- Generic messages on password reset to prevent user enumeration.
- Private updater with no tokens hardcoded in the code.

## Repository

Private repository:

```text
https://github.com/mariovicunadev/tatipilates-plugin
```

## Base prompt for new chats

Use this prompt when opening a new Codex conversation for future changes:

```text
We're working in the local repo:
D:\Proyectos\Mi Pilates Admin

Private WordPress plugin: Tati Pilates.
Private GitHub repo: https://github.com/mariovicunadev/tatipilates-plugin
Main branch: main.

Read README.md, CHANGELOG.md, and docs/release-runbook.md before touching code.
If CONTEXTO.md exists locally, read it too, but do not upload it to the repo.

Workflow:
- Do not commit or push until I ask for it.
- Log relevant changes in CHANGELOG.md.
- Keep credentials, release/, dev/, CONTEXTO.md, and ZIPs out of the repo.
- Before closing a batch, run ./pre-release-check.ps1 -Yes and test in devtatipilates.local.
- To publish, promote directly to the stable channel (docs/release-runbook.md) — there is no staging step.

Goal of this chat:
[describe the change or bug here]
```
