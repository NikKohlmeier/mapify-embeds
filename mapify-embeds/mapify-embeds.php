<?php
/**
 * Mapify Embeds (for ITI)
 * @package Mapify_Embeds
 *
 * @wordpress-plugin
 * Plugin Name:		Mapify Embeds
 * Description: 	Embed Mapify maps on your website via JavaScript.
 * Author:          Superscalar for ITI
 * Version:			0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Plugin hooks and setup.
 * All external side-effects should be included here for ease of discoverability and understanding.
 */

// This package includes a custom admin panel and settings.
add_action( 'admin_init', 'mapify_embeds_settings_init' );
add_action( 'admin_menu', 'mapify_embeds_setup_menu' );

// It also includes several API endpoints for serving map embeds.
add_action( 'rest_api_init', 'mapify_embeds_register_routes' );

// The "direct embed" endpoint doesn't serve JSON, so it needs to be pre_served.
add_filter( 'rest_pre_serve_request', 'mapify_embeds_maybe_pre_serve_map_embed', 10, 4 );

// The Mapify plugin uses the X-Requested-With header explicitly, but that's not CORS-compatible by default.
add_filter( 'rest_allowed_cors_headers', 'mapify_embeds_allow_xrequestedwith' );

function mapify_embeds_setup_menu() {
	add_menu_page( 'Mapify Embeds', 'Mapify Embeds', 'manage_options', 'mapify-embeds', '', 'dashicons-share-alt2' );
	add_submenu_page( 'mapify-embeds', 'Mapify Embeds - Overview', 'Overview', 'manage_options', 'mapify-embeds', 'mapify_embeds_panel_overview' );
	add_submenu_page( 'mapify-embeds', 'Mapify Embeds - Custom Content', 'Custom Content', 'manage_options', 'mapify-embeds-content', 'mapify_embeds_panel_custom_content' );
	add_submenu_page( 'mapify-embeds', 'Mapify Embeds - Configuration Check', 'Configuration Check', 'manage_options', 'mapify-embeds-config-check', 'mapify_embeds_panel_config_check' );
}


/**
 * Plugin functions.
 */

function mapify_embeds_settings_init() {
	register_setting( 'mapify_embeds', 'mapify_embeds_options' );
	add_settings_section( 'mapify_embeds_main', 'Custom Content Settings', 'mapify_embeds_custom_content_section_text', 'mapify_embeds' );
	add_settings_field(
		'mapify_embeds_field_custom_css',
		'Custom CSS',
		'mapify_embeds_field_custom_css_render',
		'mapify_embeds',
		'mapify_embeds_main',
		array(
			'label_for' => 'mapify_embeds_field_custom_css',
		)
	);
	add_settings_field(
		'mapify_embeds_field_custom_js',
		'Custom JavaScript',
		'mapify_embeds_field_custom_js_render',
		'mapify_embeds',
		'mapify_embeds_main',
		array(
			'label_for' => 'mapify_embeds_field_custom_js',
		)
	);
}

