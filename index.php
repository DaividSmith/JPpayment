<?php
/*
 * Plugin Name: درگاه جهان پی ووکامرس همراه با تایید سفارش
 * Plugin URI: http://http://blog.alafalaki.ir/%D9%BE%D9%84%D8%A7%DA%AF%DB%8C%D9%86-jppayment-%D8%A8%D8%B1%D8%A7%DB%8C-woocommerce-%D9%81%D8%B1%D9%88%D8%B4%DA%AF%D8%A7%D9%87-%D8%B3%D8%A7%D8%B2/
 * Description: درگاه کامل جهان پی برای سایت های فروش فایل
 * Version: 2.2
 * Author: Ala Alam Falaki
 * Author URI: http://AlaFalaki.ir
 * 
 */
session_start();
require_once("library/nusoap.php"); // Add NuSoap Library To Plugin

add_action('plugins_loaded', 'WC_JP', 0); // Make The Plugin Work...

function WC_JP() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return; // import your gate way class extends/

    class WC_full_JPpayment extends WC_Payment_Gateway {
        public function __construct(){
        	
            $this -> id 			 	 = 'JPpayment';
            $this -> method_title 	  	 = 'جهان پی';
            $this -> has_fields 	   	 = false;
            $this -> init_form_fields();
            $this -> init_settings();
			
			$this-> title				= $this-> settings['title'];
			$this-> description			= $this-> settings['description'];
			$this-> API_Key			 	= $this-> settings['API_Key'];
			$this -> redirect_page_id	= $this -> settings['redirect_page_id'];
 
			$this -> msg['message'] = "";
			$this -> msg['class'] = "";
 
			add_action('woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_JPpayment_response' ) );
			add_action('valid-JPpayment-request', array($this, 'successful_request'));

  		    if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) { // Compatibalization plugin for diffrent versions.
                add_action( 'woocommerce_update_options_payment_gateways_JPpayment', array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
			 
			add_action('woocommerce_receipt_JPpayment', array(&$this, 'receipt_page'));
        }

		/**
		 * Declaring admin page fields.
		 */
       function init_form_fields(){
            $this -> form_fields = array(
                'enabled' => array(
                    'title' => 'فعال سازی/غیر فعال سازی :',
                    'type' => 'checkbox',
                    'label' => 'فعال سازی درگاه پرداخت جهان پی',
                    'description' => 'برای امکان پرداخت کاربران از طریق این درگاه باید تیک فعال سازی زده شده باشد .',
                    'default' => 'no'),
                'API_Key' => array(
                    'title' => 'API درگاه :',
                    'type' => 'text',
                    'description' => 'شما میتوانید ازAPI درگاه خود در بخش لیست درگاه ها آگاهی پیدا کنید .'),
                'title' => array(
                    'title' => 'عنوان درگاه :',
                    'type'=> 'text',
                    'description' => 'این عتوان در سایت برای کاربر نمایش داده می شود .',
                    'default' => 'درگاه پرداخت جهان پی'),
                'description' => array(
                    'title' => 'توضیحات درگاه :',
                    'type' => 'textarea',
                    'description' => 'این توضیحات در سایت، بعد از انتخاب درگاه توسط کاربر نمایش داده می شود .',
                    'default' => 'پرداخت وجه از طریق درگاه جهان پی توسط تمام کارت های عضو شتاب .'),
				'redirect_page_id' => array(
                    'title' => 'آدرس بازگشت',
                    'type' => 'select',
                    'options' => $this -> get_pages('صفحه مورد نظر را انتخاب نمایید'),
                    'description' => "صفحه‌ای که در صورت پرداخت موفق نشان داده می‌شود را نشان دهید."),
            );
        }
        public function admin_options(){
            echo '<h3>درگاه جهان پی</h3>';
			echo '<table class="form-table">';
			$this -> generate_settings_html();
			echo '</table>';
			echo '
				<div>
					<a href="http://blog.alafalaki.ir/%D9%BE%D9%84%D8%A7%DA%AF%DB%8C%D9%86-jppayment-%D8%A8%D8%B1%D8%A7%DB%8C-woocommerce-%D9%81%D8%B1%D9%88%D8%B4%DA%AF%D8%A7%D9%87-%D8%B3%D8%A7%D8%B2/">صفحه رسمی پلاگین + مستندات .</a><br />
					<a href="https://github.com/AlaFalaki/JPpayment" target="_blank">حمایت از پروژه در GitHub .</a><br />
					<a href="https://twitter.com/AlaFalaki" target="_blank">من را در تویتر دنبال کنید .</a>
				</div>
			';
		}
        /**
         * Receipt page.
         **/
		function receipt_page($order_id){
            global $woocommerce;
			
            $order = &new WC_Order($order_id);
			
			// Get Name OF Products for JahanPay Describtion !
			$items_list = NULL;
			foreach ($order->get_items() as $item) {
				if(isset($items_list) ){
					$items_list .= "-" . $item['name'];
				}else{
					$items_list = $item['name'];
				}
			}
			
            $callback 				= ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
			$callback 				= add_query_arg( 'wc-api', get_class( $this ), $callback );

	        $_SESSION['order_id']	= $order_id;
			$API_code 				= $this->API_Key;
			$order_total			= round($order -> order_total);
			$text 					= urlencode($items_list);
			
			$client = new nusoap_client('http://www.jahanpay.com/webservice?wsdl', 'wsdl');
			$res = $client->call('requestpayment', array($API_code, $order_total, $callback, $order_id, $text));
			
				header("location: http://www.jahanpay.com/pay_invoice/{$res}");
				exit;
        }
        
        /**
         * Process_payment Function.
         **/
        function process_payment($order_id){
            $order = &new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true )); 
        }
	
 
	    /**
	     * Check for valid payu server callback
	     **/
	    function check_JPpayment_response(){
			global $woocommerce;
			$au 		= $_GET['au'];
			$order_id 	= $_GET['order_id'];

			if( isset($au) && isset($order_id)){
				$session_order_id	= $_SESSION['order_id'];
				$order 				= new WC_Order($order_id);
				
				if($session_order_id == $order_id){
					if($order -> status !=='completed'){
						$API_Key = $this->API_Key;
						$amount		= round($order -> order_total);

						$client = new nusoap_client('http://www.jahanpay.com/webservice?wsdl', 'wsdl');
						$result = $client->call("verification", array($API_Key,$amount,$au));
						
							if ($result == 1){
                                $this -> msg['class'] = 'woocommerce_message';
                                $this -> msg['message'] = "پرداخت شما با موفقیت انجام شد.";
								unset($_SESSION['order_id']);
								$order -> payment_complete();
			                    $order -> add_order_note('کد رهگیری جهان پی: '.$au);
			                    $woocommerce -> cart -> empty_cart();
							}else{
                                $this -> msg['class'] = 'woocommerce_error';
                                $this -> msg['message'] = "متاسفانه پرداخت شما ناموفق بود، لطفا دوباره تلاش نمایید.";
								unset($_SESSION['order_id']);
								$order -> add_order_note('پرداخت تایید نشد .');
							}
					
						$redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
						$redirect_url = add_query_arg( array('message'=> urlencode($this->msg['message']), 'class'=>$this->msg['class']), $redirect_url );
						wp_redirect( $redirect_url );
						exit;

					}
				}
			}else{ // Go To HomePage, The Entry Is Not Valid !
				header("location: " . get_site_url());
				exit;
			}
		}

	     // get all pages
	    function get_pages($title = false, $indent = true) {
	        $wp_pages = get_pages('sort_column=menu_order');
	        $page_list = array();
	        if ($title) $page_list[] = $title;
	        foreach ($wp_pages as $page) {
	            $prefix = '';
	            // show indented child pages?
	            if ($indent) {
	                $has_parent = $page->post_parent;
	                while($has_parent) {
	                    $prefix .=  ' - ';
	                    $next_page = get_page($has_parent);
	                    $has_parent = $next_page->post_parent;
	                }
	            }
	            // add to page list array array
	            $page_list[$page->ID] = $prefix . $page->post_title;
	        }
	        return $page_list;
	    }

	}
    /**
     * Add the Gateway to WooCommerce.
     **/
    function woocommerce_add_JPpayment_gateway($methods) {
        $methods[] = 'WC_full_JPpayment';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_JPpayment_gateway' );
}


if( isset($_GET['message']) )
{
	add_action('the_content', 'showMessage');

	function showMessage($content)
	{
		return '<div class="'.htmlentities($_GET['class']).'">'.urldecode($_GET['message']).'</div>'.$content;
	}
}
?>
