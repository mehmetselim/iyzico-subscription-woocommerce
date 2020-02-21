<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Iyzico_Subscription_For_WooCommerce_Fields {

	public static function iyzicoAdminFields() {

		return $form_fields = array(
			 'api_type' => array(
		        'title' 	=> __('Api Type', 'woocommerce-iyzico-subscription'),
		        'type' 		=> 'select',
		        'required'  => true,
		        'default' 	=> 'popup',
		        'options' 	=> 
		        	array(
		        	 'https://api.iyzipay.com'    => __('Live', 'woocommerce-iyzico-subscription'),
		        	 'https://sandbox-api.iyzipay.com' => __('Sandbox / Test', 'woocommerce-iyzico-subscription')
		     )),
		     'api_key' => array(
		         'title' => __('Api Key', 'woocommerce-iyzico-subscription'),
		         'type' => 'text'
		     ),
		     'secret_key' => array(
		         'title' => __('Secret Key', 'woocommerce-iyzico-subscription'),
		         'type' => 'text'
		     ),
		    'title' => array(
		        'title' => __('Payment Value', 'woocommerce-iyzico-subscription'),
		        'type' => 'text',
		        'description' => __('This message will show to the user during checkout.', 'woocommerce-iyzico-subscription'),
		        'default' => 'Online Ödeme'
		    ),
		    'description' => array(
		        'title' => __('Payment Form Description Value', 'woocommerce-iyzico-subscription'),
		        'type' => 'text',
		        'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-iyzico-subscription'),
		        'default' => __('Pay with your credit card via iyzico.', 'woocommerce-iyzico-subscription'),
		        'desc_tip' => true,
		    ),
		    'enabled' => array(
		        'title' => __('Enable/Disable', 'woocommerce-iyzico-subscription'),
		        'label' => __('Enable iyzico checkout', 'woocommerce-iyzico-subscription'),
		        'type' => 'checkbox',
		        'default' => 'yes'
		    ),
		);
	}
}
