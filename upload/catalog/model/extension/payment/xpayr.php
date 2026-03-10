<?php
class ModelExtensionPaymentXpayr extends Model {
    public function getMethod($address, $total) {
        if (!$this->config->get('payment_xpayr_status')) {
            return array();
        }

        $method_data = array(
            'code'       => 'xpayr',
            'title'      => $this->config->get('payment_xpayr_title') ? $this->config->get('payment_xpayr_title') : 'Pay with Crypto (XPayr)',
            'terms'      => '',
            'sort_order' => $this->config->get('payment_xpayr_sort_order')
        );

        return $method_data;
    }
}
