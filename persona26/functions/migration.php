<?php
/** Site-local, preview-first migration from Personas. No writes during planning. */
if (!defined('ABSPATH')) exit;

const P26_LEGACY_MAP = 'p26_legacy_mapping';
const P26_LEGACY_JOURNAL = 'p26_legacy_journal';
const P26_LEGACY_COMPAT = 'p26_legacy_compat';
const P26_LEGACY_LIMIT = 10000;
const P26_LEGACY_BYTES = 4194304;

function p26_legacy_plugin(): array {
    if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $active = array_merge((array) get_option('active_plugins', []), array_keys((array) get_site_option('active_sitewide_plugins', [])));
    foreach (get_plugins() as $file => $plugin) {
        if (in_array($file, $active, true) && 'Personas' === ($plugin['Name'] ?? '')) {
            return ['file' => $file, 'version' => $plugin['Version']];
        }
    }
    return [];
}

function p26_legacy_sources(): array {
    return ['__persona' => ['target_personas', '__persona'], '__interest' => ['target_interests', '__interest']];
}

function p26_legacy_mapping(?array $saved = null): array {
    $saved ??= (array) get_option(P26_LEGACY_MAP, []);
    $dimensions = array_column(p26_dimensions(), null, 'key');
    foreach ($dimensions as $dimension) {
        if (isset(p26_legacy_sources()[$dimension['post_type']])) throw new RuntimeException('Remove the original post types from Persona26 dimensions before migration.');
    }
    if (array_intersect(array_keys(p26_legacy_sources()), p26_profiled_post_types())) throw new RuntimeException('Replace original post types in Content profiling scope with their destinations before migration.');
    $map = [];
    foreach (p26_legacy_sources() as $source => $keys) {
        $dimension = $dimensions[$saved[$source] ?? ''] ?? null;
        if (!$dimension || in_array($dimension['post_type'], array_keys(p26_legacy_sources()), true)) {
            throw new RuntimeException('Save two destination mappings in Migrate Wizard before simulating.');
        }
        $map[$source] = $dimension + ['alias' => 'p26_legacy_' . $dimension['key'] . '_ids', 'field' => 'field_p26_legacy_' . $dimension['key']];
    }
    if ($map['__persona']['post_type'] === $map['__interest']['post_type']) {
        throw new RuntimeException('Personas and interests must map to different destination post types.');
    }
    return $map;
}

/** Never instantiate objects from database values. */
function p26_legacy_decode(?string $value) {
    if (null === $value) return null;
    if (!is_serialized($value)) return $value;
    $decoded = @unserialize($value, ['allowed_classes' => false]);
    if (false === $decoded && 'b:0;' !== $value) throw new RuntimeException('Invalid serialized data.');
    $check = static function ($item, $depth = 0) use (&$check): void {
        if ($depth > 40) throw new RuntimeException('Serialized nesting exceeds the safe limit.');
        if (is_object($item)) throw new RuntimeException('Serialized objects need a manual adapter.');
        if (is_array($item)) foreach ($item as $child) $check($child, $depth + 1);
    };
    $check($decoded);
    return $decoded;
}

function p26_legacy_has_reference(?string $value): bool {
    return null !== $value && (bool) preg_match('/__persona|__interest|target_personas|target_interests|field_personas|field_interests/', $value);
}

