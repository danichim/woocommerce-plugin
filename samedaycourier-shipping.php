<?php

/**
 * Plugin Name: SamedayCourier Shipping
 * Plugin URI: http://sameday.ro
 * Description: SamedayCourier Shipping Method for WooCommerce
 * Version: 1.0.1
 * Author: SamedayCourier
 * Author URI: http://sameday.ro
 * License: GPL-3.0+
 * License URI: http://sameday.ro
 * Domain Path: /ro
 * Text Domain: sameday
 */

if (! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if WooCommerce plugin is enabled
 */
if (! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )), '')) {
	exit;
}

require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
require_once (plugin_basename('lib/sameday-courier/src/Sameday/autoload.php'));
require_once (plugin_basename('classes/samedaycourier-api.php'));
require_once (plugin_basename('sql/sameday_create_db.php'));
require_once (plugin_basename('sql/sameday_drop_db.php'));
require_once (plugin_basename('sql/sameday_query_db.php'));
require_once (plugin_basename('classes/samedaycourier-services.php'));
require_once (plugin_basename('classes/samedaycourier-service-instance.php'));
require_once (plugin_basename('classes/samedaycourier-pickuppoints.php'));
require_once (plugin_basename('classes/samedaycourier-pickuppoint-instance.php'));


function samedaycourier_shipping_method() {

	if (! class_exists('SamedayCourier_Shipping_Method')) {
		class SamedayCourier_Shipping_Method extends WC_Shipping_Method
		{
			/**
			 * @var bool
			 */
			private $configValidation;

			public function __construct( $instance_id = 0 )
			{
				parent::__construct( $instance_id );

				$this->id = 'samedaycourier';
				$this->method_title = __('SamedayCourier', 'samedaycourier');
				$this->method_description = __('Custom Shipping Method for SamedayCourier', 'samedaycourier');

				$this->configValidation = false;

				$this->init();
			}

			public function calculate_shipping( $package = array() )
			{
				$rate_1 = array(
					'id' => $this->id . '_1',
					'label' => $this->title . 1,
					'cost' => 19
				);

				$rate_2 = array(
					'id' => $this->id . '_2',
					'label' => $this->title . 2,
					'cost' => 30
				);

				if ($this->settings['enabled'] === 'no') {
					return;
				}

				// $availableServices = $this->getAvailableSerives();
				// foreach ( $availableServices as $service ) { $this->add_rate( $rate_1 ); }

				$this->add_rate( $rate_1 );
				$this->add_rate( $rate_2 );
			}

			private function init()
			{
				$this->form_fields = array(
					'enabled' => array(
						'title' => __( 'Enable', 'samedaycourier' ),
						'type' => 'checkbox',
						'description' => __( 'Enable this shipping.', 'samedaycourier' ),
						'default' => 'yes'
					),

					'title' => array(
						'title' => __( 'Title', 'samedaycourier' ),
						'type' => 'text',
						'description' => __( 'Title to be display on site', 'samedaycourier' ),
						'default' => __( 'SamedayCourier Shipping', 'samedaycourier' )
					),

					'user' => array(
						'title' => __( 'Username', 'samedaycourier' ),
						'type' => 'text',
						'description' => __( 'Username', 'samedaycourier' ),
						'default' => __( '', 'samedaycourier' )
					),

					'password' => array(
						'title' => __( 'Password', 'samedaycourier' ),
						'type' => 'password',
						'description' => __( 'Password', 'samedaycourier' ),
						'default' => __( '', 'samedaycourier' )
					),

					'is_testing' => array(
						'title' => __( 'Is testing', 'samedaycourier' ),
						'type' => 'checkbox',
						'description' => __( 'Disable this for production mode', 'samedaycourier' ),
						'default' => 'yes'
					),

					'estimated_cost' => array(
						'title' => __( 'Use estimated cost', 'samedaycourier' ),
						'type' => 'checkbox',
						'description' => __( 'This will show shipping cost calculated by Sameday Api for each service and show it on checkout page', 'samedaycourier' ),
						'default' => 'no'
					),
				);

				// Show on checkout:
				$this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
				$this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'SamedayCourier', 'samedaycourier' );

				$this->init_settings();

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ));
			}

			public function process_admin_options()
			{
				$post_data = $this->get_post_data();

				$sameday = Api::initClient(
					$post_data['woocommerce_samedaycourier_user'],
					$post_data['woocommerce_samedaycourier_password'],
					$post_data['woocommerce_samedaycourier_is_testing']
				);


				if (! $this->configValidation ) {
					$this->configValidation = true;

					if ( $sameday->login() ) {
						return parent::process_admin_options();
					}
				}

				return false;
			}
		}
	}
}

