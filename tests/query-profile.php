<?php
// Dependency-free boundary tests; no installed site or data is modified.
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function add_filter(...$args) {}
function p26_dimensions() { return [
    ['key'=>'d3', 'post_type'=>'state'],
    ['key'=>'d5', 'post_type'=>'__audience'],
    ['key'=>'d6', 'post_type'=>'persona'],
]; }
function post_type_exists($type) { return in_array($type, ['state','__audience','persona'], true); }
function wp_unslash($value) { return stripslashes($value); }
class WP_Post {
    public $post_type;
    public $post_name;
    public $post_status;
    function __construct($type, $slug, $status = 'publish') {
        $this->post_type=$type; $this->post_name=$slug; $this->post_status=$status;
    }
}
function get_posts($args) {
    if ($args['post_status'] !== 'publish' || $args['posts_per_page'] !== 1) throw new RuntimeException('Unbounded or non-public query');
    foreach ([new WP_Post('state','qld'), new WP_Post('__audience','parents'), new WP_Post('state','draft','draft')] as $post) {
        if ($args['name'] === $post->post_name && $args['post_type'] === $post->post_type) return [$post];
    }
    return [];
}
require __DIR__ . '/../persona26/functions/profile.php';
class TestProfile extends P26_Profile {
    public static function selections($action='') { return parent::query_selections($action); }
}
function check($expected, $action='') {
    if (TestProfile::selections($action) !== $expected) throw new RuntimeException('Unexpected selections: '.json_encode($_GET));
}
$_GET=['state'=>'qld','__audience'=>'parents','untracked'=>'qld'];
check(['d3'=>'qld','d5'=>'parents']);
check([], 'get'); check([], 'clear');
check(['d3'=>'qld','d5'=>'parents'], 'show');
foreach (['draft', 'parents', '', 'QLD', 'qld/other', '<script>', '__proto__', ['qld']] as $invalid) {
    $_GET=['state'=>$invalid]; check([]);
}
$_GET=['d3'=>'qld','persona'=>'qld','audience'=>'parents']; check([]);
echo "PASS: exact CPT names, valid published slugs, multiple dimensions, invalid/array values, reserved persona and get/clear/show\n";
