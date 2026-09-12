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
The wizard migrates saved site configuration; it does not scan or modify component
source files. Unknown stored reference formats remain visible in the preview so
conversion can be based on the saved values. The actual site database was not
provided for validation.

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

The `p26_legacy_journal` manifest and non-autoloaded `p26_legacy_snapshot_*` options
retain one site-local recovery snapshot with no automatic expiry. Snapshot data may
contain private changed option values and is never exposed through public endpoints.
The former 4 MiB ceiling is removed. Serialized recovery data is compressed when zlib
is available and stored in chunks of at most 256 KiB after base64 encoding. Manifest
and chunks are read together in one SQL statement and replaced in one transaction.
Length and SHA-256 checks reject missing/corrupted chunks. Existing single-option
journals remain readable and become chunked on their next write. A PHP memory-headroom
check guards serialization/restoration; this is chunked storage, not a background
content migration, so PHP memory/time and the existing row limits still apply. A committed snapshot cannot be
replaced by another simulation. Successful rollback allows a new simulation. Settings
cannot remap migrated destination dimensions while compatibility is active.

Queries are bounded below 10,000 rows per result set. Exceeding this bound blocks
the interactive migration. This
release does not silently run a partial/batched migration on large sites.

## Validation

- Disposable WordPress 7.1, PHP 8.5.7, MySQL 9.6 / InnoDB.
- Integration fixtures cover both layouts and their coexistence, existing target
  merging, scalar mirrors, ACF IDs, JSON types/HTML, serialized options and metadata,
  templates, navigation, term/comment metadata and byte-exact rollback.
- NULL metadata is preserved during simulation, commit and exact rollback. Wizard
  mapping saves independently of general settings and invalidates stale previews.
- A snapshot exceeding 4 MiB with an incompressible unrelated metadata value passes
  simulation, commit and byte-exact rollback. Missing/corrupt chunks and a forced
  chunk-write failure are rejected without partial migration. Existing journal
  formats remain readable and recoverable.
- Negative cases cover stale previews/tokens, injected mid-commit SQL failure,
  post-commit edits, unsupported objects/contexts and ID/key/slug collisions.
  Source-file text does not block database migration and remains unchanged.
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

## Site report handling (0.7.3)

Missing scope is grouped by real tagged content type. The explicit **Include detected
content types** action unions those types into profiling scope, keeps dimensions
unchanged and invalidates the old preview. Empty relationships do not require scope.
Revision relationship metadata and orphaned metadata are retained untouched; they are
counted in the report rather than being treated as live content. Revision block content
still undergoes reference checks. Compatibility refresh does not add metadata to
revisions or nonexistent posts.

Targets already in the mapped destination CPT are accepted without changing IDs.
Missing/wrong-type IDs still block commit and now identify the actual ID and expected
type. The wizard does not silently discard unresolved selections. Unknown stored
references now include option names and bounded excerpts. Source-code scanning has
been removed from the wizard; commit checks apply to the saved configuration.

Verified post-type-list adapters cover [Yoast Duplicate Post's enabled types](https://github.com/Yoast/duplicate-post/blob/trunk/common-functions.php)
and [Relevanssi's indexed types](https://github.com/msaari/relevanssi/blob/master/lib/search.php).
Other generic `included` or `pattern` settings still need their saved values examined.
The WordPress `rewrite_rules` cache is excluded and regenerated. Each migrated site
also refreshes its routes once the recorded source plugin is no longer active.

Regression tests cover grouped scope repair, preserved dimensions, already-mapped
IDs, retained historical/orphaned records, both verified option adapters and rewrite
refresh after deactivation. A source-file fixture also verifies commit and rollback
without scanning or changing files. Reported unsupported database references need
their saved values examined before conversion.