/** Context separates a CPT slug from the identically named relationship field. */
function p26_legacy_transform($value, array $map, string $context = '', int $depth = 0) {
    if ($depth > 40) throw new RuntimeException('Reference nesting exceeds the safe limit.');
    $meta = [];
    foreach (p26_legacy_sources() as $source => $keys) {
        foreach ($keys as $key) $meta[$key] = $map[$source]['alias'];
        $meta['field_' . ('__persona' === $source ? 'personas' : 'interests')] = $map[$source]['field'];
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $child) {
            $new_key = $key;
            // A literal relationship key in a structured object refers to metadata.
            if (is_string($key) && isset($meta[$key])) $new_key = $meta[$key];
            if (array_key_exists($new_key, $out) || ($new_key !== $key && array_key_exists($new_key, $value))) {
                throw new RuntimeException('Replacing a reference would overwrite an existing key.');
            }
            $out[$new_key] = p26_legacy_transform($child, $map, is_int($key) ? $context : (string) $key, $depth + 1);
        }
        return $out;
    }
    if (!is_string($value) || !p26_legacy_has_reference($value)) return $value;
    if (is_serialized($value)) {
        return serialize(p26_legacy_transform(p26_legacy_decode($value), $map, $context, $depth + 1));
    }
    $trimmed = trim($value);
    if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
        $json = json_decode($value, true, 40);
        if (JSON_ERROR_NONE === json_last_error() && is_array($json)) {
            // Decode as objects too, to preserve empty objects versus empty arrays.
            $walk = static function ($item, $key = '') use (&$walk, $map, $depth) {
                if (is_object($item)) {
                    $result = new stdClass();
                    foreach (get_object_vars($item) as $k => $v) {
                        $keys = p26_legacy_transform([$k => null], $map, '', $depth + 1);
                        $new = array_key_first($keys);
                        if ($new !== $k && property_exists($item, $new)) throw new RuntimeException('Duplicate JSON key after replacement.');
                        $result->{$new} = $walk($v, $k);
                    }
                    return $result;
                }
                if (is_array($item)) return array_map(static fn($v) => $walk($v, $key), $item);
                return p26_legacy_transform($item, $map, $key, $depth + 1);
            };
            $encoded = wp_json_encode($walk(json_decode($value)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            if (!is_string($encoded)) throw new RuntimeException('Cannot encode translated JSON.');
            return $encoded;
        }
    }
    $key = strtolower(str_replace(['_', '-'], '', $context));
    if (isset($map[$value]) && in_array($key, ['posttype', 'posttypes', 'objecttype', 'menuitemobject'], true)) return $map[$value]['post_type'];
    if (isset($meta[$value]) && (in_array($key, ['metakey', 'field', 'fieldname', 'fieldkey', 'key', 'name'], true) || str_starts_with($value, 'field_') || str_starts_with($value, 'target_'))) return $meta[$value];
    throw new RuntimeException('Unrecognised reference context: ' . ($context ?: 'plain text') . '.');
}

/** Change only block JSON comments, keeping saved HTML byte-for-byte intact. */
function p26_legacy_content(string $content, array $map): string {
    $result = preg_replace_callback('/<!--\s+wp:[a-z0-9_\/-]+\s+(\{.*?\})\s*\/?-->/s', static function ($match) use ($map) {
        if (!p26_legacy_has_reference($match[1])) return $match[0];
        return str_replace($match[1], p26_legacy_transform($match[1], $map), $match[0]);
    }, $content);
    if (!is_string($result)) throw new RuntimeException('Cannot parse block comments.');
    if (p26_legacy_has_reference($result)) throw new RuntimeException('Reference outside supported block attributes; a block or template adapter is required.');
    return $result;
}

function p26_legacy_rows(string $query): array {
    global $wpdb;
    $rows = $wpdb->get_results($query, ARRAY_A);
    if ($wpdb->last_error) throw new RuntimeException('Database read failed.');
    if (count($rows) >= P26_LEGACY_LIMIT) throw new RuntimeException('This site exceeds the interactive migration limit. No content was changed; use a reviewed batch migration.');
    return $rows;
}

function p26_legacy_like(string $column): string {
    global $wpdb;
    $parts = [];
    foreach (['__persona', '__interest', 'target_personas', 'target_interests', 'field_personas', 'field_interests'] as $token) {
        $parts[] = $wpdb->prepare("$column LIKE %s", '%' . $wpdb->esc_like($token) . '%');
    }
    return '(' . implode(' OR ', $parts) . ')';
}

