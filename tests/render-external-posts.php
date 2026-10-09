<?php
define('ABSPATH', sys_get_temp_dir() . '/alps-epa-test-' . getmypid() . '/'); define('WPINC', 'fixture-wp'); define('ALPS_GUTENBERG_VERSION', '3.3.0'); define('MINUTE_IN_SECONDS', 60);
@mkdir(ABSPATH . WPINC, 0777, true); file_put_contents(ABSPATH . WPINC . '/feed.php', '<?php');
$GLOBALS['admin']=false; $GLOBALS['cache']=[]; $GLOBALS['calls']=[]; $GLOBALS['shortcodes']=[]; $GLOBALS['actions']=[];
function esc_html($s){return htmlspecialchars($s, ENT_QUOTES,'UTF-8');} function esc_attr($s){return esc_html($s);}
function esc_url_raw($s,$protocols=['http','https']){return preg_match('~^https?://~i',$s)?$s:'';} function esc_url($s){return esc_html(esc_url_raw($s));}
function wp_parse_url($s,$part=-1){return parse_url($s,$part);} function untrailingslashit($s){return rtrim($s,'/');}
function absint($s){return abs((int)$s);} function shortcode_atts($defaults,$atts,$tag){return array_intersect_key(array_merge($defaults,$atts),$defaults);}
function wp_enqueue_style($s){$GLOBALS['style']=$s;} function wp_register_style(...$a){} function plugins_url($s,$f){return $s;}
function register_block_type($p,$a){$GLOBALS['registered']=$a;} function shortcode_exists($s){return isset($GLOBALS['shortcodes'][$s]);} function add_shortcode($s,$cb){$GLOBALS['shortcodes'][$s]=$cb;}
function get_block_wrapper_attributes($a){return 'class="'.esc_attr($a['class']).'"';} function current_user_can($s){return $GLOBALS['admin'];}
function wp_json_encode($s){return json_encode($s);} function get_transient($k){return $GLOBALS['cache'][$k]??false;} function set_transient($k,$v,$ttl){$GLOBALS['cache'][$k]=$v; $GLOBALS['ttl']=$ttl;}
function wp_strip_all_tags($s,$x=false){return strip_tags($s);} function wp_trim_words($s,$n,$more){$words=explode(' ',$s);return implode(' ',array_slice($words,0,$n)).(count($words)>$n?$more:'');}
function __($s,$d){return $s;} function esc_html__($s,$d){return esc_html($s);} function is_wp_error($s){return $s instanceof WP_Error;}
function add_action($n,$cb,$p=10,$a=1){$GLOBALS['actions'][$n]=$cb;} function remove_action($n,$cb,$p){unset($GLOBALS['actions'][$n]);}
class WP_Error{function get_error_message(){return '<script>private detail</script>';}}
class Item {
 public $content='<img src="https://images.example/photo.jpg">'; public $enclosures=[]; public $tags=[];
 function get_permalink(){return 'https://news.example/post?q="';} function get_title(){return '<b>Naujienos &amp; šeima</b>';}
 function get_description(){return '<p>One &amp; two three four five</p>';} function get_content(){return $this->content;}
 function get_enclosures(){return $this->enclosures;} function get_item_tags($ns,$tag){return $this->tags[$tag]??[];}
}
class Feed{
 public $cache=true; public $timeout=0;
 function enable_cache($b){$this->cache=$b;} function set_timeout($n){$this->timeout=$n;}
 function get_title(){return 'Source &amp; news';} function get_link(){return 'https://news.example/';}
 function get_item_quantity($n){$GLOBALS['count']=$n;return $n;} function get_items($start,$n){return array_fill(0,$n,new Item);}
}
function fetch_feed($url){$GLOBALS['calls'][]=$url; $f=new Feed; ($GLOBALS['actions']['wp_feed_options'])($f); $GLOBALS['feed']=$f; if(strpos($url,'throws')!==false)throw new Exception('failure'); return strpos($url,'bad')!==false?new WP_Error:$f;}
require dirname(__DIR__) . '/src/external-posts/class-external-posts-block.php'; $b=new \ALPS\Gutenberg\Blocks\ExternalPostsBlock;
$checks=0; function check($v,$label){global $checks; ++$checks;if(!$v){fwrite(STDERR,"FAIL $label\n");exit(1);}}
check($b->normalizeFeedUrl('https://news.example')==='https://news.example/feed/','homepage');
check($b->normalizeFeedUrl('https://news.example/feed/')==='https://news.example/feed/','feed path');
check($b->normalizeFeedUrl('https://news.example/?feed=rss2')==='https://news.example/?feed=rss2','query feed');
foreach(['javascript:alert(1)','file:///etc/passwd',[],null,''] as $u)check($b->normalizeFeedUrl($u)==='','invalid URL');
$b->init();check(isset($GLOBALS['shortcodes']['external_posts']),'fallback shortcode'); $GLOBALS['shortcodes']['external_posts']='old-handler';$b->init();check($GLOBALS['shortcodes']['external_posts']==='old-handler','old handler preserved');
$html=$b->render(['feeds'=>"https://news.example,https://news.example/feed/\nhttps://other.example/feed/",'number'=>2]);
check(count($GLOBALS['calls'])===2,'normalization and deduplication');check(substr_count($html,'class="alps-epa-feed"')===2,'source grouping');check(substr_count($html,'Naujienos &amp; šeima')===4,'posts per source/entities');
check($GLOBALS['feed']->cache===false && $GLOBALS['feed']->timeout===5,'separate cache disabled and bounded timeout');check(empty($GLOBALS['actions']),'filter cleaned');check($GLOBALS['ttl']===1800,'default TTL');
$b->render(['feeds'=>"https://news.example,https://news.example/feed/\nhttps://other.example/feed/",'number'=>2]);check(count($GLOBALS['calls'])===2,'cache hit');
$html=$b->render(['feeds'=>'https://cards.example/feed/','layout'=>'cards','number'=>500,'cacheMinutes'=>9999,'imageSize'=>9999,'excerptLength'=>3]);
check($GLOBALS['count']===20 && $GLOBALS['ttl']===86400,'bounded count and TTL');check(strpos($html,'--alps-epa-image-size:500px')!==false,'image bound');check(strpos($html,'One &amp; two…')!==false,'excerpt bound/entities');check(strpos($html,'<img ')!==false && strpos($html,'loading="lazy"')!==false,'lazy image');
check(strpos($html,'<b>')===false,'feed markup escaped');check(strpos($html,'tabindex="-1" aria-hidden="true"')!==false,'thumbnail duplicate skipped');
$i=new Item;$i->enclosures=[new class{function get_type(){return 'image/jpeg';}function get_link(){return 'https://images.example/enclosure.jpg';}}];check($b->itemImage($i)==='https://images.example/enclosure.jpg','enclosure');
$i->enclosures=[];$i->tags=['thumbnail'=>[['attribs'=>[''=>['url'=>'https://images.example/media.png']]]]];check($b->itemImage($i)==='https://images.example/media.png','Media RSS');$i->tags=[];check($b->itemImage($i)==='https://images.example/photo.jpg','content image');$i->content='<img src="javascript:alert(1)">';check($b->itemImage($i)==='','unsafe image');
$GLOBALS['calls']=[];$b->render(['feeds'=>implode(',',array_map(fn($n)=>"https://source$n.example",range(1,20)))]);check(count($GLOBALS['calls'])===8,'feed count capped');
$GLOBALS['admin']=true;$html=$b->render(['feeds'=>'https://news.example/feed/,https://bad.example/feed/','debug'=>true]);check(strpos($html,'private detail')!==false && strpos($html,'<script>')===false,'escaped administrator diagnostics');
$GLOBALS['admin']=false;$html=$b->render(['feeds'=>'https://news.example/feed/,https://bad.example/feed/','debug'=>true]);check(strpos($html,'private detail')===false,'no diagnostic cache leak');
$html=$b->render(['feeds'=>'https://bad.example/feed/']);check(strpos($html,'could not be loaded')!==false && strpos($html,'private detail')===false,'public error');
check(strpos($b->render(['feeds'=>[],'number'=>[]]),'No valid feed')!==false,'malformed attributes');
try{$b->fetchFeed('https://throws.example/feed/');}catch(Exception $e){}check(empty($GLOBALS['actions']),'filter removed after exception');
check(strpos($b->render(['feeds'=>'https://headings.example/feed/','layout'=>'cards']), '<h2 class="alps-epa-feed-title">')!==false,'source heading is level two');
check($GLOBALS['style']===$b::HANDLE,'conditional style');unlink(ABSPATH . WPINC . "/feed.php"); rmdir(ABSPATH . WPINC); rmdir(ABSPATH); echo "PASS: $checks external-feed checks\n";
