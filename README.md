# Persona26

Persona26 extends Independent Analytics with visitor identity mapping, engagement dimensions, profile data, and front-end personalisation support.

Version 0.6.2 applies the current Techn branding, WordPress plugin and GitHub update standards while retaining the existing alignment structure and Gravity Forms integration.

Branding mode: **Author Branded — Techn** on the standalone settings page; **Extension Branded — Gravity Forms** for embedded feeds. The interface follows the approved Content Planner reference: product-only labels, full-width layout, matching typography, right-aligned pill badges and rounded tabs with an orange top selection accent. Techn authorship remains in plugin metadata. Plugin slug, text domain and existing `p26_` storage keys remain unchanged.

## Release

Build the release ZIP with:

```bash
scripts/build-plugin-zip.sh
```

The ZIP is written to `dist/persona26.zip` and copied to `persona26.zip` in the repository root for direct WordPress upload.


Release workflow: run validation, update the version/readmes/changelog, build the ZIP, commit and push, then publish `v<version>` with the matching `persona26.zip` asset. Publish legacy update.json and controller catalogue metadata only after verifying the uploaded release asset.

## Validation

- `php -l` on every distributed PHP file.
- `node --check` on each JavaScript asset.
- `node tests/browser-profile.cjs` for profile and Gravity Forms update logic.
- On a **disposable WordPress installation only**, `wp eval-file tests/wordpress.php` for persistence and update-provider checks. The test changes site settings and creates test data.
- `scripts/build-plugin-zip.sh` then `python3 scripts/validate-package.py`.

See `STANDARDS_REVIEW.md` for the review findings, verification and limitations.

## Migration from Personas

Version 0.7.0 adds a conditional **Migrate Wizard** for active Personas installations.
Configure destination post types, dimensions and content scope in Dimensions first.
Choose and save **Legacy destination mapping** in Migrate Wizard. The wizard simulates without changing content, reports
record counts and blockers, then applies the reviewed plan only on explicit commit.
After checking migrated pages, switch off Personas. Recovery remains on the wizard tab while a committed snapshot exists.

See [migration design and limits](MIGRATION.md) before a staging migration.

## Controller migration — 0.7.5

Updates are now supplied by [TN Update Controller](https://github.com/cchatterton/tn-update-controller). The old independent updater has been removed. Plugin identity, feature settings and activation scope are unchanged. Install/activate/check links use local controller detection and never fetch release metadata while rendering. Legacy update guidance below or in historical notes is superseded by this controller integration.

Release order: build and validate the ZIP, publish its matching GitHub release asset, then publish verified controller catalogue metadata. Existing update.json endpoints are maintained only for older, not-yet-migrated installations, after asset verification.