/** Build exact before/after records. The same planner is rerun under locks at commit. */
function p26_legacy_plan(bool $lock = false): array {
    global $wpdb;
    $map = p26_legacy_mapping();
    $plan = ['mapping' => $map, 'settings' => p26_settings(), 'posts' => [], 'meta' => [], 'other' => [], 'blockers' => [], 'counts' => ['personas' => 0, 'interests' => 0, 'tagged_content' => 0, 'reference_records' => 0]];
    $suffix = ' LIMIT ' . P26_LEGACY_LIMIT . ($lock ? ' FOR UPDATE' : '');
    $posts = p26_legacy_rows("SELECT * FROM {$wpdb->posts} WHERE post_type IN ('__persona','__interest') OR " . p26_legacy_like('post_content') . ' OR ' . p26_legacy_like('post_excerpt') . ' ORDER BY ID' . $suffix);
    $source_ids = [];
    foreach ($posts as $row) {
        $after = $row;
        if (isset($map[$row['post_type']])) {
            $source_ids[(int) $row['ID']] = $row;
            $after['post_type'] = $map[$row['post_type']]['post_type'];
            $plan['counts']['__persona' === $row['post_type'] ? 'personas' : 'interests']++;
            if ('' !== $row['post_name']) {
                $collision = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND ID <> %d LIMIT 1", $after['post_type'], $row['post_name'], $row['ID']));
                if ($collision) $plan['blockers'][] = "Post {$row['ID']}: destination slug already belongs to post $collision.";
            }
        }
        foreach (['post_content', 'post_excerpt'] as $column) {
            if (!p26_legacy_has_reference($row[$column])) continue;
            try {
                $after[$column] = 'acf-field' === $row['post_type'] ? p26_legacy_transform($row[$column], $map) : p26_legacy_content($row[$column], $map);
                $plan['counts']['reference_records']++;
            } catch (RuntimeException $error) { $plan['blockers'][] = "Post {$row['ID']} $column: " . $error->getMessage(); }
        }
        if ($after !== $row) $plan['posts'][$row['ID']] = ['before' => $row, 'after' => $after];
    }
    $keys = ['target_personas', '__persona', 'target_interests', '__interest'];
    $key_sql = "'target_personas','__persona','target_interests','__interest'";
    $candidates = p26_legacy_rows("SELECT * FROM {$wpdb->postmeta} WHERE meta_key IN ($key_sql) OR " . p26_legacy_like('meta_value') . ' ORDER BY meta_id' . $suffix);
    $post_ids = array_unique(array_map('intval', array_column($candidates, 'post_id')));
    foreach ($post_ids as $post_id) {
        $before = p26_legacy_rows($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id) . $suffix);
        $after = $before;
        $tags = [];
        foreach ($before as $index => $row) {
            $source = null;
            foreach (p26_legacy_sources() as $type => $names) if (in_array($row['meta_key'], $names, true)) $source = $type;
            if ($source) {
                try {
                    $ids = p26_legacy_decode($row['meta_value']);
                    if ('' === $ids || false === $ids || null === $ids) $ids = [];
                    if (!is_array($ids)) throw new RuntimeException('Relationship value is not an ID array.');
                    foreach ($ids as $id) {
                        if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id < 1 || !isset($source_ids[(int) $id]) || $source_ids[(int) $id]['post_type'] !== $source) throw new RuntimeException('Relationship contains a missing or wrong-type ID.');
                        if ('publish' !== $source_ids[(int) $id]['post_status']) throw new RuntimeException('A tagged persona/interest is not published; resolve its status before migration.');
                        $tags[$map[$source]['key']][] = (int) $id;
                    }
                    $tags[$map[$source]['key']] ??= [];
                } catch (RuntimeException $error) { $plan['blockers'][] = "Post $post_id {$row['meta_key']}: " . $error->getMessage(); }
                continue; // Original relationship rows and ACF companions remain intact.
            }
            if (in_array($row['meta_key'], array_map(static fn($k) => '_' . $k, $keys), true)) continue;
            if (p26_legacy_has_reference($row['meta_value'])) {
                try {
                    $after[$index]['meta_value'] = p26_legacy_transform($row['meta_value'], $map, $row['meta_key']);
                    $plan['counts']['reference_records']++;
                } catch (RuntimeException $error) { $plan['blockers'][] = "Post $post_id meta {$row['meta_id']}: " . $error->getMessage(); }
            }
        }
        if ($tags) {
            $content_type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $post_id));
            $content_type = $map[$content_type]['post_type'] ?? $content_type;
            if (!in_array($content_type, p26_profiled_post_types(), true)) $plan['blockers'][] = "Post $post_id: enable $content_type in Content profiling scope first.";
            $alignment_rows = array_values(array_filter($before, static fn($r) => P26_ALIGNMENT_META === $r['meta_key']));
            try {
                if (count($alignment_rows) > 1) throw new RuntimeException('Multiple alignment records.');
                $alignment = $alignment_rows ? p26_legacy_decode($alignment_rows[0]['meta_value']) : [];
                if (!is_array($alignment)) throw new RuntimeException('Existing alignment is invalid.');
                $alignment = p26_normalize_alignment($alignment);
                foreach ($tags as $key => $ids) {
                    if (isset($alignment['dims'][$key]) && !is_array($alignment['dims'][$key])) throw new RuntimeException('Existing dimension targets are invalid.');
                    $alignment['dims'][$key] = array_values(array_unique(array_merge($alignment['dims'][$key] ?? [], $ids)));
                }
                p26_legacy_set_meta($after, $post_id, P26_ALIGNMENT_META, $alignment);
                $mirrors = [];
                foreach (p26_dimensions() as $dimension) {
                    foreach ($alignment['dims'][$dimension['key']] ?? [] as $id) {
                        $target = $source_ids[(int) $id] ?? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id), ARRAY_A);
                        if (!$target || 'publish' !== $target['post_status'] || ($map[$target['post_type']]['post_type'] ?? $target['post_type']) !== $dimension['post_type']) throw new RuntimeException('An existing target does not match its configured dimension.');
                        if ('' === $target['post_title']) throw new RuntimeException('A target has no title for queryable metadata.');
                        $mirrors[$dimension['post_type']][] = $target['post_title'];
                    }
                }
                $owned = [];
                foreach ($before as $row) if (P26_ALIGNMENT_MIRRORS_META === $row['meta_key']) {
                    $owned = p26_legacy_decode($row['meta_value']);
                    if (!is_array($owned)) throw new RuntimeException('Existing mirror ownership is invalid.');
                }
                // Only remove previously owned scalar values.
                $after = array_values(array_filter($after, static fn($r) => !isset($owned[$r['meta_key']]) || !in_array($r['meta_value'], (array) $owned[$r['meta_key']], true)));
                foreach ($mirrors as $key => &$values) {
                    $values = array_values(array_unique($values));
                    foreach ($values as $value) $after[] = ['meta_id' => null, 'post_id' => (string) $post_id, 'meta_key' => $key, 'meta_value' => $value];
                }
                unset($values);
                p26_legacy_set_meta($after, $post_id, P26_ALIGNMENT_MIRRORS_META, $mirrors);
                foreach ($map as $dimension) {
                    $alias = $dimension['alias'];
                    foreach ($before as $row) if (in_array($row['meta_key'], [$alias, '_' . $alias], true)) throw new RuntimeException('A migration compatibility field already exists.');
                    p26_legacy_set_meta($after, $post_id, $alias, array_map('strval', $alignment['dims'][$dimension['key']] ?? []));
                    p26_legacy_set_meta($after, $post_id, '_' . $alias, $dimension['field']);
                }
                $plan['counts']['tagged_content']++;
            } catch (RuntimeException $error) { $plan['blockers'][] = "Post $post_id: " . $error->getMessage(); }
        }
        if ($after !== $before) $plan['meta'][$post_id] = ['before' => $before, 'after' => $after];
    }
    foreach (['options' => ['option_id', 'option_value'], 'termmeta' => ['meta_id', 'meta_value'], 'commentmeta' => ['meta_id', 'meta_value']] as $table => [$id, $value]) {
        $where = p26_legacy_like($value);
        if ('options' === $table) $where .= " AND option_name NOT LIKE 'p26\\_%' AND option_name NOT LIKE '\\_transient\\_%' AND option_name NOT LIKE '\\_site\\_transient\\_%' AND option_name NOT LIKE 'options\\_\\_\\_%' AND option_name NOT LIKE '\\_options\\_\\_\\_%' AND option_name NOT LIKE 'persona\\_%' AND option_name NOT IN ('active_plugins','recently_activated','acf_site_health')";
        foreach (p26_legacy_rows("SELECT * FROM {$wpdb->$table} WHERE $where ORDER BY $id" . $suffix) as $row) {
            try {
                $after = $row;
                $after[$value] = p26_legacy_transform($row[$value], $map, $row['meta_key'] ?? $row['option_name']);
                if ($after !== $row) { $plan['other'][] = ['table' => $table, 'id' => $id, 'before' => $row, 'after' => $after]; $plan['counts']['reference_records']++; }
            } catch (RuntimeException $error) { $plan['blockers'][] = "$table {$row[$id]}: " . $error->getMessage(); }
        }
    }
    $plan['blockers'] = array_values(array_unique(array_merge($plan['blockers'], p26_legacy_code_references())));
    if (strlen(serialize($plan)) > P26_LEGACY_BYTES) throw new RuntimeException('The migration snapshot exceeds 4 MiB. No content was changed; use a reviewed batch migration.');
    return $plan;
}

