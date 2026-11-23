<?php
/**
 * Plugin Name: LNKTA Async Popup
 * Description: Open one saved Elementor template as an on-demand menu, retaining it until page navigation.
 * Version: 1.2.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/pafmobile/lnkta-async-popup
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/github-updates.php';
function atm_options() { return wp_parse_args(get_option('atm_options', []), ['templates'=>'','width'=>420,'preload'=>0]); }
function atm_ids(){ return array_values(array_unique(array_filter(array_map('absint',preg_split('/[\s,]+/',atm_options()['templates']))))); }
function atm_valid($id) { $p=get_post($id); return $p && $p->post_type==='elementor_library' && $p->post_status==='publish' && !$p->post_password; }
add_action('admin_menu', function(){ add_options_page('LNKTA Async Popup','LNKTA Async Popup','manage_options','async-template-menu','atm_settings'); });
function atm_sanitize($v){
 $ids=preg_split('/[\s,]+/',(string)($v['templates']??''));
 $ids=array_unique(array_filter(array_map('absint',$ids)));
 return ['templates'=>implode(',',$ids),'width'=>max(280,min(1600,absint($v['width']??420))),'preload'=>empty($v['preload'])?0:1];
}
add_action('admin_init',function(){register_setting('atm','atm_options',['sanitize_callback'=>'atm_sanitize']);});
function atm_settings(){
 if(!current_user_can('manage_options')) return;
 $o=atm_options(); ?>
 <div class="wrap"><h1>LNKTA Async Popup</h1><p>Choose a published saved Elementor section or container template. This template will be publicly accessible. Avoid popup templates.</p>
 <form action="options.php" method="post"><?php settings_fields('atm'); ?>
 <table class="form-table"><tr><th>Allowed template IDs</th><td><input required type="text" name="atm_options[templates]" value="<?php echo esc_attr($o['templates']); ?>"><p>Comma-separated, for example: 123,456,789. Each ID must be a published saved Elementor template.</p></td></tr>
 <tr><th>Fallback width (px)</th><td><input type="number" min="280" max="1600" name="atm_options[width]" value="<?php echo esc_attr($o['width']); ?>"><p>Used when the Elementor template has a fluid width. Set the width or maximum width on the template’s outer container to control the panel size.</p></td></tr>
 <tr><th>Background preload</th><td><label><input type="checkbox" name="atm_options[preload]" value="1" <?php checked($o['preload'],1); ?>> Load after the main page finishes, during browser idle time.</label><p>Off: load only on the first click. On: faster first opening, with an extra background page request.</p></td></tr></table>
 <?php submit_button(); ?></form><p>In your header icon set Link to <code>#async-menu-123</code> (replace 123 with the template ID). Use a descriptive accessible label, such as “Open menu”.</p>
 <p>For an ACF image or gallery, append the field name to the link, for example <code>#async-menu-123-gallery</code>. Add <code>allow="gallery"</code> to the template’s <code>[atm_acf_media]</code> shortcode to accept that field.</p>
 <?php if(array_filter(atm_ids(),function($id){return !atm_valid($id);})) echo '<p><strong>Template must be a published, non-password-protected Elementor saved template.</strong></p>'; ?>
 </div><?php
}
function atm_frame_request(){ return isset($_GET['atm_frame']) && '1' === $_GET['atm_frame']; }
function atm_source_post() {
 if (!is_singular()) return null;
 $source = get_queried_object();
 if (!$source instanceof WP_Post || 'elementor_library' === $source->post_type || 'publish' !== $source->post_status || $source->post_password) return null;
 return $source;
}
function atm_source_author() {
 $source = atm_source_post();
 return $source && $source->post_author ? get_userdata((int) $source->post_author) : false;
}
add_shortcode('atm_contact_author', function() {
 $author = atm_source_author();
 $name = $author ? trim($author->display_name) : '';
 return esc_html($name !== '' ? $name : __('Contact invalid', 'async-template-menu'));
});
add_action('template_redirect', function() {
 if (!atm_frame_request()) return;
 nocache_headers();
 header('X-Robots-Tag: noindex, nofollow', true);
}, 0);
add_filter('template_include', function($path){ return atm_frame_request() ? __DIR__.'/frame.php' : $path; },99);
add_action('wp_enqueue_scripts',function(){
 $o=atm_options(); if(atm_frame_request() || !atm_ids() || !did_action('elementor/loaded')) return;
 wp_enqueue_style('atm-menu',plugins_url('menu.css',__FILE__),[],'1.2.0');
 wp_enqueue_script('atm-menu',plugins_url('menu.js',__FILE__),[],'1.2.0',true);
 wp_localize_script('atm-menu','ATMMenu',['ids'=>array_values(array_filter(atm_ids(),'atm_valid')),'width'=>$o['width'],'preload'=>(bool)$o['preload']]);
});

function atm_youtube_embed_url($value) {
 if (!is_string($value) || '' === trim($value)) {
  return '';
 }

 $value = trim($value);
 $id = $value;
 $query = [];
 if (!preg_match('/\A[a-zA-Z0-9_-]{11}\z/', $id)) {
  $url = wp_parse_url($value);
  if (!$url || !in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)) {
   return '';
  }

  $host = strtolower($url['host'] ?? '');
  $path = trim($url['path'] ?? '', '/');
  parse_str($url['query'] ?? '', $query);
  if ('youtu.be' === $host) {
   $id = $path;
  } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
   if ('watch' === $path) {
    $id = $query['v'] ?? '';
   } elseif (preg_match('~\A(?:embed|shorts|live)/([a-zA-Z0-9_-]{11})\z~', $path, $match)) {
    $id = $match[1];
   }
  }
  if (!is_string($id) || !preg_match('/\A[a-zA-Z0-9_-]{11}\z/', $id)) {
   return '';
  }
 }

 $start = 0;
 $time = $query['start'] ?? $query['t'] ?? '';
 if (is_string($time) && preg_match('/\A(\d{1,7})s?\z/', $time, $match)) {
  $start = (int) $match[1];
 } elseif (is_string($time) && preg_match('/\A(?:(\d{1,4})h)?(?:(\d{1,4})m)?(?:(\d{1,4})s)?\z/', $time, $match)) {
  $start = 3600 * (int) ($match[1] ?? 0) + 60 * (int) ($match[2] ?? 0) + (int) ($match[3] ?? 0);
 }

 $embed = 'https://www.youtube-nocookie.com/embed/' . $id . '?playsinline=1';
 return $start ? $embed . '&start=' . $start : $embed;
}

function atm_acf_media($attributes, $source, $requested_field = null) {
 if (!$source || !function_exists('get_field_object')) {
  return '';
 }
 $attributes = shortcode_atts([
  'field' => '',
  'alt' => '',
  'allow' => '',
  'label' => '',
  'labels' => '',
  'label_target' => '',
  'youtube' => '',
 ], $attributes, 'atm_acf_media');
 $name = sanitize_key($attributes['field']);
 if (null !== $requested_field && '' !== $attributes['allow']) {
  // A link is public input. Only read fields the template author opted into.
  $allowed = preg_split('/[\s,]+/', trim($attributes['allow']), -1, PREG_SPLIT_NO_EMPTY);
  if (!is_string($requested_field)
      || !preg_match('/\A[a-zA-Z0-9_][a-zA-Z0-9_-]{0,127}\z/', $requested_field)
      || !in_array($requested_field, $allowed, true)) {
   return '<p role="status">' . esc_html__('No media is available.', 'async-template-menu') . '</p>';
  }
  $name = $requested_field;
 }
 $field = $name ? get_field_object($name, $source->ID, false) : false;
 if (!$field) {
  return '';
 }

 if ('yes' === $attributes['youtube'] && in_array($field['type'], ['url', 'oembed', 'text'], true)) {
  $embed = atm_youtube_embed_url($field['value']);
  if ('' === $embed) {
   return '<p role="status">' . esc_html__('No YouTube video is available.', 'async-template-menu') . '</p>';
  }
  $class = 'atm-media-video';
  $body = '<iframe title="' . esc_attr__('YouTube video', 'async-template-menu') . '" data-atm-youtube="'
      . esc_url($embed) . '" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
 } elseif (in_array($field['type'], ['image', 'gallery'], true)) {
  $gallery = 'gallery' === $field['type'];
  $class = $gallery ? 'atm-media-gallery' : 'atm-media-image';
  $body = atm_acf_images($field['value'], $gallery, $attributes['alt']);
  if ('' === $body) {
   return '<p role="status">' . esc_html__('No image is available.', 'async-template-menu') . '</p>';
  }
 } else {
  return '';
 }

 return atm_media_with_label($class, $body, $name, $attributes);
}

function atm_acf_images($value, $gallery, $alt) {
 // ACF's unformatted image and gallery values are attachment IDs.
 $ids = $gallery ? (array) $value : [$value];
 $images = [];
 foreach ($ids as $id) {
  if (!is_numeric($id) || !wp_attachment_is_image((int) $id)) {
   continue;
  }
  $image_attributes = $gallery || '' === $alt ? [] : ['alt' => $alt];
  $images[] = wp_get_attachment_image((int) $id, $gallery ? 'large' : 'full', false, $image_attributes);
 }
 return implode('', $images);
}

function atm_media_with_label($class, $body, $name, $attributes) {
 $label = trim($attributes['label']);
 foreach (explode('|', $attributes['labels']) as $pair) {
  $parts = explode(':', $pair, 2);
  if (count($parts) === 2 && trim($parts[0]) === $name && trim($parts[1]) !== '') {
   $label = trim($parts[1]);
   break;
  }
 }

 $target = trim($attributes['label_target']);
 $target_attributes = '';
 if ('' !== $label && preg_match('/\A[.#]?[a-zA-Z0-9_-]+\z/', $target)) {
  $target_attributes = ' data-atm-label-target="' . esc_attr($target) . '" data-atm-label="' . esc_attr($label) . '"';
 }

 $media = '<div class="' . esc_attr($class) . '"' . $target_attributes . '>' . $body . '</div>';
 if ('' === $label || '' !== $target) {
  return $media;
 }
 return '<p class="atm-media-label">' . esc_html($label) . '</p>' . $media;
}
