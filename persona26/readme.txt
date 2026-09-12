=== Persona26 ===
Contributors:
Tags: personalisation, analytics, audience, profiles, gravity-forms
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.6.2
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