/** Stored references cannot repair hard-coded consumers in another active component. */
function p26_legacy_code_references(): array {
    $legacy = p26_legacy_plugin();
    $roots = [];
    foreach (array_merge((array) get_option('active_plugins', []), array_keys((array) get_site_option('active_sitewide_plugins', []))) as $plugin) {
        if ($plugin === ($legacy['file'] ?? '') || $plugin === plugin_basename(P26_PLUGIN_FILE)) continue;
        $file = WP_PLUGIN_DIR . '/' . $plugin;
        $roots[] = str_contains($plugin, '/') ? dirname($file) : $file;
    }
    $roots[] = get_stylesheet_directory();
    $roots[] = get_template_directory();
    if (is_dir(WPMU_PLUGIN_DIR)) $roots[] = WPMU_PLUGIN_DIR;
    $blockers = [];
    $files = 0;
    $bytes = 0;
    foreach (array_unique($roots) as $root) {
        if (!file_exists($root)) continue;
        try {
            $iterator = is_file($root) ? [new SplFileInfo($root)] : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'json'], true)) continue;
                $files++;
                $bytes += $file->getSize();
                if ($files > 15000 || $bytes > 100 * 1024 * 1024) return array_merge($blockers, ['Active-code inspection exceeded its safe limit; a reviewed code audit is required.']);
                $content = file_get_contents($file->getPathname());
                if (false === $content) { $blockers[] = 'Cannot inspect an active component: ' . basename($root); continue; }
                if (p26_legacy_has_reference($content) || preg_match('/\bget_(?:persona|personas|interest|interests|cookie_history)\s*\(/', $content)) {
                    $blockers[] = 'Hard-coded legacy consumer in ' . str_replace(trailingslashit(WP_CONTENT_DIR), '', $file->getPathname()) . '. Update this component before migration.';
                }
            }
        } catch (UnexpectedValueException $error) { $blockers[] = 'Cannot inspect an active component: ' . basename($root); }
    }
    return $blockers;
}

