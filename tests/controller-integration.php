<?php
/** Disposable WordPress with this plugin active. */
wp_set_current_user(1);
$requests = array();
$guard = function ($pre, $args, $url) use (&$requests) { $requests[]=$url; return new WP_Error('unexpected_http', $url); };
add_filter('pre_http_request', $guard, 10, 3);
$_GET['force-check']='1';
for ($i=0;$i<10;$i++) {
 $transient=apply_filters('site_transient_update_plugins',(object)array('response'=>array()));
 apply_filters('pre_set_site_transient_update_plugins',$transient);
 $links=apply_filters('plugin_row_meta',array(),'persona26/persona26.php',array(),'all');
}
unset($_GET['force-check']);
if ($requests) { throw new RuntimeException('Metadata rendering performed HTTP'); }
if (count(array_filter($links,function($v){return strpos($v,'>GitHub<')!==false;}))!==1) { throw new RuntimeException('Expected one GitHub link'); }
if (strpos(implode(' ', $links), 'Visit plugin site')!==false) { throw new RuntimeException('Unexpected duplicate site link'); }
remove_filter('pre_http_request',$guard,10);
echo "PASS: local controller integration, unique row links, zero metadata HTTP\n";
