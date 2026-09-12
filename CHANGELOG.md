## 0.7.2 - 2026-09-12

- Removed the fixed 4 MiB migration snapshot ceiling.
- Store recovery snapshots as compressed, non-autoloaded database chunks with integrity verification.
- Preserve atomic commit and rollback when a snapshot chunk cannot be written or read.
- Continue reading existing migration snapshots and check available PHP memory before encoding or restoring larger ones.

## 0.7.1 - 2026-09-12

- Moved legacy destination mapping and its independent save action into Migrate Wizard.
- Kept recovery on the wizard tab after Personas deactivation while a committed snapshot exists.
- Fixed mapping field alignment with paired labels and responsive columns.
- Fixed migration simulation on SQL NULL metadata, preserving those values through commit and rollback.
- Kept migration actions on the wizard tab and invalidated stale previews after mapping changes.

## 0.7.0 - 2026-09-12

- Added Migrate Wizard when the original Personas plugin is active on the current site, including network activation.
- Added legacy destination mappings in Dimensions, outside the wizard.
- Added read-only migration previews, explicit atomic commits, retained recovery snapshots and conflict-aware rollback after legacy deactivation.
- Preserved post IDs and original tags; merged both legacy relationship layouts into Persona26 targets and maintained ID-based compatibility fields for existing block queries and ACF.
- Added context-aware block, template, navigation and serialized metadata/options reference conversion. Unknown references and hard-coded active-code consumers block commit.
- Added migration integration coverage, SQL-failure recovery and responsive keyboard-accessible wizard screens.

# Changelog

All notable changes to Persona26 are recorded here.

## 0.6.2 - 2026-09-12

- Matched the Content Planner reference with a full-width layout, compact header and matching title, tagline and badge typography.
- Removed TN/Techn from product labels, menus and the header eyebrow while retaining Techn author metadata.
- Right-aligned translucent pill badges alongside the tagline on desktop, with responsive wrapping on smaller screens.
- Matched rounded-top tabs, inset orange top selection accents and connected white working panels; retained keyboard navigation and visible focus.

## 0.6.1 - 2026-09-12

- Applied TN Persona26 naming and Techn's navy-and-orange branding to the standalone settings screen, including a live version watermark, responsive panels and native notice placement.
- Added keyboard-operable tabs, labelled dimension controls, readable heatmaps, save feedback and a compact developer reference; kept Gravity Forms screens extension branded.
- Replaced API-first updates with repository manifest, public release redirect and API fallback in that order, with validated package URLs, separate failure backoff and manual-check feedback.
- Preserved visitor history on deactivation, initialised site schemas safely and prevented dimension clearing from shifting later stored keys.
- Moved identity/profile scripts into versioned WordPress assets, hardened cookie input and retained all configured dimensions when rebuilding profiles.
- Fixed stale personalisation rules and scalar metadata after dimension deletion; restricted visitor reporting to published content in the configured scope.
- Added GPL licensing, a WordPress readme with storage/service disclosures, regression tests and packaging validation.

## 0.6 - 2026-08-10

- Reconciled the production 0.5 Gravity Forms integration with the GitHub 0.3 metadata release.
- Added Persona26 Gravity Forms feeds for incrementing or replacing profile dimension values.
- Added dynamic Persona26 choices and profile defaults for Gravity Forms radio and checkbox fields, including advertised context-name CSS tokens such as `p26-dimension-audience`.
- Preserved exact registered post-type keys throughout Gravity Forms, profiling, personalisation, and queryable metadata.
- Retained the automatic scalar-meta backfill, accessible Persona Targets editor, and compliant native GitHub updater from 0.3.

## 0.3 - 2026-08-10

- Added exact, queryable scalar post meta for configured persona dimensions while preserving `p26_alignment`.
- Added automatic backfill and synchronisation for existing alignment data and renamed dimension values.
- Changed Persona Targets labels to show exact registered post-type keys and canonical value titles.
- Improved Persona Targets keyboard access and moved its inline assets into the plugin asset files.
- Updated the GitHub release updater to the current native WordPress update standard.
- Added REST registration for Persona26-managed dimension mirror metadata.

## 0.2 - 2026-06-14

- Added compliant plugin metadata, version constant, and GitHub repository links.
- Added GitHub release update support for native WordPress plugin updates.
- Added stale update cleanup and forced update-check cache bypass handling.
- Moved Persona26 admin JavaScript and CSS into dedicated asset files.
- Added release ZIP build script and root upload ZIP workflow.
- Improved selected admin callbacks and cookie input handling.

## 0.1 - 2026-06-14

- Initial Persona26 plugin build.