function p26_legacy_set_meta(array &$rows, int $post_id, string $key, $value): void {
    $found = false;
    foreach ($rows as &$row) {
        if ($row['meta_key'] !== $key) continue;
        if ($found) throw new RuntimeException('Duplicate metadata for ' . $key . '.');
        $row['meta_value'] = maybe_serialize($value);
        $found = true;
    }
    unset($row);
    if (!$found) $rows[] = ['meta_id' => null, 'post_id' => (string) $post_id, 'meta_key' => $key, 'meta_value' => maybe_serialize($value)];
}

function p26_legacy_sql($result): void {
    if (false === $result) throw new RuntimeException('Database write failed; the transaction was rolled back.');
}

/** Transaction plus a connection-owned advisory lock; a crash releases both. */
function p26_legacy_transaction(callable $callback) {
    global $wpdb;
    $name = 'p26-migrate-' . md5(DB_NAME . $wpdb->prefix);
    if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name))) throw new RuntimeException('Another migration is running. Wait for it to finish.');
    try {
        foreach (['posts', 'postmeta', 'options', 'termmeta', 'commentmeta'] as $table) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->$table));
            if ('InnoDB' !== $engine) throw new RuntimeException('All affected tables must use InnoDB for atomic commit and rollback.');
        }
        p26_legacy_sql($wpdb->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'));
        p26_legacy_sql($wpdb->query('START TRANSACTION'));
        $result = $callback();
        p26_legacy_sql($wpdb->query('COMMIT'));
        return $result;
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        throw $error;
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }
}

function p26_legacy_read_journal(bool $lock = false): array {
    global $wpdb;
    $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s" . ($lock ? ' FOR UPDATE' : ''), P26_LEGACY_JOURNAL));
    return $raw ? (array) p26_legacy_decode($raw) : [];
}

