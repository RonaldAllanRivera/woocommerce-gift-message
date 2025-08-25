<?php
/**
 * REST: Latest Order endpoint for Gift Messages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WCGM_REST_Orders_Controller' ) ) :

class WCGM_REST_Orders_Controller extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'giftmessages/v1';
        $this->rest_base = 'orders';
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_latest_order' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );
    }

    /**
     * Only allow logged-in administrators.
     */
    public function permissions_check( $request ) {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    /**
     * GET: Return the latest order with id, customer name, gift message(s), and order date.
     */
    public function get_latest_order( WP_REST_Request $request ) {
        if ( ! class_exists( 'WC_Order' ) || ! function_exists( 'wc_get_orders' ) ) {
            return new WP_Error( 'wc_not_loaded', __( 'WooCommerce is not available.', 'woocommerce-gift-message' ), [ 'status' => 500 ] );
        }

        $orders = wc_get_orders( [
            'limit'   => 1,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        ] );

        if ( empty( $orders ) ) {
            return new WP_Error( 'no_orders', __( 'No orders found.', 'woocommerce-gift-message' ), [ 'status' => 404 ] );
        }

        /** @var WC_Order $order */
        $order = $orders[0];

        // Customer name: prefer billing full name, fallback to shipping, otherwise 'Guest'.
        $customer_name = trim( (string) $order->get_formatted_billing_full_name() );
        if ( '' === $customer_name ) {
            $customer_name = trim( (string) $order->get_formatted_shipping_full_name() );
        }
        if ( '' === $customer_name ) {
            $customer_name = __( 'Guest', 'woocommerce-gift-message' );
        }

        // Collect gift messages from line items using the hidden meta key.
        $messages = [];
        $meta_key = class_exists( 'WCGM' ) ? WCGM::HIDDEN_META : '_wcgm_gift_message';
        foreach ( $order->get_items() as $item ) {
            $msg = $item->get_meta( $meta_key );
            if ( is_string( $msg ) ) {
                $msg = trim( $msg );
            }
            if ( ! empty( $msg ) ) {
                $messages[] = $msg;
            }
        }
        // Unique, preserve order.
        $messages = array_values( array_unique( $messages ) );
        $gift_message = implode( '; ', $messages );

        $date_created = $order->get_date_created();
        $order_date   = $date_created ? $date_created->date( 'c' ) : null;

        $data = [
            'order_id'      => $order->get_id(),
            'customer_name' => $customer_name,
            'gift_message'  => $gift_message,
            'order_date'    => $order_date,
        ];

        return new WP_REST_Response( $data, 200 );
    }
}

endif;