function mapify_embeds_panel_overview() {
	$permalink_structure = get_option( 'permalink_structure' );

	$maps = get_posts(
		array(
			'post_type' => 'map',
			'numberposts' => -1
		)
	);

	?>
	<div class="wrap">
		<h2>
			<?= esc_html( get_admin_page_title() ) ?>
		</h2>
		<p>Mapify Embeds is a plugin that allows you to embed Mapify maps on your website via JavaScript.</p>

		<h3>Usage</h3>
		<p>To embed a map, use the following JavaScript code:</p>
		<pre>&lt;script src="<?= get_bloginfo( 'wpurl' ) ?>/wp-json/get-map/v1/embed/<code>MAPID</code>?height=<code>MAPHEIGHT</code>&width=<code>MAPWIDTH</code>" render-target="<code>RENDERTARGET</code>"<?php if ( ! $permalink_structure ) {
				echo ( ' use-plain-permalinks="true"' );
			} ?>&gt;&lt;/script&gt;</pre>

		<p>The URL parameters include:</p>
		<dl>
			<dt><strong>MAPID</strong> (required)</dt>
			<dd>
				The ID of the map you want to embed. Available maps:
				<ul>
					<?php
					foreach ( $maps as $map ) {
						echo ( '<li><code>' . $map->ID . '</code> (' . esc_html( $map->post_title ) . ')</li>' );
					}
					?>
				</ul>
			</dd>
			<dt><strong>height</strong> (optional)</dt>
			<dd>The desired height (in pixels) of the map. This value must be an integer (e.g. '100'), and will be passed to
				Mapify.</dd>
			<dt><strong>width</strong> (optional)</dt>
			<dd>The desired width (in pixels) of the map. This value must be an integer (e.g. '100'), and will be passed to
				Mapify.</dd>
		</dl>
		<p>Additionally, the script attributes may include:</p>
		<dl>
			<dt><strong>render-target</strong> (optional)</dt>
			<dd>The <em>selectors</em> that specify the element (without <code>#</code>) to be replaced with the map.
				Defaults to
				<code>#mapify-embed</code>, and can be set to <code>self</code> if you want to render inline. This value
				will be passed
				to <a href="https://developer.mozilla.org/en-US/docs/Web/API/Document/querySelector"
					target="_blank">querySelector</a>,
				so any valid selector is acceptable. Note: The first matched element will be used.
			</dd>
			<dt><strong>use-plain-permalinks</strong> (optional)</dt>
			<dd>If your site uses plain permalinks (see the results in the 'configuration check' below), set this to true.
				Plain permalinks are not recommended and, if enabled, the plugin may not work as expected.</dd>
		</dl>

		<h3>Example</h3>
		<p>To embed a map inline, you could use:</p>
		<pre>&lt;script src="<?= get_bloginfo( 'wpurl' ) ?>/wp-json/get-map/v1/embed/<strong style="color:blue"><?= $maps[0]->ID ?></strong>?height=<strong style="color:blue">800</strong>" render-target="<strong style="color:blue">self</strong>"&gt;&lt;/script&gt;</pre>
		<p>To embed a map in a target div somewhere else on the page, you could use:</p>
		<pre>&lt;script src="<?= get_bloginfo( 'wpurl' ) ?>/wp-json/get-map/v1/embed/<strong style="color:blue"><?= $maps[0]->ID ?></strong>?height=<strong style="color:blue">800</strong>" render-target="<strong style="color:blue">#main-content .target-div</strong>"&gt;&lt;/script&gt;</pre>
	</div>
	<?php
}

function mapify_embeds_panel_custom_content() {
	?>
	<div class="wrap">
		<h2>
			<?= esc_html( get_admin_page_title() ) ?>
		</h2>
		<p>Embedded plugins can also inject custom CSS to change the display of the map on the client site. This does not
			need to match the styles used on the main site.</p>

		<?php
		if ( current_user_can( 'manage_options' ) ) {
			// Display a confirmation if the settings were updated
			if ( isset( $_GET['settings-updated'] ) ) {
				add_settings_error( 'mapify_embeds_messages', 'mapify_embeds_message', 'Settings Saved', 'updated' );
			}

			settings_errors( 'mapify_embeds_messages' );
			?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'mapify_embeds' ); /* Output hidden fields used by WP. */
				do_settings_sections( 'mapify_embeds' );
				submit_button( 'Save Settings' );
				?>
			</form>
			<?php
		}
		?>
	</div>
	<?php
}

function mapify_embeds_panel_config_check() {
	$permalink_structure = get_option( 'permalink_structure' );

	$maps = get_posts(
		array(
			'post_type' => 'map',
			'numberposts' => -1
		)
	);

	?>
	<div class="wrap">
		<h2>
			<?= esc_html( get_admin_page_title() ) ?>
		</h2>
		<p>This page displays some helpful information to debug your setup.</p>

		<h3>Configuration Check</h3>
		<ul>
			<li>Maps found: <strong><?= count( $maps ) ?></strong></li>
			<li>Permalink structure:
				<?php
				if ( $permalink_structure ) {
					echo ( '<strong>OK.</strong> Pretty permalinks active.' );
				} else {
					echo ( '<strong>WARNING!</strong> Pretty permalinks are not active. The plugin may not work as expected. Please enable <a href="https://wordpress.org/documentation/article/customize-permalinks/" target="_blank">pretty permalinks</a> in your settings.' );
				} ?>
			</li>
		</ul>
	</div>
	<?php
}

