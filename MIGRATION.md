# Migrating Personas into Persona26

The supplied Personas 3.1 source defines fixed `__persona` and `__interest` post types.
Its ACF option `version` changes relationship names from `target_personas` /
`target_interests` to `__persona` / `__interest`. Both layouts store multiple post IDs;
they can coexist after an upgrade. Persona26 instead uses `p26_alignment` for ID
selections and scalar metadata named after each destination CPT for title queries.

## Workflow

1. Register destination CPTs outside the wizard. In Dimensions, save destination
   dimensions and content profiling scope. In Migrate Wizard, save each legacy-to-dimension mapping.
2. With Personas active on the site (or network), open Migrate Wizard and simulate.
3. Review source counts, affected records and blockers. Resolve blockers and resimulate.
4. Commit the reviewed plan explicitly. Test the destination posts, targets, affected
   blocks and front-end behaviour, then deactivate Personas through WordPress.
5. To undo, run Check rollback, then Roll back migration. Recovery remains on the
   wizard tab when Personas is inactive and a committed snapshot exists. Reactivate Personas after restoration.

## Data and references

Post IDs, slugs, parents, status and media associations are retained. Source CPT rows
change to their configured destination. Legacy IDs are unioned with existing targets
rather than replacing them. Selected unpublished/missing/wrong-type targets block
migration; untagged draft or trashed dimension posts retain their status.

Original relationship metadata and ACF companions stay intact. New
`p26_legacy_dN_ids` fields store ID arrays and have distinct ACF field keys. Translated
legacy queries use these fields, preserving serialized-ID comparison semantics.
Persona26's usual CPT-named scalar title rows are generated independently. Later
Persona26 target edits keep aliases current. ACF can resolve the new relationship
fields after Personas is inactive. The legacy editor is not an ongoing editing
interface: complete verification and deactivate Personas before resuming editing.

Supported structured references include `postType`/`post_type`/`post_types`, menu
object types, metadata keys and ACF field references. Block JSON comments are changed
without rewriting saved HTML. Serialized arrays are decoded with object creation
disabled, transformed recursively and reserialized; nested JSON preserves arrays
versus objects. This is prepared SQL over planned records, never blind SQL REPLACE.

Covered tables are the current site's posts, postmeta, options, termmeta and
commentmeta. Shared users/usermeta, other sites, external services and files are not
changed. Original options/history and diagnostic/transient caches are excluded.
Active plugin/theme/MU-plugin PHP, JS and JSON are scanned for hard-coded consumers;
findings block commit. This scan is conservative, not a proof of all dynamically
constructed code references. Custom block schemas may need an adapter. The actual
block plugin source/site database was not provided for validation.

## Atomicity and recovery

Simulation only writes the non-autoloaded preview journal. Commit acquires a
site-specific database advisory lock, requires InnoDB, re-plans under a SERIALIZABLE
transaction and compares the full plan with the preview. Changed data or settings
invalidate the preview. Posts, metadata, references, compatibility settings and the
committed journal are written in one transaction. Failed statements roll back all
writes. A killed connection releases its transaction and advisory lock.

Rollback locks and compares every affected record with its committed version. It
refuses changed/deleted records, new compatibility-dependent content and source slug
collisions. It restores exact original rows, including metadata IDs and serialized
bytes, atomically. Unchanged mirror refreshes are idempotent so routine backfills do
not create false recovery conflicts. WordPress caches and derived CSS are refreshed
after commit/rollback; external page/object cache integrations may require their own
purge. The database undo record does not replace a full database/files backup.

Snapshots remain in `p26_legacy_journal`; they may contain private changed option
values and are never exposed through public endpoints. There is one site-local
snapshot, capped at 4 MiB, and no automatic expiry. A committed snapshot cannot be
replaced by another simulation. Successful rollback allows a new simulation. Settings
cannot remap migrated destination dimensions while compatibility is active.

Queries are bounded below 10,000 rows per result set; active-code inspection is capped
at 15,000 files / 100 MiB. Exceeding a bound blocks the interactive migration. This
release does not silently run a partial/batched migration on large sites.

## Validation

- Disposable WordPress 7.1, PHP 8.5.7, MySQL 9.6 / InnoDB.
- Integration fixtures cover both layouts and their coexistence, existing target
  merging, scalar mirrors, ACF IDs, JSON types/HTML, serialized options and metadata,
  templates, navigation, term/comment metadata and byte-exact rollback.
- NULL metadata is preserved during simulation, commit and exact rollback. Wizard
  mapping saves independently of general settings and invalidates stale previews.
- Negative cases cover stale previews/tokens, injected mid-commit SQL failure,
  post-commit edits, unsupported objects/contexts, ID/key/slug collisions and active
  hard-coded source consumers.
- Actual supplied Personas 3.1 and ACF Free 6.8.10 loaded together. The disposable
  harness stubs only ACF Pro's options-page registration API, which is unavailable
  in ACF Free. Real field registration, update/read and relationship resolution ran.
- Browser flow covers simulation, explicit commit, rollback check, native Personas
  deactivation and recovery on the wizard tab. Keyboard navigation, no overflow and
  axe WCAG A/AA checks passed at 1440, 768, 390 and 320 pixel widths.
- Subscriber capability and invalid administrator nonce requests returned HTTP 403.
- Network-active detection, second-site commit/rollback and unchanged first-site
  posts, metadata and journal passed.

No production site was modified. A real-site staging run remains necessary for the
site's block plugin, theme, external caches and any historical visitor-profile logic.
