<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://woodevz.com
 * @since      1.0.0
 *
 * @package    Shipping_Manager
 * @subpackage Shipping_Manager/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Shipping_Manager
 * @subpackage Shipping_Manager/admin
 * @author     Opendra <info.ansh012@gmail.com>
 */
class Shipping_Manager_Admin
{

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

	private $fraud_detector_options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://lookhype.com/endpoint.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=get_data&key=fraud_detector_options&table=fraud_detector_options');
        $response = curl_exec($ch);
        curl_close($ch);
        $this->fraud_detector_options = json_decode(json_decode($response, true)['value'], true);

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles($page_id)
	{

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

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/shipping-manager-admin.css', array(), $this->version, 'all');
		if ($page_id == "toplevel_page_shiprocket-orders") {
			wp_enqueue_style('shipping_manager_bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css', array(), $this->version, 'all');
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($page_id)
	{

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


		wp_enqueue_script('shipping_manager_bootstrap_js', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js');
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/shipping-manager-admin.js', array('jquery'), $this->version, false);
		wp_localize_script(
			$this->plugin_name,
			'ajax_object',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
			)
		);
	}

	function admin_menu_callback()
	{
		add_menu_page("Shiprocket Orders", "Shiprocket Orders", "manage_options", "shiprocket-orders", array($this, "shiprocket_orders_menu_callback"), "", 6);
	}

	function shiprocket_orders_menu_callback()
	{
		global $wpdb;
		$table = $wpdb->prefix . 'postmeta';
		$result = $wpdb->get_results("SELECT * FROM `" . $table . "` WHERE `meta_key` = 'shiprocket_shipment'");
		?>
		<h3>Shiprocket Orders</h3>
		<table class="table table-bordered">
			<thead>
				<tr>
					<th scope="col">Order ID</th>
					<th scope="col">Shipment ID</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($result as $order) {
					$value = unserialize($order->meta_value);
					if (array_key_exists("only_order", $value) && $value['only_order']) {
				?>
						<tr>
							<td><?php echo $value['order_id']; ?></td>
							<td><?php echo $value['shipment_id']; ?></td>
						</tr>
				<?php
					}
				}
				?>
			</tbody>
		</table>
		<?php
	}

	function nimbus($order)
	{
		$response = login_curl($this->fraud_detector_options['nimbus_email'], $this->fraud_detector_options['nimbus_password']);
		$token = json_decode($response, true)['data'];
		$origin = $this->fraud_detector_options['pincode'];
		$status = $order->get_payment_method() == 'cod' ? $order->get_payment_method() : 'prepaid';
		$shipping_zip_code = $order->get_shipping_postcode();
		$amount = $order->get_total();
		$result = check_availability_and_price($origin, $shipping_zip_code, $status, $amount, $token);
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
				'warehouse_name' => $this->fraud_detector_options['warehouse_name'],
				'name' => $this->fraud_detector_options['name'],
				'address' => $this->fraud_detector_options['address'],
				'city' => $this->fraud_detector_options['city'],
				'state' => $this->fraud_detector_options['state'],
				'pincode' => $this->fraud_detector_options['pincode'],
				'phone' => $this->fraud_detector_options['phone'],
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
			update_post_meta($order->get_id(), 'nimbus_shipment', array(
				'awb_number' => $shipment['data']['awb_number'],
				'link' => $shipment['data']['label'],
			));
			$order->update_status('completed', 'Order Completed');
			return "Shipment Generated Successfully.|" . $shipment['data']['awb_number'];
		} else {
			return $shipment['message'];
		}
	}

	function shiprocket($order, $key)
	{
		$option = get_option("printer_automation", array());
		$prefix = $option['site_prefix'];
		$email = $this->fraud_detector_options['shiprocket_email'];
		$password = $this->fraud_detector_options['shiprocket_password'];
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
			if ($key == "only_order") {
				$create_shipment_data['order_id'] .= time();
				$create_shipment_result = shiprocket_create_shipment($token, $create_shipment_data);
				if (is_array($create_shipment_result) && array_key_exists("shipment_id", $create_shipment_result)) {
					return "Order Created Successfully.";
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
			} else if ($key == "full") {
				$create_shipment_result = shiprocket_create_shipment($token, $create_shipment_data);
				$cod = $order->get_payment_method() == "cod" ? 1 : 0;
				start:
				if($qty%2 == 0){
					$weight = (string)$qty * $base_weight;
				}else{
					$qty += 1;
					goto start;
				}
				$check_courier_serviceablility = shiprocket_check_courier_serviceability($token, $this->fraud_detector_options['pincode'], $order->get_shipping_postcode(), $cod, $weight);
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
				$low = $this->fraud_detector_options['lowest_shipping_charges'];
				$high = $this->fraud_detector_options['highest_shipping_charges'];
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
							if (is_array($generate_label_result) && $generate_label_result['label_created']) {
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
							} else {
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
			}
		} else {
			return $auth_token_result['message'];
		}
	}

	public function shipping_manager_generate_shipment_ajax($data)
	{
		if ($_POST['key'] == "full") {
			if ($this->check_if_shipment_generated_or_not($_POST['post_id'])['status']) {
				echo "Shipment Already Generated!";
			}else{
				$order = wc_get_order($_POST['post_id']);
				if (is_array($this->fraud_detector_options)) {
					switch ($this->fraud_detector_options['default_shipping_provider']) {
						case 'nimbus':
							echo $this->nimbus($order, $_POST['key']) . "|nimbus";
							break;
						case 'shiprocket':
							echo $this->shiprocket($order, $_POST['key']) . "|shiprocket";
							break;
					}
				} else {
					echo "Please give the crendentials first!";
				}
			}
		}else{
			$order = wc_get_order($_POST['post_id']);
			if (is_array($this->fraud_detector_options)) {
				switch ($this->fraud_detector_options['default_shipping_provider']) {
					case 'nimbus':
						echo $this->nimbus($order, $_POST['key']);
						break;
					case 'shiprocket':
						echo $this->shiprocket($order, $_POST['key']);
						break;
				}
			} else {
				echo "Please give the crendentials first!";
			}
		}
		exit();
	}

	public function after_thankyou_send_to_socket_shipping_manager($order_id)
	{
		if ($this->check_if_shipment_generated_or_not($order_id)['status']) {
			return "Shipment Already Generated!";
		}else{
			$order = new WC_Order($order_id);
			if (is_array($this->fraud_detector_options)) {
				switch ($this->fraud_detector_options['default_shipping_provider']) {
					case 'nimbus':
						return $this->nimbus($order);
						break;
					case 'shiprocket':
						return $this->shiprocket($order, 'full');
						break;
				}
			} else {
				return "Please give the crendentials first!";
			}
		}
	}

	function register_custom_status_order_status()
	{
		register_post_status('wc-shipment-error', array(
			'label'                     => 'Shipment Error',
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			'label_count'               => _n_noop('Shipment Error <span class="count">(%s)</span>', 'Shipment Error <span class="count">(%s)</span>'),
		));
		register_post_status('wc-indian-post', array(
			'label'                     => 'Indian Post',
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			'label_count'               => _n_noop('Indian Post <span class="count">(%s)</span>', 'Indian Post <span class="count">(%s)</span>'),
		));
		register_post_status('wc-service-not-available', array(
			'label'                     => 'Service Not Available',
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			'label_count'               => _n_noop('Service Not Available <span class="count">(%s)</span>', 'Service Not Available <span class="count">(%s)</span>'),
		));
		register_post_status('wc-processed', array(
			'label'                     => 'Processed',
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			'label_count'               => _n_noop('Processed <span class="count">(%s)</span>', 'Processed <span class="count">(%s)</span>'),
		));
		register_post_status('wc-file-not-found', array(
			'label'                     => 'File Not Found',
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			'label_count'               => _n_noop('File Not Found <span class="count">(%s)</span>', 'File Not Found <span class="count">(%s)</span>'),
		));
	}

	function add_custom_status_to_order_statuses($order_statuses)
	{
		$new_order_statuses = array();
		if (is_array($order_statuses)) {
			foreach ($order_statuses as $key => $status) {
				$new_order_statuses[$key] = $status;
				if ('wc-processing' === $key) {
					$new_order_statuses['wc-shipment-error'] = 'Shipment Error';
					$new_order_statuses['wc-indian-post'] = 'Indian Post';
					$new_order_statuses['wc-service-not-available'] = 'Service Not Available';
					$new_order_statuses['wc-processed'] = 'Processed';
					$new_order_statuses['wc-file-not-found'] = 'File Not Found';
				}
			}
		}
		return $new_order_statuses;
	}

	function debug($data)
	{
		echo "<pre>";
		print_r($data);
		die;
	}

	function shop_order_page_dropdown_options($columns)
	{
		$columns['shipment'] = 'Shipment';
		$columns['add_order'] = 'Add Order';
		return $columns;
	}

	function shop_order_page_dropdown_options_rows($column, $postid)
	{
		if ($column == 'shipment') {
			$nimbus_shipment = (!empty(get_post_meta($postid, 'nimbus_shipment'))) ? get_post_meta($postid, 'nimbus_shipment')[0] : '';
			$shiprocket_shipment = !empty(get_post_meta($postid, 'shiprocket_shipment')) ? get_post_meta($postid, 'shiprocket_shipment')[0] : '';
			$shipment_error = isset(get_post_meta($postid, 'shipment_error', array())[0]) && !empty(get_post_meta($postid, 'shipment_error', array())[0]) ? get_post_meta($postid, 'shipment_error', array())[0] : '';
			echo '<div>';
			if (is_array($nimbus_shipment) && !empty($nimbus_shipment)) {
				echo '<a class="track-shipment-btn btn btn-outline-danger" target="_blank" href="https://ship.nimbuspost.com/shipping/tracking/' . $nimbus_shipment['awb_number'] . '">Track Shipment</a>';
			} else if (is_array($shiprocket_shipment) && array_key_exists("awb_number", $shiprocket_shipment) && !empty($shiprocket_shipment)) {
				echo '<a class="track-shipment-btn btn btn-outline-danger" target="_blank" href="https://shiprocket.co/tracking/' . $shiprocket_shipment['awb_number'] . '">Track Shipment</a>';
			} else {
				echo '<div class="row">';
				if (!empty($shipment_error)) {
					echo '<div class="col-2">';
					echo '<span data-toggle="tooltip" data-placement="right" data-original-title="' . $shipment_error . '"><img width="20" src="' . plugin_dir_url(__DIR__) . 'assets/images/warning.png' . '"></span>';
					echo '</div>';
				}
				echo '<div class="col-3">';
				echo '<button class="generate-shipment-btn btn btn-outline-warning">Create Shipment</button>';
				echo '<input type="hidden" id="post_id" value="' . $postid . '">';
				echo '<input type="hidden" id="url" value="' . get_site_url() . $_SERVER['REQUEST_URI'] . '">
				<img style="width:100px;" class="d-none" id="loader" src="' . plugin_dir_url(__DIR__) . 'assets/images/loader.gif' . '">
				<input type="hidden" id="warning" value="' . plugin_dir_url(__DIR__) . 'assets/images/warning.png' . '">
				</div>';
				echo '</div>';
				echo '</div>';
			}
		}
		if ($column == 'add_order') {
			$order = wc_get_order($postid);
			$product = array();
			foreach ($order->get_items() as $item_id => $item) {
				$product[$item->get_product_id()] = array(
					'name' => $item->get_name(),
					'meta_value' => is_array(get_post_meta($item->get_product_id(), '_print_file_name')) && !empty(get_post_meta($item->get_product_id(), '_print_file_name')) ? get_post_meta($item->get_product_id(), '_print_file_name')[0] : '',
				);
			}
			echo '
			<div class="text-center">
			<button type="button" class="btn btn-primary my-2 add-order-shiprocket">Add Order</button>
			<input type="hidden" value="' . $postid . '">
			<input type="hidden" value="' . plugin_dir_url(__DIR__) . 'assets/images/warning.png' . '">';
			if ($order->get_status() == 'file-not-found') {
				echo '<button type="button" order-id="'.$postid.'" data-product="' . str_replace('"', "'", json_encode($product)) . '" class="btn btn-success my-2 book_name_add" data-toggle="modal" data-target="#add_file_name_book">Add Book Name</button>';
			}
			echo '<img style="width:100px;" class="d-none mereloader" order-id="'.$postid.'" src="'.str_replace('shipping-manager','vaswh',plugin_dir_url(__DIR__)). 'admin/assets/loader.gif'.'">';
			echo '</div>';
		}
	}

	function update_product_file_name_shop_order(){
		$order_id = $_POST['order_id'];
		// sleep(5);
		// print('hogaya');
		if (is_array($_POST['products'])) {
			foreach ($_POST['products'] as $key => $value) {
				update_post_meta($value['id'], "_print_file_name", array($value['english'], $value['hindi']));
				delete_post_meta($value['id'], "file_name_not_found");
			}
			$order = new WC_Order($order_id);
			if (!empty($order)) {
				$date1 = new DateTime(date("Y/m/d"));
				$date2 = new DateTime($order->get_date_created()->date("Y/m/d"));
				$difference = $date1->diff($date2);
				$days_of_input = $order->get_payment_method() == "cod" ? $this->fraud_detector_options['cod_range'] : $this->fraud_detector_options['prepaid_range'];
				if ($difference->d < $days_of_input) {
					$shipment = apply_filters('shipping_manager_generate_shipment_admin', $order->get_id());
					if(str_contains($shipment, 'Shipment Generated Successfully.') || $shipment == 'Shipment Already Generated!' ){
						// If shipment is successfully created then updating order status to completed 
						$order->update_status('completed', 'Shipment Generated Successfully.');
						// Sending the file to print.
						apply_filters('vashwh_order_printing_admin', $order->get_id());
						echo "File Name added Successfully.";
					}else{
						// IF their is any error in creating shipment then updating status to shipment error
						$order->update_status('shipment-error', $shipment);
						update_post_meta($order->get_id(), "shipment_error", $shipment);
						echo $shipment;
					}
				}else{
					echo 'Limit Reached According to Days of Input!';
				}
			}else{
				echo "Order ID Not Available!";
			}
		}else{
			echo 'Invalid Input!';
		}
		exit();
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

}
