<?php
if (!defined('ABSPATH')) exit;

/**
 * Persona26 Meta Boxes (scalable)
 *
 * - Adds a native WP metabox to post types selected in "Content Profiling Scope"
 * - Renders ONE tag-picker per Engagement Dimension (unlimited rows)
 * - Stores selections in ONE meta key: p26_alignment
 *
 * Meta shape:
 *   [
 *     'dims' => [
 *        'd0' => [12,45],   // dimension 0 selected post IDs
 *        'd1' => [7],
 *        'd2' => [3,9],
 *     ]
 *   ]
 */

if (!defined('P26_SETTINGS_OPTION')) {
    define('P26_SETTINGS_OPTION', 'p26_settings');
}

if (!defined('P26_ALIGNMENT_META')) {
    define('P26_ALIGNMENT_META', 'p26_alignment');
}

if (!defined('P26_META_NONCE')) {
    define('P26_META_NONCE', 'p26_alignment_nonce');
}

/* ---------------------------------------------------------
 * Settings helpers
 * --------------------------------------------------------- */
function p26_settings(): array {
    $settings = get_option(P26_SETTINGS_OPTION);
    return is_array($settings) ? $settings : [];
}

function p26_profiled_post_types(): array {
    $settings = p26_settings();
    $pts = $settings['content_post_types'] ?? [];
    if (!is_array($pts)) return [];
    $pts = array_values(array_filter(array_map('sanitize_key', $pts)));
    return $pts;
}

/**
 * Returns dimensions from options repeater.
 * Each entry:
 *   [
 *     'key'      => 'd0',
 *     'post_type'=> 'page',
 *     'context'  => 'Audience',
 *     'label'    => 'Audience (Page)',
 *   ]
 */
function p26_dimensions(): array {
    $settings = p26_settings();
    $tracked = $settings['tracked'] ?? [];
    if (!is_array($tracked)) $tracked = [];

    $dims = [];
    $i = 0;

    foreach ($tracked as $row) {
        if (!is_array($row)) continue;

        $pt = sanitize_key($row['post_type'] ?? '');
        $cx = sanitize_text_field($row['context'] ?? '');

        // allow empty rows, but don't render if no post_type
        if (!$pt) {
            $i++;
            continue;
        }

        $dims[] = [
            'key'       => 'd' . $i,
            'post_type' => $pt,
            'context'   => $cx ?: ('Dimension ' . ($i + 1)),
            'label'     => ($cx ?: ('Dimension ' . ($i + 1))) . ' (' . p26_post_type_label($pt) . ')',
        ];

        $i++;
    }

    return $dims;
}

function p26_post_type_label(string $post_type): string {
    $obj = get_post_type_object($post_type);
    if ($obj && !empty($obj->labels->singular_name)) return (string) $obj->labels->singular_name;
    return $post_type;
}

