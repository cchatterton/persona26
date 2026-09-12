=== Persona26 ===
Contributors:
Tags: personalisation, analytics, audience, profiles, gravity-forms
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.7.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visitor profiles, engagement dimensions and content personalisation with optional Independent Analytics and Gravity Forms integrations.

== Description ==

Persona26 is authored and maintained by Techn (https://techn.com.au).
Configure persona dimensions, assign content targets, inspect engagement matrices,
and personalise content from a visitor's browser profile.

Independent Analytics provides visitor activity reporting when active. Gravity
Forms can supply dynamic choices and update dimension counters through feeds.
Both integrations are optional; install and configure them separately.

The settings page uses Techn's Author Branded navy-and-orange interface. Embedded
Gravity Forms settings use Extension Branded mode and retain Gravity Forms UI.

== Installation ==

1. Upload persona26.zip through Plugins > Add New > Upload Plugin.
2. Activate Persona26.
3. Open Persona26, choose dimension post types and content profiling scope, then save settings.
4. Set Persona Targets on individual content items.
5. When applicable, activate Independent Analytics or configure Gravity Forms feeds.

Updates are delivered through the native WordPress Plugins screen. Use the
plugin's Check for updates link, then update now when a release is available.

== Frequently Asked Questions ==

= What data is stored? =

First-party localStorage and cookie keys p26_id and p26_profile store a random
visitor identifier and persona counters. Identity and profile cookies request a
20-year maximum age; browsers may cap it. localStorage persists until cleared.
A p26_pending_profile cookie can carry Gravity Forms profile changes for up to
10 minutes. Mapping rows link the identifier to Independent Analytics visitor IDs
in the current site's independent_analytics_p26 table. Settings use p26_settings;
content targets use p26_alignment and scalar dimension metadata.

There is no automatic database retention purge. Deactivation preserves mappings,
settings and content metadata. Browser profiles may be cleared with ?persona=clear;
this does not erase the visitor identifier or historical database mappings.

= Does personalisation protect restricted content? =

No. Persona values are browser-controlled and personalise presentation only.
Never use them for access control. Configure page caches to avoid sharing HTML
that contains visitor-specific body classes or preselected form values.

= Does the plugin provide a consent system? =

No. Identity and profile storage run on front-end visits while the plugin is active.
Site owners must configure their privacy notice, retention and consent integration
for their deployment. The plugin does not send visitor profiles to Techn or GitHub.

= Can I delete a dimension? =

Clear a dimension to retire it while retaining its position. Later dimension
keys remain stable so existing content targets and browser profiles do not shift.
Reusing a cleared position for a different concept requires reviewing old targets
and browser profiles first.

= Does network activation work? =

Each visited site initialises its own mapping table and retains independent
settings. New sites initialise when the plugin first runs in their context.

= Can I migrate from the original Personas plugin? =

With Personas active, configure destination dimensions in Dimensions, then open
Migrate Wizard to choose and save Legacy destination mapping. Run a simulation, resolve its
blockers and review affected records before committing. Take a full site backup
and test on staging first. Post IDs stay fixed; both legacy metadata layouts
are merged into Persona26 targets. Original relationship fields are retained.
Translated relationship fields store IDs, with ACF support; dimension mirror
fields store titles. Compatibility fields stay current when targets change.

After checking migrated pages, deactivate Personas through WordPress. The wizard
tab remains available while a committed recovery snapshot exists. Rollback checks for edits
made after commit and refuses to overwrite them. Keep Persona26 active while
translated relationships are used. Mapped dimension post types cannot be changed
while migration compatibility is active.

The site-local p26_legacy_journal option stores before/after records (including
changed option values), non-autoloaded, for recovery. It is retained until a
new simulation replaces a rolled-back preview. A committed snapshot is not
automatically discarded. Deactivation retains the journal and compatibility data.

The wizard handles posts, supported block JSON, templates, reusable blocks,
post metadata, structured options, term metadata and comment metadata. Unknown
reference contexts, invalid targets, destination slug collisions, serialized
objects and hard-coded consumers in active components block commit. Original
plugin options, transient caches, ACF health diagnostics and visitor history
are retained rather than rewritten. Theme/plugin files, external systems,
legacy visitor-history files and URL redirects are not converted.

Interactive migrations require InnoDB. Each scanned result set must be below
10,000 rows; snapshots are limited to 4 MiB. Active-code inspection is bounded
at 15,000 PHP/JS/JSON files and 100 MiB. Larger sites stop without changing
content and need a reviewed batch migration. The snapshot is a migration undo
record, not a replacement for a complete site backup.

== External services ==

GitHub hosts update metadata and release downloads. During WordPress update
checks, the server requests the repository's update.json from
raw.githubusercontent.com, falling back to github.com release redirects and then
api.github.com only if necessary. Requests include the server IP address, the
requested repository URL and a plugin/version User-Agent. WordPress downloads
the release ZIP when an administrator installs an update. No visitor IDs,
profiles, form values or site credentials are sent by this plugin to GitHub.

GitHub terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
GitHub privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

Independent Analytics and Gravity Forms are separately installed plugins, not
bundled external services. This plugin reads local analytics data and locally
submitted form values. Their own configuration and privacy documentation govern
any services those plugins use.

== Changelog ==

= 0.7.1 =
* Moved mapping, saving and recovery into Migrate Wizard; corrected field layout.
* Fixed simulation and rollback handling of NULL database metadata.
* Saved mapping independently and kept migration actions on the wizard tab.

= 0.7.0 =
* Added conditional Personas migration wizard, external destination mapping, previews, atomic commits and protected rollback.
* Preserved legacy tag IDs through compatibility fields and translated structured references safely.
* Added migration conflict, source-code, database-engine and capacity checks.

= 0.6.2 =
* Matched Content Planner typography, full-width header, right-aligned pill badges and rounded tabs with top selection accents.
* Removed TN/Techn from interface naming while retaining Techn authorship.

= 0.6.1 =
* Applied Techn branding, version watermark, accessible navigation and responsive settings panels.
* Added manifest-first native WordPress updates, safe fallback/backoff and manual-check feedback.
* Preserved visitor data on deactivation and dimension positions when clearing rows.
* Moved browser scripts into versioned WordPress assets and hardened cookie handling.
* Fixed stale personalisation rules and metadata after dimension deletion.
* Added GPL licensing, service disclosures and repeatable release validation.

= 0.6 =
* Combined scalar dimension metadata with the Gravity Forms feed and dynamic choice integration.

== Upgrade Notice ==

= 0.6.1 =
Existing settings, alignment keys and browser storage names are retained. Clear
replaces row removal to preserve dimension positions. Deactivation now keeps data.
