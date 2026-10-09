<?php
/**
 * Plugin Name:       wp_kses Parser Comparison
 * Description:       Runs the same HTML through wp_kses() twice, once with the legacy parser and once with the new parser built on the HTML API, and shows the results side by side.
 * Version:           0.1.0
 * Requires PHP:      7.4
 * Author:            Ryan Welcher
 * License:           GPL-2.0-or-later
 * Text Domain:       kses-html-api-demo
 *
 * @package KsesHtmlApiDemo
 */

namespace KsesHtmlApiDemo;

defined( 'ABSPATH' ) || exit;

/**
 * The sample inputs. Most of these come straight from the Make Core post:
 * https://make.wordpress.org/core/2026/10/07/progress-report-wp_kses/
 *
 * `allowed` is passed as the second argument to wp_kses(). Use a context
 * name like 'post', or an array of allowed tags.
 *
 * @return array[]
 */
function get_samples() {
	return array(
		array(
			'label'   => 'Unescaped ampersand',
			'html'    => 'Add <code>/?preview=true&section=grilling</code>.',
			'allowed' => 'post',
		),
		array(
			'label'   => 'Disallowed SCRIPT',
			'html'    => 'Click on my <script>alert(1)</script>!',
			'allowed' => 'post',
		),
		array(
			'label'   => 'Truncated tag at the end',
			'html'    => 'Click on the <butto',
			'allowed' => 'post',
		),
		array(
			'label'   => 'Normalization',
			'html'    => 'The <IMG class=bike class="bo&#x0061;t" src=\'vehicle.png\' /> & the wheels&hellip;',
			'allowed' => 'post',
		),
		array(
			'label'   => 'Inline SVG icon (custom allowed tags)',
			'html'    => '<a href="/download"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 2L2 22h20z"/></svg> Download</a> the file.',
			'allowed' => get_svg_allowed_html(),
		),
		array(
			'label'   => 'MathML',
			'html'    => "<ul>\n<li>XML-like <math><mtext>MathML</mtext></math>\n<li>HTML-like <math><mtext><span>MathML</span></mtext></math>\n<li>Plain text\n</ul>",
			'allowed' => 'post',
		),
	);
}

/**
 * The kind of allowed-tags array a lot of plugins use to let inline SVG icons through.
 *
 * @return array[]
 */
function get_svg_allowed_html() {
	return array_merge(
		wp_kses_allowed_html( 'post' ),
		array(
			'svg'  => array(
				'viewbox' => true,
				'width'   => true,
				'height'  => true,
				'xmlns'   => true,
			),
			'path' => array(
				'd' => true,
			),
		)
	);
}

/**
 * Runs the content through wp_kses() with each parser.
 *
 * Core reads the `wp_kses_force_legacy_parser` filter on every call, so we can
 * flip it on and off within the same request.
 *
 * @param string       $html    The content to sanitize.
 * @param array|string $allowed Allowed HTML or a context name.
 * @return array { legacy: string, html_api: string }
 */
function compare( $html, $allowed ) {
	add_filter( 'wp_kses_force_legacy_parser', '__return_true', 99 );
	$legacy = wp_kses( $html, $allowed );
	remove_filter( 'wp_kses_force_legacy_parser', '__return_true', 99 );

	add_filter( 'wp_kses_force_legacy_parser', '__return_false', 99 );
	$html_api = wp_kses( $html, $allowed );
	remove_filter( 'wp_kses_force_legacy_parser', '__return_false', 99 );

	return array(
		'legacy'   => $legacy,
		'html_api' => $html_api,
	);
}

/**
 * Registers the Tools > KSES Demo page.
 */
