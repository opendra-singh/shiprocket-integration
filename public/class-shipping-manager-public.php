<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://woodevz.com
 * @since      1.0.0
 *
 * @package    Shipping_Manager
 * @subpackage Shipping_Manager/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Shipping_Manager
 * @subpackage Shipping_Manager/public
 * @author     Shashwat <shashwat.srivastava@woodevz.com>
 */
class Shipping_Manager_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Shipping_Manager_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Shipping_Manager_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/shipping-manager-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Shipping_Manager_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Shipping_Manager_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/shipping-manager-public.js', array( 'jquery' ), $this->version, false );

	}

	function debug($data){
		echo "<pre>";
		print_r($data);
		die;
	}

	function nimbus($fraud_detector_options, $order){
		$response = login_curl($fraud_detector_options['email'], $fraud_detector_options['password']);
		if(json_decode($response, true)['status']){
			$token = json_decode($response, true)['data'];
			$origin = $fraud_detector_options['pincode'];
			$status = $order->get_payment_method() == 'cod' ? $order->get_payment_method() : 'prepaid';
			$shipping_zip_code = $order->get_shipping_postcode();
			$amount = $order->get_total();
			$result = check_availability_and_price($origin, $shipping_zip_code, $status, $amount, $token);
			if(json_decode($result, true)['status']){
				if(!empty(json_decode($result, true)['data'])){
					$id = array_splice(json_decode($result, true)['data'], array_search(min(array_column(json_decode($result, true)['data'], 'total_charges')), array_column(json_decode($result, true)['data'], 'total_charges')), 1)[0]['id'];
					$data = array(
						'order_number' => $order->get_id(),
						'payment_type' => $status,
						'order_amount' => $amount,
						'request_auto_pickup ' => "Yes",
						'consignee' => array(
							'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
							'address' => $order->get_shipping_address_1(),
							'address_2' => $order->get_shipping_address_2(),
							'city' => $order->get_shipping_city(),
							'state' => $order->get_shipping_state(),
							'pincode' => $order->get_shipping_postcode(),
							'phone' => $order->get_billing_phone(),
						),
						'pickup' => array(
							'warehouse_name' => $fraud_detector_options['warehouse_name'],
							'name' => $fraud_detector_options['name'],
							'address' => $fraud_detector_options['address'],
							'city' => $fraud_detector_options['city'],
							'state' => $fraud_detector_options['state'],
							'pincode' => $fraud_detector_options['pincode'],
							'phone' => $fraud_detector_options['phone'],
							'courier_id' => $id,
						),
						'order_items' => [],
					);
					foreach ($order->get_items() as $item_id => $item) {
						$data['order_items'] = [array(
							'name' => $item->get_name(),
							'qty' => $item->get_quantity(),
							'price' => $item->get_total(),
						)];
					}
					$shipment_curl_response = shipment_curl($token, $data);
					$shipment = json_decode($shipment_curl_response, true);
					if ($shipment['status']) {
						update_post_meta($order->get_id(), 'shipment_generated', array(
							'awb_number' => $shipment['data']['awb_number'],
							'link' => $shipment['data']['label'],
						));
						$order = new WC_Order($order->get_id());
						$order->update_status('completed', 'Order Completed');
						return "Shipment Generated Successfully.";
					}else{
						return $shipment['message'];
					}
				}else{
					return 'No service provider available in your area.';
				}
			}else{
				return json_decode($result, true)['message'];
			}
		}else{
			return json_decode($response, true)['message'];
		}
	}

	function shiprocket($fraud_detector_options, $order){
		$option = get_option("printer_automation", array());
		$prefix = $option['site_prefix'];
		$email = $fraud_detector_options['shiprocket_email'];
		$password = $fraud_detector_options['shiprocket_password'];
		$auth_token_result = shiprocket_auth_token($email, $password);
		if (is_array($auth_token_result) && array_key_exists('token', $auth_token_result)) {
			$token = $auth_token_result['token'];
			$shipping_phone = substr(str_replace(" ", '', $order->get_shipping_phone()), -10);
			$billing_phone = substr(str_replace(" ", '', $order->get_billing_phone()), -10);
			$create_shipment_data = array(
				"order_id" => $prefix . ' - ' . $order->get_id(),
				"order_date" => $order->get_date_created()->date("Y-m-d H:i:s"),
				"billing_customer_name" => $order->get_billing_first_name(),
				"billing_last_name" => $order->get_billing_last_name(),
				"billing_address" => $order->get_billing_address_1(),
				"billing_address_2" => $order->get_billing_address_2(),
				"billing_city" => $order->get_billing_city(),
				"billing_pincode" => $order->get_billing_postcode(),
				"billing_state" => $order->get_billing_state(),
				"billing_country" => $order->get_billing_country(),
				"billing_email" => $order->get_billing_email(),
				"billing_phone" => $billing_phone,
				"shipping_is_billing" => $order->get_billing_address_1() == $order->get_shipping_address_1() ? true : false,
				"shipping_customer_name" => $order->get_shipping_first_name(),
				"shipping_last_name" => $order->get_shipping_last_name(),
				"shipping_address" => $order->get_shipping_address_1(),
				"shipping_address_2" => $order->get_shipping_address_2(),
				"shipping_city" => $order->get_shipping_city(),
				"shipping_pincode" => $order->get_shipping_postcode(),
				"shipping_country" => $order->get_shipping_country(),
				"shipping_state" => $order->get_shipping_state(),
				"shipping_email" => $order->get_billing_email(),
				"shipping_phone" => $shipping_phone,
				"order_items" => [],
				"payment_method" => $order->get_payment_method() == "cod" ? "COD" : "Prepaid",
				"shipping_charges" => $order->get_shipping_total(),
				"sub_total" => $order->get_subtotal(),
				"length" => 0,
				"breadth" => 0,
				"height" => 0,
				"weight" => 0,
			);
			$qty = 0;
			foreach ($order->get_items() as $item_id => $item) {
				$qty += $item->get_quantity();
				$create_shipment_data['length'] = $item->get_product()->get_length();
				$create_shipment_data['breadth'] = $item->get_product()->get_width();
				$create_shipment_data['order_items'][] =
					array(
						"name" => $item->get_name(),
						"sku" => empty($item->get_product()->get_sku()) ? "Book-" . $item_id : $item->get_product()->get_sku(),
						"units" => $item->get_quantity(),
						"selling_price" => $item->get_subtotal(),
						"discount" => $order->get_total_discount(),
						"tax" => $order->get_total_tax(),
					);
			}
			$create_shipment_data['height'] = $qty * 2.5;
			$base_weight = 0.25;
			$create_shipment_data['weight'] = $qty * $base_weight;
			$create_shipment_result = shiprocket_create_shipment($token, $create_shipment_data);
			$cod = $order->get_payment_method() == "cod" ? 1 : 0;
			start:
			if($qty%2 == 0){
				$weight = (string)$qty * $base_weight;
			}else{
				$qty += 1;
				goto start;
			}
			$check_courier_serviceablility = shiprocket_check_courier_serviceability($token, $fraud_detector_options['pincode'], $order->get_shipping_postcode(), $cod, $weight);
			$pincode_list = array();
			$l = $check_courier_serviceablility['data']['available_courier_companies'];
			foreach ($l as $key => $value) {
				$pincode_list[] = array(
					'id' => $value['courier_company_id'],
					'total' => $value['cod_charges'] + $value['freight_charge'],
					'days' => $value['estimated_delivery_days'],
				);
			}
			$courier_id = 0;
			$low = $fraud_detector_options['lowest_shipping_charges'];
			$high = $fraud_detector_options['highest_shipping_charges'];
			$samount = $order->get_shipping_total();
			if($samount < $low){
				array_multisort(array_map(function($element) {
					return $element['total'];
				}, $pincode_list), SORT_ASC, $pincode_list);
				$courier_id = $pincode_list[0]['id'];
			}else if($samount >= $low && $samount <= $high){
				array_multisort(array_map(function($element) {
					return $element['total'];
				}, $pincode_list), SORT_ASC, $pincode_list);
				$courier_id = $pincode_list[floor(count($pincode_list)/2)]['id'];
			}else if($samount > $high){
				array_multisort(array_map(function($element) {
					return $element['days'];
				}, $pincode_list), SORT_DESC, $pincode_list);
				$days = array_slice($pincode_list,-3);
				array_multisort(array_map(function($element) {
					return $element['total'];
				}, $days), SORT_ASC, $days);
				$courier_id = $days[0]['id'];
			}
			if (is_array($create_shipment_result) && array_key_exists("shipment_id", $create_shipment_result)) {
				$shipment_id = $create_shipment_result['shipment_id'];
				$generate_awb_result = shiprocket_generate_awb($token, $shipment_id, $courier_id);
				if (is_array($generate_awb_result) && array_key_exists("awb_assign_status", $generate_awb_result) && $generate_awb_result['awb_assign_status']) {
					$awb_number = $generate_awb_result['response']['data']['awb_code'];
					$shipment_pickup_result = shiprocket_shipment_pickup($token, $shipment_id);
					if (is_array($shipment_pickup_result) && $shipment_pickup_result['pickup_status']) {
						$generate_label_result = shiprocket_generate_label($shipment_id, $token);
						if (is_array($generate_label_result) && $generate_label_result['label_created'] ) {
							$generate_manifest_result = shiprocket_generate_manifest($token, $shipment_id);
							if (is_array($generate_manifest_result) && $generate_manifest_result['status']) {
								update_post_meta($order->get_id(), 'shiprocket_shipment', array(
									'awb_number' => $awb_number,
									'pickup_token_number' => $shipment_pickup_result['response']['pickup_token_number'],
									'manifest_url' => $generate_manifest_result['manifest_url'],
									'label_url' => $generate_label_result['label_url'],
								));
								return "Shipment Generated Successfully.|" . $awb_number;
							} else {
								return $generate_manifest_result['message'];
							}
						}else{
							return $generate_label_result['message'];
						}
					} else {
						return $shipment_pickup_result['message'];
					}
				} else {
					return array_key_exists("message", $generate_awb_result) ? $generate_awb_result['message'] : $generate_awb_result['response']['data']['awb_assign_error'];
				}
			} else {
				if (array_key_exists("errors", $create_shipment_result)) {
					$err_msg = array();
					foreach ($create_shipment_result['errors'] as $key => $value) {
						$err_msg[] = $value[0];
					}
					return implode(", ", $err_msg);
				} else {
					return $create_shipment_result['message'];
				}
			}
		} else {
			return $auth_token_result['message'];
		}
	}

	function check_if_shipment_generated_or_not($order_id){
		$nimbus_shipment = get_post_meta($order_id, 'nimbus_shipment', true)['link'] ?? '';
		$nimbus_awb = get_post_meta($order_id, 'nimbus_shipment', true)['awb_number'] ?? '';
		$shiprocket_shipment = get_post_meta($order_id, 'shiprocket_shipment', true)['label_url'] ?? '';
		$shiprocket_awb = get_post_meta($order_id, 'shiprocket_shipment', true)['awb_number'] ?? '';
		if(!empty($shiprocket_shipment) || $shiprocket_shipment != ""){
			$shipment = $shiprocket_shipment;
			$awb = $shiprocket_awb;
		}else{
			$shipment = $nimbus_shipment;
			$awb = $nimbus_awb;
		}
		return array(
			'status' => !empty($shipment) ? true : false,
			"link" => $shipment,
			"awb" => $awb,
		);
	}

	public function after_thankyou_send_to_socket_shipping_manager($order_id){
		if ($this->check_if_shipment_generated_or_not($order_id)['status']) {
			return "Shipment Already Generated!";
		}else{
			$order = new WC_Order($order_id);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://lookhype.com/endpoint.php');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=get_data&key=fraud_detector_options&table=fraud_detector_options');
			$response = curl_exec($ch);
			if (curl_errno($ch)) {
				return 'Error:' . curl_error($ch);
			}
			curl_close($ch);
			$fraud_detector_options = json_decode(json_decode($response, true)['value'], true);
			if (is_array($fraud_detector_options)) {
				switch ($fraud_detector_options['default_shipping_provider']) {
					case 'nimbus':
						return $this->nimbus($fraud_detector_options, $order);		
						break;				
					case 'shiprocket':	
						return $this->shiprocket($fraud_detector_options, $order);
						break;
				}
			} else {
				return "Please give the crendentials first!";
			}
		}
	}

	public function get_wallet_balance_shiprocket_callback(){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://lookhype.com/endpoint.php');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=get_data&key=fraud_detector_options&table=fraud_detector_options');
		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			return 'Error:' . curl_error($ch);
		}
		curl_close($ch);
		$fraud_detector_options = json_decode(json_decode($response, true)['value'], true);
		$email = $fraud_detector_options['shiprocket_email'];
		$password = $fraud_detector_options['shiprocket_password'];
		$auth_token_result = shiprocket_auth_token($email, $password);
		if (is_array($auth_token_result) && array_key_exists('token', $auth_token_result)) {
			$token = $auth_token_result['token'];
			$get_wallet_balance_result = shiprocket_get_wallet_balance($token);
			return $get_wallet_balance_result;
		} else {
			return $auth_token_result['message'];
		}
	}
}
