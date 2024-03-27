<?php
/**
 * Telr plugin admin handler
 */

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

include(dirname(__FILE__) . '/settings/settings-telr.php');

class WC_Gateway_Telr_Admin_Handler
{
    public function __construct()
    {
        $this->allowed_currencies = include(dirname(__FILE__) . '/settings/supportd-currencies-telr.php');
    }
    public function is_valid_for_use()
    {
        $currency_list = apply_filters('woocommerce_telr_supported_currencies', $this->allowed_currencies);
        return in_array(get_woocommerce_currency(), $currency_list);
    }
    public function init_form_fields()
    {
        return WC_Telr_Cards_Settings::core_settings();
    }
	
	public function init_apple_form_fields()
    {
        return WC_Telr_Cards_Settings::apple_pay_settings();
    }
	
	/**
	 * Generate HTML links for the settings page.	 
	 *
	 * @return void
	 */
	public static function generate_links() {

		$screen = ! empty( $_GET['screen'] ) ? sanitize_text_field( wp_unslash( $_GET['screen'] ) ) : '';
		if ( empty( $screen ) ) {
			$screen = ! empty( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		}

		?>
		<style>			
			.cko-admin-settings__links li {
				color: #646970;
				font-size: 17px;
				font-weight: 600;
				display: inline-block;
				margin: 0;
				padding: 0;
				white-space: nowrap;
			}
			.cko-admin-settings__links a {
				text-decoration: none;
			}
			.cko-admin-settings__links .current {
				color: #000;
			}
		</style>
		<div class="cko-admin-settings__links">
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wctelr' ) ); ?>"
						class="<?php echo 'wctelr' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Core Setting', 'wctelr' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_telr_apple_pay' ) ); ?>"
						class="<?php echo 'wc_telr_apple_pay' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Apple Pay', 'wctelr' ); ?></a></li>
			</ul>
		</div>
		<?php
	}
}
