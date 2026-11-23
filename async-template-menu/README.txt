LNKTA Async Popup 1.2.1

1. Upload the ZIP through Plugins > Add New > Upload Plugin. When updating an existing installation, replace the current version.
2. In Settings > LNKTA Async Popup, allow the IDs of the published Elementor templates you want to open, separated by commas.
3. Set a link to #async-menu-123, replacing 123 with the template ID. Give icon links an accessible label. Links inside another async template can open a different allowed template in the same panel.
4. Optional: turn on background preload. It loads allowed templates linked from the page when the browser is idle and respects Save-Data.
5. To close a template from within it, link to #async-menu-close.

Sizing: The panel centers on screen and follows the template's rendered outer-container width and content height. Define a width or max-width on the outer container in Elementor. Templates with fluid width use the saved fallback width (420 px by default). Height and width fit within the viewport with a 16 px gutter. Tall templates scroll inside the panel. Viewport-relative heights resolve against the available viewport height. Responsive styles still apply inside the iframe.

Current-page context: The frame requests the current page's URL with atm_frame and atm_id query parameters. WordPress resolves the source post from that URL. The Elementor Author Name dynamic tag uses the source page's author and honors its configured fallback. Use "Contact invalid" for the fallback. A WordPress Shortcode widget can also render [atm_contact_author], which displays the source author's name or "Contact invalid". PHP integrations can use atm_source_post() and atm_source_author() during template rendering. The plugin does not publish the author's email or modify a form's submission actions.

Frames are same-origin and retained until page navigation. Normal links open in the parent window unless an explicit target is set. Hash links jump to the parent page, except allowed async template links and the close link.

Security: Only administrator-allowlisted published, non-password-protected Elementor templates are accessible. Draft, private and password-protected posts are excluded from author lookup. Frame responses are noindex and use no-cache headers. If hosting or CDN rules override cache headers, exclude requests with atm_frame=1. Same-origin framing must be permitted by the site's CSP or frame policy.

Requirements: Elementor, PHP 7.4+, WordPress 6+, and a browser with native dialog support. Existing settings are retained.

LNKTA Dark Mode: Same-origin frames follow the active page's light/dark theme, including preloaded frames.

Elementor Atomic Forms: The frame supplies localized success/error text when the template omits Elementor's own message elements. Elementor's submission handler controls when each message appears. Custom message elements added in Elementor take priority. Existing site CSS that hard-codes the dialog width or height must be removed for automatic sizing to apply.

Opening animation: Panels fade in and move up slightly when their content is ready. The backdrop fades in separately. Both effects are skipped if the visitor prefers reduced motion.

== Dynamic images and galleries ==
Add an Elementor Shortcode widget to a saved template used by LNKTA Async Popup.
Use [atm_acf_media field="construction_optical_formula_diagram" alt="Optical formula diagram"] for a lens diagram.
Use [atm_acf_media field="gallery"] for an ACF gallery. The published page that opened the panel supplies the field value.
Choose a width on the outer container. Add the saved template ID in Settings > LNKTA Async Popup and set the button link to #async-menu-ID.

To reuse one template for several image or gallery fields, list the field names in the Shortcode widget:
[atm_acf_media field="construction_optical_formula_diagram" allow="construction_optical_formula_diagram,product_diagram,gallery"]

Use #async-menu-18796-product_diagram on a post with an ACF field named product_diagram, or #async-menu-18796-gallery on another post. The field name is the ACF Field Name, not its label. The original #async-menu-18796 link uses the default field. An unlisted field name is rejected. Each link reads its field from the current published post, so the same template can be reused across custom post types.

To show your own text above the image or gallery, add a label to the shortcode:
[atm_acf_media field="construction_optical_formula_diagram" label="Optical formula diagram"]
To set a different label for each field, pair each exact ACF field name with its label using a colon and separate entries with a pipe:
[atm_acf_media field="construction_optical_formula_diagram" allow="construction_optical_formula_diagram,gallery" labels="construction_optical_formula_diagram:Optical formula diagram|gallery:Photo gallery"]
The matching label takes priority over label, which remains an optional fallback. Keep the field names in allow if links should be able to select them. Labels only appear when media is available.

To put the label in an existing heading, give the heading widget a CSS ID such as lightbox-title and add label_target="lightbox-title" to the media shortcode. You can also use a unique CSS class with label_target=".lightbox-title". The plugin changes the text of the heading inside the async frame. With a target specified, it skips its own separate label. Keep the existing heading text as a fallback for missing media or an unmatched target.

== Dynamic YouTube videos ==
Add youtube="yes" to enable YouTube playback from an ACF URL, oEmbed, or Text field on the current post. Add the video field name to allow to select it from a button, for example:
[atm_acf_media allow="youtube_video" youtube="yes" label="Watch video" label_target="lightbox-title"]
Link to #async-menu-18804-youtube_video for template 18804. A fixed field="youtube_video" can also be used with the original #async-menu-18804 link.
Store a YouTube URL in the ACF field. A Text field also accepts a YouTube video ID. Start times in the URL (such as &t=257s) are preserved. The YouTube player loads when the panel opens and unloads on close or when switching to a different panel. Images, galleries, and field-specific labels can share the same shortcode.

Standalone Atomic Forms keep the page's responsive breakpoints in the iframe. The dialog follows the rendered form's border box and corner radius, with viewport gutters on small screens. The frame skips Atomic CSS from the source page so shared class names use the template's styles.

Full screen: A button beside Close expands both the panel and its content to the browser viewport. Press the same button to restore the template's size. The existing iframe and form entries are retained. Closing the panel resets its size.

Updates: Install this version once to receive future stable GitHub releases in WordPress. To install them automatically, enable auto-updates for LNKTA Async Popup on the Plugins screen.
