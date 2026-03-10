<?php
class ControllerExtensionPaymentXpayr extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/xpayr');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['pay_action'] = $this->url->link('extension/payment/xpayr/confirm', '', true);

        return $this->load->view('extension/payment/xpayr', $data);
    }

    public function confirm()
    {
        if (!isset($this->session->data['order_id'])) {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $this->load->model('checkout/order');
        $order_id = (int) $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $api_base = rtrim((string) $this->config->get('payment_xpayr_api_base_url'), '/');
        $secret = trim((string) $this->config->get('payment_xpayr_secret_key'));
        $network = strtolower(trim((string) $this->config->get('payment_xpayr_network')));
        $currency = strtoupper(trim((string) $this->config->get('payment_xpayr_currency')));

        if (!$api_base || !$secret || !$network || !$currency) {
            $this->log->write('[XPAYR] Missing api/secret/network/currency config');
            $this->session->data['error'] = 'XPayr configuration is incomplete.';
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $amount = (float) $order_info['total'];

        $success_url = $this->url->link('extension/payment/xpayr/success', 'order_id=' . $order_id, true);
        $cancel_url = $this->url->link('extension/payment/xpayr/cancel', 'order_id=' . $order_id, true);

        $payload = array(
            'amount' => number_format($amount, 8, '.', ''),
            'currency' => $currency,
            'network' => $network,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'ipn_callback_url' => $this->url->link('extension/payment/xpayr/callback', '', true),
            'metadata' => array(
                'opencart_order_id' => (string) $order_id,
                'opencart_customer_id' => (string) $order_info['customer_id'],
                'module' => 'opencart-xpayr'
            )
        );

        $response = $this->httpPostJson($api_base . '/payments', $payload, $secret);

        if (!$response['ok']) {
            $this->log->write('[XPAYR] Create payment failed HTTP=' . $response['http'] . ' ERR=' . $response['err'] . ' BODY=' . $response['body']);
            $this->session->data['error'] = 'Could not start XPayr checkout.';
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $json = $response['json'];
        $payment_url = isset($json['payment_url']) ? (string) $json['payment_url'] : '';
        $session_id = isset($json['session_id']) ? (string) $json['session_id'] : '';
        $invoice_id = isset($json['invoice_id']) ? (string) $json['invoice_id'] : '';

        if (!$payment_url) {
            $this->log->write('[XPAYR] payment_url missing: ' . $response['body']);
            $this->session->data['error'] = 'Could not get payment URL.';
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $pending_status = (int) $this->config->get('payment_xpayr_pending_status_id');
        if ($pending_status <= 0) {
            $pending_status = (int) $this->config->get('config_order_status_id');
        }

        $comment = 'XPayr session created';
        if ($session_id) {
            $comment .= ' | session_id=' . $session_id;
        }
        if ($invoice_id) {
            $comment .= ' | invoice_id=' . $invoice_id;
        }

        $this->model_checkout_order->addOrderHistory($order_id, $pending_status, $comment, false);

        $this->response->redirect($payment_url);
    }

    public function callback()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);

        if (!is_array($data)) {
            $this->jsonResponse(400, array('ok' => false, 'error' => 'invalid_json'));
            return;
        }

        $secret = trim((string) $this->config->get('payment_xpayr_webhook_secret'));
        if ($secret) {
            $header_sig = isset($this->request->server['HTTP_X_XPAYR_SIGNATURE']) ? (string) $this->request->server['HTTP_X_XPAYR_SIGNATURE'] : '';
            if (!$header_sig) {
                $this->jsonResponse(401, array('ok' => false, 'error' => 'missing_signature'));
                return;
            }

            $expected = hash_hmac('sha256', (string) $raw, $secret);
            if (!hash_equals($expected, $header_sig)) {
                $this->log->write('[XPAYR] Invalid webhook signature');
                $this->jsonResponse(401, array('ok' => false, 'error' => 'invalid_signature'));
                return;
            }
        }

        $event = isset($data['event']) ? strtolower((string) $data['event']) : '';
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : array();

        $order_id = 0;
        if (isset($metadata['opencart_order_id'])) {
            $order_id = (int) $metadata['opencart_order_id'];
        } elseif (isset($data['order_id'])) {
            $order_id = (int) $data['order_id'];
        }

        if ($order_id <= 0) {
            $this->jsonResponse(400, array('ok' => false, 'error' => 'missing_order_id'));
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            $this->jsonResponse(404, array('ok' => false, 'error' => 'order_not_found'));
            return;
        }

        $status_id = $this->mapEventToStatus($event);
        $comment = 'XPayr webhook: ' . ($event ? $event : 'unknown');
        if (isset($data['tx_hash'])) {
            $comment .= ' | tx=' . (string) $data['tx_hash'];
        }

        if ($status_id > 0) {
            $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true);
        }

        $this->jsonResponse(200, array('ok' => true));
    }

    public function success()
    {
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    public function cancel()
    {
        $this->response->redirect($this->url->link('checkout/checkout', '', true));
    }

    private function mapEventToStatus($event)
    {
        if ($event === 'payment.completed') {
            return (int) $this->config->get('payment_xpayr_paid_status_id');
        }

        if ($event === 'payment.failed') {
            return (int) $this->config->get('payment_xpayr_failed_status_id');
        }

        if ($event === 'payment.expired') {
            return (int) $this->config->get('payment_xpayr_expired_status_id');
        }

        return 0;
    }

    /**
     * Send an HTTP POST request with JSON payload using stream context.
     *
     * @param string $url     Full API endpoint URL
     * @param array  $payload Request body as associative array
     * @param string $secret  Bearer token for authorization
     * @return array Result with ok, http, err, body, and json keys
     */
    private function httpPostJson($url, array $payload, $secret)
    {
        $encoded = json_encode($payload);

        $headers = "Authorization: Bearer " . $secret . "\r\n" .
            "Content-Type: application/json\r\n" .
            "Accept: application/json\r\n";

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => $headers,
                'content' => $encoded,
                'timeout' => 30,
                'ignore_errors' => true,
            ),
        );

        $context = stream_context_create($options);
        $resp_body = @file_get_contents($url, false, $context);

        $http = 0;
        if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $http = isset($matches[1]) ? (int) $matches[1] : 0;
        }

        $err = ($resp_body === false) ? 'Request failed' : '';
        $json = json_decode((string) $resp_body, true);
        $ok = !$err && $http >= 200 && $http < 300 && is_array($json);

        return array(
            'ok' => $ok,
            'http' => $http,
            'err' => $err,
            'body' => (string) $resp_body,
            'json' => is_array($json) ? $json : array(),
        );
    }

    private function jsonResponse($status_code, array $payload)
    {
        $this->response->addHeader('Content-Type: application/json');
        http_response_code((int) $status_code);
        $this->response->setOutput(json_encode($payload));
    }
}
