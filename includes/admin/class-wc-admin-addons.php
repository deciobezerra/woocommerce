<?php
/**
 * Addons Page
 *
 * @package  WooCommerce\Admin
 * @version  2.5.0
 */

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Admin_Addons Class.
 */
class WC_Admin_Addons {

	/**
	 * Get featured for the addons screen
	 *
	 * @return void
	 */
	public static function render_featured() {
		$featured = get_transient( 'wc_addons_featured' );
		if ( false === $featured ) {
			$headers = array();
			$auth    = WC_Helper_Options::get( 'auth' );

			if ( ! empty( $auth['access_token'] ) ) {
				$headers['Authorization'] = 'Bearer ' . $auth['access_token'];
			}

			$parameter_string = '';
			$country  = WC()->countries->get_base_country();
			if ( ! empty( $country ) ) {
				$parameter_string = '?' . http_build_query( array( 'country' => $country ) );
			}

			// Important: WCCOM Extensions API v2.0 is used.
			$raw_featured = wp_safe_remote_get(
				'https://woocommerce.com/wp-json/wccom-extensions/2.0/featured' . $parameter_string,
				array(
					'headers'    => $headers,
					'user-agent' => 'WooCommerce Addons Page',
				)
			);

			if ( ! is_wp_error( $raw_featured ) ) {
				$featured = json_decode( wp_remote_retrieve_body( $raw_featured ) );
				if ( $featured ) {
					set_transient( 'wc_addons_featured', $featured, DAY_IN_SECONDS );
				}
			}
		}

		if ( ! empty( $featured ) ) {
			self::output_featured( $featured );
		}
	}

	/**
	 * Build url parameter string
	 *
	 * @param  string $category Addon (sub) category.
	 * @param  string $term     Search terms.
	 * @param  string $country  Store country.
	 *
	 * @return string url parameter string
	 */
	public static function build_parameter_string( $category, $term, $country ) {

		$parameters = array(
			'category' => $category,
			'term'     => $term,
			'country'  => $country,
		);

		return '?' . http_build_query( $parameters );
	}

