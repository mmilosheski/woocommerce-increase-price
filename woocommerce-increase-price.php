<?php
/**
 * Plugin Name: WooCommerce Increase Price
 * Plugin URI: https://linkedin.com/in/mmilosheski/
 * Description: WooCommerce extension that Increases Woocommerce cart total by a configurable amount (10% by default) if a certain product is in the cart.
 * Author: Mile B. Milosheski
 * Author URI: http://linkedin.com/in/mmilosheski/
 * Version: 0.0.1
 */
class WC_Increase_Price {
    /**
     * Bootstraps the class and hooks required actions & filters.
     *
     */
    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_settings_increase_price', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_settings_increase_price', __CLASS__ . '::update_settings' );
        add_action( 'woocommerce_cart_calculate_fees', __CLASS__ . '::increase_cart_total' );
        add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_scripts' );
        add_action( 'wp_enqueue_scripts', __CLASS__ . '::front_scripts' );
        add_action( 'wp_ajax_get_products_ajax', __CLASS__ . '::get_all_products' );
    }
    
    
    /**
     * Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_increase_price'] = __( 'Increase Cart Total by %', 'woocommerce-increase_price' );
        return $settings_tabs;
    }
    /**
     * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
     *
     * @uses woocommerce_admin_fields()
     * @uses self::get_settings()
     */
    public static function settings_tab() {
    	// creating the array of preselected product ids in javascript in order to get the values straight
    	echo '<script>';
    	echo 'selections = [';
        if(get_option('wc_increase_price_products')) {
        	foreach (explode(",", get_option('wc_increase_price_products')) as $value) {
        		echo '{id:'.$value.',text:"'.htmlspecialchars_decode(get_the_title($value)).'"},';
        	}				  
        }
		echo '];';
    	echo '</script>';
        echo '<style>#wc_increase_price_products { display: none; }</style>';
    	woocommerce_admin_fields( self::get_settings() );
    }

    public static function admin_scripts($hook) {
        //load assets only on the woo settings page
    	if(is_admin() && $hook == "woocommerce_page_wc-settings") {
	        wp_register_script( 'wc-increase-admin-scripts', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', array('jquery','select2'), '', true );
	        wp_enqueue_script( 'wc-increase-admin-scripts' );
    	}
    }
    /**
     * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
     *
     * @uses woocommerce_update_options()
     * @uses self::get_settings()
     */
    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }
    /**
     * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
     *
     * @return array Array of settings for @see woocommerce_admin_fields() function.
     */
    public static function get_settings() {
        $settings = array(
            'section_title' => array(
                'name'     => __( 'Increase Cart Total by %', 'woocommerce-increase_price' ),
                'type'     => 'title',
                'desc'     => '',
                'id'       => 'wc_increase_price_section_title'
            ),
            'percentage' => array(
                'name' => __( 'Percentage increase %', 'woocommerce-increase_price' ),
                'type' => 'text',
                'desc' => __( 'Default is 10%', 'woocommerce-increase_price' ),
                'id'   => 'wc_increase_price_percentage'
            ),
            'products' => array(
                'name' => __( '', 'woocommerce-increase_price' ),
                'type' => 'text',
                'id'   => 'wc_increase_price_products',
                'value'=> get_option('wc_increase_price_products'),
            ),
            'products_select' => array(
                'name' => __( 'Choose Products which will increase the total, if they are in the cart', 'woocommerce-increase_price' ),
                'type' => 'select',
                'desc' => __( 'Default is none, choose form the dropdown', 'woocommerce-increase_price' ),
                'id'   => 'wc_increase_price_products_select',
                'value'=> get_option('wc_increase_price_products'),
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id' => 'wc_increase_price_section_end'
            )
        );
        return apply_filters( 'wc_increase_price_settings', $settings );
    }

    /*
    * Increasing the product cart by %
    * 
    * @return html
    */
    public static function increase_cart_total( $cart ) {
        global $woocommerce; 
        // check if is admin logged in or ajax in progress
	    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
	        return;

	    $products = [];
	    foreach ( WC()->cart->get_cart() as $cart_item ) {
	    	$products[] = (string)$cart_item['product_id'];
	    }
        if ( !WC()->cart->is_empty() ) {
            $products_intersect = (!empty(explode(",", get_option('wc_increase_price_products')))) ? explode(",", get_option('wc_increase_price_products')) : [];
	    	$percentage = ((int)get_option('wc_increase_price_percentage',10) > 10) ? (int)get_option('wc_increase_price_percentage',10) : 10; 

            if(!empty(array_intersect($products_intersect, $products))) {
		    	$increase = $cart->subtotal * $percentage/100;
		   		$cart->add_fee( __( 'Product Increase Price by '.$percentage.'%', 'woocommerce-increase_price' ) , $increase );
			}
		}
	}

    /** Ajax callback function to the select2 
    *
    *
    * @return object of id and name of products 
    */
	public static function get_all_products(){
        global $wpdb;
        $wpdb->show_errors( true );
        $resp = $wpdb->get_results( $wpdb->prepare('SELECT '
                . 'ID as `id`, '
                . 'post_title AS `text` '
                . 'FROM '.$wpdb->prefix.'posts '
                . 'WHERE post_type=%s '
                . 'AND post_status=%s '
                . 'AND post_title LIKE %s '
                . 'ORDER BY `ID` DESC '
                . 'LIMIT 50', 'product', 'publish', '%'.$_REQUEST['q']['term'].'%'
            ), OBJECT 
        );
        // var_dump($_REQUEST['q']);
    
        if($resp == null){
            die(json_encode(array('status' => false, 'message' => 'Error occured')));
        }else{
            die(json_encode(array('results' => $resp)));
        }
	}

}
WC_Increase_Price::init();
