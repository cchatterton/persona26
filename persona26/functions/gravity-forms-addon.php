<?php
/**
 * Persona26 Gravity Forms feed add-on class.
 */

if (!defined('ABSPATH')) {
    exit;
}

class P26_Gravity_Forms_Addon extends GFFeedAddOn {
    protected $_version = P26_VERSION;
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'persona26';
    protected $_path = 'persona26/persona26.php';
    protected $_full_path = P26_PLUGIN_FILE;
    protected $_title = 'Persona26';
    protected $_short_title = 'Persona26';
    protected $_async_feed_processing = false;

    private static $_instance = null;
    private static array $pending_updates = array();
    private static array $pending_updates_by_entry = array();

    public static function get_instance() {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();
        add_filter('gform_confirmation', array($this, 'inject_confirmation_profile_script'), 10, 4);
    }

    public function feed_settings_fields() {
        return array(
            array(
                'title' => esc_html__('Persona26 Feed Settings', 'persona26'),
                'fields' => array(
                    array(
                        'type' => 'text',
                        'name' => 'feedName',
                        'label' => esc_html__('Feed Name', 'persona26'),
                        'required' => true,
                        'class' => 'medium',
                    ),
                    array(
                        'type' => 'field_select',
                        'name' => 'sourceField',
                        'label' => esc_html__('Source Field', 'persona26'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'targetDimension',
                        'label' => esc_html__('Target Dimension', 'persona26'),
                        'required' => true,
                        'choices' => $this->dimension_choices(),
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'valueMode',
                        'label' => esc_html__('Value Mode', 'persona26'),
                        'default_value' => 'match_dimension',
                        'choices' => array(
                            array(
                                'label' => esc_html__('Match submitted value to a Persona26 dimension item', 'persona26'),
                                'value' => 'match_dimension',
                            ),
                            array(
                                'label' => esc_html__('Use submitted value as a slug', 'persona26'),
                                'value' => 'submitted_slug',
                            ),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'updateMode',
                        'label' => esc_html__('Update Mode', 'persona26'),
                        'default_value' => 'increment',
                        'choices' => array(
                            array(
                                'label' => esc_html__('Increment this persona value', 'persona26'),
                                'value' => 'increment',
                            ),
                            array(
                                'label' => esc_html__('Replace this dimension with this value', 'persona26'),
                                'value' => 'replace',
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    public function feed_list_columns() {
        return array(
            'feedName' => esc_html__('Name', 'persona26'),
            'targetDimension' => esc_html__('Dimension', 'persona26'),
            'sourceField' => esc_html__('Source Field', 'persona26'),
        );
    }

    public function get_column_value_targetDimension($feed) {
        $dim_key = sanitize_key((string) rgars($feed, 'meta/targetDimension'));
        $dimension = p26_gravity_forms_dimension($dim_key);

        return $dimension ? esc_html((string) $dimension['label']) : esc_html($dim_key);
    }

    public function process_feed($feed, $entry, $form) {
        $payloads = $this->profile_update_payloads($feed, $entry);
        if (empty($payloads)) {
            return;
        }

        $entry_id = isset($entry['id']) ? (int) $entry['id'] : 0;
        if ($entry_id > 0) {
            foreach ($payloads as $payload) {
                self::$pending_updates_by_entry[$entry_id][] = $payload;
            }
        }

        foreach ($payloads as $payload) {
            self::$pending_updates[] = $payload;
        }

        p26_set_gravity_forms_pending_profile_cookie(self::$pending_updates);
    }

    public function inject_confirmation_profile_script($confirmation, $form, $entry, $ajax) {
        $entry_id = isset($entry['id']) ? (int) $entry['id'] : 0;
        if ($entry_id <= 0 || empty(self::$pending_updates_by_entry[$entry_id])) {
            return $confirmation;
        }

        if (!is_string($confirmation)) {
            return $confirmation;
        }

        return $confirmation . p26_gravity_forms_profile_update_script(self::$pending_updates_by_entry[$entry_id], true);
    }

    private function dimension_choices(): array {
        $choices = array();
        $dimensions = p26_gravity_forms_dimensions();

        foreach ($dimensions as $dimension) {
            $choices[] = array(
                'label' => (string) $dimension['label'],
                'value' => (string) $dimension['key'],
            );
        }

        if (empty($choices)) {
            $choices[] = array(
                'label' => esc_html__('Configure Persona26 dimensions first', 'persona26'),
                'value' => '',
            );
        }

        return $choices;
    }

    private function profile_update_payloads($feed, $entry): array {
        $meta = isset($feed['meta']) && is_array($feed['meta']) ? $feed['meta'] : array();

        $field_id = sanitize_text_field((string) ($meta['sourceField'] ?? ''));
        $dim_key = sanitize_key((string) ($meta['targetDimension'] ?? ''));
        $value_mode = sanitize_key((string) ($meta['valueMode'] ?? 'match_dimension'));
        $update_mode = sanitize_key((string) ($meta['updateMode'] ?? 'increment'));

        if ('' === $field_id || '' === $dim_key) {
            return array();
        }

        $dimension = p26_gravity_forms_dimension($dim_key);
        if (empty($dimension)) {
            return array();
        }

        $raw_values = $this->entry_field_values($entry, $field_id);
        if (empty($raw_values)) {
            return array();
        }

        $payloads = array();

        $payload_index = 0;

        foreach ($raw_values as $raw_value) {
            $value = p26_gravity_forms_resolve_value($raw_value, $dimension, $value_mode);
            if ('' === $value) {
                continue;
            }

            $payloads[] = array(
                'dimKey' => $dim_key,
                'value' => $value,
                'mode' => ('replace' === $update_mode && 0 === $payload_index) ? 'replace' : 'increment',
                'order' => p26_gravity_forms_dimension_order(),
            );

            $payload_index++;
        }

        return $payloads;
    }

    private function entry_field_values($entry, string $field_id): array {
        $values = array();
        $direct_value = rgar($entry, $field_id);

        if (is_array($direct_value)) {
            foreach ($direct_value as $value) {
                $values[] = $value;
            }
        } elseif ('' !== (string) $direct_value) {
            $values[] = $direct_value;
        }

        $prefix = $field_id . '.';
        foreach ($entry as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, $prefix)) {
                continue;
            }

            if ('' !== (string) $value) {
                $values[] = $value;
            }
        }

        $values = array_map(
            static fn($value) => sanitize_text_field((string) $value),
            $values
        );

        return array_values(array_unique(array_filter($values)));
    }
}
