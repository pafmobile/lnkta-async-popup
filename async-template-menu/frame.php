<?php
if (!defined('ABSPATH')) exit;
$id=isset($_GET['atm_id']) && is_string($_GET['atm_id']) && ctype_digit($_GET['atm_id']) ? (int) $_GET['atm_id'] : 0;
if (!in_array($id,atm_ids(),true) || !atm_valid($id) || !did_action('elementor/loaded')) { status_header(404); exit('Menu unavailable.'); }
show_admin_bar(false);
// get_builder_content_for_display renders classic CSS, but v4's Atomic Styles
// Manager collects document IDs separately via elementor/post/render.
// Register this template before the head's enqueue/print cycle. Re-run the
// v4 enqueue hook at priority 7 (after enqueue_scripts, before print_styles).
// Capture the source post before Elementor can switch the rendering context.
$source_post = atm_source_post();
$requested_field = $_GET['atm_field'] ?? null;
add_shortcode('atm_acf_media', static function ($attributes) use ($source_post, $requested_field) {
 return atm_acf_media($attributes, $source_post, $requested_field);
});
$source_author = atm_source_author();
$source_name = static function() use ($source_author) {
 return $source_author ? trim($source_author->display_name) : '';
};
// Elementor temporarily switches the global post to the saved template when
// it renders. Keep its built-in Author Name tag tied to the queried page.
add_filter('the_author', $source_name);
add_filter('get_the_author_display_name', $source_name);
try {
 $html=\Elementor\Plugin::instance()->frontend->get_builder_content_for_display($id,true);
} finally {
 remove_filter('the_author', $source_name);
 remove_filter('get_the_author_display_name', $source_name);
}
if ( class_exists('\Elementor\Modules\AtomicWidgets\Styles\Atomic_Styles_Manager') ) {
 do_action('elementor/post/render', $id);
 add_action('wp_head', static function() use ($source_post){
  do_action('elementor/frontend/after_enqueue_post_styles');
  if (!$source_post) return;

  // The source page provides context, but its Atomic styles share class names
  // with this template and can overwrite the template's own cascade.
  $source_styles = '/^(?:global|local)-' . (int) $source_post->ID . '-frontend-/';
  foreach (wp_styles()->queue as $handle) {
   if (preg_match($source_styles, $handle)) wp_dequeue_style($handle);
  }
 }, 7);
}
?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_top"><title><?php echo esc_html(get_the_title($id)); ?></title><?php wp_head(); ?>
<style>
html,body{margin:0!important;padding:0!important;min-height:0!important;background:transparent!important;}
html{overflow-x:hidden;}
#atm-template-content{display:flow-root;}
#atm-template-content.atm-form-layout{max-inline-size:calc(100% - 32px);margin-inline:auto;}
:where(#atm-template-content > .elementor > *, #atm-template-content > .elementor > * > :is(.e-con-inner,.elementor-container,.e-con,.e-atomic-element,form)){max-inline-size:100%;}
.atm-media-image img,.atm-media-gallery img{display:block;max-inline-size:100%;block-size:auto;margin-inline:auto;}
.atm-media-label{margin:0 0 .75em;font-weight:600;}
.atm-media-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:12px;align-items:start;}
.atm-media-video{aspect-ratio:16/9;background:#111;}
.atm-media-video iframe{display:block;width:100%;height:100%;border:0;}
.atm-expanded-layout {
    box-sizing: border-box;
    padding-inline: max(16px, env(safe-area-inset-left)) max(16px, env(safe-area-inset-right));
}
#atm-template-content.atm-expanded-layout { max-inline-size: none; }
.atm-expanded-layout > .elementor,
.atm-expanded-layout > .elementor > *,
.atm-expanded-layout :is(.e-con, .e-con-inner, .elementor-container):has(:is(form, .atm-media-image, .atm-media-gallery, .atm-media-video)),
.atm-expanded-layout.atm-form-layout form[data-element_type="e-form"] {
    inline-size: 100% !important;
    max-inline-size: 100% !important;
    box-sizing: border-box;
}
.atm-expanded-layout.atm-form-layout form[data-element_type="e-form"] { min-block-size: 100dvh; }
.atm-expanded-layout.atm-form-layout form[data-element_type="e-form"] > textarea { flex-grow: 1; }
.atm-expanded-layout .atm-media-image img {
    inline-size: 100%;
    max-block-size: max(12rem, calc(100dvh - 5rem));
    object-fit: contain;
}
.atm-expanded-layout .atm-media-video {
    max-inline-size: max(16rem, calc((100dvh - 5rem) * 16 / 9));
    margin-inline: auto;
}
.atm-form-message{display:none;margin:0;color:var(--lnkta-text, currentColor);}
form.form-state-success .atm-form-message[data-e-type="e-form-success-message"],
form.form-state-error .atm-form-message[data-e-type="e-form-error-message"]{display:block;}
</style></head><body <?php body_class('atm-template-frame'); ?>>
<?php wp_body_open(); ?>
<div id="atm-template-content"><?php echo $html; ?></div>
<template id="atm-form-messages">
 <p class="atm-form-message" data-element_type="e-form-success-message" data-e-type="e-form-success-message" role="status" tabindex="-1"><?php esc_html_e('Thanks. Your message was submitted.', 'async-template-menu'); ?></p>
 <p class="atm-form-message" data-element_type="e-form-error-message" data-e-type="e-form-error-message" role="alert" tabindex="-1"><?php esc_html_e('Your message could not be sent. Please try again.', 'async-template-menu'); ?></p>
</template>
<?php wp_footer(); ?>
<script src="<?php echo esc_url(add_query_arg('ver', '1.2.1', plugins_url('frame.js', __FILE__))); ?>"></script>
</body></html>
