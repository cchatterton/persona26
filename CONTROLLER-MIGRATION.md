# Controller migration — 0.7.6

This release requires WordPress 7.0+ and PHP 7.4+. It preserves the `persona26/persona26.php` plugin basename and replaces independent release discovery with TN Update Controller API 1.

The existing feature code and settings are retained. Plugin rows show `By Techn`, one GitHub link and the appropriate Install/Activate/Check controller action. The controller is optional for feature operation. No feature-plugin metadata HTTP, update schedule or force-refresh handler remains. The removed updater implementation registered no recurring updater cron event, so there is no obsolete job to reschedule or clear. Unrelated feature schedules are retained.

Existing sites can install TN Update Controller and select this plugin in its guided bulk updater; a separate manual upload of this plugin is not necessary for recognised basenames. Inactive plugins remain inactive. On multisite, use a network-active controller. A controller is not permission to activate feature plugins or migrate their feature data.

Validation used disposable WordPress 7.1.2 / PHP 7.4.7 installations. Ten migrated clients ran together with the controller absent, inactive and active. All cases produced unique row links and zero metadata HTTP during repeated reads. A native-upgrader batch from the previous release packages installed all ten target versions, retained saved test settings and activation scope, and reported full controller integration. TN User Management also used the published 1.8 baseline. QR Codes used 1.5 because a published 1.4 release was unavailable.

Persona26 feature integration checks, Content Planner's 55 single-site integration assertions and all eight WP Migrate standalone suites passed. Minimum WordPress 7.0 itself and every feature workflow on every hosting platform were not exhaustively tested. The standards applied are the WordPress plugin, update, general development and branding/UX standards; this change preserves existing feature layouts and only standardises the native plugin row. No customer site is changed by publishing this release.

Release order: validate and build the root ZIP, publish the matching release asset, verify downloaded bytes, then advance the controller catalogue and any legacy update.json endpoint. Historical updater instructions are superseded by this document.
