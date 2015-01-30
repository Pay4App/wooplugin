<?php

class WC_Pay4App extends WC_Payment_Gateway{

public function __construct(){
	$this->id = 'pay4app';
	$this->icon = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/assets/icon.png';
	$this->method_title = 'Pay4App';
	$this->has_fields = false;

	$this->init_form_fields();
	$this->init_settings();

	$this->title = $this->settings['title'];
	$this->description =  $this->settings['description'];
	$this->merchant_id = $this->settings['merchant_id'];
	$this->apiSecretKey = $this->settings['apiSecretKey'];
	//$this->redirect_page_id = $this->settings['redirect_page_id'];
	$this->live_url = 'https://pay4app.com/checkout.php';

	$this->msg['message'] = "";
	$this->msg['class'] = "";

	//add_action('init', array( &$this, 'sam' ) );

	if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=') ){
		add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( &$this, 'process_admin_options' ) );
		}
	else{
		add_action('woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
		}

	add_action('woocommerce_receipt_pay4app', array(&$this, 'receipt_page'));
	add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( &$this, 'handle_pay4app_callback' )  );

	

	if ( $this->pay4app_got_us_here() AND is_page()){
			$this->check_pay4app_response();
	}
	
	//add_action( 'woocommerce_api_redirect_pay4app', array( &$this, 'sam' )  );
	
	}


	function pay4app_got_us_here(){
		if ( isset($_REQUEST['order']) AND isset($_REQUEST['digest']) AND isset($_REQUEST['merchant']) ){
			return true;
		}
		return false;
	}
	
	function sam(){
		@ob_clean();
		echo "yeah";
		exit();
		//wp_die( "Pay4APPl IPN Request Failure" );
	}


	function init_form_fields(){
		$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'pay4app'),
					'type' => 'checkbox',
					'label' => __('Enable Pay4App Payment Module.', 'pay4app'),
					'default' => 'no'),
				'title' => array(
					'title' => __('Title:', 'pay4app'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees when checking out.', 'pay4app'),
					'default' => __('EcoCash, Telecash, ZimSwitch and VISA via Pay4App', 'pay4app')),
				'description' => array(
					'title' => __('Description:', 'pay4app'),
					'type' => 'textarea',
					'description' => __('This controls the description which the user sees when checking out.', 'pay4app'),
					'default' => __('Pay securely with EcoCash, Telecash, ZimSwitch and VISA through Pay4App.', 'pay4app')),
				'merchant_id' => array(
					'title' => __('Merchant ID', 'pay4app'),
					'type' => 'text',
					'description' => __('Available in your Merchant Account Settings.', 'pay4app')),
				'apiSecretKey' => array(
						'title' => __('Pay4App Secret Key', 'pay4app'),
						'type' => 'text',
						'description' => __('Available from your Pay4App Merchant Settings', 'pay4app')),
				
				);
				/*
				'redirect_page_id' => array(
						'title' => __('Return Page', 'pay4app'),
						'type' => 'select',
						'options' => $this->get_pages('Select Page'),
						'description' => __('URL of success page ok will change this', 'pay4app'))
				*/
			
	}// end init form fields

	public function admin_options(){
		echo "<h3>".__('Pay4App Payment Gateway', 'pay4app')."</h3>";
		echo "<p>".__('Pay4App is the most popular payment gateway for shopping online with mobile money in Zimbabwe', 'pay4app')."</p>";
		echo '<table class="form-table">';
		//Generate the HTML for the settings form
		$this->generate_settings_html();
		echo '</table>';
	}

	/*
	 *  There are no payment fields for Pay4App, but we want to show the description if set
	 */
	function payment_fields(){
		if ( $this->description ) echo wpautop(wptexturize($this->description));
	}

	/*
	 * Receipt Page
	 */
	function receipt_page($order){
		echo '<p>'.__('Thank you for your order, please click the button below to pay with Pay4App.', 'pay4app');
		echo $this->generate_pay4app_form($order);
	}

	/*
	 * Generate Pay4App button link
	 */
	public function generate_pay4app_form($order_id){
		
		global $woocommerce;

		$order = new WC_Order($order_id);
		$txnid = $order_id.'_'.date("ymds");

		//$redirect_url = ( $this->redirect_page_id =="" || $this->redirect_page_id==0) ? get_site_url()."/" : get_permalink($this->redirect_page_id);

		$product_info = "Order $order_id";
		
		$str = $this->merchant_id.$order_id.$order->order_total.$this->apiSecretKey;
		$hash = hash('sha256', $str);

		$redirect_url = add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) );

		$pay4app_args = array(
				'merchantid' => $this->merchant_id,
				'orderid' => $order_id,
				'signature' => $hash,
				'amount' => $order->order_total,
				'redirect' => $redirect_url,
				'transferpending' => $redirect_url
				);

		$pay4app_args_array = array();
		foreach( $pay4app_args as $key=>$value ){
			$pay4app_args_array[] = "<input type='hidden' name='$key' value='$value' />";
		}
		return '<form action="'.$this->live_url.'" method="post" id="pay4app_payment_form">'.implode('', $pay4app_args_array).'
				<input type="submit" class="button-alt" id="submit_pay4app_payment_form" value="'.__('Pay via Pay4App', 'pay4app').'" />
				<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'pay4app').'</a></form>';
		

		}		

		/*
		 * Process the payment and return the result
		 */	

		function process_payment($order_id)	{
			$order = new WC_Order($order_id);

			if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>') ){
				return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));			
				}
			else{
				return array('result' => 'success', 'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id')))));	
				}

			
		}    

		function handle_pay4app_callback(){
			@ob_clean();
			global $woocommerce;

			if ( isset( $_REQUEST['merchant'] ) && isset( $_REQUEST['checkout'] ) && isset($_REQUEST['order']) && isset($_REQUEST['amount']) && isset($_REQUEST['email']) && isset($_REQUEST['phone']) && isset($_REQUEST['timestamp']) && isset($_REQUEST['digest']) ){
				$order_id = $_GET['order'];
				if ($order_id != ''){
					try{
						$order = new WC_Order($order_id);
						$merchant_id = $_REQUEST['merchant'];
						$amount = $_REQUEST['amount'];
						$hash = $_REQUEST['digest'];						
						$status = 'success';
						
						$productinfo = "Order $order_id";
						
						//echo "{$this->apiSecretKey}|$status|{$order->billing_email}|{$order->order_total}|{$this->merchant_id}";
						$checkhash = hash('sha256',  $_REQUEST['merchant'].$_REQUEST['checkout'].$_REQUEST['order'].$_REQUEST['amount'].$_REQUEST['email'].$_REQUEST['phone'].$_REQUEST['timestamp'].$this->apiSecretKey );
						//var_dump($_REQUEST);
						//echo $checkhash;
						$transauthorised = false;
						if( $order->status !== 'completed' ){
							if($hash == $checkhash)
							{

								$status = strtolower($status);							


								if ($status=='success'){
									$transauthorised = true;
									if($order->status == 'processing'){
										echo json_encode(array("status"=>1));

									}
									else{

										// Validate Amount
									    if ( !$this->equal_floats($order->get_total(), $_REQUEST['amount']) ) {							    	
									    	// Put this order on-hold for manual checking
									    	$order->update_status( 'on-hold', sprintf( __( 'Validation error: Pay4App amounts do not match (gross %s).', 'woocommerce' ), $_REQUEST['amount'] ) );
									    	echo json_encode(array("status"=>1));


									    	//email admin
									    	$mailer = $woocommerce->mailer();

							            	$message = $mailer->wrap_message(
							            		__( 'Order payment amounts mismatch', 'woocommerce' ),
							            		sprintf( __( 'Order %s has had an amount mismatch. Please review and contact Pay4App (with ref %s) and the customer.', 'woocommerce' ), $order->get_order_number(), $_REQUEST['checkout'] )
											);

											$mailer->send( get_option( 'admin_email' ), sprintf( __( 'Payment amount mismatch for order %s', 'woocommerce' ), $order->get_order_number() ), $message );

									    	//end em ail admin
									    	echo json_encode(array("status"=>1));
									    	exit();
									    }

									    update_post_meta( $order->id, 'Pay4App payer email address', $_REQUEST['email'] );
						                update_post_meta( $order->id, 'Pay4App payer phone number', $_REQUEST['phone'] );


										$order->payment_complete();
										$order->add_order_note("Pay4App payment successful<br/>Unique Checkout ID from Pay4App: ".$_REQUEST['checkout']);
										$order->add_order_note($this->msg['message']);
										$woocommerce->cart->empty_cart();
										echo json_encode(array("status"=>1));
									}
								}
								
							}else{
								//ignore
							}

							
							//add_action('the_content', array(&$this, 'showMessage'));
						}}catch(Exception $e){
							echo json_encode(array("status"=>0));
							$msg = "Error";
						}
				}
			}
		exit();
		}

		function equal_floats($a, $b){
			$a = (float)$a;
			$b = (float)$b;
			if (abs(($a-$b)/$b) < 0.00001) {
				return TRUE;
			}
			return FALSE;

		}

		function check_pay4app_response(){
			
			global $woocommerce;
			//$this->msg['message'] = 'testing';
			//$this->msg['class'] = 'testing';
			
			if ( isset( $_REQUEST['digest'] ) && isset( $_REQUEST['merchant'] ) && isset( $_REQUEST['order'] ) ){
				$order_id = $_GET['order'];
				if ($order_id != ''){
					try{
						$order = new WC_Order($order_id);
						$merchant_id = $_REQUEST['merchant'];
						$amount = $_REQUEST['amount'];
						$hash = $_REQUEST['digest'];

						if ( isset($_REQUEST['checkout']) AND isset($_REQUEST['amount']) AND isset($_REQUEST['email']) AND isset($_REQUEST['phone']) AND isset($_REQUEST['timestamp']) ){
							$checkhash = hash('sha256',  $_REQUEST['merchant'].$_REQUEST['checkout'].$_REQUEST['order'].$_REQUEST['amount'].$_REQUEST['email'].$_REQUEST['phone'].$_REQUEST['timestamp'].$this->apiSecretKey );			
							$status = 'success';
						}
						else{
							$checkhash = hash('sha256',  $_REQUEST['merchant'].$_REQUEST['checkout'].$_REQUEST['order'].$this->apiSecretKey );
							$status = 'pending';	
						}
						$productinfo = "Order $order_id";
						
						//echo $hash;
						//echo "{$this->apiSecretKey}|$status|{$order->billing_email}|{$order->order_total}|{$this->merchant_id}";						
						//var_dump($_REQUEST);
						//echo $checkhash;

						$transauthorised = false;
						if( $order->status !== 'completed' ){
							if($hash == $checkhash)
							{

								$status = strtolower($status);

								if ($status=='success'){
									$transauthorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
									$this->msg['class'] = 'woocommerce_message';
									if($order->status == 'processing'){

									}
									else{
										$order->payment_complete();
										$order->add_order_note("Pay4App payment successful<br/>Unique ID from Pay4App: ".$_REQUEST['checkout']);
										$order->add_order_note($this->msg['message']);
										$woocommerce->cart->empty_cart();
									}
								}else if($status=='pending'){									
									$this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending and the notification should come through in a moment, we will keep you posted regarding the status of your order through e-mail";
									$this->msg['class'] = 'woocommerce_message woocommerce_message_info';
									$order->add_order_note('Pay4App payment confirmation notification is yet to come through');
									$order->add_order_note($this->msg['message']);
									$order->update_status('on-hold');
									$woocommerce->cart->empty_cart();
								}
								else{
									$this->msg['class'] = 'woocommerce_error';
									$this->msg['message'] = "Thank youf or shopping with us. However the transaction has been declined.";
									$order->add_order_note('Transaction Declined');
									//and whatever else to handle this scenario
								}
							}else{
								$this->msg['class'] = 'error';
								$this->msg['message'] = "Security Error. Illegal access detected";
								//ignore
							}

							//apply filters
							
							add_action('the_content', array(&$this, 'showMessage'));
						}}catch(Exception $e){
							

							$msg = "Error";
						}
				}
			}
		
		}					
		
		//add_action('the_content', array(&$this, 'showMessage'));
		function showMessage($content){
			//return '<div class="box">Sam</div>';//.$content;
			return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
		}

		//get all pages
		function get_pages($title = false, $indent = true){
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach($wp_pages as $page){
				$prefix = '';
				//show indented child pages?
				if ($indent) {
					$has_parent = $page->post_parent;
					while($has_parent){
						$prefix .= ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				//addd to page list array array
				$page_list[$page->ID] = $prefix.$page->post_title;
			}
			return $page_list;
		}
	}
	

function woocommerce_add_pay4app_gateway($methods){
	$methods[] = 'WC_Pay4App';
	return $methods;
	}

add_filter('woocommerce_payment_gateways', 'woocommerce_add_pay4app_gateway');
