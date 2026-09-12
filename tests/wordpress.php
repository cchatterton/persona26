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

$scenario = 'manifest'; $requests = array();
$http = function($pre, $args, $url) use (&$scenario, &$requests) {
    $requests[] = $url;
    $response = function($code, $body='', $headers=array()) {return array('response'=>array('code'=>$code), 'body'=>$body, 'headers'=>$headers);};
    if ($scenario === 'rate') return $response(429);
    if ($scenario === 'error') return new WP_Error('transport','Sensitive diagnostic');
    if (str_contains($url,'raw.githubusercontent.com')) {
        if ($scenario === 'redirect' || $scenario === 'api') return $response(404);
        return $response(200, wp_json_encode(array('version'=>$scenario === 'current' ? P26_VERSION : '99.0.0', 'body'=>'Release notes', 'package'=>'https://evil.test/plugin.zip')));
    }
    if (str_contains($url,'api.github.com')) {
        return $response(200, wp_json_encode(array('tag_name'=>'v99.0.0','body'=>'API notes','assets'=>array(array('name'=>'persona26.zip','browser_download_url'=>'https://github.com/cchatterton/persona26/releases/download/v99.0.0/persona26.zip')))));
    }
    if ($scenario === 'api') return $response(302,'',array('location'=>'https://evil.test/releases/tag/v99.0.0'));
    return $response(302,'',array('location'=>'https://github.com/cchatterton/persona26/releases/tag/v99.0.0'));
};
add_filter('pre_http_request', $http, 10, 3);
$clear = new ReflectionMethod(P26_GitHub_Updater::class, 'clear_release_cache');
$base = (object)array('response'=>array('other/plugin.php'=>(object)array('new_version'=>'2')), 'no_update'=>array());
$check = function($mode) use (&$scenario, &$requests, $clear, $base) {
    $scenario=$mode; $requests=array(); $clear->invoke(null);
    return P26_GitHub_Updater::inject_update(clone $base);
};
$t=$check('manifest');
p26_test(count($requests)===1, 'Valid manifest uses one request and no API call');
p26_test($t->response['persona26/persona26.php']->package === 'https://github.com/cchatterton/persona26/releases/download/v99.0.0/persona26.zip', 'Package URL is restricted to configured repository');
P26_GitHub_Updater::inject_update($t);
p26_test(count($requests)===1, 'Repeated transient hooks reuse cache');
p26_test(isset($t->response['other/plugin.php']), 'Other plugin updates remain intact');
$t=$check('current');
p26_test(!isset($t->response['persona26/persona26.php']), 'Equal version removes stale update');
$t=$check('redirect');
p26_test(count($requests)===2 && isset($t->response['persona26/persona26.php']), 'Public release redirect works without API or HEAD');
$t=$check('api');
p26_test(count($requests)===3 && isset($t->response['persona26/persona26.php']), 'API used only after both earlier sources fail validation');
$t=$check('rate');
P26_GitHub_Updater::inject_update($t);
p26_test(count($requests)===1 && !get_site_transient('p26_github_latest_release') && get_site_transient('p26_github_backoff'), 'Rate limit stops lookup and activates separate backoff');
$t=$check('error');
p26_test(!isset($t->no_update['persona26/persona26.php']) && !get_site_transient('p26_github_latest_release'), 'Transport failures do not become release or no-update state');
$scenario='manifest'; $requests=array();
$_GET['p26_check_updates']='1'; $_GET['_wpnonce']=wp_create_nonce('p26_check_updates');
$t=P26_GitHub_Updater::inject_update($t);
P26_GitHub_Updater::inject_update($t);
p26_test(count($requests)===1 && isset($t->response['persona26/persona26.php']), 'Authorised manual check bypasses backoff once per request');
unset($_GET['p26_check_updates'], $_GET['_wpnonce']);
wp_set_current_user(0);
$links=P26_GitHub_Updater::plugin_row_meta(array(),'persona26/persona26.php');
p26_test(count($links)===1 && str_contains($links[0],'GitHub'), 'Update check link is capability-gated');
remove_filter('pre_http_request', $http, 10); $clear->invoke(null);
wp_delete_post($content,true);
echo "WordPress integration checks passed.\n";
