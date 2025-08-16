<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WCGM' ) ) {
	return;
}

final class WCGM {
	const FIELD_NAME    = 'wcgm_gift_message';
	const NONCE_NAME    = 'wcgm_gift_message_nonce';
	const NONCE_ACTION  = 'wcgm_add_gift_message';
	const HIDDEN_META   = '_wcgm_gift_message';

	/** @var WCGM */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_input_field' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_input' ], 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4 );

		// Admin list table columns (legacy posts table).
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_orders_list_column_legacy' ], 20 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_orders_list_column_legacy' ], 10, 2 );

		// Admin list table columns (HPOS / new orders table).
		add_filter( 'woocommerce_shop_order_list_table_columns', [ $this, 'add_orders_list_column_hpos' ], 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ $this, 'render_orders_list_column_hpos' ], 10, 2 );
	}

	public function get_max_length() {
		$len = (int) apply_filters( 'wcgm_max_length', 150 );
		return $len > 0 ? $len : 150;
	}

	public function get_label() {
		return apply_filters( 'wcgm_gift_message_label', __( 'Gift Message', 'woocommerce-gift-message' ) );
	}

	public function enqueue_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		wp_enqueue_style( 'wcgm-frontend', WCGM_PLUGIN_URL . 'assets/css/frontend.css', [], WCGM_VERSION );
		wp_enqueue_script( 'wcgm-frontend', WCGM_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], WCGM_VERSION, true );
		wp_localize_script( 'wcgm-frontend', 'WCGM', [
			'maxLen' => $this->get_max_length(),
			'label'  => $this->get_label(),
		] );
	}

	public function render_input_field() {
		$label = $this->get_label();
		$max   = $this->get_max_length();
		$value = isset( $_POST[ self::FIELD_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD_NAME ] ) ) : '';
		?>
		<div class="wcgm-field">
			<label for="<?php echo esc_attr( self::FIELD_NAME ); ?>" class="wcgm-label"><?php echo esc_html( $label ); ?></label>
			<input type="text" class="input-text wcgm-input" id="<?php echo esc_attr( self::FIELD_NAME ); ?>" name="<?php echo esc_attr( self::FIELD_NAME ); ?>" maxlength="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<small class="wcgm-help"><span id="wcgm-counter">0</span> / <?php echo esc_html( $max ); ?></small>
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
		</div>
		<?php
		// Allow extensions to add content after the field.
		do_action( 'wcgm_after_field' );
	}

	public function validate_input( $passed, $product_id, $quantity ) {
		if ( isset( $_POST[ self::FIELD_NAME ] ) ) {
			// Nonce check (soft): if nonce present but invalid, block.
			if ( isset( $_POST[ self::NONCE_NAME ] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
				wc_add_notice( esc_html__( 'Security check failed for Gift Message.', 'woocommerce-gift-message' ), 'error' );
				return false;
			}

			$raw = (string) wp_unslash( $_POST[ self::FIELD_NAME ] );
			$val = sanitize_text_field( $raw );
			$val = preg_replace( "/\r|\n/", ' ', $val ); // single-line only
			$max = $this->get_max_length();
			if ( function_exists( 'mb_strlen' ) ) {
				$len = mb_strlen( $val );
			} else {
				$len = strlen( $val );
			}
			if ( $len > $max ) {
				wc_add_notice( sprintf( esc_html__( 'Gift Message must be %d characters or fewer.', 'woocommerce-gift-message' ), (int) $max ), 'error' );
				return false;
			}
		}
		return $passed;
	}

	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $_POST[ self::FIELD_NAME ] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST[ self::FIELD_NAME ] ) );
			$val = preg_replace( "/\r|\n/", ' ', $val );
			if ( '' !== $val ) {
				$cart_item_data[ self::FIELD_NAME ] = $val;
				// Prevent merging by adding a unique key when message differs.
				$cart_item_data['wcgm_unique'] = md5( $val . '|' . (string) $product_id . '|' . (string) $variation_id . '|' . microtime( true ) );
			}
		}
		return $cart_item_data;
	}

	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item[ self::FIELD_NAME ] ) && '' !== $cart_item[ self::FIELD_NAME ] ) {
			$item_data[] = [
				'key'     => $this->get_label(),
				'name'    => $this->get_label(),
				'value'   => esc_html( $cart_item[ self::FIELD_NAME ] ),
				'display' => esc_html( $cart_item[ self::FIELD_NAME ] ),
			];
		}
		return $item_data;
	}

	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values[ self::FIELD_NAME ] ) && '' !== $values[ self::FIELD_NAME ] ) {
			$val = sanitize_text_field( $values[ self::FIELD_NAME ] );
			$val = preg_replace( "/\r|\n/", ' ', $val );
			$label = $this->get_label();
			// Visible, nicely labeled meta (for emails and front-end order display).
			$item->add_meta_data( $label, $val, true );
			// Hidden machine-readable copy for admin list aggregation.
			$item->add_meta_data( self::HIDDEN_META, $val, true );
		}
	}

	private function collect_order_messages( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return [];
		}
		$messages = [];
		$label = $this->get_label();
		foreach ( $order->get_items() as $item ) {
			$val = $item->get_meta( self::HIDDEN_META, true );
			if ( '' === $val ) {
				$val = $item->get_meta( $label, true );
			}
			if ( '' !== $val ) {
				$messages[] = (string) $val;
			}
		}
		$messages = array_values( array_unique( array_filter( array_map( 'trim', $messages ) ) ) );
		return $messages;
	}

	// Legacy posts table column handlers
	public function add_orders_list_column_legacy( $columns ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $columns;
		}
		$injected = [];
		foreach ( $columns as $key => $label ) {
			$injected[ $key ] = $label;
			if ( 'order_total' === $key ) {
				$injected['wcgm_gift_message'] = $this->get_label();
			}
		}
		if ( ! isset( $injected['wcgm_gift_message'] ) ) {
			$injected['wcgm_gift_message'] = $this->get_label();
		}
		return $injected;
	}

	public function render_orders_list_column_legacy( $column, $post_id ) {
		if ( 'wcgm_gift_message' !== $column ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			echo '&ndash;';
			return;
		}
		$order = wc_get_order( (int) $post_id );
		if ( ! $order ) {
			echo '&ndash;';
			return;
		}
		$messages = $this->collect_order_messages( $order );
		echo $messages ? esc_html( implode( '; ', $messages ) ) : '&ndash;';
	}

	// HPOS orders table column handlers
	public function add_orders_list_column_hpos( $columns ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $columns;
		}
		$columns['wcgm_gift_message'] = $this->get_label();
		return $columns;
	}

	public function render_orders_list_column_hpos( $column, $order ) {
		if ( 'wcgm_gift_message' !== $column ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			echo '&ndash;';
			return;
		}
		if ( $order && $order instanceof WC_Order ) {
			$messages = $this->collect_order_messages( $order );
			echo $messages ? esc_html( implode( '; ', $messages ) ) : '&ndash;';
		} else {
			echo '&ndash;';
		}
	}
}
