<?php
if (!defined('ABSPATH')) exit;

/**
 * Persona26 Profile
 *
 * Uses:
 * - p26_profiled_post_types()
 * - p26_dimensions()
 * - P26_ALIGNMENT_META / p26_alignment
 *
 * Stores:
 * - localStorage: p26_profile
 * - cookie mirror: p26_profile (best effort)
 *
 * Query strings:
 * - ?persona=show  => update profile, then show debug
 * - ?persona=get   => show debug only, do not update
 * - ?persona=clear => clear profile and reload clean
 */

class P26_Profile {

    const FOREVER = 630720000; // 20 years

    public static function boot(): void {
        add_action('wp_head', [__CLASS__, 'output_clear'], 1);
        add_action('wp_head', [__CLASS__, 'output_page_data'], 2);
        add_action('wp_head', [__CLASS__, 'output_profile_script'], 3);
        add_action('wp_footer', [__CLASS__, 'output_debug'], 999);
        add_filter('body_class', [__CLASS__, 'add_persona_body_classes'], 20, 1);
    }

    protected static function storage_key(string $key): string {
        return 'p26_' . $key;
    }

    protected static function alignment_meta_key(): string {
        return defined('P26_ALIGNMENT_META') ? P26_ALIGNMENT_META : 'p26_alignment';
    }

    protected static function current_post(): ?WP_Post {
        if (is_admin() || !is_singular()) return null;
        $post = get_queried_object();
        return ($post instanceof WP_Post) ? $post : null;
    }

    protected static function profiled_post_types(): array {
        if (!function_exists('p26_profiled_post_types')) return [];
        $pts = p26_profiled_post_types();
        return is_array($pts) ? $pts : [];
    }

    protected static function dimensions(): array {
        if (!function_exists('p26_dimensions')) return [];
        $dims = p26_dimensions();
        return is_array($dims) ? $dims : [];
    }

    protected static function is_enabled_post_type(?WP_Post $post): bool {
        if (!$post) return false;
        return in_array($post->post_type, self::profiled_post_types(), true);
    }

    protected static function saved_alignment(int $post_id): array {
        $saved = get_post_meta($post_id, self::alignment_meta_key(), true);
        $saved = is_array($saved) ? $saved : [];

        if (!isset($saved['dims']) || !is_array($saved['dims'])) {
            $saved['dims'] = [];
        }

        return $saved;
    }