function mapify_embeds_custom_content_section_text( $args ) {
	?>
	<div id="<?= esc_attr( $args['id'] ) ?>">
		<p>You can optionally specify custom CSS to be included with <strong>all</strong> maps embedded from this site. Any
			CSS can be added here, and it will be added to a <code>&lt;style&gt;</code> tag in the target document.</p>
		<p><strong>Please note:</strong> No validation, sanitization, or minification will be applied to these settings.
			Additionally, due to caching, changes made here may not show up immediately.</p>
		<p>Specific selectors that may be useful are included below. All selectors will be contained within the outer div
			(<code>#mapify-embed</code> by default), with the exception of pop-ups, which will appear at the top level.</p>
		<dl>
			<dt>Selectors added by the plugin:</dt>
			<dd>
				<code>.map-dropin-container</code>,
				<code>.markup-container</code>, and
				<code>.setup-container</code>
			</dd>
			<dt>Selectors used by Mapify for the main map:</dt>
			<dd>
				<code>.mpfy-container</code>, and
				<code>.mpfy-map-controls</code>
			</dd>
			<dt>Selectors used by Mapify for popups:</dt>
			<dd>
				<code>.mpf-p-popup-holder</code>, and
				<code>section.mpfy-p-popup</code>
			</dd>
		</dl>
	</div>
	<?php
}

function mapify_embeds_field_custom_css_render( $args ) {
	$options = get_option( 'mapify_embeds_options' );
	$label_for = esc_attr( $args['label_for'] );

	// Set $value to $options[ $args['label_for'] ] if it exists
	$value = ( is_array( $options ) && isset( $options[ $args['label_for'] ] ) ) ? $options[ $args['label_for'] ] : '';

	?>
	<textarea id="<?= $label_for ?>" name="mapify_embeds_options[<?= $label_for ?>]" rows=15
		cols=80><?= $value ?></textarea>
	<?php
}

function mapify_embeds_field_custom_js_render( $args ) {
	$options = get_option( 'mapify_embeds_options' );
	$label_for = esc_attr( $args['label_for'] );

	// Set $value to $options[ $args['label_for'] ] if it exists
	$value = ( is_array( $options ) && isset( $options[ $args['label_for'] ] ) ) ? $options[ $args['label_for'] ] : '';

	?>
	<textarea id="<?= $label_for ?>" name="mapify_embeds_options[<?= $label_for ?>]" rows=15
		cols=80><?= $value ?></textarea>
	<?php
}

function mapify_embeds_register_routes() {
	register_rest_route(
		'get-map/v1',
		'/embed/(?P<id>\d+)',
		array(
			'methods' => 'GET',
			'callback' => 'mapify_embeds_embed_map',
			'permission_callback' => '__return_true',
			'args' => array(
				'id' => array(
					'default' => 0,
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric( $param );
					},
					'sanitize_callback' => 'absint'
				),
				'height' => array(
					'default' => 400,
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric( $param );
					},
					'sanitize_callback' => 'absint'
				),
				'width' => array(
					'default' => 0,
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric( $param );
					},
					'sanitize_callback' => 'absint'
				)
			)
		)
	);
	register_rest_route(
		'get-map/v1',
		'/data/(?P<id>\d+)',
		array(
			'methods' => 'GET',
			'callback' => 'mapify_embeds_get_map',
			'permission_callback' => '__return_true',
			'args' => array(
				'id' => array(
					'default' => 0,
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric( $param );
					},
					'sanitize_callback' => 'absint'
				),
				'height' => array(
					'default' => 400,
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric( $param );
					}
				),
				'width' => array(
					'default' => 0,
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric( $param );
					}
				)
			)
		)
	);
	register_rest_route(
		'get-map/v1',
		'/location/(?P<location_path>.+)',
		array(
			'methods' => 'GET',
			'callback' => 'mapify_embeds_get_location',
			'permission_callback' => '__return_true',
			'args' => array(
				'location_path' => array(
					'required' => true,
				),
				'arguments' => array(
					'required' => false,
				)
			)
		)
	);
}

function mapify_embeds_embed_map( $data ) {
	// Render the map to be served.
	// As a reminder, all values are sanitized by the REST API.
	$components = mapify_embeds_get_map( $data );

	$map_setup = $components['mapSetup'];
	$map_markup = $components['mapMarkup'];
	$map_side_effects = $components['sideEffects'];
	$map_custom_css = $components['customCss'];
	$map_custom_js = $components['customJs'];

	// Read the JS from embed.js and add it to the response
	$embed_js = file_get_contents( plugin_dir_path( __FILE__ ) . 'embed.js' );

	// We need to generate the initialization string. It will look basically like:
	// MapifyEmbed.loadMap(data.mapMarkup, data.mapSetup, data.sideEffects, targetID);
	// but we also need to make sure we properly escape the strings.
	$init_string = 'MapifyEmbed.embedMapDirectly(' .
		json_encode( $map_markup ) . ', ' .
		json_encode( $map_setup ) . ', ' .
		json_encode( $map_side_effects ) . ', ' .
		json_encode( $map_custom_css ) . ', ' .
		json_encode( $map_custom_js ) . ' );';

	$response = new WP_HTTP_Response();
	$response->header( 'Content-Type', 'application/javascript' );
	$response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
	$response->header( 'Pragma', 'no-cache' );
	$response->header( 'Expires', '0' );
	$response->set_data( $embed_js . "\n" . $init_string );

	// NOTE: rest_pre_serve_request filter will be used to serve the response directly

	return $response;

}