function p26_legacy_write_option(string $name, array $value): void {
    global $wpdb;
    p26_legacy_sql($wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'off'", $name, serialize($value))));
}

function p26_legacy_simulate(): array {
    if (!p26_legacy_plugin()) throw new RuntimeException('Activate the original Personas plugin on this site to simulate.');
    return p26_legacy_transaction(static function () {
        $journal = p26_legacy_read_journal(true);
        if ('committed' === ($journal['status'] ?? '')) throw new RuntimeException('A committed migration already has a recovery snapshot. Roll it back before simulating again.');
        $plan = p26_legacy_plan();
        $journal = ['status' => 'preview', 'token' => wp_generate_uuid4(), 'created' => gmdate('c'), 'plan' => $plan];
        p26_legacy_write_option(P26_LEGACY_JOURNAL, $journal);
        return $journal;
    });
}

function p26_legacy_commit(string $token): array {
    if (!p26_legacy_plugin()) throw new RuntimeException('The original Personas plugin must still be active when committing.');
    $journal = p26_legacy_transaction(static function () use ($token) {
        global $wpdb;
        $journal = p26_legacy_read_journal(true);
        if ('preview' !== ($journal['status'] ?? '') || !hash_equals($journal['token'], $token)) throw new RuntimeException('This preview is no longer current. Run a new simulation.');
        // Read current settings through SQL to avoid stale option caches.
        wp_cache_delete(P26_SETTINGS_OPTION, 'options');
        wp_cache_delete(P26_LEGACY_MAP, 'options');
        wp_cache_delete('alloptions', 'options');
        $stored_settings = p26_legacy_decode((string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", P26_SETTINGS_OPTION)));
        $stored_mapping = p26_legacy_decode((string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", P26_LEGACY_MAP)));
        $plan = p26_legacy_plan(true);
        if ($stored_settings !== $plan['settings']) throw new RuntimeException('Settings cache changed during migration. Simulate again.');
        foreach ($plan['mapping'] as $source => $dimension) {
            if (($stored_mapping[$source] ?? null) !== $dimension['key']) throw new RuntimeException('Destination mapping changed during migration. Simulate again.');
        }
        if ($plan['blockers']) throw new RuntimeException('Resolve every preview blocker before committing.');
        if (serialize($plan) !== serialize($journal['plan'])) throw new RuntimeException('Site data or configuration changed after the preview. Simulate again; nothing was changed.');
        if (!$plan['posts'] && !$plan['meta'] && !$plan['other']) throw new RuntimeException('There is nothing to migrate.');
        foreach ($plan['posts'] as $id => $change) p26_legacy_sql($wpdb->update($wpdb->posts, $change['after'], ['ID' => $id]));
        foreach ($plan['meta'] as $post_id => &$change) {
            p26_legacy_sql($wpdb->delete($wpdb->postmeta, ['post_id' => $post_id]));
            foreach ($change['after'] as &$row) {
                $insert = $row;
                if (null === $insert['meta_id']) unset($insert['meta_id']);
                p26_legacy_sql($wpdb->insert($wpdb->postmeta, $insert));
                $row['meta_id'] = (string) $wpdb->insert_id;
            }
            unset($row);
        }
        unset($change);
        foreach ($plan['other'] as $change) p26_legacy_sql($wpdb->update($wpdb->{$change['table']}, $change['after'], [$change['id'] => $change['before'][$change['id']]]));
        $journal['plan'] = $plan;
        $journal['status'] = 'committed';
        $journal['committed'] = gmdate('c');
        p26_legacy_write_option(P26_LEGACY_COMPAT, $plan['mapping']);
        p26_legacy_write_option(P26_LEGACY_JOURNAL, $journal);
        return $journal;
    });
    p26_legacy_clear_caches($journal['plan']);
    return $journal;
}

/** Refuse to overwrite any record edited since commit. Recovery works with Personas off. */
function p26_legacy_rollback(string $token, bool $check_only = false): array {
    $journal = p26_legacy_transaction(static function () use ($token, $check_only) {
        global $wpdb;
        $journal = p26_legacy_read_journal(true);
        if ('committed' !== ($journal['status'] ?? '') || !hash_equals($journal['token'], $token)) throw new RuntimeException('No matching committed snapshot is available.');
        $plan = $journal['plan'];
        $compat = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", P26_LEGACY_COMPAT));
        if ($compat !== serialize($plan['mapping'])) throw new RuntimeException('Migration compatibility settings changed. Recovery stopped.');
        $alias_rows = p26_legacy_rows("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN (" . implode(',', array_map(static fn($d) => $wpdb->prepare('%s', $d['alias']), $plan['mapping'])) . ') LIMIT ' . P26_LEGACY_LIMIT . ' FOR UPDATE');
        foreach ($alias_rows as $alias_row) if (!isset($plan['meta'][$alias_row['post_id']])) throw new RuntimeException('New content now uses migrated relationship fields. Recovery stopped to protect its tags.');
        foreach ($plan['posts'] as $id => $change) {
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $id), ARRAY_A);
            if ($current !== $change['after']) throw new RuntimeException("Post $id changed after migration. Rollback stopped without changing data.");
            if ($change['before']['post_type'] !== $change['after']['post_type'] && '' !== $change['before']['post_name']) {
                $collision = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND ID <> %d LIMIT 1 FOR UPDATE", $change['before']['post_type'], $change['before']['post_name'], $id));
                if ($collision) throw new RuntimeException("A new original post conflicts with post $id. Recovery stopped.");
            }
        }
        foreach ($plan['meta'] as $post_id => $change) {
            $current = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id FOR UPDATE", $post_id), ARRAY_A);
            $expected = $change['after'];
            usort($expected, static fn($a, $b) => (int) $a['meta_id'] <=> (int) $b['meta_id']);
            if ($current !== $expected) throw new RuntimeException("Metadata for post $post_id changed after migration. Rollback stopped without changing data.");
        }
        foreach ($plan['other'] as $change) {
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->{$change['table']}} WHERE {$change['id']} = %d FOR UPDATE", $change['before'][$change['id']]), ARRAY_A);
            if ($current !== $change['after']) throw new RuntimeException("A migrated {$change['table']} record changed. Rollback stopped without changing data.");
        }
        if ($check_only) return $journal;
        foreach ($plan['posts'] as $id => $change) p26_legacy_sql($wpdb->update($wpdb->posts, $change['before'], ['ID' => $id]));
        foreach ($plan['meta'] as $post_id => $change) {
            p26_legacy_sql($wpdb->delete($wpdb->postmeta, ['post_id' => $post_id]));
            foreach ($change['before'] as $row) p26_legacy_sql($wpdb->insert($wpdb->postmeta, $row));
        }
        foreach ($plan['other'] as $change) p26_legacy_sql($wpdb->update($wpdb->{$change['table']}, $change['before'], [$change['id'] => $change['before'][$change['id']]]));
        p26_legacy_sql($wpdb->delete($wpdb->options, ['option_name' => P26_LEGACY_COMPAT]));
        $journal['status'] = 'rolled_back';
        $journal['rolled_back'] = gmdate('c');
        p26_legacy_write_option(P26_LEGACY_JOURNAL, $journal);
        return $journal;
    });
    if (!$check_only) p26_legacy_clear_caches($journal['plan']);
    return $journal;
}

function p26_legacy_clear_caches(array $plan): void {
    foreach (array_unique(array_merge(array_keys($plan['posts']), array_keys($plan['meta']))) as $id) clean_post_cache($id);
    foreach ($plan['other'] as $change) {
        if ('options' === $change['table']) wp_cache_delete($change['before']['option_name'], 'options');
        if ('termmeta' === $change['table']) wp_cache_delete($change['before']['term_id'], 'term_meta');
        if ('commentmeta' === $change['table']) wp_cache_delete($change['before']['comment_id'], 'comment_meta');
    }
    foreach ([P26_LEGACY_JOURNAL, P26_LEGACY_COMPAT, 'alloptions', 'notoptions'] as $key) wp_cache_delete($key, 'options');
    p26_rebuild_personalize_css();
    flush_rewrite_rules(false);
}

/** Keep translated ID queries current when an editor changes Persona26 targets. */
function p26_legacy_sync_aliases(int $post_id, array $alignment): void {
    $map = get_option(P26_LEGACY_COMPAT, []);
    if (!$map) return;
    $alignment = p26_normalize_alignment($alignment);
    $alignment['dims'] = p26_validate_alignment_dimensions($alignment['dims']);
    foreach ($map as $dimension) {
        update_post_meta($post_id, $dimension['alias'], array_map('strval', $alignment['dims'][$dimension['key']] ?? []));
        update_post_meta($post_id, '_' . $dimension['alias'], $dimension['field']);
    }
}

/** ACF consumers can still resolve the translated relationship field after Personas is off. */
function p26_legacy_register_acf_fields(): void {
    if (!function_exists('acf_add_local_field')) return;
    foreach ((array) get_option(P26_LEGACY_COMPAT, []) as $dimension) {
        acf_add_local_field(['key' => $dimension['field'], 'name' => $dimension['alias'], 'label' => $dimension['context'], 'type' => 'post_object', 'post_type' => [$dimension['post_type']], 'multiple' => 1, 'return_format' => 'id']);
    }
}
add_action('acf/init', 'p26_legacy_register_acf_fields', 30);
