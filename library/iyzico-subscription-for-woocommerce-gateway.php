<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Iyzico_Subscription_For_WooCommerce_Gateway extends WC_Payment_Gateway {

    public function __construct() {

        $this->id = 'iyzico_subscription';
        $this->iyziV = '1.0.0';
        $this->method_title = __('iyzico', 'woocommerce-iyzico-subscription');
        $this->method_description = __('Subscription Pay');
        $this->has_fields = true;
        $this->order_button_text = __('Pay With Card', 'woocommerce-iyzico-subscription');
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->enabled      = $this->get_option( 'enabled' );
        $this->icon         = plugins_url().IYZICO_PLUGIN_NAME.'/image/cards.png?v=1';


        add_action('init', array(&$this, 'iyzico_response'));
        add_action('woocommerce_api_wc_gateway_iyzico', array($this, 'iyzico_response'));


        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options',
        ) );
        
        add_action('woocommerce_receipt_iyzico_subscription', array($this, 'iyzico_subscription_form'));

    }




    public function init_form_fields() {
        
        $this->form_fields = Iyzico_Subscription_For_WooCommerce_Fields::iyzicoAdminFields();

    }

    public function process_payment($order_id) {
        
        $order = wc_get_order($order_id);

        return array(
          'result'   => 'success',
          'redirect' => $order->get_checkout_payment_url(true)
        );

    }

    public function iyzico_subscription_form($order_id) {

        global $woocommerce;

        $getOrder                  = new WC_Order($order_id);
        $customerCart              = $woocommerce->cart->get_cart();

        $apiKey                    = $this->settings['api_key'];
        $secretKey                 = $this->settings['secret_key'];
        $baseUrl                   = $this->settings['api_type'];
        $rand                      = uniqid();


        $formObjectGenerate        = new Iyzico_Subscription_For_WooCommerce_FormObjectGenerate();
        $iyzicoRequest             = new Iyzico_Subscription_For_WooCommerce_Request();
        $hashV2Builder             = new Iyzico_Subscription_For_WooCommerce_Authorization();

        /* V2 */
        $v2Request          = new stdClass();
        $v2Request->locale  = 'tr';

        $subsUrl =  $baseUrl."/v2/subscription/checkoutform/initialize?";

        $iyzico                            = $formObjectGenerate->subscripotionObjectGenerate($getOrder,$customerCart);
        $iyzico->customer                  = $formObjectGenerate->subscriptionCustomerObjectGenerate($getOrder);
        $iyzico->customer->billingAddress  = $formObjectGenerate->subscriptionBillingAddressGenerate($getOrder);
        $iyzicoJson                        = json_encode($iyzico,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $authorizationDataV2               = $hashV2Builder->generateAuthV2Content($subsUrl,$apiKey,$secretKey,$rand,$iyzicoJson);

        $requestResponse                   = $iyzicoRequest->iyzicoSubscriptionRequest($subsUrl,$iyzicoJson,$authorizationDataV2);

        if(isset($requestResponse->status)) {
            if($requestResponse->status == 'success') {
                echo ' <div style="display:none" id="iyzipay-checkout-form" class="popup">' . $requestResponse->checkoutFormContent . '</div>';
                echo '<p style="display:none;" id="iyziVersion">'.$this->iyziV.'</p>';
            } else {
                echo $requestResponse->errorMessage;
            }

        } else {
            echo 'Not Connection...';
        }

    }

    public function iyzico_response($order_id) {

        global $woocommerce;

        $token           = $_POST['token'];

        $conversationId  = "";
        $apiKey          = $this->settings['api_key'];
        $secretKey       = $this->settings['secret_key'];
        $baseUrl         = $this->settings['api_type'];
        $user            = wp_get_current_user();
        $rand            = rand(1,99999);

        if(!$token) {
            $entityBody = file_get_contents("php://input");
            $subscriptionNotification = $this->notificationListener($entityBody,$order_id);
        } 
        
        $formObjectGenerate        = new Iyzico_Subscription_For_WooCommerce_FormObjectGenerate();
        $pkiBuilder                = new Iyzico_Subscription_For_WooCommerce_PkiBuilder();
        $iyzicoRequest             = new Iyzico_Subscription_For_WooCommerce_Request();
        $hashV2Builder             = new Iyzico_Subscription_For_WooCommerce_Authorization();


        $tokenDetailObject         = $formObjectGenerate->generateTokenDetailObject($conversationId,$token);
        $pkiString                 = $pkiBuilder->pkiStringGenerate($tokenDetailObject);
        $authorizationData         = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$rand);
        $tokenDetailObject         = json_encode($tokenDetailObject,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $requestResponse           = $iyzicoRequest->iyzicoCheckoutFormDetailRequest($baseUrl,$tokenDetailObject,$authorizationData);


        if($requestResponse->paymentStatus != 'SUCCESS' || $requestResponse->status != 'success') {

            /* Redirect Error */
            $errorMessage = isset($requestResponse->errorMessage) ? $requestResponse->errorMessage : 'Failed';
            throw new \Exception($errorMessage);
        }

        $paymentId = (int) $requestResponse->paymentId;
        $referenceCode = esc_sql($requestResponseSubs->data->referenceCode);
        $orderAwaitingPaymentId = (int) $woocommerce->session->order_awaiting_payment;
        $userId = (int) $user->ID;

        $order = new WC_Order($woocommerce->session->order_awaiting_payment);
        
        if($baseUrl == 'https://sandbox-api.iyzipay.com') {

            $orderMessage = '<strong><p style="color:red">TEST SUBSCRIPTION STARTED</a></strong>';
            $order->add_order_note($orderMessage,0,true);
        } else {

            $orderMessage = '<strong><p style="color:red">SUBSCRIPTION STARTED</a></strong>';
            $order->add_order_note($orderMessage,0,true);

        }

        $orderMessage = 'Payment ID: '.$paymentId;
        $order->add_order_note($orderMessage,0,true);
        
        $subsRetrieveUrl           = $baseUrl."/v2/subscription/checkoutform/".$token."?=locale=tr";
        $authorizationDataV2       = $hashV2Builder->generateAuthV2Content($subsRetrieveUrl,$apiKey,$secretKey,$rand,null);
        $requestResponseSubs       = $iyzicoRequest->iyzicoSubscriptionRetrieveRequest($subsRetrieveUrl,$tokenDetailObject,$authorizationDataV2);

        $subscriptionIyziModel       = new Iyzico_Subscription_For_WooCommerce_Model();

        $addSubsObject = new stdClass();
        $addSubsObject->referenceCode = $referenceCode;
        $addSubsObject->orderId = $orderAwaitingPaymentId;
        $addSubsObject->userId = $userId;

        $addSubscription  = $subscriptionIyziModel->addSubscription($addSubsObject);

        $order->payment_complete();   
        
        $order->update_status('processing');

        $woocommerce->cart->empty_cart();
        
        $checkoutOrderUrl = $order->get_checkout_order_received_url();

        $redirectUrl = add_query_arg(array('msg' => 'Thank You', 'type' => 'woocommerce-message'), $checkoutOrderUrl);
        
        wp_redirect($redirectUrl);

    }

    private function notificationListener($entityBody,$order_id) {
   
        $entityBody = json_decode($entityBody);
        $subscriptionReferenceCode = esc_sql($entityBody->subscriptionReferenceCode);
 
        $subscriptionIyziModel  = new Iyzico_Subscription_For_WooCommerce_Model();

        $findSubscription = $subscriptionIyziModel->findSubscription($subscriptionReferenceCode);

        if($findSubscription->subscription_reference_code == $subscriptionReferenceCode) {

                //$order = new WC_Order($findSubscription->order_id);

                echo 'SUCCESS';
                exit;
        }

            echo 'FAILURE';
            exit;
    }

    public static function pricingPlanMethod() {

        global $post;
      
        $postId = (int) $post->ID;
        $pricingPlanCode        = 'pricing_plan_code_'.$postId;
        $pricingPlanCodeOption  = get_option($pricingPlanCode);

        echo '<h1 style="margin-left:10px;">iyzico Subscription</h1>';
        ?>
            <div class="options_group pricing show_if_simple show_if_external hidden" style="display: block;">
                <p class="form-field _regular_price_field ">
                    <label for="_regular_price">Pricing Plan Code: </label>
                    <input type="text" class="short wc_input_text" name="pricingPlanReferenceCode" value="<?php echo $pricingPlanCodeOption; ?>" /> 
                </p>
            </div>
        <?php
    }

    public static function pricingPlanMethodSave() {

        global $post;
 
        $postId = (int) $post->ID;
        
        if(!empty($_POST['pricingPlanReferenceCode'])) {
            $pricingPlanReferenceCode = $_POST['pricingPlanReferenceCode'];
        } else {
            $pricingPlanReferenceCode = false;
        }

        $createOrUpdateControl = false;


        $pricingPlanReferenceCode = esc_sql($pricingPlanReferenceCode);

        $pricingPlanReferenceCodeField = 'pricing_plan_code_'.$postId;
        $pricingPlanReferenceCodeOption = get_option($pricingPlanReferenceCodeField);

        /* Empty Post  Control */
        if(empty($pricingPlanReferenceCode)) {

            delete_option($pricingPlanReferenceCodeField);
            return;
        }

        /* Sleeping Data Control */
        if($pricingPlanReferenceCode == $pricingPlanReferenceCodeOption) {

            return;
        }

        if(!empty($pricingPlanReferenceCodeOption)) {

            $createOrUpdateControl = true;
        }

        if(empty($createOrUpdateControl)) {

            add_option($pricingPlanReferenceCodeField,$pricingPlanReferenceCode,'','no'); 

        } else {

            update_option($pricingPlanReferenceCodeField,$pricingPlanReferenceCode);   
        } 

        return;

    }

}