	/**
	 * Call API to get extensions
	 *
	 * @param  string $category Addon (sub) category.
	 * @param  string $term     Search terms.
	 * @param  string $country  Store country.
	 *
	 * @return array of extensions
	 */
	public static function get_extension_data( $category, $term, $country ) {
		$parameters     = self::build_parameter_string( $category, $term, $country );

		$headers = array();
		$auth    = WC_Helper_Options::get( 'auth' );

		if ( ! empty( $auth['access_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $auth['access_token'];
		}

		$raw_extensions = wp_safe_remote_get(
			'https://woocommerce.com/wp-json/wccom-extensions/1.0/search' . $parameters,
			array( 'headers' => $headers )
		);

		if ( ! is_wp_error( $raw_extensions ) ) {
			$addons = json_decode( wp_remote_retrieve_body( $raw_extensions ) )->products;
		}
		return $addons;
	}

	/**
	 * Get sections for the addons screen
	 *
	 * @return array of objects
	 */
	public static function get_sections() {
		$addon_sections = get_transient( 'wc_addons_sections' );
		if ( false === ( $addon_sections ) ) {
			$raw_sections = wp_safe_remote_get(
				'https://woocommerce.com/wp-json/wccom-extensions/1.0/categories'
			);
			if ( ! is_wp_error( $raw_sections ) ) {
				$addon_sections = json_decode( wp_remote_retrieve_body( $raw_sections ) );
				if ( $addon_sections ) {
					set_transient( 'wc_addons_sections', $addon_sections, WEEK_IN_SECONDS );
				}
			}
		}
		return apply_filters( 'woocommerce_addons_sections', $addon_sections );
	}

	/**
	 * Handles the outputting of featured page
	 *
	 * @param array $blocks Featured page's blocks.
	 */
	private static function output_featured( $blocks ) {
		foreach ( $blocks as $block ) {
			switch ( $block->type ) {
				case 'group':
					self::output_group( $block );
					break;
				case 'banner':
					self::output_banner( $block );
					break;
			}
		}
	}

	/**
	 * Render a group block including products
	 *
	 * @param mixed $block Block of the page for rendering.
	 *
	 * @return void
	 */
	private static function output_group( $block ) {
		// TODO: Output products according to the group's capacity.
		?>
			<section class="addon-product-group">
				<h1 class="addon-product-group-title"><?php echo esc_html( $block->title ); ?></h1>
				<div class="addon-product-group-description-container">
					<?php if ( ! empty( $block->description ) ) : ?>
					<div class="addon-product-group-description">
						<?php echo esc_html( $block->description ); ?>
					</div>
					<?php endif; ?>
					<?php if ( null !== $block->url ) : ?>
					<a class="addon-product-group-see-more" href="<?php echo esc_url( $block->url ); ?>">
						<?php esc_html_e( 'See more', 'woocommerce' ); ?>
					</a>
					<?php endif; ?>
				</div>
				<div class="addon-product-group__items">
					<ul class="products">
					<?php
					$products = array_slice( $block->items, 0, $capacity );
					foreach ( $products as $item ) {
						self::render_product_card( $item );
					}
					?>
					</ul>
				<div>
			</section>
		<?php
	}

	/**
	 * Render a banner contains a product
	 *
	 * @param mixed $block Block of the page for rendering.
	 *
	 * @return void
	 */
	private static function output_banner( $block ) {
		?>
			<ul class="products">
			<?php self::render_product_card( $block ); ?>
			</ul>
		<?php
	}

	/**
	 * Returns in-app-purchase URL params.
	 */
	public static function get_in_app_purchase_url_params() {
		// Get url (from path onward) for the current page,
		// so WCCOM "back" link returns user to where they were.
		$back_admin_path = add_query_arg( array() );
		return array(
			'wccom-site'          => site_url(),
			'wccom-back'          => rawurlencode( $back_admin_path ),
			'wccom-woo-version'   => Constants::get_constant( 'WC_VERSION' ),
			'wccom-connect-nonce' => wp_create_nonce( 'connect' ),
		);
	}

	/**
	 * Add in-app-purchase URL params to link.
	 *
	 * Adds various url parameters to a url to support a streamlined
	 * flow for obtaining and setting up WooCommerce extensons.
	 *
	 * @param string $url    Destination URL.
	 */
	public static function add_in_app_purchase_url_params( $url ) {
		return add_query_arg(
			self::get_in_app_purchase_url_params(),
			$url
		);
	}

	/**
	 * Handles output of the addons page in admin.
	 */
	public static function output() {
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '_featured';
		$search  = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
			do_action( 'woocommerce_helper_output' );
			return;
		}

		if ( isset( $_GET['install-addon'] ) ) {
			switch ( $_GET['install-addon'] ) {
				case 'woocommerce-services':
					self::install_woocommerce_services_addon();
					break;
				case 'woocommerce-payments':
					self::install_woocommerce_payments_addon( $section );
					break;
				default:
					// Do nothing.
					break;
			}
		}

		$sections        = self::get_sections();
		$theme           = wp_get_theme();
		$current_section = isset( $_GET['section'] ) ? $section : '_featured';
		$addons          = array();

		if ( '_featured' !== $current_section ) {
			$category = $section ? $section : null;
			$term     = $search ? $search : null;
			$country  = WC()->countries->get_base_country();
			$addons   = self::get_extension_data( $category, $term, $country );
		}

		/**
		 * Addon page view.
		 *
		 * @uses $addons
		 * @uses $search
		 * @uses $sections
		 * @uses $theme
		 * @uses $current_section
		 */
		include_once dirname( __FILE__ ) . '/views/html-admin-page-addons.php';
	}

	/**
	 * Install WooCommerce Services from Extensions screens.
	 */
	public static function install_woocommerce_services_addon() {
		check_admin_referer( 'install-addon_woocommerce-services' );

		$services_plugin_id = 'woocommerce-services';
		$services_plugin    = array(
			'name'      => __( 'WooCommerce Services', 'woocommerce' ),
			'repo-slug' => 'woocommerce-services',
		);

		WC_Install::background_installer( $services_plugin_id, $services_plugin );

		wp_safe_redirect( remove_query_arg( array( 'install-addon', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Install WooCommerce Payments from the Extensions screens.
	 *
	 * @param string $section Optional. Extenstions tab.
	 *
	 * @return void
	 */
	public static function install_woocommerce_payments_addon( $section = '_featured' ) {
		check_admin_referer( 'install-addon_woocommerce-payments' );

		$wcpay_plugin_id = 'woocommerce-payments';
		$wcpay_plugin    = array(
			'name'      => __( 'WooCommerce Payments', 'woocommerce' ),
			'repo-slug' => 'woocommerce-payments',
		);

		WC_Install::background_installer( $wcpay_plugin_id, $wcpay_plugin );

		do_action( 'woocommerce_addon_installed', $wcpay_plugin_id, $section );

		wp_safe_redirect( remove_query_arg( array( 'install-addon', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * We're displaying page=wc-addons and page=wc-addons&section=helper as two separate pages.
	 * When we're on those pages, add body classes to distinguishe them.
	 *
	 * @param string $admin_body_class Unfiltered body class.
	 *
	 * @return string Body class with added class for Marketplace or My Subscriptions page.
	 */
	public static function filter_admin_body_classes( string $admin_body_class = '' ): string {
		if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
			return " $admin_body_class woocommerce-page-wc-subscriptions ";
		}

		return " $admin_body_class woocommerce-page-wc-marketplace ";
	}

	/**
	 * Map data from different endpoints to a universal format
	 *
	 * Search and featured products has a slightly different products' field names.
	 * Mapping converts different data structures into a universal one for further processing.
	 *
	 * @param mixed $data Product Card Data.
	 *
	 * @return object Converted data.
	 */
	public static function map_product_card_data( $data ) {
		$mapped = (object) null;

		$type = $data->type ?? null;

		// Icon.
		$mapped->icon = $data->icon ?? null;
		// URL.
		$mapped->url = $data->link ?? null;
		if ( empty( $mapped->url ) ) {
			$mapped->url = $data->url ?? null;
		}
		// Title.
		$mapped->title = $data->title ?? null;
		// Vendor Name.
		$mapped->vendor_name = $data->vendor_name ?? null;
		if ( empty( $mapped->vendor_name ) ) {
			$mapped->vendor_name = $data->vendorName ?? null; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		// Vendor URL.
		$mapped->vendor_url = $data->vendor_url ?? null;
		if ( empty( $mapped->vendor_url ) ) {
			$mapped->vendor_url = $data->vendorUrl ?? null; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		// Description.
		$mapped->description = $data->excerpt ?? null;
		if ( empty( $mapped->description ) ) {
			$mapped->description = $data->description ?? null;
		}
		$has_currency = ! empty( $data->currency ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// Is Free.
		if ( $has_currency ) {
			$mapped->is_free = 0 === $data->price;
		} else {
			$mapped->is_free = '&#36;0.00' === $data->price;
		}
		// Price.
		if ( $has_currency ) {
			$mapped->price = wc_price( $data->price, array( 'currency' => $data->currency ) );
		} else {
			$mapped->price = $data->price;
		}
		// Rating.
		$mapped->rating = $data->rating ?? null;
		// Reviews Count.
		$mapped->reviews_count = $data->reviews_count ?? null;

		return $mapped;
	}

	/**
	 * Render a product card
	 *
	 * There's difference in data structure (e.g. field names) between endpoints such as search and
	 * featured. Inner mapping helps to use universal field names for further work.
	 *
	 * @param mixed $data Product data.
	 */
	public static function render_product_card( $data ) {
		$mapped      = self::map_product_card_data( $data );
		$product_url = self::add_in_app_purchase_url_params( $mapped->url );
		?>
			<li class="product">
				<div class="product-details">
					<?php if ( ! empty( $mapped->icon ) ) : ?>
						<span class="product-img-wrap">
							<?php /* Show an icon if it exists */ ?>
							<img src="<?php echo esc_url( $mapped->icon ); ?>" />
						</span>
					<?php endif; ?>
					<a href="<?php echo esc_url( $product_url ); ?>">
						<h2><?php echo esc_html( $mapped->title ); ?></h2>
					</a>
					<?php if ( ! empty( $mapped->vendor_name ) && ! empty( $mapped->vendor_url ) ) : ?>
						<div class="product-developed-by">
							<?php
								printf(
									/* translators: %s vendor link */
									esc_html__( 'Developed by %s', 'woocommerce' ),
									sprintf(
										'<a class="product-vendor-link" href="%1$s" target="_blank">%2$s</a>',
										esc_url_raw( $mapped->vendor_url ),
										wp_kses_post( $mapped->vendor_name )
									)
								);
							?>
						</div>
					<?php endif; ?>
					<p><?php echo wp_kses_post( $mapped->description ); ?></p>
				</div>
				<div class="product-footer">
					<div class="product-price-and-reviews-container">
						<div class="product-price-block">
							<?php if ( $mapped->is_free ) : ?>
								<span class="price"><?php esc_html_e( 'Free', 'woocommerce' ); ?></span>
							<?php else : ?>
								<span class="price"><?php echo wp_kses_post( $mapped->price ); ?></span>
								<span class="price-suffix"><?php esc_html_e( 'per year', 'woocommerce' ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $mapped->reviews_count ) && ! empty( $mapped->rating ) ) : ?>
							<?php /* Show rating and the number of reviews */ ?>
							<div class="product-reviews-block">
								<?php for ( $index = 1; $index <= 5; ++$index ) : ?>
									<?php $rating_star_class = 'product-rating-star product-rating-star__' . wccom_get_star_class( $mapped->rating, $index ); ?>
									<div class="<?php echo esc_attr( $rating_star_class ); ?>"></div>
								<?php endfor; ?>
								<span class="product-reviews-count">(<?php echo wp_kses_post( $mapped->reviews_count ); ?>)</span>
							</div>
						<?php endif; ?>
					</div>
					<a class="button" href="<?php echo esc_url( $product_url ); ?>">
						<?php esc_html_e( 'View details', 'woocommerce' ); ?>
					</a>
				</div>
			</li>
		<?php
	}
}