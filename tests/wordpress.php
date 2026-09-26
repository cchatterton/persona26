<?php
// Run against a disposable installation: wp eval-file tests/wordpress.php.
function p26_test($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
wp_set_current_user(1);
register_post_type('p26_test_audience', array('public'=>true, 'show_ui'=>true));
update_option(P26_SETTINGS_OPTION, array('tracked'=>array(array('post_type'=>'p26_test_audience','context'=>'Audience'),array('post_type'=>'category','context'=>'Interests')), 'content_post_types'=>array('post')));
$dimension = wp_insert_post(array('post_type'=>'p26_test_audience','post_status'=>'publish','post_title'=>'Parents'));
$content = wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_title'=>'Persona test'));
$alignment = array('dims'=>array('d0'=>array($dimension)));
update_post_meta($content, P26_ALIGNMENT_META, $alignment);
p26_sync_alignment_mirrors($content, $alignment);
p26_test(get_post_meta($content, 'p26_test_audience', true) === 'Parents', 'Exact dimension metadata is preserved');
p26_test(p26_validate_alignment_dimensions(array('d0'=>array($content, $dimension))) === array('d0'=>array($dimension)), 'Alignment rejects selections from other post types');
wp_update_post(array('ID'=>$dimension,'post_title'=>'Caregivers'));
p26_test(get_post_meta($content, 'p26_test_audience', true) === 'Caregivers', 'Renamed dimension refreshes scalar metadata');
p26_rebuild_personalize_css();
p26_test(str_contains(get_option(P26_PERSONALIZE_CSS_OPTION), 'parents'), 'Personalisation CSS reflects configured dimensions');
wp_delete_post($dimension, true);
p26_test(get_option(P26_PERSONALIZE_CSS_OPTION) === '', 'Deleting a dimension item rebuilds personalisation CSS');
$_COOKIE['p26_id'] = array('invalid');
p26_test(p26_cookie_id() === '', 'Malformed identity cookies are rejected');
$_COOKIE['p26_id'] = str_repeat('a',64);
p26_insert_map(p26_cookie_id(), 123);
global $wpdb;
$map_count = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . p26_table_name());
p26_deactivate();
p26_test((int)$wpdb->get_var('SELECT COUNT(*) FROM ' . p26_table_name()) === $map_count, 'Deactivation preserves visitor history');
p26_test(get_option('p26_db_version') === P26_DB_VERSION, 'Deactivation preserves schema version');
p26_test(p26_build_users_heatmap(p26_get_settings(), array((object)array('ID'=>1)), array((object)array('ID'=>2)))['visitors_total'] === 0, 'Absent analytics fails safely');

require __DIR__ . '/controller-integration.php';
wp_delete_post($content,true);
echo "WordPress integration checks passed.\n";