    /**
     * Return post_name slugs for selected dimension items.
     */
    protected static function slugs_from_ids(array $ids, string $post_type): array {
        $out = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            if (!$id) continue;

            $p = get_post($id);
            if (!$p instanceof WP_Post) continue;
            if ($p->post_type !== $post_type) continue;
            if ($p->post_status !== 'publish') continue;

            $slug = trim((string) $p->post_name);
            if ($slug !== '') {
                $out[] = $slug;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Output shape:
     * [
     *   'order' => ['d0','d1'],
     *   'labels' => ['d0' => 'Audience', 'd1' => 'Interests'],
     *   'dimensions' => [
     *      'd0' => ['parent', 'carer'],
     *      'd1' => ['giving']
     *   ]
     * ]
     */
    protected static function page_data(): array {
        $empty = [
            'order'      => [],
            'labels'     => [],
            'dimensions' => [],
        ];

        $post = self::current_post();
        if (!$post) return $empty;
        if (!self::is_enabled_post_type($post)) return $empty;

        $dims = self::dimensions();
        if (empty($dims)) return $empty;

        $saved = self::saved_alignment((int) $post->ID);
        $map   = $saved['dims'] ?? [];
        if (empty($map) || !is_array($map)) return $empty;

        foreach ($dims as $dim) {
            if (!is_array($dim)) continue;

            $dim_key  = sanitize_key($dim['key'] ?? '');
            $dim_pt   = sanitize_key($dim['post_type'] ?? '');
            $dim_name = sanitize_text_field($dim['context'] ?? '');

            if (!$dim_key || !$dim_pt) continue;

            $ids = $map[$dim_key] ?? [];
            if (!is_array($ids) || empty($ids)) continue;

            $ids    = array_values(array_unique(array_filter(array_map('intval', $ids))));
            $values = self::slugs_from_ids($ids, $dim_pt);

            if (empty($values)) continue;

            $empty['order'][] = $dim_key;
            $empty['labels'][$dim_key] = $dim_name ?: $dim_key;
            $empty['dimensions'][$dim_key] = $values;
        }

        return $empty;
    }

    protected static function should_run_update(): bool {
        $data = self::page_data();
        return !empty($data['order']);
    }

    public static function output_page_data(): void {
        if (!self::should_run_update()) return;

        $data = self::page_data();

        echo '<script>window.p26PageProfileData=' . wp_json_encode($data) . ';</script>' . "\n";
    }

    public static function output_profile_script(): void {
        if (!self::should_run_update()) return;

        $persona_action = isset($_GET['persona']) ? sanitize_key(wp_unslash($_GET['persona'])) : '';

        if ('clear' === $persona_action || 'get' === $persona_action) {
            return;
        }

        $profile_key = esc_js(self::storage_key('profile'));
        $max_age     = (int) self::FOREVER;

        echo "<script>(function(){try{
            var PROFILE_KEY = '{$profile_key}';
            var MAXAGE = {$max_age};
            var PAGE = window.p26PageProfileData || {order:[],labels:{},dimensions:{}};

            function getLS(k){
                try { return localStorage.getItem(k) || ''; } catch(e){ return ''; }
            }

            function setLS(k,v){
                try { localStorage.setItem(k,v); } catch(e){}
            }

            function getCookie(n){
                var m = document.cookie.match('(?:^|; )' + n.replace(/([.$?*|{}()\\[\\]\\\\\\/\\+^])/g, '\\\\$1') + '=([^;]*)');
                return m ? decodeURIComponent(m[1]) : '';
            }

            function setCookie(n,v){
                try {
                    document.cookie = n + '=' + encodeURIComponent(v) + '; Path=/; Max-Age=' + MAXAGE;
                } catch(e){}
            }

            function parseJSON(raw, fallback){
                if (!raw) return fallback;
                try {
                    var v = JSON.parse(raw);
                    return (v && typeof v === 'object') ? v : fallback;
                } catch(e){
                    return fallback;
                }
            }

            function isObj(v){
                return !!v && typeof v === 'object' && !Array.isArray(v);
            }

            function arr(v){
                return Array.isArray(v) ? v.filter(Boolean) : [];
            }

            function ensureProfile(v){
                if (!isObj(v)) v = {};
                if (typeof v.persona !== 'string') v.persona = '';
                if (!isObj(v.counters)) v.counters = {};
                return v;
            }

            function bump(map, values){
                map = isObj(map) ? map : {};
                values.forEach(function(value){
                    value = String(value).trim();
                    if (!value) return;
                    map[value] = (parseInt(map[value], 10) || 0) + 1;
                });
                return map;
            }

            function topAll(map){
                map = isObj(map) ? map : {};
                var winners = [];
                var max = -1;

                for (var key in map) {
                    if (!Object.prototype.hasOwnProperty.call(map, key)) continue;
                    var val = parseInt(map[key], 10) || 0;

                    if (val > max) {
                        max = val;
                        winners = [key];
                    } else if (val === max) {
                        winners.push(key);
                    }
                }

                return winners;
            }

            function rebuildPersona(profile, order){
                var parts = [];

                arr(order).forEach(function(dimKey){
                    var winners = topAll(profile.counters[dimKey]);
                    if (winners.length) {
                        parts.push(winners.join(' | '));
                    }
                });

                profile.persona = parts.join(', ');
                return profile;
            }

            function personaValues(profile){
                if (!profile || typeof profile.persona !== 'string' || !profile.persona) return [];
                return profile.persona
                    .split(/\\s*,\\s*|\\s*\\|\\s*/)
                    .map(function(v){ return String(v).trim(); })
                    .filter(Boolean);
            }

            function applyBodyClasses(oldProfile, newProfile){
                if (!document.body) return;

                personaValues(oldProfile).forEach(function(value){
                    document.body.classList.remove(value);
                });

                personaValues(newProfile).forEach(function(value){
                    document.body.classList.add(value);
                });
            }

            var raw = getLS(PROFILE_KEY) || getCookie(PROFILE_KEY);
            var oldProfile = ensureProfile(parseJSON(raw, {persona:'', counters:{}}));

            // clone-ish base so oldProfile remains available for class cleanup
            var profile = ensureProfile(parseJSON(JSON.stringify(oldProfile), {persona:'', counters:{}}));

            var order = arr(PAGE.order);
            var dimensions = isObj(PAGE.dimensions) ? PAGE.dimensions : {};

            order.forEach(function(dimKey){
                var values = arr(dimensions[dimKey]);
                if (!values.length) return;
                profile.counters[dimKey] = bump(profile.counters[dimKey], values);
            });

            profile = rebuildPersona(profile, order);

            var encoded = JSON.stringify(profile);
            setLS(PROFILE_KEY, encoded);
            setCookie(PROFILE_KEY, encoded);

            window.p26 = window.p26 || {};
            window.p26.profile = profile;
            window.p26.page = PAGE;

            function runApplyBodyClasses(){
                applyBodyClasses(oldProfile, profile);
            }
            
            if (document.body) {
                runApplyBodyClasses();
            } else {
                document.addEventListener('DOMContentLoaded', runApplyBodyClasses, { once: true });
            }

        }catch(e){}})();</script>" . "\n";
    }

    public static function add_persona_body_classes(array $classes): array {
        if (is_admin()) return $classes;

        $cookie_key = self::storage_key('profile');
        $raw = isset($_COOKIE[$cookie_key]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_key])) : '';
        if (!$raw || !is_string($raw)) return $classes;

        $decoded = json_decode(wp_unslash($raw), true);
        if (!is_array($decoded) || empty($decoded['persona']) || !is_string($decoded['persona'])) {
            return $classes;
        }

        $parts = preg_split('/\s*,\s*|\s*\|\s*/', $decoded['persona']);
        if (!is_array($parts) || empty($parts)) return $classes;

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') continue;

            $class = sanitize_html_class($part);
            if ($class !== '') {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }

    public static function output_debug(): void {
        if (is_admin()) return;

        $persona_action = isset($_GET['persona']) ? sanitize_key(wp_unslash($_GET['persona'])) : '';
        if ('show' !== $persona_action && 'get' !== $persona_action) return;

        $profile_key = esc_js(self::storage_key('profile'));

        echo '<script>(function(){
            try{
                var raw = localStorage.getItem("'.$profile_key.'") || "";
                var page = window.p26PageProfileData || null;
                var prettyProfile = raw;
                var prettyPage = page
                    ? JSON.stringify(page, null, 2)
                    : "No current page targets found.";

                try {
                    prettyProfile = raw
                        ? JSON.stringify(JSON.parse(raw), null, 2)
                        : "No '.$profile_key.' found in localStorage.";
                } catch(e) {
                    prettyProfile = raw || "No '.$profile_key.' found in localStorage.";
                }

                var wrap = document.createElement("div");
                wrap.style.cssText = "position:relative;z-index:999999;margin:24px auto;max-width:1200px;padding:20px;border:2px solid #111;background:#fff;color:#111;";

                var h1 = document.createElement("div");
                h1.style.cssText = "font:600 16px/1.3 sans-serif;margin:0 0 12px;";
                h1.textContent = "Persona26 Profile";

                var h2 = document.createElement("div");
                h2.style.cssText = "font:600 14px/1.3 sans-serif;margin:18px 0 8px;";
                h2.textContent = "Current Page Targets";

                var pre1 = document.createElement("pre");
                pre1.style.cssText = "margin:0;white-space:pre-wrap;word-break:break-word;font:14px/1.5 monospace;";
                pre1.textContent = prettyPage;

                var h3 = document.createElement("div");
                h3.style.cssText = "font:600 14px/1.3 sans-serif;margin:18px 0 8px;";
                h3.textContent = "Local Profile";

                var pre2 = document.createElement("pre");
                pre2.style.cssText = "margin:0;white-space:pre-wrap;word-break:break-word;font:14px/1.5 monospace;";
                pre2.textContent = prettyProfile;

                wrap.appendChild(h1);
                wrap.appendChild(h2);
                wrap.appendChild(pre1);
                wrap.appendChild(h3);
                wrap.appendChild(pre2);

                document.body.appendChild(wrap);
            }catch(e){}
        })();</script>' . "\n";
    }

    public static function output_clear(): void {
        if (is_admin()) return;
        $persona_action = isset($_GET['persona']) ? sanitize_key(wp_unslash($_GET['persona'])) : '';
        if ('clear' !== $persona_action) return;

        $profile_key = esc_js(self::storage_key('profile'));

        echo '<script>(function(){
            try{
                var KEY = "'.$profile_key.'";

                try { localStorage.removeItem(KEY); } catch(e){}

                try {
                    document.cookie = KEY + "=; Path=/; Max-Age=0";
                    document.cookie = KEY + "=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT";
                } catch(e){}

                if (window.p26 && window.p26.profile) {
                    delete window.p26.profile;
                }

                var msg = document.createElement("div");
                msg.style.cssText = "position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:999999;padding:10px 16px;background:#111;color:#fff;font:14px sans-serif;";
                msg.textContent = "Persona cleared. Reloading...";
                document.body.appendChild(msg);

                var url = window.location.pathname + window.location.hash;
                setTimeout(function(){
                    window.location.replace(url);
                }, 300);

            }catch(e){}
        })();</script>' . "\n";
    }
}

P26_Profile::boot();
