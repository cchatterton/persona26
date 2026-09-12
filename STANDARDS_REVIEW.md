# Persona26 0.7.4 saved configuration migration

Added report-shaped JSON, serialized ACF, AIOSEO, cache post-type map and Content
Planner adapters. Missing IDs are explicitly skipped and reported as requested.
Preserved valid selections, taxonomy identifiers, source metadata and rollback bytes.
Content Planner option renames protect existing destinations and clear both caches.
Reference conversion is isolated in migration-references.php for maintainability.

Migration and format integration suites cover preview, commit, exact rollback,
collisions, large snapshots and invalid data. Production site data remains untouched.

---

# Persona26 0.7.3 actionable migration reports

Corrected false profiling blockers for empty selections, revisions and orphaned
metadata. Required content types are grouped with a separate, explicit scope action
in the wizard. Historical metadata stays intact. Target diagnostics now name missing
or mismatched IDs and accept IDs already in the configured destination type.

Added source-verified Duplicate Post/Relevanssi option adapters; WordPress rewrite
rules are regenerated rather than rewritten as arbitrary serialized configuration.
Source-plugin deactivation triggers a per-site route refresh on the next request.
Remaining unknown references stay blocking and expose bounded diagnostic excerpts.
Removed source-code scanning from this saved-configuration migration. Source-file
text no longer blocks commit. Unsupported stored references still need their saved
values examined; this release does not claim all reported database formats are converted.

Migration regression tests passed, including retained revision/orphan metadata,
grouped scope repair, already-mapped IDs, option conversion and route refresh.
A component-source fixture confirms commit/rollback preserve IDs and leave files unchanged.

---

# Persona26 0.7.2 large recovery snapshots

Replaced the fixed 4 MiB recovery limit with compressed, non-autoloaded database
chunks. Root manifest and chunks are read in one statement for a consistent view;
all replacements stay inside the existing InnoDB migration transaction. Length and
SHA-256 validation protect recovery. Existing journals remain readable. A lightweight
status read avoids loading the full snapshot just to determine tab visibility.

Regression coverage includes a snapshot over 4 MiB, exact restoration of a large
unrelated value, missing/corrupt chunks, injected chunk-write failure and compatibility
with pre-chunked journals. The migration suite passed under a 256 MiB PHP limit.
This change removes the reported storage ceiling; migrations still execute in one
request and remain subject to server memory/time and existing row-count limits.

---

# Persona26 0.7.1 migration corrections

The user's correction moves migration-specific mapping, saving and recovery into
Migrate Wizard. Destination post types and the general audience model remain configured
separately. The wizard remains available after legacy deactivation while its committed
recovery snapshot exists. Server-rendered state retains the selected tab after actions.

The reported TypeError was reproduced using SQL NULL metadata alongside legacy tags.
Nullable reference scanning and decoding now preserve these values through simulation,
commit and byte-exact rollback. Mapping saves have their own nonce/capability-gated
action, validate configured destinations, preserve general settings and clear stale
previews. Committed mappings remain protected by the existing transaction lock.

Migration integration tests, PHP syntax and package validation passed. Browser checks
cover independent mapping save, tab retention, commit/recovery, paired field geometry,
keyboard access and responsive axe scans at 1440, 768, 390 and 320 pixels.