// Shipping Method init.
add_action('woocommerce_shipping_init', 'samedaycourier_shipping_method');

function add_samedaycourier_shipping_method( $methods ) {
	$methods['samedaycourier'] = 'SamedayCourier_Shipping_Method';

	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_samedaycourier_shipping_method');

// Plugin settings.
add_action('plugins_loaded', function () {
	SamedayCourierServiceInstance::get_instance();
	SamedayCourierPickupPointInstance::get_instance();
});

function refreshServices() {
	$samedayOption = get_option('woocommerce_samedaycourier_settings');
	if ( !isset($samedayOption) && empty($samedayOption) ) {
		wp_redirect(admin_url() . '/admin.php?page=sameday_services');
	}

	$is_testing = $samedayOption['is_testing'] === 'yes' ? 1 : 0;

	$sameday = new \Sameday\Sameday(Api::initClient(
		$samedayOption['user'],
		$samedayOption['password'],
		$is_testing
	));

	$remoteServices = [];
	$page = 1;

	do {
		$request = new \Sameday\Requests\SamedayGetServicesRequest();
		$request->setPage($page++);

		try {
			$services = $sameday->getServices($request);
		} catch (\Sameday\Exceptions\SamedayAuthenticationException $e) {
			wp_redirect(admin_url() . '/admin.php?page=sameday_services');
		}

		foreach ($services->getServices() as $serviceObject) {
			$service = getServiceSameday($serviceObject->getId(), $is_testing);
			if (! $service) {
				// Service not found, add it.
				addService($serviceObject, $is_testing);
			}

			// Save as current sameday service.
			$remoteServices[] = $serviceObject->getId();
		}

	} while ($page <= $services->getPages());

	// Build array of local services.
	$localServices = array_map(
		function ($service) {
			return array(
				'id' => $service->id,
				'sameday_id' => $service->sameday_id
			);
		},
		getServices($is_testing)
	);

	// Delete local services that aren't present in remote services anymore.
	foreach ($localServices as $localService) {
		if (!in_array($localService['sameday_id'], $remoteServices)) {
			deleteService($localService['id']);
		}
	}

	wp_redirect(admin_url() . '/admin.php?page=sameday_services');
}

function refreshPickupPoints() {
	$samedayOption = get_option('woocommerce_samedaycourier_settings');
	if ( !isset($samedayOption) && empty($samedayOption) ) {
		wp_redirect(admin_url() . 'admin.php?page=sameday_pickup_points');
	}

	$is_testing = $samedayOption['is_testing'] === 'yes' ? 1 : 0;

	$sameday = new \Sameday\Sameday(Api::initClient(
		$samedayOption['user'],
		$samedayOption['password'],
		$is_testing
	));

	$remotePickupPoints = [];
	$page = 1;
	do {
		$request = new Sameday\Requests\SamedayGetPickupPointsRequest();
		$request->setPage($page++);
		try {
			$pickUpPoints = $sameday->getPickupPoints($request);
		} catch (\Sameday\Exceptions\SamedayAuthenticationException $e) {
			wp_redirect(admin_url() . 'admin.php?page=sameday_pickup_points');
		}

		foreach ($pickUpPoints->getPickupPoints() as $pickupPointObject) {
			$pickupPoint = getPickupPointSameday($pickupPointObject->getId(), $is_testing);
			if (!$pickupPoint) {
				// Pickup point not found, add it.
				addPickupPoint($pickupPointObject, $is_testing);
			} else {
				updatePickupPoint($pickupPointObject, $is_testing);
			}

			// Save as current pickup points.
			$remotePickupPoints[] = $pickupPointObject->getId();
		}
	} while ($page <= $pickUpPoints->getPages());

	// Build array of local pickup points.
	$localPickupPoints = array_map(
		function ($pickupPoint) {
			return array(
				'id' => $pickupPoint->id,
				'sameday_id' => $pickupPoint->sameday_id
			);
		},
		getPickupPoints($is_testing)
	);

	// Delete local pickup points that aren't present in remote pickup points anymore.
	foreach ($localPickupPoints as $localPickupPoint) {
		if (!in_array($localPickupPoint['sameday_id'], $remotePickupPoints)) {
			deletePickupPoint($localPickupPoint['id']);
		}
	}

	wp_redirect(admin_url() . 'admin.php?page=sameday_pickup_points');
}

add_action('admin_post_refresh_services', 'refreshServices' );
add_action('admin_post_refresh_pickup_points', 'refreshPickupPoints');


register_activation_hook( __FILE__, 'samedaycourier_create_db' );
register_uninstall_hook( __FILE__, 'samedaycourier_drop_db');



