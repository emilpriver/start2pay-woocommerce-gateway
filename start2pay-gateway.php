<?php
/*
 * Plugin Name: WooCommerce Start 2 pay
 * Description: Take payment from start2pay
 * Author: Emil Privér
 * Author URI: https://emilpriver.com
 * Version: 1.0.0
 *
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'start2pay_gateway_class' );
function start2pay_gateway_class( $gateways ) {
	$gateways[] = 'Start2pay_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'start2pay_init_gateway_class' );
function start2pay_init_gateway_class() {
 
	class Start2pay_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
 
            $this->id = 'start2pay'; 
            $this->icon = ''; 
            $this->has_fields = true; 
            $this->method_title = 'Start2Pay';
            $this->method_description = 'Make payments with Start2pay';
         
            $this->supports = array(
                'products'
            );

            $this->init_form_fields();
         
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->payment_box_text = $this->get_option( 'payment_box_text' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->var_dump = 'yes' === $this->get_option( 'var_dump' );
            $this->username = $this->get_option('username');
            $this->password = $this->get_option('password');
            $this->callback_sign = $this->get_option('cb_salt');
            $this->key = $this->get_option('key');
            
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            
            add_action( 'woocommerce_api_'.strtolower(get_class($this)).'_progress', array( &$this, 'check_progress' ) );
            add_action( 'woocommerce_api_'.strtolower(get_class($this)) . '_status', array( &$this, 'webhook' ) );
 		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
 
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Start2pay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Credit Card',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'payment_box_text' => array(
                    'title'       => 'Payment box text',
                    'type'        => 'textarea',
                    'description' => 'This text tells the user what will happen during checkout',
                    'default'     => 'You will be redirected to an external page to finalize your payment',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'var_dump' => array(
                    'title'       => 'Var_dump request',
                    'label'       => 'Var_dump request',
                    'type'        => 'checkbox',
                    'description' => 'Var_dump request',
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),
                'username' => array(
                    'title'       => 'Username',
                    'type'        => 'password'
                ),
                'password' => array(
                    'title'       => 'Password',
                    'type'        => 'password',
                ),
                'key' => array(
                    'title'       => 'Key',
                    'type'        => 'password'
                ),
                'cb_salt' => array(
                    'title'       => 'Callback sign',
                    'type'        => 'password'
                )
            );
		
	 	}
 
		/**
		 * Custom fields in checkout
		 */
		public function payment_fields() {
            
		    /**
             * Display description to the user on payment
             */
            if ( $this->description ) {
                if ( $this->testmode ) {
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            /**
             * Echo the form
             */
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
            /**
             * Run action for the payment provider
             */
            do_action( 'woocommerce_credit_card_form_start', $this->id );
        
            /**
            * Echo fields
            */
            echo '<div class="form-row form-row-wide"><span> '. $this->payment_box_text .' </span></div>
                <div class="clear"></div>';
            
            /**
             * Run closing hook
             */
            do_action( 'woocommerce_credit_card_form_end', $this->id );
            
            /**
             * Close form wrapper
             */
            echo '<div class="clear"></div></fieldset>';
 
		}
        
         public function process_payment($order_id){
            global $woocommerce;
            /**
             * Get the order so we are able to update data or fetch data about the order
             */
            $order = wc_get_order( $order_id );

            /**
             * Functions
             */
            function ksortTree(&$array) {
                if (!is_array($array)) {
                    return false;
                }
                ksort($array);
                foreach ($array as $k => $v) {
                    ksortTree($array[$k]);
                }
                return true;
            }

            function get_headers_from_curl_response($response) {
                $headers = [];
                foreach (explode("\r\n", $response) as $i => $line) {
                    if ($i === 0) {
                        $headers['http_code'] = $line;
                    } else {
                        if (strpos($line, ': ')) {
                            list ($key, $value) = explode(': ', $line);
                            $headers[strtolower($key)] = $value;
                        } else {
                          $headers[strtolower($line)] = null;
                        }
                    }
                }
                return $headers;
            }

            /**
             * Variabels
             */
            $username = $this->username;
            $password = $this->password;
            $key = $this->key; 
            $host = $this->testmode ? 'https://sandapi.start2pay.com' : 'https://api.start2pay.com';
            $uri = '/pay_context/create';
            $method = 'POST';
            
            /**
             * Send first request to get code
             */
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $host.$uri);
            curl_setopt($curl, CURLOPT_PORT, 443);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HEADER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            $content = curl_exec($curl);
            curl_close($curl);
            if($this->var_dump){
                var_dump($content);
                exit;
            }

            /**
             * Set headers
             */
            $headers = get_headers_from_curl_response($content);
            $authRespHeader = explode(',', preg_replace("/^Digest/i", "", $headers['www-authenticate']));
            $authPieces = [];
            foreach ($authRespHeader as &$piece) {
                $piece = trim($piece);
                $piece = explode('=', $piece);
                $authPieces[$piece[0]] = trim($piece[1], '"');
            }

            /**
             * Create auth keys
             */
            $nc = str_pad('1', 8, '0', STR_PAD_LEFT);
            $cnonce = '0a4f113b';
            $A1 = md5("{$username}:{$authPieces['realm']}:{$password}");
            $A2 = md5("{$method}:{$uri}");
            $authPieces['response'] = md5("{$A1}:{$authPieces['nonce']}:{$nc}:{$cnonce}:{$authPieces['qop']}:${A2}");
            $digestHeader = "Authorization: Digest username=\"{$username}\", realm=\"{$authPieces['realm']}\", nonce=\"{$authPieces['nonce']}\", uri=\"{$uri}\", cnonce=\"{$cnonce}\", nc={$nc}, qop=\"{$authPieces['qop']}\", response=\"{$authPieces['response']}\"";
            $payContextData = [
                'currency' => 'USD',
                'amount' => $order->get_total(),
                'selected_payment_system' => 'bank_cards',
                'custom' => array(
                    'order' => (string)$order_id,
                ),
                'settings' => array(
                    'progress_url' =>   home_url( '/wc-api/start2pay_gateway_status' )  ,
                    'success_url' =>  home_url( '/wc-api/start2pay_gateway_status' ) ,
                    'fail_url' =>  home_url( '/wc-api/start2pay_gateway_status' ) ,
                )
            ];
            ksortTree($payContextData);
            $payContextData['signature'] = hash('sha256', json_encode($payContextData).$key);

            /**
             * Send last request to get payment url
             */
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $host.$uri);
            curl_setopt($curl, CURLOPT_PORT, 443);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [$digestHeader]);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payContextData));
            $content = curl_exec($curl);
            curl_close($curl);
            $json_data = json_decode($content);

             /**
             * Add content key to order
             */
            if($this->var_dump){
                var_dump($content);
                exit;
            }

            $order = wc_get_order( $order_id );
            $order->update_meta_data( 'context', $json_data->context );
            $order->save();
            
            /**
             * Send user to payment site if success, else send error
             */
            if($json_data->status == 'success'):
                return array(
                    'result'   => 'success',
                    'redirect' => $json_data->payment_url
                );
            else: 
                return array(
                    'result'   => 'failure',
                    'message' => 'Error'
                );
            endif; 
            
         }

        public function check_progress() {
             /**
             * If request is a verification request
             */
            $post = file_get_contents('php://input');
            $data = json_decode($post, true);
            echo json_encode(array(
                'status' => 'approve',
                'invoice' => !empty((String)$data['uuid']) ? (String)$data['uuid'] : $_GET['order']
            ));

            if($_POST):
                $order_id = sanitize_text_field($_POST['custom']['order']);
                $order = new WC_Order($order_id);
                if($_POST['status'] == 'new'):
                    $order->update_status('pending', 'Order recived');
                    $order->add_order_note( 'Order recived' );
                elseif($_POST['status'] == 'success'):
                    $order->update_status('Completed', 'Order Finished');
                    $order->add_order_note( 'Order paid', true );
                    $order->payment_complete();
                    $order->reduce_order_stock();
                    $order->add_order_note( 'Order paid', true );
                elseif($_POST['status'] == 'fail'):
                    $order->add_order_note( 'Order failed', true );
                    $order->update_status('failed', 'Payment failed');
                elseif($_POST['status'] == 'process'):
                    $order->add_order_note( 'Order processing');
                    $order->update_status('processing', 'Order proccessing');
                elseif($_POST['status'] == 'pending'):
                    $order->add_order_note( 'Order pending' );
                    $order->update_status('pending', 'Order pending');
                elseif($_POST['status'] == 'manual'):
                    $order->add_order_note( 'This order need manual check', true );
                    $order->update_status('On-Hold', 'This order need manual check');
                elseif($_POST['status'] == 'handle'):
                    $order->add_order_note( 'This order must be confermed', true );
                    $order->update_status('pending', 'This payment must be confermed');
                else:
                    $order->add_order_note( 'Status from start2pay: ' . $_POST['status'], true );
                    $order->update_status('Processing ',  'Status from start2pay: ' . $_POST['status']);
                endif;
            endif;

            exit;
        }

        public function webhook($order_id) {

            global $woocommerce, $order;
            if($_GET['order']):

                $get_order = sanitize_text_field($_GET['order']);
                $endpoint = $this->testmode ? 'https://sandapi.start2pay.com' : 'https://api.start2pay.com';
                $url = $endpoint . '/order/'.$get_order.'/status';
                $user = $this->username;
                $password = $this->password;
                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_USERPWD, $user . ':' . $password);
                curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                curl_setopt($curl, CURLOPT_TIMEOUT, 10);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                $result = curl_exec($curl);
                $data = json_decode($result);
                curl_close($curl);
                if($this->var_dump){
                    var_dump($result);
                    exit;
                }
                /** 
                 * Proccess order and finish of order
                 */
                $order_id = $data->custom->order;
                if(!empty($order_id)):
                    $order = wc_get_order($order_id);
                    if($data->status === 'new'):
                        $order->update_status('Pending', 'Order recived');
                        $order->add_order_note( 'Order recived' );
                    elseif($data->status === 'success'):
                        $order->update_status('Completed', 'Order Finished');
                        $order->add_order_note( 'Order paid', true );
                        $order->payment_complete();
                        wc_reduce_stock_levels($order_id);
                        $order->add_order_note( 'Order paid', true );
                        $woocommerce->cart->empty_cart();
                    elseif($data->status ===  'fail'):
                        $order->add_order_note( 'Order failed', true );
                        $order->update_status('failed', 'Payment failed');
                    elseif($data->status === 'process'):
                        $order->add_order_note( 'Order processing');
                        $order->update_status('processing', 'Order proccessing');
                    elseif($data->status === 'pending'):
                        $order->add_order_note( 'Order pending' );
                        $order->update_status('pending', 'Order pending');
                    elseif($data->status ===  'manual'):
                        $order->add_order_note( 'This order need manual check', true );
                        $order->update_status('On-Hold', 'This order need manual check');
                    elseif($data->status ===  'handle'):
                        $order->add_order_note( 'This order must be confermed', true );
                        $order->update_status('pending', 'This payment must be confermed');
                    else:
                        $order->add_order_note( 'Status from start2pay: ' . $_POST['status'], true );
                        $order->update_status('Processing ',  'Status from start2pay: ' . $_POST['status']);
                    endif;
                     wp_redirect($this->get_return_url( $order ),302); 
                else:
                    echo '<h1> Payment could not be confermed, please contact support</h1>';
                endif;

            endif;

            exit;

         }
    }
}
