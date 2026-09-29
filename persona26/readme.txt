=== Persona26 ===
Contributors:
Tags: personalisation, analytics, audience, profiles, gravity-forms
Requires at least: 7.0
Tested up to: 7.1.2
Stable tag: 0.7.7
Requires PHP: 7.4
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
plugin's controller setup/check link, then update now when a release is available.

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
blockers and review affected records before committing. Missing profiling scope
is grouped by content type; Include detected content types adds those types
without changing your dimensions, then requires a new simulation. Take a full site backup
and test on staging first. Post IDs stay fixed; both legacy metadata layouts
are merged into Persona26 targets. Original relationship fields are retained.
Translated relationship fields store IDs, with ACF support; dimension mirror
fields store titles. Compatibility fields stay current when targets change.

After checking migrated pages, deactivate Personas through WordPress. The wizard
tab remains available while a committed recovery snapshot exists. Rollback checks for edits
made after commit and refuses to overwrite them. Keep Persona26 active while
translated relationships are used. Mapped dimension post types cannot be changed
while migration compatibility is active.

The site-local p26_legacy_journal option stores a recovery manifest; compressed
before/after records (including changed option values) are kept in non-autoloaded
p26_legacy_snapshot_* options. Integrity is checked before use. It is retained until a
new simulation replaces a rolled-back preview. A committed snapshot is not
automatically discarded. Deactivation retains the journal and compatibility data.

The wizard handles posts, supported block JSON, templates, reusable blocks,
post metadata, structured options, term metadata and comment metadata. Unknown
reference contexts, mismatched targets, destination slug collisions, serialized
objects block commit. The wizard migrates saved configuration and does not scan source files. Revision relationship metadata and orphaned metadata are retained without
requiring profiling. Remaining ID errors show the specific target, and reference
errors include record names and excerpts. Deleted target IDs are skipped and counted;
original relationship fields remain intact. WordPress rewrite rules are regenerated.
Original plugin options, transient caches, ACF health diagnostics and visitor history
are retained rather than rewritten. Theme/plugin files, external systems,
legacy visitor-history files and URL redirects are not converted.

Interactive migrations require InnoDB. Each scanned result set must be below
10,000 rows. Snapshots use database chunks instead of a fixed 4 MiB ceiling;
available PHP memory still limits the total working set. Larger sites stop without changing
content and need a reviewed batch migration. The snapshot is a migration undo
record, not a replacement for a complete site backup.

== External services ==

Update discovery and release details are supplied by TN Update Controller. This plugin does not independently request release metadata, repository readmes or changelogs. The explicit controller-install action downloads its official GitHub release ZIP; see Controller installation service below.


GitHub terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
GitHub privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

Independent Analytics and Gravity Forms are separately installed plugins, not
bundled external services. This plugin reads local analytics data and locally
submitted form values. Their own configuration and privacy documentation govern
any services those plugins use.

== Changelog ==

= 0.7.7 =
* Select published dimension values through exact CPT query parameters; raise each selected counter to the dimension maximum plus one after page increments.
* Sync localStorage, cookie, persona and body classes; preserve other counters and get/clear behaviour.

= 0.7.6 =
* Lower the PHP requirement to 7.4 to match WordPress 7.0; update the controller installation compatibility check.

= 0.7.5 =
* Replace the independent updater with TN Update Controller integration.
* Standardise author and plugin-row links; preserve feature settings and plugin identity.
* Require WordPress 7.0+ and PHP 8.5+.

= 0.7.4 =
* Added saved JSON/ACF configuration, AIOSEO and cache post-type dictionary conversion.
* Migrate Content Planner plan names and XP pattern references with destination conflict checks.
* Skip deleted target IDs while preserving valid selections and reporting skipped references.
* Preserve taxonomy identifiers and exact rollback data.

= 0.7.3 =
* Removed source-code scanning from the saved-configuration migration wizard.
* Grouped profiling scope checks and added a wizard action to include detected types.
* Retained historical/orphaned metadata and improved target/reference diagnostics.
* Added verified plugin option adapters and route-cache regeneration.

= 0.7.2 =
* Replaced the 4 MiB snapshot ceiling with compressed chunked recovery storage.
* Verified snapshot integrity and preserved atomic writes and existing journals.

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

== Managed updates ==

Install and activate TN Update Controller to discover and install updates. The plugin row offers Install Techn Update Controller, Activate Techn Update Controller, or Check for updates according to local state and permissions. Feature operation does not require the controller. No release lookup happens while rendering this plugin's row. On multisite the controller must be network active. This plugin release requires WordPress 7.0 and PHP 7.4 or later.

== Controller installation service ==

Only an explicit authorised Install Techn Update Controller action downloads the official controller ZIP from GitHub. No plugin settings or site inventory are submitted; GitHub receives the server IP address and normal request metadata. Routine update discovery is delegated to the installed controller. Repository links open GitHub when selected.
Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