function p26_fetch_dimension_items(string $post_type, int $limit = 200): array {
    if (!$post_type) return [];

    $posts = get_posts([
        'post_type'      => $post_type,
        'posts_per_page' => $limit,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);

    $items = [];
    foreach ($posts as $p) {
        $items[] = [
            'id'    => (int) $p->ID,
            'title' => (string) $p->post_title,
        ];
    }
    return $items;
}

/* ---------------------------------------------------------
 * Metabox registration
 * --------------------------------------------------------- */
function p26_register_alignment_metaboxes(): void {
    $post_types = p26_profiled_post_types();
    if (empty($post_types)) return;

    foreach ($post_types as $pt) {
        add_meta_box(
            'p26-intended-engagement-alignment',
            'Persona Targets',
            'p26_render_alignment_metabox',
            $pt,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'p26_register_alignment_metaboxes');

/* ---------------------------------------------------------
 * Render metabox
 * --------------------------------------------------------- */
function p26_render_alignment_metabox(\WP_Post $post): void {
    $dims = p26_dimensions();

    if (empty($dims)) {
        echo '<p><em>Set your Engagement Dimensions in Persona26 first.</em></p>';
        return;
    }

    $saved = get_post_meta($post->ID, P26_ALIGNMENT_META, true);
    $saved = is_array($saved) ? $saved : [];

    // Back-compat: if old shape exists, map to d0/d1 once for UI
    if (!isset($saved['dims']) || !is_array($saved['dims'])) {
        $saved['dims'] = [];
        if (isset($saved['primary']) && is_array($saved['primary'])) {
            $saved['dims']['d0'] = array_values(array_unique(array_map('intval', $saved['primary'])));
        }
        if (isset($saved['secondary']) && is_array($saved['secondary'])) {
            $saved['dims']['d1'] = array_values(array_unique(array_map('intval', $saved['secondary'])));
        }
    }

    wp_nonce_field('p26_save_alignment', P26_META_NONCE);

    // Namespace so multiple metaboxes never collide
    $uid = 'p26_' . $post->ID . '_' . wp_rand(1000, 9999);

    // Styles (WP-ish tag pills)
    ?>
    <style>
        .p26-tagbox{border:1px solid #c3c4c7;border-radius:2px;padding:6px;background:#fff}
        .p26-tagbox .p26-tags{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 6px}
        .p26-tag{display:inline-flex;align-items:center;gap:6px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:2px;padding:2px 6px;font-size:12px;line-height:1.6}
        .p26-tag button{border:0;background:transparent;cursor:pointer;padding:0;margin:0;color:#b32d2e;font-size:14px;line-height:1}
        .p26-addrow{display:flex;gap:6px}
        .p26-addrow input{width:100%}
        .p26-dropdown{display:none;max-height:180px;overflow:auto;border:1px solid #c3c4c7;background:#fff;margin-top:6px}
        .p26-dropdown.open{display:block}
        .p26-option{padding:6px 8px;cursor:pointer}
        .p26-option:hover{background:#f6f7f7}
        .p26-help{margin:6px 0 0;color:#646970;font-size:12px}
        .p26-section{margin-bottom:12px}
        .p26-section:last-child{margin-bottom:0}
        .p26-label{display:block;margin:0 0 6px;font-weight:600}
    </style>
    <?php

    foreach ($dims as $dim) {
        $dim_key = $dim['key'];
        $items   = p26_fetch_dimension_items($dim['post_type']);
        $selected = $saved['dims'][$dim_key] ?? [];
        $selected = is_array($selected) ? array_values(array_unique(array_map('intval', $selected))) : [];

        echo '<div class="p26-section" id="'.esc_attr($uid . '_' . $dim_key).'">';
        echo '<span class="p26-label">'.esc_html($dim['label']).'</span>';
        p26_render_tagpicker_ui(
            $uid . '_' . $dim_key,
            'p26_alignment_dims['.$dim_key.']',
            $items,
            $selected
        );
        echo '</div>';
    }

    echo '<p class="p26-help">These selections power the <strong>Actual</strong> matrix.</p>';

    // One JS init pass for all pickers in this metabox
    ?>
    <script>
    (function(){
        function initPicker(root){
            var tagsWrap   = root.querySelector('.p26-tags');
            var input      = root.querySelector('.p26-input');
            var dropdown   = root.querySelector('.p26-dropdown');
            var options    = Array.from(root.querySelectorAll('.p26-option'));
            var hiddenWrap = root.querySelector('.p26-hidden');

            function selectedIds(){
                return Array.from(hiddenWrap.querySelectorAll('input[type="hidden"]')).map(function(i){ return i.value; });
            }
            function close(){ dropdown.classList.remove('open'); }
            function open(){
                var selected = new Set(selectedIds());
                options.forEach(function(opt){
                    opt.style.display = selected.has(opt.dataset.id) ? 'none' : '';
                });
                dropdown.classList.add('open');
            }
            function addTag(id, label){
                // pill
                var pill = document.createElement('span');
                pill.className = 'p26-tag';
                pill.dataset.id = id;
                pill.innerHTML = '<span class="p26-tag-label"></span><button type="button" aria-label="Remove">×</button>';
                pill.querySelector('.p26-tag-label').textContent = label;

                pill.querySelector('button').addEventListener('click', function(){
                    var hid = hiddenWrap.querySelector('input[value="'+CSS.escape(id)+'"]');
                    if (hid) hid.remove();
                    pill.remove();
                });

                tagsWrap.appendChild(pill);

                // hidden input (for save_post)
                var hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = root.dataset.field + '[]';
                hid.value = id;
                hiddenWrap.appendChild(hid);
            }

            input.addEventListener('click', function(){
                if (dropdown.classList.contains('open')) close(); else open();
            });

            options.forEach(function(opt){
                opt.addEventListener('click', function(){
                    addTag(opt.dataset.id, opt.dataset.label);
                    close();
                });
            });

            // remove buttons on pre-rendered pills
            root.querySelectorAll('.p26-tag button').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var pill = btn.closest('.p26-tag');
                    if (!pill) return;
                    var id = pill.dataset.id;
                    var hid = hiddenWrap.querySelector('input[value="'+CSS.escape(id)+'"]');
                    if (hid) hid.remove();
                    pill.remove();
                });
            });

            // click outside closes
            document.addEventListener('click', function(e){
                if (!root.contains(e.target)) close();
            });
        }

        document.querySelectorAll('#<?php echo esc_js('p26-intended-engagement-alignment'); ?>, .p26-tagbox').forEach(function(box){
            // only init our tagboxes (they have data-field)
            if (box.classList && box.classList.contains('p26-tagbox') && box.dataset.field) {
                initPicker(box);
            }
        });
    })();
    </script>
    <?php
}

/**
 * Render a tag-picker UI.
 *
 * @param string $id Unique DOM id base
 * @param string $field Base field name (without [])
 * @param array  $items [{id,title}]
 * @param array  $selected_ids [int...]
 */
function p26_render_tagpicker_ui(string $id, string $field, array $items, array $selected_ids): void {
    $selected_ids = array_map('intval', $selected_ids);
    $selected_map = array_flip($selected_ids);

    echo '<div class="p26-tagbox" id="'.esc_attr($id).'_picker" data-field="'.esc_attr($field).'">';
    echo '<div class="p26-tags">';

    foreach ($items as $it) {
        $pid = (int)$it['id'];
        if (!isset($selected_map[$pid])) continue;

        echo '<span class="p26-tag" data-id="'.esc_attr((string)$pid).'">';
        echo '<span class="p26-tag-label">'.esc_html($it['title']).'</span>';
        echo '<button type="button" aria-label="Remove">×</button>';
        echo '</span>';
    }

    echo '</div>';

    echo '<div class="p26-addrow">';
    echo '<input type="text" class="p26-input" readonly value="Click to add…" />';
    echo '</div>';

    echo '<div class="p26-dropdown">';
    foreach ($items as $it) {
        $pid = (int)$it['id'];
        echo '<div class="p26-option" data-id="'.esc_attr((string)$pid).'" data-label="'.esc_attr($it['title']).'">'.esc_html($it['title']).'</div>';
    }
    echo '</div>';

    // Hidden inputs
    echo '<div class="p26-hidden" style="display:none;">';
    foreach ($selected_ids as $pid) {
        echo '<input type="hidden" name="'.esc_attr($field).'[]" value="'.esc_attr((string)$pid).'" />';
    }
    echo '</div>';

    echo '</div>';
}

/* ---------------------------------------------------------
 * Save handler
 * --------------------------------------------------------- */
function p26_save_alignment_meta($post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $nonce = isset($_POST[P26_META_NONCE]) ? sanitize_text_field(wp_unslash($_POST[P26_META_NONCE])) : '';
    if (!wp_verify_nonce($nonce, 'p26_save_alignment')) return;

    if (!current_user_can('edit_post', $post_id)) return;

    $post_type = get_post_type($post_id);
    if (!$post_type) return;

    $scoped = p26_profiled_post_types();
    if (!in_array($post_type, $scoped, true)) return;

    $posted_dims = isset($_POST['p26_alignment_dims']) ? (array) wp_unslash($_POST['p26_alignment_dims']) : [];
    if (!is_array($posted_dims)) $posted_dims = [];

    $clean = [];
    foreach ($posted_dims as $dim_key => $ids) {
        $dim_key = sanitize_key($dim_key); // e.g. d0, d1
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!empty($ids)) {
            $clean[$dim_key] = $ids;
        }
    }

    if (empty($clean)) {
        delete_post_meta($post_id, P26_ALIGNMENT_META);
        return;
    }

    update_post_meta($post_id, P26_ALIGNMENT_META, [
        'dims' => $clean,
    ]);
}
add_action('save_post', 'p26_save_alignment_meta', 10, 1);
