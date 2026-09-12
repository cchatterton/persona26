<?php
/** Report-shaped saved configuration fixtures; disposable WordPress only. */
wp_set_current_user(1);
function p26_ftest($ok, $message) { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
global $wpdb;
$settings = get_option(P26_SETTINGS_OPTION); $mapping = get_option(P26_LEGACY_MAP); $active = get_option('active_plugins');
$ids = []; $saved = [];
$names = ['aioseo_options','wpe_cache_config','tncp_plan___interest','tncp_plan___persona','tncp_plan_migration_interest','tncp_plan_migration_audience','tncp_patterns'];
foreach ($names as $name) { $saved[$name] = get_option($name); delete_option($name); }
$fixture = WP_PLUGIN_DIR . '/p26-format-fixture'; mkdir($fixture);
file_put_contents($fixture . '/personas.php', "<?php\n/* Plugin Name: Personas\nVersion: 3.1\n*/");
wp_clean_plugins_cache(); update_option('active_plugins', array_merge($active, ['p26-format-fixture/personas.php']));
foreach (['__persona','__interest','migration_audience','migration_interest'] as $type) register_post_type($type,['public'=>true]);
$make = static function($type, $content='') use (&$ids) { $id=wp_insert_post(['post_type'=>$type,'post_status'=>'publish','post_title'=>wp_generate_uuid4(),'post_content'=>wp_slash($content)]); $ids[]=$id; return $id; };
try {
 p26_legacy_delete_journal(); delete_option(P26_LEGACY_COMPAT);
 update_option(P26_SETTINGS_OPTION,['tracked'=>[['post_type'=>'migration_audience','context'=>'Audience'],['post_type'=>'migration_interest','context'=>'Interest']],'content_post_types'=>['post']]);
 update_option(P26_LEGACY_MAP,['__persona'=>'d0','__interest'=>'d1']);
 $a=$make('__persona'); $i=$make('__interest'); $post=$make('post');
 add_post_meta($post,'target_personas',[(string)$a,'999999999']); add_post_meta($post,'target_interests',['999999998',(string)$i]);
 add_post_meta($i,'_tncp_pattern','__interest-0-single-0');
 $definitions = [
  ['block_lab', json_encode(['fields'=>['post-type'=>['options'=>[['value'=>'post','label'=>'Posts'],['value'=>'__interest','label'=>'Interest'],['value'=>'__persona','label'=>'Persona']],'default'=>'__interest','name'=>'post-type','control'=>'select'],'empty'=>new stdClass()]])],
  ['acf-field-group',serialize(['location'=>[[['param'=>'post_type','operator'=>'==','value'=>'__interest'],['param'=>'post_type','operator'=>'!=','value'=>'__persona']]],'position'=>'side'])],
  ['acf-taxonomy',serialize(['taxonomy'=>'__persona-group','object_type'=>['__persona'],'labels'=>['name'=>'Persona groups']])],
  ['acf-taxonomy',serialize(['taxonomy'=>'__interest_group','object_type'=>['__interest']])],
 ];
 $definition_ids=[]; foreach ($definitions as [$type,$body]) $definition_ids[]=$make($type,$body);
 $options=[
  'aioseo_options'=>json_encode(['sitemap'=>['general'=>['postTypes'=>['all'=>true,'included'=>['post','__interest']],'taxonomies'=>['all'=>true,'included'=>['category']]]]]),
  'wpe_cache_config'=>['sanitized_custom_post_types'=>['post'=>'post','__interest'=>'__interest'],'sanitized_builtin_post_types'=>['post'=>'post','page'=>'page']],
  'tncp_plan___interest'=>['revision'=>4,'rows'=>[['id'=>'stable-row','post_id'=>$i,'pattern'=>'__interest-0-single-0','title'=>'Interest','parent'=>'post:'.$a,'snapshot'=>['title'=>'Interest']]]],
  'tncp_plan___persona'=>['revision'=>1,'rows'=>[]],
  'tncp_patterns'=>['revision'=>2,'entries'=>['__interest-0-single-0'=>['post_id'=>$i,'status'=>'done','description'=>'Example']]],
 ];
 foreach ($options as $name=>$value) update_option($name,$value,false);
 // Prewarm both option names to exercise invalidation of WordPress negative caches.
 get_option('tncp_plan_migration_interest');
 $journal=p26_legacy_simulate();
 p26_ftest(!$journal['plan']['blockers'],'All reported saved formats simulate: '.implode('; ',$journal['plan']['blockers']));
 p26_ftest(count($journal['plan']['skipped_missing'])===2,'Deleted IDs are counted without blocking valid tags');
 p26_legacy_commit($journal['token']);
 p26_ftest(get_post_type($a)==='migration_audience' && get_post_type($i)==='migration_interest','Destination records keep their IDs');
 p26_ftest(get_post_meta($post,'p26_legacy_d0_ids',true)===[(string)$a] && get_post_meta($post,'p26_legacy_d1_ids',true)===[(string)$i],'Only valid selections migrate');
 p26_ftest(get_post_meta($post,'target_personas',true)===[(string)$a,'999999999'],'Original broken selections remain untouched');
 $json=json_decode(get_post_field('post_content',$definition_ids[0]));
 p26_ftest($json->fields->{'post-type'}->options[1]->value==='migration_interest' && $json->fields->{'post-type'}->options[2]->label==='Persona' && $json->fields->empty instanceof stdClass,'Dropdown values and defaults change while labels and empty objects survive');
 $rules=unserialize(get_post_field('post_content',$definition_ids[1]));
 p26_ftest($rules['location'][0][0]['value']==='migration_interest' && $rules['location'][0][1]['value']==='migration_audience' && $rules['location'][0][1]['operator']==='!=','Serialized ACF location rules preserve operators');
 $tax=unserialize(get_post_field('post_content',$definition_ids[2]));
 p26_ftest($tax['taxonomy']==='__persona-group' && $tax['object_type']===['migration_audience'],'Taxonomy identity retained and post-type assignment moved');
 p26_ftest(json_decode(get_option('aioseo_options'),true)['sitemap']['general']['postTypes']['included']===['post','migration_interest'],'AIOSEO included post types migrate');
 p26_ftest(get_option('wpe_cache_config')['sanitized_custom_post_types']===['post'=>'post','migration_interest'=>'migration_interest'],'Cache post-type map keys and values migrate');
 $plan=get_option('tncp_plan_migration_interest');
 p26_ftest(false===get_option('tncp_plan___interest') && $plan['revision']===4 && $plan['rows'][0]['post_id']===$i && $plan['rows'][0]['pattern']==='migration_interest-0-single-0','Content Planner option name, pattern and stable row IDs migrate');
 p26_ftest(get_option('tncp_plan_migration_audience')===['revision'=>1,'rows'=>[]],'Empty plan discovered through its option name');
 p26_ftest(isset(get_option('tncp_patterns')['entries']['migration_interest-0-single-0']) && get_post_meta($i,'_tncp_pattern',true)==='migration_interest-0-single-0','XP catalog keys and post metadata migrate together');
 update_option('tncp_plan___interest',['revision'=>99,'rows'=>[]],false);
 try { p26_legacy_rollback($journal['token'],true); throw new LogicException('Expected rollback conflict'); } catch (RuntimeException $e) { echo "PASS: Recreated original plans block recovery check\n"; }
 delete_option('tncp_plan___interest');
 p26_legacy_rollback($journal['token']);
 foreach ($options as $name=>$value) p26_ftest(get_option($name)===$value,'Exact option rollback: '.$name);
 foreach ($definition_ids as $index=>$id) p26_ftest(get_post_field('post_content',$id)===$definitions[$index][1],'Exact definition bytes restored: '.$index);
 p26_ftest(false===get_option('tncp_plan_migration_interest'),'Rollback clears destination option caches');
 update_option('tncp_plan_migration_interest',['revision'=>1,'rows'=>[]],false);
 $conflict=p26_legacy_simulate();
 p26_ftest((bool)array_filter($conflict['plan']['blockers'],static fn($s)=>str_contains($s,'Destination Content Planner plan already exists')),'Existing destination plans protected from overwrite');
 $map=p26_legacy_mapping();
 try { p26_legacy_transform(['postTypes'=>['__interest'=>1,'migration_interest'=>2]],$map); throw new LogicException('Expected collision'); } catch (RuntimeException $e) { echo "PASS: Post-type dictionary collisions rejected\n"; }
} finally {
 delete_option(P26_LEGACY_COMPAT); p26_legacy_delete_journal();
 foreach ($ids as $id) wp_delete_post($id,true);
 foreach ($saved as $name=>$value) { if(false===$value)delete_option($name);else update_option($name,$value); }
 update_option(P26_SETTINGS_OPTION,$settings); if(false===$mapping)delete_option(P26_LEGACY_MAP);else update_option(P26_LEGACY_MAP,$mapping);
 update_option('active_plugins',$active); unlink($fixture.'/personas.php');rmdir($fixture);wp_clean_plugins_cache();
}
