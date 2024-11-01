<?php
/*
Plugin Name: TT Discount Option for WooCommerce
Plugin URI: https://terrytsang.com/product/tt-woocommerce-discount-option/
Description: Add a fixed fee/percentage discount based on minimum order amount.
Version: 1.0.0
Author: Terry Tsang
Author URI: https://www.terrytsang.com
*/

/*  Copyright 2022 Terry Tsang (email: terrytsang811@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

// Define plugin name
define('tt_plugin_name_discount_option', 'TT Discount Option for WooCommerce');

// Define plugin version
define('tt_version_discount_option', '1.0.0');


// Checks if the WooCommerce plugins is installed and active.
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
	if(!class_exists('WooCommerce_Discount_Option')){
		class WooCommerce_Discount_Option{

			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;

			var $textdomain;
		    var $types;
		    var $options_discount_option;
		    var $saved_options_discount_option;

			/**
			 * Gets things started by adding an action to initialize this plugin once
			 * WooCommerce is known to be active and initialized
			 */
			public function __construct(){
				load_plugin_textdomain('tt-discount-option-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
				
				WooCommerce_Discount_Option::$plugin_prefix = 'wc_discount_option_';
				WooCommerce_Discount_Option::$plugin_basefile = plugin_basename(__FILE__);
				WooCommerce_Discount_Option::$plugin_url = plugin_dir_url(WooCommerce_Discount_Option::$plugin_basefile);
				WooCommerce_Discount_Option::$plugin_path = trailingslashit(dirname(__FILE__));

				$this->types = array('fixed' => __('Fixed Fee', 'tt-discount-option-for-woocommerce'), 'percentage' => __('Cart Percentage(%)', 'tt-discount-option-for-woocommerce'));
				
				$this->options_discount_option = array(
					'discount_option_enabled' => '',
					'discount_option_label' => 'Discount',
					'discount_option_type' => 'fixed',
					'discount_option_cost' => 0,
					'discount_option_taxable' => false,
					'discount_option_minorder' => 0,
					'discount_option_date_start' => '',
					'discount_option_date_end' => '',
				);
	
				$this->saved_options_discount_option = array();
				
				add_action('woocommerce_init', array(&$this, 'init'));
			}

			/**
			 * Initialize extension when WooCommerce is active
			 */
			public function init(){

				add_action( 'admin_menu', array( &$this, 'tt_add_menu_discount_option' ) );
				add_action( 'admin_enqueue_scripts', array( &$this, 'tt_wc_discount_admin_scripts' ) );
				
				if(get_option('discount_option_enabled'))
				{
					add_action( 'woocommerce_cart_calculate_fees', array( &$this, 'tt_woo_add_discount') );
				}
			}

			public function tt_wc_discount_admin_scripts($hook)
			{
				global $discount_settings_page;
 
				if( $hook != $discount_settings_page ) 
					return;
				
				wp_register_style( 'admin-css', plugins_url('/assets/css/admin.css', __FILE__) );
				wp_enqueue_style( 'admin-css' );
			}
		
			/**
			 * Apply the discount with min order cart total
			 */
			public function tt_woo_add_discount() {
				global $woocommerce;
			
				$discount_option_label		= get_option( 'discount_option_label' ) ? get_option( 'discount_option_label' ) : 'Discount';
				$discount_option_cost		= get_option( 'discount_option_cost' ) ? get_option( 'discount_option_cost' ) : '0';
				$discount_option_type		= get_option( 'discount_option_type' ) ? get_option( 'discount_option_type' ) : 'fixed';
				$discount_option_taxable	= get_option( 'discount_option_taxable' ) ? get_option( 'discount_option_taxable' ) : false;
				$discount_option_minorder	= get_option( 'discount_option_minorder' ) ? get_option( 'discount_option_minorder' ) : '0';
				$discount_option_date_start	= get_option( 'discount_option_date_start' ) ? get_option( 'discount_option_date_start' ) : '';
				$discount_option_date_end	= get_option( 'discount_option_date_end' ) ? get_option( 'discount_option_date_end' ) : '';
				
				//get cart total
				$total = $woocommerce->cart->subtotal;

				//check for fee type (fixed fee or cart %)
				if($discount_option_type == 'percentage'){
					$discount_option_cost = ($discount_option_cost / 100) * $total;
				} 

				$discount_option_cost = round($discount_option_cost, 2);

				$boolAllow = 1;

				//check date
				$today_date = date("Y-m-d");
				if($discount_option_date_start != '' && $discount_option_date_end != ''){
					if($today_date < $discount_option_date_start || $today_date > $discount_option_date_end){
						$boolAllow = 0;
					}
				}
			
				//if cart total more or equal than $min_order
				if($discount_option_minorder > 0){
					if($total >= $discount_option_minorder) {
						if($boolAllow) {
							$woocommerce->cart->add_fee( __($discount_option_label, 'woocommerce'), -$discount_option_cost, $discount_option_taxable );
						}
					}
				} else {
					if($boolAllow) {
						$woocommerce->cart->add_fee( __($discount_option_label, 'woocommerce'), -$discount_option_cost, $discount_option_taxable );
					}
				}
			}
			
			/**
			 * Add a menu link to the woocommerce section menu
			 */
			function tt_add_menu_discount_option() {
				$wc_page = 'woocommerce';
				global $discount_settings_page;
				$discount_settings_page = add_submenu_page( $wc_page , __( 'TT Discount Option', "tt-discount-option-for-woocommerce" ), __( 'TT Discount Option', "tt-discount-option-for-woocommerce" ), 'manage_options', 'wc-discount-option', array(
						&$this,
						'settings_page_discount_option'
				));
			}
			
			/**
			 * Create the settings page content
			 */
			public function settings_page_discount_option() {
			
				// If form was submitted
				if ( isset( $_POST['submitted'] ) )
				{
					check_admin_referer( 'woocommerce-discount-option' );

					$this->saved_options_discount_option['discount_option_enabled'] = ! isset( $_POST['discount_option_enabled'] ) ? '1' : sanitize_text_field($_POST['discount_option_enabled']);
					$this->saved_options_discount_option['discount_option_label'] = ! isset( $_POST['discount_option_label'] ) ? 'Discount' : sanitize_text_field($_POST['discount_option_label']);
					$this->saved_options_discount_option['discount_option_cost'] = ! isset( $_POST['discount_option_cost'] ) ? 0 : sanitize_text_field($_POST['discount_option_cost']);
					$this->saved_options_discount_option['discount_option_type'] = ! isset( $_POST['discount_option_type'] ) ? 'fixed' : sanitize_text_field($_POST['discount_option_type']);
					$this->saved_options_discount_option['discount_option_taxable'] = ! isset( $_POST['discount_option_taxable'] ) ? false : sanitize_text_field($_POST['discount_option_taxable']);
					$this->saved_options_discount_option['discount_option_minorder'] = ! isset( $_POST['discount_option_minorder'] ) ? 0 : sanitize_text_field($_POST['discount_option_minorder']);
					$this->saved_options_discount_option['discount_option_date_start'] = ! isset( $_POST['discount_option_date_start'] ) ? '' : sanitize_text_field($_POST['discount_option_date_start']);
					$this->saved_options_discount_option['discount_option_date_end'] = ! isset( $_POST['discount_option_date_end'] ) ? '' : sanitize_text_field($_POST['discount_option_date_end']);
						

					foreach($this->options_discount_option as $field => $value)
					{
						$option_discount_option = get_option( $field );
			
						if($option_discount_option != $this->saved_options_discount_option[$field])
							update_option( $field, $this->saved_options_discount_option[$field] );
					}
						
					// Show message
					echo '<div id="message" class="updated fade"><p>' . __( 'WooCommerce Discount Option options saved.', "tt-discount-option-for-woocommerce" ) . '</p></div>';
				}

				$discount_option_enabled	= get_option( 'discount_option_enabled' );
				$discount_option_label		= get_option( 'discount_option_label' ) ? get_option( 'discount_option_label' ) : 'Discount';
				$discount_option_cost		= get_option( 'discount_option_cost' ) ? get_option( 'discount_option_cost' ) : '0';
				$discount_option_type		= get_option( 'discount_option_type' ) ? get_option( 'discount_option_type' ) : 'fixed';
				$discount_option_taxable	= get_option( 'discount_option_taxable' ) ? get_option( 'discount_option_taxable' ) : false;
				$discount_option_minorder	= get_option( 'discount_option_minorder' ) ? get_option( 'discount_option_minorder' ) : '0';
				$discount_option_date_start	= get_option( 'discount_option_date_start' ) ? get_option( 'discount_option_date_start' ) : '';
				$discount_option_date_end	= get_option( 'discount_option_date_end' ) ? get_option( 'discount_option_date_end' ) : '';

				$checked_enabled = '';
				$checked_taxable = '';
			
				if($discount_option_enabled)
					$checked_enabled = 'checked="checked"';
				
				if($discount_option_taxable)
					$checked_taxable = 'checked="checked"';

			
				$actionurl = sanitize_url( $_SERVER['REQUEST_URI'] );
				$nonce = wp_create_nonce( 'woocommerce-discount-option' );
			
			
				// Configuration Page
			
				?>
				<div id="icon-options-general" class="icon32"></div>
				<h3><?php _e( 'TT Discount Option', "tt-discount-option-for-woocommerce"); ?></h3>

				<form action="<?php echo esc_url($actionurl); ?>" method="post">
				<table>
						<tbody>
							<tr>
								<td colspan="2">
									<table class="widefat" cellspacing="2" cellpadding="2" border="0">
										<tr>
											<td width="25%"><?php _e( 'Enable', "tt-discount-option-for-woocommerce" ); ?></td>
											<td>
												<input class="checkbox" name="discount_option_enabled" id="discount_option_enabled" value="0" type="hidden">
												<input class="checkbox" name="discount_option_enabled" id="discount_option_enabled" value="1" <?php echo esc_attr($checked_enabled); ?> type="checkbox">
											</td>
										</tr>
										<tr>
											<td><?php _e( 'Label', "tt-discount-option-for-woocommerce" ); ?></td>
											<td>
												<input type="text" id="discount_option_label" name="discount_option_label" value="<?php echo esc_attr($discount_option_label); ?>" size="30" />
											</td>
										</tr>
										<tr>
											<td><?php _e( 'Amount', "tt-discount-option-for-woocommerce" ); ?></td>
											<td>
												<input type="text" id="discount_option_cost" name="discount_option_cost" value="<?php echo esc_attr($discount_option_cost); ?>" size="10" />
											</td>
										</tr>
										<tr>
											<td width="25%"><?php _e( 'Type', "tt-discount-option-for-woocommerce" ); ?></td>
											<td>
												<select name="discount_option_type">
													<option value="fixed" <?php if($discount_option_type == 'fixed') { echo 'selected="selected"'; } ?>><?php _e( 'Fixed Fee', "tt-discount-option-for-woocommerce" ); ?></option>
													<option value="percentage" <?php if($discount_option_type == 'percentage') { echo 'selected="selected"'; } ?>><?php _e( 'Cart Percentage(%)', "tt-discount-option-for-woocommerce" ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<td width="25%"><?php _e( 'Taxable', "tt-discount-option-for-woocommerce" ); ?></td>
											<td>
												<input class="checkbox" name="discount_option_taxable" id="discount_option_taxable" value="0" type="hidden">
												<input class="checkbox" name="discount_option_taxable" id="discount_option_taxable" value="1" <?php echo esc_attr($checked_taxable); ?> type="checkbox">
											</td>
										</tr>
										<tr>
											<td><?php _e( 'Minumum Order<br><span style="color:#999;">(Optional, apply discount when cart total is more or equal than this amount)</span>', "tt-discount-option-for-woocommerce" ); ?></td>
											<td>
													<input type="text" id="discount_option_minorder" name="discount_option_minorder" value="<?php echo esc_attr($discount_option_minorder); ?>" size="10" />
											</td>
										</tr>
										<tr><td colspan="2">&nbsp;</td></tr>
									</table>

									<div>&nbsp;</div>
									<div><strong>Apply to : <small style="color:#cccccc">(PRO)</small></strong></div>
									<div>&nbsp;</div>

									<div>
										<div class="label" style="width:200px;float:left;padding:5px 3px;">Product Categories: <small style="color:#cccccc">(PRO)</small></div>
										<div class="side-by-side clearfix"> 
											<select name="discount_option_product_categories[]" style="width:350px" class="chosen-container chosen-select" disabled>
												<option>PRO VERSION</option>
											</select>
										</div>
									</div>

									<div>
										<div class="label" style="width:200px;float:left;padding:5px 3px;">Products:  <small style="color:#cccccc">(PRO)</small></div>
										<div class="side-by-side clearfix"> 
											<select name="discount_option_products[]" style="width:350px" class="chosen-container chosen-select" disabled>
												<option>PRO VERSION</option>
											</select>
										</div>
									</div>

									<div>&nbsp;</div>
									<div><strong>Availability :  <small style="color:#cccccc">(PRO)</small></strong></div>
									<div>&nbsp;</div>

									<div>
										<div class="label" style="width:200px;float:left;padding:5px 3px;">Date:  <small style="color:#cccccc">(PRO)</small></div>
										<div class="side-by-side clearfix"> 
											<input type="date" class="discount_date_start" name="discount_option_date_start" value="<?php echo esc_attr($discount_option_date_start); ?>" placeholder="Select a start date..." disabled />
											&nbsp;
											<input type="date" class="discount_date_end" name="discount_option_date_end" value="<?php echo esc_attr($discount_option_date_end); ?>" placeholder="Select an end date..." disabled />
										</div>
									</div>

									<div>
										<div class="label" style="width:200px;float:left;padding:5px 3px;">Repeat Every: <small style="color:#cccccc">(PRO)</small></div>
										<div class="side-by-side clearfix"> 
											<select name="discount_option_repeat_day" disabled>
												<option>PRO VERSION</option>
											</select>
										</div>
									</div>

									<div>
										<div class="label" style="width:200px;float:left;padding:5px 3px;">Repeat Every Day of The Month: <small style="color:#cccccc">(PRO)</small></div>
										<div class="side-by-side clearfix"> 
											<select name="discount_option_repeat_day_number" disabled>
												<option>PRO VERSION</option>
											</select>
										</div>
									</div>

								</td>
							</tr>
							<tr><td>&nbsp;</td></tr>
							<tr>
								<td colspan=2">
									<input class="button-primary" type="submit" name="Save" value="<?php _e('Save Options', "tt-discount-option-for-woocommerce"); ?>" id="submitbutton" />
									<input type="hidden" name="submitted" value="1" /> 
									<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $nonce; ?>" />
								</td>
							</tr>
							
						</tbody>
				</table>
				</form>
					
				<br />
				<hr />
				<div style="height:30px"></div>
				<div class="center woocommerce-BlankState">
					<p><img src="<?php echo plugin_dir_url( __FILE__ ) ?>logo-terrytsang.png" title="Terry Tsang" alt="Terry Tsang" /></p>
					<h2 class="woocommerce-BlankState-message">Hi, I'm <a href="https://terrytsang.com" target="_blank">Terry Tsang</a> from 3 Mini Monsters. I have built WooCommerce plugins since 10 years ago and always find ways to make WooCommerce experience better through my products and articles. Thanks for using my plugin and do share around if you love this.</h2>

					<a class="woocommerce-BlankState-cta button" target="_blank" href="https://terrytsang.com/products">Check out our WooCommerce plugins</a>

					<a class="woocommerce-BlankState-cta button-primary button" href="https://terrytsang.com/product/tt-woocommerce-discount-option-pro" target="_blank">Upgrade to TT Discount Option PRO</a>

					
				</div>

				<br /><br /><br />

				<div class="components-card is-size-medium woocommerce-marketing-recommended-extensions-card woocommerce-marketing-recommended-extensions-card__category-coupons woocommerce-admin-marketing-card">
					<div class="components-flex components-card__header is-size-medium"><div>
						<span class="components-truncate components-text"></span>
						<div style="margin: 20px 20px">Try my other WooCommerce plugins to power up your online store and bring more sales/leads to you.</div>
					</div>
				</div>

				<div class="components-card__body is-size-medium">
					<div class="woocommerce-marketing-recommended-extensions-card__items woocommerce-marketing-recommended-extensions-card__items--count-6">
						<a href="https://terrytsang.com/product/tt-woocommerce-add-to-cart-buy-now-pro/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-add-to-cart-buy-now.png" title="TT Add to Cart Buy Now for WooCommerce" alt="TT Add to Cart Buy Now for WooCommerce" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Add to Cart Buy Now</h4>
								<p style="color:#333333;">Customize the "Add to cart" button and add a simple “Buy Now” button to your WooCommerce website.</p>
							</div>
						</a>

						<a href="https://terrytsang.com/product/tt-woocommerce-donation-checkout-pro/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-donation-checkout.png" title="WooCommerce Donation Checkout" alt="WooCommerce Donation Checkout" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Donation Checkout</h4>
								<p style="color:#333333;">Enable customers to topup their donation/tips at the checkout page.</p>
							</div>
						</a>

						<a href="https://terrytsang.com/product/tt-woocommerce-one-page-checkout-pro/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-onepage-checkout.png" title="WooCommerce OnePage Checkout" alt="WooCommerce OnePage Checkout" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT One-Page Checkout</h4>
								<p style="color:#333333;">Combine cart and checkout at one page to simplify entire WooCommerce checkout process.</p>
							</div>
						</a>

						<a href="https://terrytsang.com/product/tt-woocommerce-extra-fee-option-pro/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-custom-checkout.png" title="WooCommerce Extra Fee Options" alt="WooCommerce Extra Fee Options" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Extra Fee Option</h4>
								<p style="color:#333333;">Add a discount based on minimum order amount, product categories, products and date range.</p>
							</div>
						</a>

						<!-- <a href="https://terrytsang.com/product/tt-woocommerce-coming-soon/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-coming-soon-product.png" title="WooCommerce Coming Soon Product" alt="WooCommerce Coming Soon Product" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Coming Soon</h4>
								<p style="color:#333333;">Display countdown clock at coming-soon product page.</p>
							</div>
						</a> -->

						<!-- <a href="https://terrytsang.com/product/tt-woocommerce-product-badge/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-product-badge.png" title="WooCommerce Product Badge" alt="WooCommerce Product Badge" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Product Badge</h4>
								<p style="color:#333333;">Add product badges liked Popular, Sales, Featured to the product.</p>
							</div>
						</a> -->

						<!-- <a href="https://terrytsang.com/product/tt-woocommerce-product-catalog/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-product-catalog.png" title="WooCommerce Product Catalog" alt="WooCommerce Product Catalog" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Product Catalog</h4>
								<p style="color:#333333;">Hide Add to Cart / Checkout button and turn your website into product catalog.</p>
							</div>
						</a> -->

					
					</div>
				</div>
				
			<?php
			}
			
			/**
			 * Get the setting options
			 */
			function get_options() {
				
				foreach($this->options_discount_option as $field => $value)
				{
					$array_options[$field] = get_option( $field );
				}
					
				return $array_options;
			}
			

		}//end class
			
	}//if class does not exist
	
	$woocommerce_discount_option = new WooCommerce_Discount_Option();
}
else{
	add_action('admin_notices', 'tt_discount_option_error_notice');
	function tt_discount_option_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__(tt_plugin_name_discount_option.' requires <a href="http://www.woocommerce.com/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">WooCommerce</a> first.').'</p></div>';
		}
	}
}

?>