Released [0.7.1](https://github.com/cchatterton/persona26/releases/tag/v0.7.1)
through [PR #6](https://github.com/cchatterton/persona26/pull/6). The published tag
matches source commit `45c0d63fed23f37468f76bd12b7abc2fea5ad090`. The 56,755-byte ZIP
matches the committed artifact; SHA-256:
`4ccab11ee23b804d39cb1313884592c1de8eef9b9e1052a642eb32c5f42c74dd`.
Native WordPress update from 0.7.0 to 0.7.1 passed; installed files match the release.

---

# Persona26 0.7.0 migration wizard

The original Personas 3.1 source and both of its relationship naming layouts were
reviewed. The migration keeps IDs stable and separates serialized-ID compatibility
fields from Persona26’s scalar title mirrors. Mapping is configured in Dimensions;
the conditional wizard owns simulation, explicit commit and recovery only.

See [MIGRATION.md](MIGRATION.md) for coverage, transactional design, safety limits,
validation and remaining integration limits. Checks passed for both metadata layouts,
ACF reads, supported stored references, exact rollback, injected SQL failure, edit
conflicts, nonce/capability enforcement, native browser deactivation/recovery and
multisite isolation. PHP/JS syntax, browser-profile regressions, the existing 20
WordPress integration assertions and exact package validation also passed.

The actual block-plugin source and site database were not supplied. Unrecognised
references and detected hard-coded consumers fail closed at simulation. No production
site data was changed. Branding remains the user-approved 0.6.2 treatment.

Released [0.7.0](https://github.com/cchatterton/persona26/releases/tag/v0.7.0)
through [PR #5](https://github.com/cchatterton/persona26/pull/5). Source tag:
`f628e03a70cfa6ccb971ed25cfa8cf6c1549aca9`. The latest public release and expected
56,147-byte ZIP were verified. SHA-256:
`034c5b2bd8d7b308d2d6432b563ecd2273807ae24dd0e8409200cb7669f28bc7`.

Native WordPress upgrade from 0.6.2 to 0.7.0 passed and every installed file matches
the public asset. The subsequent check reports up to date. A committed migration
snapshot survived the upgrade; serialized-ID queries, ACF relationship reads and
rollback all passed with the original Personas plugin inactive.

---

# Persona26 0.6.2 branding correction

The user-approved Content Planner screenshot and source styles override the generic TN naming treatment from the earlier standards review. Techn remains the declared author; the product interface now says Persona26.

- Reused the Content Planner header layout, spacing, title (1.75rem), tagline (0.9375rem), eyebrow (0.75rem), pill typography (0.78rem), and version positioning.
- Removed the 80rem page cap. Capability pills align right alongside the tagline on desktop and wrap at narrow widths.
- Matched rounded tab tops, the inset orange top selection accent, and connected white task panels. Keyboard focus remains separate and visible.
- Verified rendered geometry, product/menu/eyebrow text, keyboard navigation and no page overflow at 1960, 1440, 1024, 768, 390 and 320 CSS-pixel viewport widths.
- axe WCAG 2 A/AA and 2.1 AA scans returned no violations at those widths. PHP lint and exact package/version validation passed.
- Author Branded styling remains on the standalone settings page; Gravity Forms retains Extension Branded native surfaces. This is a presentation update; the prior integration-test limitations below still apply.

Released [0.6.2](https://github.com/cchatterton/persona26/releases/tag/v0.6.2) through [PR #4](https://github.com/cchatterton/persona26/pull/4). Tag and source commit: `989aa5175abfebaa3bd85490b91327224d94200f`. The public latest release and expected asset were verified. SHA-256: `53153218f68bc1f68f3c15e357dbc61afb4c86fd0002b07bbbb4b73c4ec82972`.

Native WordPress upgrade from 0.6.1 to 0.6.2 passed. The installed plugin remains active, uses the Persona26 display name, and every installed file matches the published ZIP and committed package. The subsequent manual check reports the installed version is current. No production site was changed.

---

# TN Persona26 0.6.1 standards review

Reviewed against `cchatterton/codex-standards` commit `156c5e1821663593c89fa4eda64d851a25b5280c` on 12 September 2026:

- Codex development standards
- WordPress plugin build standard
- Branding and UX standard
- WordPress plugin GitHub update standard

## Findings and changes

| Area | Previous behaviour | 0.6.1 |
| --- | --- | --- |
| Identity and branding | Generic Persona26 settings, no version header | TN Persona26; Author Branded Techn navy/orange header, live version watermark, task panels and native notice placement |
| Embedded integrations | Gravity Forms native feed framework | Extension Branded; native framework retained, TN author identity consistent |
| Accessibility | Hash links as tabs, unlabelled row controls, low-contrast heatmap values | Button tabs with arrow/Home/End navigation, labelled controls, predictable number contrast, scrollable tables and focus styles |
| Feedback | Save and manual update checks returned without confirmation | Native success/failure notices and explicit actions |
| Update delivery | API first, asset HEAD probe, no failure backoff | Manifest first, public redirect second, API last; fixed repository package URLs, separate backoff, capability/nonce controls and reinjection after manual refresh |
| Package | Plugin URI, no GPL declaration/LICENSE/readme.txt | Required header and documents, no Visit plugin site link, synchronised 0.6.1 version and package verification |
| Data safety | Deactivation dropped visitor mappings; removing rows reindexed dimension keys | Deactivation retains data; clear controls retain dimension positions; per-site lazy schema initialisation |
| Front end | Large inline scripts; malformed cookie handling; profile dimensions could disappear from the current summary | Versioned WordPress assets, bounded identity validation, robust cookie decoding, configured dimension order retained |
| Derived data | Deleted dimensions could leave cached CSS and scalar metadata | Rebuild after WordPress has cleared deletion caches; published/scope-filtered visitor reports |

Existing plugin slug, text domain, option names, identity/profile storage keys, alignment format and scalar metadata names are retained. No new production dependencies, telemetry, or services were introduced.

## Validation

- All distributed PHP files pass PHP lint (PHP 8.5.7).
- All JavaScript assets pass Node syntax checking; dependency-free browser-profile tests pass.
- 20 WordPress integration assertions cover alignment metadata, title changes/deletion, cookie validation, data retention, missing analytics, updater lookup order/cache/backoff, trusted packages and permissions.
- WordPress 7.1 single-site activation and admin/browser checks passed.
- Independent Analytics 2.15.5 activation, schema detection, visitor lookup/mapping and visitor matrix SQL passed. Missing-integration UI was also checked.
- Network activation and a newly created second site were checked for separate mapping tables and settings.
- Desktop and responsive checks cover 1440, 768, 390 and 320 CSS-pixel widths. Tables scroll inside their own regions; there is no page-level horizontal overflow.
- Keyboard navigation, adding/clearing dimensions, save confirmation, debug display and profile-clear redirect were checked in Chrome. No JavaScript page errors were observed.
- axe-core WCAG 2 A/AA and 2.1 AA scans of the plugin settings surface and content matrix returned no violations. These scans do not replace manual assistive-technology evaluation.
- Build validation checks matching version metadata, required licence/readme files, ZIP layout, exact source/archive equality and identical root/dist artifacts.

## Release verification

Published [TN Persona26 0.6.1](https://github.com/cchatterton/persona26/releases/tag/v0.6.1) after merging [PR #3](https://github.com/cchatterton/persona26/pull/3).

- Release tag points to source merge commit `279fefcb3ecacf3f863d11bce92194a08a2610fd`.
- Public `update.json`, latest-release API, release tag and expected `persona26.zip` asset were verified.
- Downloaded asset matches the committed ZIP; SHA-256: `4ba5f7bfeb0b7b59f8e5caba6160646a0348af53bcf1a0819794b951d605477d`.
- WordPress offered the update from the original 0.6 package. Clicking the native **update now** action successfully installed 0.6.1. Every installed file matched the published archive.
- Both test visitor mapping rows survived the upgrade. The plugin remained active, displayed TN Persona26, and exposed **GitHub** and **Check for updates** without **Visit plugin site**.
- The new manual check returned a native up-to-date notice. A fresh live lookup retrieved version, release notes and the trusted download URL with exactly one manifest request and no API fallback.
- Clearing a middle dimension retained later indexes after saving; an invalid settings nonce returned HTTP 403.

No production WordPress site was changed.

## Limitations and deployment notes

- A licensed Gravity Forms installation was not available. Profile merging, replace/multiselect semantics and browser behaviour are regression-tested, but actual Gravity Forms feed administration and live form submissions need a staging smoke test.
- PHP 8.1 and WordPress 6.0 remain the declared minimums; execution testing used PHP 8.5.7 and WordPress 7.1. Production theme/cache combinations and assistive-technology screen readers were not available.
- No WordPress.org contributor username was verified. `Contributors` is deliberately empty rather than inventing an account; confirm a real contributor and run the official readme validator before any WordPress.org submission. This release uses GitHub distribution.
- Existing long-lived first-party storage remains unchanged. `readme.txt` documents storage, retention, optional integrations, GitHub requests and page-cache considerations. This update does not add a consent platform or automatic data purge.
- Clearing a dimension retains its stored position. Reusing that position for a different concept still requires reviewing historical targets/profiles.
