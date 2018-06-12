<?php 
class WC_Epos_Payment extends WC_Payment_Gateway{
    
	public function __construct(){
		$this->id = 'epos_payment';
		$this->method_title = 'Epos';
		$this->title = 'Epos';
		$this->has_fields = false;
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled = $this->get_option('enabled');
		$this->title = $this->get_option('title');
        $this->public_key = $this->get_option('public_key');
		$this->private_key = $this->get_option('private_key');
        $this->payment_url = $this->get_option('payment_url');        
		$this->success_url = $this->get_option('success_url');      
		$this->error_url = $this->get_option('error_url');
		$this->payment_status = $this->get_option('payment_status');
		$this->usd_to_azn = $this->get_option('usd_to_azn');
		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
	}
    
	public function init_form_fields(){
        $this->form_fields = array(
            'enabled' => array(
                'title' 		=> __('Enable/Disable', 'woocommerce-epos-payment'),
                'type' 			=> 'checkbox',
                'label' 		=> __('Enable Epos Payment', 'woocommerce-epos-payment'),
                'default' 		=> 'no',
            ),
            'title' => array(
                'title' 		=> __('Payment Method Title', 'woocommerce-epos-payment'),
                'type' 			=> 'text',
                'description' 	=> __('It will be shown on checkout page', 'woocommerce-epos-payment'),
                'default'		=> 'Visa/MasterCard',
                'desc_tip'		=> true,
            ),
            'public_key' => array(
                'title' 		=> 'Public Key',
                'type' 			=> 'text',
                'description' 	=> __('Public Key value is given by Epos', 'woocommerce-epos-payment'),
                'placeholder'	=> __('Provided by Epos', 'woocommerce-epos-payment'),
                'desc_tip'		=> true,
            ),
            'private_key' => array(
                'title' 		=> 'Private key',
                'type' 			=> 'text',
                'description' 	=> __('Private key value is given by Epos', 'woocommerce-epos-payment'),
                'placeholder'	=> __('Provided by Epos', 'woocommerce-epos-payment'),
                'desc_tip'		=> true,
            ),
            'success_url' => array(
                'title' 		=> __('Success URL', 'woocommerce-epos-payment'),
                'type' 			=> 'text',
                'description' 	=> __('The success URL that was set on registration in Epos.', 'woocommerce-epos-payment'),
                'placeholder'	=> __('ex.: http(s)://your.domain/epos_success.php', 'woocommerce-epos-payment'),
                'desc_tip'		=> true,
            ),
            'error_url' => array(
                'title' 		=> __('Error URL', 'woocommerce-epos-payment'),
                'type' 			=> 'text',
                'description' 	=> __('The errpr URL that was set on registration in Epos.', 'woocommerce-epos-payment'),
                'placeholder'	=> __('ex.: http(s)://your.domain/epos_error.php', 'woocommerce-epos-payment'),
                'desc_tip'		=> true,
            ),
            'payment_url' => array(
                'title' 		=> __('Payment URL', 'woocommerce-epos-payment'),
                'type' 			=> 'text',
                'description' 	=> __('URL to Epos payment page (provided by Epos)', 'woocommerce-epos-payment'),
                'placeholder'	=> __('ex.: https://epos.az/api/pay2me/pay/', 'woocommerce-epos-payment'),
                'desc_tip'		=> true,
            ),
            'payment_status' => array(
                'title' 		=> __('Payment Status', 'woocommerce-epos-payment'),
                'type' 			=> 'text',
                'description' 	=> __('Payment Status checking URL (provided by Epos)', 'woocommerce-epos-payment'),
                'placeholder'	=> __('ex.: https://epos.az/api/pay2me/status/', 'woocommerce-epos-payment'),
                'desc_tip'		=> true,
            ),
            'usd_to_azn' => array(
                'title' 		=> __('USD to AZN', 'woocommerce-epos-payment'),
                'type' 			=> 'text',
                'description' 	=> __('USD exchange rate for AZN', 'woocommerce-epos-payment'),
                'placeholder'	=> __('ex.: 1.7015', 'woocommerce-epos-payment'),
                'desc_tip'		=> true,
            )
        );
	}
    
	public function process_payment($order_id){
		global $woocommerce, $wpdb;
		$order = new WC_Order($order_id);
        $epos_payment = $wpdb->prefix . "woocommerce_epos";

        $public_key = $this->public_key;
        $private_key = $this->private_key;
        $time = time();
        $params['amount'] = round(($order->get_total()*$this->usd_to_azn), 2);
        $params['phone'] = "994111111111";
        $params['cardType' ] = 0;
        $params['payFormType' ] = 'DESKTOP';
        $params['successUrl' ] = $this->success_url;
        $params['errorUrl' ] = $this->error_url;
        $params['key'] = $public_key;

        ksort($params);
        $sum = '';
        foreach ($params as $k => $v) {
            $sum .= (string)$v;
        }
        $sum .= $private_key ;
        $control_sum = md5($sum);

        $paymentURL = $this->payment_url."?key=".$this->public_key."&sum=".$control_sum."&amount=".$params['amount']."&phone=".$params['phone']."&cardType=".$params['cardType']."&successUrl=".$params['successUrl']."&errorUrl=".$params['errorUrl']."&payFormType=".$params['payFormType'];
        
        $data = file_get_contents($paymentURL);
        $result = json_decode($data);
        if($result->result == 'success')
        {
            parse_str( parse_url( $result->paymentUrl, PHP_URL_QUERY ), $my_array_of_vars );
            $mdOrder = $my_array_of_vars['mdOrder']; 
            //Order marks as pending
            $order->update_status('pending', __('<b>Pending payment!</b>', 'woocommerce-epos-payment'));
            //Adding order reference into db
            $reference_db_add = $wpdb->insert($epos_payment, array('order_id' => $order->id, 'mdOrder' => $mdOrder, 'payment_id' => $result->id, 'payment_url' => $result->paymentUrl, 'control_sum' => $control_sum, 'description' => '', 'date' => date('Y-m-d H:i:s')));
            // Remove cart
            $woocommerce->cart->empty_cart();
            // Redirect to Epos payment page
            return [
                'result'     => 'success',
                'redirect'   => $result->paymentUrl
            ];
        }
        else
        {   return [
                'result'    => 'error'
            ];

        }
        
	}
}