function mapify_embeds_get_map( $data ) {
	$components = mapify_embeds_render_map_components( $data['id'], $data['height'], $data['width'] );
	return $components;
}

function mapify_embeds_get_location( $data ) {
	$location = $data['location_path'];
	$arguments = $data['arguments'];

	global $wp_query;
	$orig_query = $wp_query;
	$wp_query = new WP_Query(
		array(
			'name' => $location,
			'post_type' => 'map-location'
		)
	);

	global $post;
	$orig_post = $post;

	// There's probably a cleaner way to do this, but this is the only way I could get it to work.
	// Since the Mapify code uses `get_the_ID` it needs $post to be set already, but it also calls
	// `the_post()` which in turn triggers `next_post`. So... :shrug:
	$page = get_page_by_path( $location, OBJECT, 'map-location' );

	if ( $page ) {
		$post = $page;

		ob_start();
		$template = mpfy_get_single_template( $post );
		include $template;
		$content = ob_get_clean();
	} else {
		$content = 'No page found at this location';
	}

	$post = $orig_post;
	$wp_query = $orig_query;

	$response = new WP_REST_Response( $content );
	return $response;
}

function mapify_embeds_render_map_components( $id, $height, $width ) {
	if ( absint( $id ) ) {
		// First we need to render the map via shortcode
		ob_start();
		$map_shortcode = 'custom-mapping map_id="' . $id . '"';

		if ( $height ) {
			$map_shortcode .= ' height="' . $height . '"';
		}

		if ( $width ) {
			$map_shortcode .= ' width="' . $width . '"';
		}

		$simple_markup = do_shortcode( '[' . $map_shortcode . ']' );
		$map_markup = do_shortcode( $simple_markup ); // custom-mapping doesn't render internal shortcodes, so we need to do it

		// We should have everything we need in $map_markup, but if anything ended up in the output buffer, we should know about it
		$map_side_effects = ob_get_clean();

		// Next, we need to get the Mapify dependencies, the extract them from the queue
		ob_start();
		mpfy_enqueue_assets();

		global $wp_scripts;
		global $wp_styles;

		wp_print_scripts( $wp_scripts->queue );
		wp_print_styles( $wp_styles->queue );

		$map_setup = ob_get_clean();

		// Last, we want to get any custom CSS to be included with the map
		$options = get_option( 'mapify_embeds_options' );
		$customCss = ( is_array( $options ) && isset( $options['mapify_embeds_field_custom_css'] ) ) ? $options['mapify_embeds_field_custom_css'] : '';
		$customJs = ( is_array( $options ) && isset( $options['mapify_embeds_field_custom_js'] ) ) ? $options['mapify_embeds_field_custom_js'] : '';

		return array(
			'mapSetup' => $map_setup,
			'mapMarkup' => $map_markup,
			'sideEffects' => $map_side_effects,
			'customCss' => $customCss,
			'customJs' => $customJs
		);
	}
	return 'id required';
}

function mapify_embeds_allow_xrequestedwith( $allow_headers ) {
	// Make sure `X-Requested-With` is in the list of allowed headers.
	// Only add it if not already present.

	if ( ! in_array( 'X-Requested-With', $allow_headers, true ) ) {
		$allow_headers[] = 'X-Requested-With';
	}

	return $allow_headers;
}

function mapify_embeds_maybe_pre_serve_map_embed( $served, $result, $request, $server ) {
	// If the request is for the embed script, serve it directly.
	// The script is generated by the mapify_embeds_embed_map() function, and
	// will be raw JavaScript served with the appropriate headers.
	if (
		$request->get_attributes()['callback'] === 'mapify_embeds_embed_map' &&
		preg_match( '/get-map\/v1\/embed\/\d+/', $request->get_route() )
	) {
		echo $result->get_data();
		exit;
	} else {
		return $served;
	}
}
