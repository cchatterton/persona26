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


Release workflow: run validation, update the version/readmes/changelog and `update.json`, build the ZIP, commit and push, then publish `v<version>` with the matching `persona26.zip` asset. Do not publish the manifest without completing its release.

## Validation

- `php -l` on every distributed PHP file.
- `node --check` on each JavaScript asset.
- `node tests/browser-profile.cjs` for profile and Gravity Forms update logic.
- On a **disposable WordPress installation only**, `wp eval-file tests/wordpress.php` for persistence and update-provider checks. The test changes site settings and creates test data.
- `scripts/build-plugin-zip.sh` then `python3 scripts/validate-package.py`.

See `STANDARDS_REVIEW.md` for the review findings, verification and limitations.
