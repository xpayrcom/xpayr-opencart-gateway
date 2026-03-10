<?php
class ControllerExtensionPaymentXpayr extends Controller
{
    private $error = array();

    public function index()
    {
        $data = $this->load->language('extension/payment/xpayr');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $synced = false;
            if (!empty($this->request->post['payment_xpayr_webhook_auto_sync'])) {
                $webhook_url = $this->getWebhookUrl();
                $sync = $this->syncWebhookSecret(
                    (string) $this->request->post['payment_xpayr_api_base_url'],
                    (string) $this->request->post['payment_xpayr_secret_key'],
                    $webhook_url
                );

                if ($sync['ok']) {
                    $this->request->post['payment_xpayr_webhook_secret'] = $sync['secret'];
                    $synced = true;
                } else {
                    $this->session->data['warning'] = $this->language->get('text_warning_webhook') . ' ' . $sync['error'];
                }
            }

            $this->model_setting_setting->editSetting('payment_xpayr', $this->request->post);

            $success = $this->language->get('text_success');
            if ($synced) {
                $success .= ' ' . $this->language->get('text_webhook_synced');
            }
            $this->session->data['success'] = $success;

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } elseif (!empty($this->session->data['warning'])) {
            $data['error_warning'] = $this->session->data['warning'];
            unset($this->session->data['warning']);
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/xpayr', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/xpayr', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $fields = array(
            'payment_xpayr_status',
            'payment_xpayr_title',
            'payment_xpayr_description',
            'payment_xpayr_api_base_url',
            'payment_xpayr_secret_key',
            'payment_xpayr_network',
            'payment_xpayr_currency',
            'payment_xpayr_webhook_secret',
            'payment_xpayr_webhook_auto_sync',
            'payment_xpayr_pending_status_id',
            'payment_xpayr_paid_status_id',
            'payment_xpayr_failed_status_id',
            'payment_xpayr_expired_status_id',
            'payment_xpayr_sort_order',
            'payment_xpayr_debug'
        );

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        if (!$data['payment_xpayr_title']) {
            $data['payment_xpayr_title'] = 'Pay with Crypto (XPayr)';
        }

        if (!$data['payment_xpayr_description']) {
            $data['payment_xpayr_description'] = 'Secure on-chain checkout via XPayr';
        }

        if (!$data['payment_xpayr_api_base_url']) {
            $data['payment_xpayr_api_base_url'] = 'https://xpayr.com/api/v1';
        }

        if (!$data['payment_xpayr_network']) {
            $data['payment_xpayr_network'] = 'bsc-testnet';
        }

        if (!$data['payment_xpayr_currency']) {
            $data['payment_xpayr_currency'] = 'USDC';
        }

        $data['webhook_url'] = $this->getWebhookUrl();

        $catalog = $this->fetchNetworkCatalog($data['payment_xpayr_api_base_url'], $data['payment_xpayr_secret_key']);
        $data['network_options'] = $this->buildNetworkOptions($catalog);
        $data['currency_options'] = $this->buildCurrencyOptions($catalog);

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (!$data['payment_xpayr_pending_status_id']) {
            $data['payment_xpayr_pending_status_id'] = $this->config->get('config_order_status_id');
        }
        if (!$data['payment_xpayr_paid_status_id']) {
            $data['payment_xpayr_paid_status_id'] = $this->config->get('config_complete_status');
            if (is_array($data['payment_xpayr_paid_status_id'])) {
                $data['payment_xpayr_paid_status_id'] = (int) reset($data['payment_xpayr_paid_status_id']);
            }
            if (!$data['payment_xpayr_paid_status_id']) {
                $data['payment_xpayr_paid_status_id'] = $this->config->get('config_order_status_id');
            }
        }
        if (!$data['payment_xpayr_failed_status_id']) {
            $data['payment_xpayr_failed_status_id'] = $this->config->get('config_order_status_id');
        }
        if (!$data['payment_xpayr_expired_status_id']) {
            $data['payment_xpayr_expired_status_id'] = $this->config->get('config_order_status_id');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/xpayr', $data));
    }

    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/xpayr')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_xpayr_api_base_url'])) {
            $this->error['warning'] = $this->language->get('error_api_base_url');
        }

        if (empty($this->request->post['payment_xpayr_secret_key'])) {
            $this->error['warning'] = $this->language->get('error_secret_key');
        }

        if (empty($this->request->post['payment_xpayr_network'])) {
            $this->error['warning'] = $this->language->get('error_network');
        }

        if (empty($this->request->post['payment_xpayr_currency'])) {
            $this->error['warning'] = $this->language->get('error_currency');
        }

        return !$this->error;
    }

    private function getWebhookUrl()
    {
        return rtrim(HTTPS_CATALOG, '/') . '/index.php?route=extension/payment/xpayr/callback';
    }

    private function fetchNetworkCatalog($api_base_url, $secret_key)
    {
        $api_base_url = rtrim((string) $api_base_url, '/');
        $secret_key = trim((string) $secret_key);

        if (!$api_base_url || !$secret_key) {
            return array();
        }

        $result = $this->httpRequest('GET', $api_base_url . '/me/networks', $secret_key);

        if (!$result['ok']) {
            return array();
        }

        $json = $result['json'];
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return array();
        }

        return $json['data'];
    }

    private function buildNetworkOptions($rows)
    {
        $options = array();

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $network = isset($row['network']) ? trim((string) $row['network']) : '';
                if (!$network) {
                    continue;
                }
                $network_name = isset($row['network_name']) ? trim((string) $row['network_name']) : $network;
                $options[$network] = array('key' => $network, 'name' => $network_name);
            }
        }

        if (!$options) {
            $options = array(
                'bsc-testnet' => array('key' => 'bsc-testnet', 'name' => 'BSC Testnet'),
                'base-sepolia' => array('key' => 'base-sepolia', 'name' => 'Base Sepolia'),
                'polygon-amoy' => array('key' => 'polygon-amoy', 'name' => 'Polygon Amoy'),
            );
        }

        return array_values($options);
    }

    private function buildCurrencyOptions($rows)
    {
        $symbols = array();

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $symbol = isset($row['symbol']) ? strtoupper(trim((string) $row['symbol'])) : '';
                if ($symbol) {
                    $symbols[$symbol] = array('key' => $symbol, 'name' => $symbol);
                }
            }
        }

        if (!$symbols) {
            $symbols = array(
                'USDC' => array('key' => 'USDC', 'name' => 'USDC'),
                'USDT' => array('key' => 'USDT', 'name' => 'USDT'),
            );
        }

        ksort($symbols);
        return array_values($symbols);
    }

    private function syncWebhookSecret($api_base_url, $secret_key, $webhook_url)
    {
        $api_base_url = rtrim((string) $api_base_url, '/');
        $secret_key = trim((string) $secret_key);

        if (!$api_base_url || !$secret_key || !$webhook_url) {
            return array('ok' => false, 'secret' => '', 'error' => 'missing config');
        }

        $result = $this->httpRequest('POST', $api_base_url . '/webhooks', $secret_key, array('url' => $webhook_url));

        if (!$result['ok']) {
            return array('ok' => false, 'secret' => '', 'error' => $result['err'] ? $result['err'] : 'HTTP ' . $result['http']);
        }

        $json = $result['json'];
        $secret = is_array($json) && isset($json['secret']) ? (string) $json['secret'] : '';

        if (!$secret) {
            return array('ok' => false, 'secret' => '', 'error' => 'missing secret in response');
        }

        return array('ok' => true, 'secret' => $secret, 'error' => '');
    }

    /**
     * Centralized HTTP request helper using file_get_contents with stream context.
     *
     * @param string $method  HTTP method (GET or POST)
     * @param string $url     Full API endpoint URL
     * @param string $secret  Bearer token for authorization
     * @param array  $payload Optional request body for POST requests
     * @return array Result with ok, http, err, body, and json keys
     */
    private function httpRequest($method, $url, $secret, array $payload = array())
    {
        $headers = "Authorization: Bearer " . $secret . "\r\n" .
            "Accept: application/json\r\n";

        $options = array(
            'http' => array(
                'method' => strtoupper($method),
                'header' => $headers,
                'timeout' => 20,
                'ignore_errors' => true,
            ),
        );

        if (!empty($payload) && strtoupper($method) === 'POST') {
            $options['http']['header'] .= "Content-Type: application/json\r\n";
            $options['http']['content'] = json_encode($payload);
        }

        $context = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);

        $http = 0;
        if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $http = isset($matches[1]) ? (int) $matches[1] : 0;
        }

        $err = ($body === false) ? 'Request failed' : '';
        $json = json_decode((string) $body, true);
        $ok = !$err && $http >= 200 && $http < 300 && is_array($json);

        return array(
            'ok' => $ok,
            'http' => $http,
            'err' => $err,
            'body' => (string) $body,
            'json' => is_array($json) ? $json : array(),
        );
    }
}
