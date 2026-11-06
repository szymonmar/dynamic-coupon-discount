<?php
/**
 * Plugin Name: Dynamic Coupon Discount for WooCommerce
 * Description: Adds a "Dynamic discount" tab to the coupon editor, letting you define discount rules (flat or percent) based on the cart total.
 * Version:     1.0.0
 * Author:      Szymon Marszałek
 * Requires Plugins: woocommerce
 * Text Domain: dynamic-coupon-discount
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

final class DCD_Dynamic_Coupon_Discount {

	const META_ENABLED = '_dcd_enabled';
	const META_RULES   = '_dcd_rules';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ) );
	}

	public static function boot() {
		load_plugin_textdomain( 'dynamic-coupon-discount', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Admin: tab, panel, saving.
		add_filter( 'woocommerce_coupon_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_coupon_data_panels', array( __CLASS__, 'render_panel' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'save' ), 10, 2 );

		// Frontend: override coupon type/amount depending on the cart total.
		add_filter( 'woocommerce_coupon_get_discount_type', array( __CLASS__, 'filter_discount_type' ), 20, 2 );
		add_filter( 'woocommerce_coupon_get_amount', array( __CLASS__, 'filter_amount' ), 20, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate_coupon' ), 20, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Admin UI
	 * ------------------------------------------------------------------ */

	public static function add_tab( $tabs ) {
		$tabs['dcd_dynamic_discount'] = array(
			'label'  => __( 'Dynamic discount', 'dynamic-coupon-discount' ),
			'target' => 'dcd_coupon_data',
			'class'  => '',
		);
		return $tabs;
	}

	public static function render_panel( $coupon_id, $coupon ) {
		$enabled = 'yes' === get_post_meta( $coupon_id, self::META_ENABLED, true );
		$rules   = get_post_meta( $coupon_id, self::META_RULES, true );
		$rules   = is_array( $rules ) ? $rules : array();
		$symbol  = get_woocommerce_currency_symbol();
		?>
		<div id="dcd_coupon_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'dcd_enabled',
						'label'       => __( 'Activate dynamic discount', 'dynamic-coupon-discount' ),
						'description' => __( 'When active and at least one rule exists, the default coupon amount is ignored and only the rules below are used.', 'dynamic-coupon-discount' ),
						'value'       => $enabled ? 'yes' : 'no',
						'cbvalue'     => 'yes',
					)
				);
				?>
			</div>

			<div class="options_group" id="dcd_rules_wrap" style="padding:12px;">
				<p class="description" style="margin-top:0;"><?php
					printf(
						/* translators: %s: currency symbol */
						esc_html__( 'Rules are matched against the cart total (%s). Leave "Price to" empty for no upper limit. If ranges overlap, the first matching rule (lowest "Price from") wins. If no rule matches, the coupon cannot be applied.', 'dynamic-coupon-discount' ),
						esc_html( $symbol )
					);
				?></p>

				<table class="widefat" id="dcd_rules_table" style="max-width:760px;">
					<thead>
						<tr>
							<th><?php echo esc_html( sprintf( __( 'Price from (%s)', 'dynamic-coupon-discount' ), $symbol ) ); ?></th>
							<th><?php echo esc_html( sprintf( __( 'Price to (%s)', 'dynamic-coupon-discount' ), $symbol ) ); ?></th>
							<th><?php esc_html_e( 'Discount', 'dynamic-coupon-discount' ); ?></th>
							<th><?php esc_html_e( 'Type', 'dynamic-coupon-discount' ); ?></th>
							<th style="width:40px;"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rules as $rule ) : ?>
							<?php self::render_row( $rule, $symbol ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button button-primary" id="dcd_add_row">
						<?php esc_html_e( 'Add rule', 'dynamic-coupon-discount' ); ?>
					</button>
				</p>
			</div>

			<script type="text/template" id="dcd_row_template">
				<?php self::render_row( array(), $symbol ); ?>
			</script>
		</div>

		<script>
		jQuery(function ($) {
			var $enabled = $('#dcd_enabled'),
				$wrap    = $('#dcd_rules_wrap'),
				$tbody   = $('#dcd_rules_table tbody');

			function toggle() {
				$wrap.toggle($enabled.is(':checked'));
			}
			$enabled.on('change', toggle);
			toggle();

			$('#dcd_add_row').on('click', function () {
				$tbody.append($('#dcd_row_template').html());
			});

			$tbody.on('click', '.dcd-remove-row', function () {
				$(this).closest('tr').remove();
			});
		});
		</script>
		<?php
	}

	private static function render_row( $rule, $symbol ) {
		$from     = isset( $rule['from'] ) ? $rule['from'] : '';
		$to       = isset( $rule['to'] ) ? $rule['to'] : '';
		$discount = isset( $rule['discount'] ) ? $rule['discount'] : '';
		$type     = isset( $rule['type'] ) ? $rule['type'] : 'percent';
		?>
		<tr>
			<td style="position:relative;"><input type="text" class="wc_input_price" style="width:100%;" name="dcd_from[]" value="<?php echo esc_attr( wc_format_localized_price( $from ) ); ?>" placeholder="0" /></td>
			<td style="position:relative;"><input type="text" class="wc_input_price" style="width:100%;" name="dcd_to[]" value="<?php echo esc_attr( wc_format_localized_price( $to ) ); ?>" placeholder="&infin;" /></td>
			<td style="position:relative;"><input type="text" class="wc_input_price" style="width:100%;" name="dcd_discount[]" value="<?php echo esc_attr( wc_format_localized_price( $discount ) ); ?>" /></td>
			<td>
				<select name="dcd_type[]" style="width:100%;">
					<option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Percent (%)', 'dynamic-coupon-discount' ); ?></option>
					<option value="flat" <?php selected( $type, 'flat' ); ?>><?php echo esc_html( sprintf( __( 'Flat (%s)', 'dynamic-coupon-discount' ), $symbol ) ); ?></option>
				</select>
			</td>
			<td><button type="button" class="button dcd-remove-row" aria-label="<?php esc_attr_e( 'Remove rule', 'dynamic-coupon-discount' ); ?>">&minus;</button></td>
		</tr>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Saving
	 * ------------------------------------------------------------------ */

	public static function save( $post_id, $coupon ) {
		// Nonce and capability checks are performed by WooCommerce before this hook fires.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$enabled = isset( $_POST['dcd_enabled'] ) ? 'yes' : 'no';

		$from     = isset( $_POST['dcd_from'] ) ? (array) wp_unslash( $_POST['dcd_from'] ) : array();
		$to       = isset( $_POST['dcd_to'] ) ? (array) wp_unslash( $_POST['dcd_to'] ) : array();
		$discount = isset( $_POST['dcd_discount'] ) ? (array) wp_unslash( $_POST['dcd_discount'] ) : array();
		$type     = isset( $_POST['dcd_type'] ) ? (array) wp_unslash( $_POST['dcd_type'] ) : array();
		// phpcs:enable

		$rules = array();
		foreach ( $discount as $i => $d ) {
			$d = wc_clean( $d );
			if ( '' === $d ) {
				continue; // A rule without a discount value is meaningless.
			}

			$f = isset( $from[ $i ] ) ? wc_clean( $from[ $i ] ) : '';
			$t = isset( $to[ $i ] ) ? wc_clean( $to[ $i ] ) : '';
			$k = ( isset( $type[ $i ] ) && 'flat' === $type[ $i ] ) ? 'flat' : 'percent';

			$f = '' === $f ? 0.0 : (float) wc_format_decimal( $f );
			$t = '' === $t ? '' : (float) wc_format_decimal( $t );
			$d = max( 0.0, (float) wc_format_decimal( $d ) );

			if ( 'percent' === $k ) {
				$d = min( 100.0, $d );
			}
			if ( '' !== $t && $t < $f ) {
				$t = $f; // Keep ranges sane.
			}

			$rules[] = array(
				'from'     => $f,
				'to'       => $t,
				'discount' => $d,
				'type'     => $k,
			);
		}

		usort(
			$rules,
			function ( $a, $b ) {
				return $a['from'] <=> $b['from'];
			}
		);

		update_post_meta( $post_id, self::META_ENABLED, $enabled );
		update_post_meta( $post_id, self::META_RULES, $rules );
	}

	/* ---------------------------------------------------------------------
	 * Discount logic
	 * ------------------------------------------------------------------ */

	/**
	 * Returns the active rules for a coupon, or null when dynamic discount is not in use.
	 */
	private static function get_active_rules( $coupon ) {
		$id = $coupon->get_id();
		if ( ! $id ) {
			return null;
		}
		if ( 'yes' !== get_post_meta( $id, self::META_ENABLED, true ) ) {
			return null;
		}
		$rules = get_post_meta( $id, self::META_RULES, true );
		return ( is_array( $rules ) && $rules ) ? $rules : null;
	}

	private static function in_cart_context() {
		return function_exists( 'WC' ) && WC()->cart && ! ( is_admin() && ! wp_doing_ajax() );
	}

	/**
	 * Cart total used to pick a rule (sum of line prices before any coupon, excl. tax).
	 * Filterable, e.g. to include tax.
	 */
	private static function get_cart_total() {
		$total = 0.0;
		foreach ( WC()->cart->get_cart() as $item ) {
			$total += (float) $item['data']->get_price() * (int) $item['quantity'];
		}
		return (float) apply_filters( 'dcd_cart_total', $total, WC()->cart );
	}

	private static function match_rule( $rules, $total ) {
		foreach ( $rules as $rule ) {
			$above_from = $total >= (float) $rule['from'];
			$below_to   = '' === $rule['to'] || $total <= (float) $rule['to'];
			if ( $above_from && $below_to ) {
				return $rule;
			}
		}
		return null;
	}

	public static function filter_discount_type( $type, $coupon ) {
		$rules = self::get_active_rules( $coupon );
		if ( ! $rules || ! self::in_cart_context() ) {
			return $type;
		}
		$rule = self::match_rule( $rules, self::get_cart_total() );
		if ( ! $rule ) {
			return $type;
		}
		return 'flat' === $rule['type'] ? 'fixed_cart' : 'percent';
	}

	public static function filter_amount( $amount, $coupon ) {
		$rules = self::get_active_rules( $coupon );
		if ( ! $rules || ! self::in_cart_context() ) {
			return $amount;
		}
		$rule = self::match_rule( $rules, self::get_cart_total() );
		// No matching rule: the default amount must not apply.
		return $rule ? $rule['discount'] : 0;
	}

	public static function validate_coupon( $valid, $coupon ) {
		$rules = self::get_active_rules( $coupon );
		if ( $rules && self::in_cart_context() && ! self::match_rule( $rules, self::get_cart_total() ) ) {
			throw new Exception(
				esc_html__( 'This coupon is not available for the current cart total.', 'dynamic-coupon-discount' ),
				WC_Coupon::E_WC_COUPON_INVALID_FILTERED
			);
		}
		return $valid;
	}
}

DCD_Dynamic_Coupon_Discount::init();