function register_page() {
	add_management_page(
		__( 'KSES Demo', 'kses-html-api-demo' ),
		__( 'KSES Demo', 'kses-html-api-demo' ),
		'manage_options',
		'kses-demo',
		__NAMESPACE__ . '\render_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\register_page' );

/**
 * Prints a string as visible source. Trailing whitespace is marked so a dropped
 * tag at the end of the input is easy to see.
 *
 * @param string $value The string to show.
 */
function print_source( $value ) {
	$trimmed  = rtrim( $value );
	$trailing = strlen( $value ) - strlen( $trimmed );

	echo '<pre class="kses-demo__code">';
	echo esc_html( $trimmed );
	if ( $trailing ) {
		echo '<span class="kses-demo__ws">' . esc_html( str_repeat( '·', $trailing ) ) . '</span>';
	}
	echo '</pre>';
}

/**
 * Prints one comparison row.
 *
 * @param string       $label   Row label.
 * @param string       $html    Input.
 * @param array|string $allowed Allowed HTML or a context name.
 */
function render_row( $label, $html, $allowed ) {
	$result  = compare( $html, $allowed );
	$changed = $result['legacy'] !== $result['html_api'];
	?>
	<tr class="<?php echo $changed ? 'kses-demo__changed' : ''; ?>">
		<th scope="row">
			<?php echo esc_html( $label ); ?>
			<br><span class="kses-demo__badge"><?php echo $changed ? esc_html__( 'Different', 'kses-html-api-demo' ) : esc_html__( 'Same', 'kses-html-api-demo' ); ?></span>
		</th>
		<td><?php print_source( $html ); ?></td>
		<td><?php print_source( $result['legacy'] ); ?></td>
		<td><?php print_source( $result['html_api'] ); ?></td>
	</tr>
	<?php
}

/**
 * Renders the admin page.
 */
function render_page() {
	$custom = '';
	if ( isset( $_POST['kses_demo_input'] ) && check_admin_referer( 'kses_demo' ) ) {
		$custom = wp_unslash( $_POST['kses_demo_input'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The point is to sanitize it with both parsers below.
	}
	?>
	<div class="wrap kses-demo">
		<h1><?php esc_html_e( 'wp_kses(): legacy parser vs. new parser', 'kses-html-api-demo' ); ?></h1>

		<?php if ( ! function_exists( 'wp_sanitize_html_kses' ) ) : ?>
			<div class="notice notice-warning"><p>
				<?php esc_html_e( 'This version of WordPress does not have the new wp_kses() parser yet, so both columns will match. Use a nightly build (r64233 or later).', 'kses-html-api-demo' ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'kses_demo' ); ?>
			<p><label for="kses_demo_input"><strong><?php esc_html_e( 'Try your own HTML (uses the post context):', 'kses-html-api-demo' ); ?></strong></label></p>
			<textarea id="kses_demo_input" name="kses_demo_input" rows="4" class="large-text code"><?php echo esc_textarea( $custom ); ?></textarea>
			<?php submit_button( __( 'Compare', 'kses-html-api-demo' ) ); ?>
		</form>

		<table class="widefat striped kses-demo__table">
			<thead>
				<tr>
					<th scope="col"></th>
					<th scope="col"><?php esc_html_e( 'Input', 'kses-html-api-demo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Legacy parser', 'kses-html-api-demo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'New parser (built on the HTML API)', 'kses-html-api-demo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( '' !== $custom ) {
					render_row( __( 'Your input', 'kses-html-api-demo' ), $custom, 'post' );
				}
				foreach ( get_samples() as $sample ) {
					render_row( $sample['label'], $sample['html'], $sample['allowed'] );
				}
				?>
			</tbody>
		</table>
	</div>
	<style>
		.kses-demo__table th[scope="row"] { width: 14%; }
		.kses-demo__table td { width: 28%; vertical-align: top; }
		.kses-demo__code { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 14px; }
		.kses-demo__ws { color: #d63638; }
		.kses-demo__badge { font-weight: normal; font-size: 12px; color: #50575e; }
		.kses-demo__changed .kses-demo__badge { color: #d63638; font-weight: 600; }
		.kses-demo__changed th[scope="row"] { box-shadow: inset 4px 0 0 #d63638; }
	</style>
	<?php
}
