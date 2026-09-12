<?php
/** Conversion of saved site configuration, preserving structured value types. */
if (!defined('ABSPATH')) exit;

function p26_legacy_context(string $key): string {
    return strtolower(str_replace(['_', '-'], '', $key));
}

/** Context separates a CPT slug from the identically named relationship field. */
function p26_legacy_transform($value, array $map, string $context = '', int $depth = 0) {
    if ($depth > 40) throw new RuntimeException('Reference nesting exceeds the safe limit.');
    $meta = [];
    foreach (p26_legacy_sources() as $source => $keys) {
        foreach ($keys as $key) $meta[$key] = $map[$source]['alias'];
        $meta['field_' . ('__persona' === $source ? 'personas' : 'interests')] = $map[$source]['field'];
    }
    $parent = p26_legacy_context($context);
    $post_types = ['posttype', 'posttypes', 'objecttype', 'menuitemobject', 'sanitizedposttypes', 'sanitizedcustomposttypes', 'sanitizedbuiltinposttypes'];
    if (is_array($value) || $value instanceof stdClass) {
        $object = is_object($value);
        $items = $object ? get_object_vars($value) : $value;
        $out = [];
        $choice = isset($items['name']) && is_string($items['name']) && 'posttype' === p26_legacy_context($items['name']);
        foreach ($items as $key => $child) {
            $new_key = $key;
            $child_context = is_int($key) ? $context : (string) $key;
            if (is_string($key) && isset($map[$key]) && in_array($parent, $post_types, true)) {
                $new_key = $map[$key]['post_type'];
                $child_context = 'post_type';
            } elseif (is_string($key) && isset($meta[$key])) $new_key = $meta[$key];
            if ('value' === $key && ('post_type' === ($items['param'] ?? '') || 'posttypechoices' === $parent)) $child_context = 'post_type';
            if ($choice && in_array($key, ['options', 'choices'], true)) $child_context = 'post_type_choices';
            if ($choice && 'default' === $key) $child_context = 'post_type';
            if ('included' === $key && 'posttypes' === $parent) $child_context = 'post_type';
            if (array_key_exists($new_key, $out) || ($new_key !== $key && array_key_exists($new_key, $items))) throw new RuntimeException('Replacing a reference would overwrite an existing key.');
            $out[$new_key] = p26_legacy_transform($child, $map, $child_context, $depth + 1);
        }
        return $object ? (object) $out : $out;
    }
    if (!is_string($value) || !p26_legacy_has_reference($value)) return $value;
    if (is_serialized($value)) return serialize(p26_legacy_transform(p26_legacy_decode($value), $map, $context, $depth + 1));
    $trimmed = trim($value);
    if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
        $json = json_decode($value, false, 40);
        if (JSON_ERROR_NONE === json_last_error() && (is_array($json) || is_object($json))) {
            $encoded = wp_json_encode(p26_legacy_transform($json, $map, $context, $depth + 1), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            if (!is_string($encoded)) throw new RuntimeException('Cannot encode translated JSON.');
            return $encoded;
        }
    }
    // Taxonomy identifiers and terms are not renamed; only their object_type changes.
    if (in_array($parent, ['taxonomy', 'taxonomies'], true)) return $value;
    if (isset($map[$value]) && in_array($parent, $post_types, true)) return $map[$value]['post_type'];
    if (isset($meta[$value]) && (in_array($parent, ['metakey', 'field', 'fieldname', 'fieldkey', 'key', 'name'], true) || str_starts_with($value, 'field_') || str_starts_with($value, 'target_'))) return $meta[$value];
    throw new RuntimeException('Unrecognised reference context: ' . ($context ?: 'plain text') . '.');
}

/** Saved definitions may be full JSON or serialized arrays, not Gutenberg content. */
function p26_legacy_content(string $content, array $map): string {
    $trimmed = trim($content);
    if (is_serialized($content) || str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) return p26_legacy_transform($content, $map);
    $pattern = '/<!--\s+wp:[a-z0-9_\/-]+\s+(\{.*?\})\s*\/?-->/s';
    $result = preg_replace_callback($pattern, static function ($match) use ($map) {
        if (!p26_legacy_has_reference($match[1])) return $match[0];
        return str_replace($match[1], p26_legacy_transform($match[1], $map), $match[0]);
    }, $content);
    $outside = preg_replace($pattern, '', $content);
    if (!is_string($result) || !is_string($outside)) throw new RuntimeException('Cannot parse block comments.');
    if (p26_legacy_has_reference($outside)) throw new RuntimeException('Reference outside supported saved configuration.');
    return $result;
}

/** Content Planner XP keys encode posttype-level-template-flagcount. */
function p26_legacy_pattern(string $value, array $map): string {
    foreach ($map as $source => $dimension) {
        if (preg_match('/^' . preg_quote($source, '/') . '-[0-9]+-[a-z0-9_-]+-[0-9]+$/D', $value)) return $dimension['post_type'] . substr($value, strlen($source));
    }
    return $value;
}

function p26_legacy_planner($value, array $map, string $context = '', int $depth = 0) {
    if ($depth > 40) throw new RuntimeException('Planner nesting exceeds the safe limit.');
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $child) {
            $new = 'entries' === $context && is_string($key) ? p26_legacy_pattern($key, $map) : $key;
            if (array_key_exists($new, $out) || ($new !== $key && array_key_exists($new, $value))) throw new RuntimeException('Destination Content Planner pattern already exists.');
            $out[$new] = p26_legacy_planner($child, $map, (string) $key, $depth + 1);
        }
        return $out;
    }
    if (is_string($value) && in_array($context, ['pattern', '_tncp_pattern'], true)) return p26_legacy_pattern($value, $map);
    return $value;
}
