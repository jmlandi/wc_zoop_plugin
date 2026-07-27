<?php
if (!defined('ABSPATH')) {
    error_log('WC Letztech PIX: ABSPATH não definido, encerrando');
    exit;
}

class WC_Gateway_Zoop_PIX extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'zoop_pix';
        $this->method_title = __('PIX Letztech', 'wc-zoop-payments');
        $this->method_description = __('Pague com PIX via API Letztech', 'wc-zoop-payments');
        $this->title = $this->get_option('title', __('PIX', 'wc-zoop-payments'));
        $this->has_fields = true;
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->description = $this->get_option('description', __('Pague instantaneamente com PIX via nossa API Letztech', 'wc-zoop-payments'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Ativar/Desativar', 'wc-zoop-payments'),
                'type' => 'checkbox',
                'label' => __('Ativar PIX', 'wc-zoop-payments'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Título', 'wc-zoop-payments'),
                'type' => 'text',
                'description' => __('Título exibido no checkout', 'wc-zoop-payments'),
                'default' => __('PIX', 'wc-zoop-payments')
            ],
            'description' => [
                'title' => __('Descrição', 'wc-zoop-payments'),
                'type' => 'textarea',
                'description' => __('Descrição exibida no checkout', 'wc-zoop-payments'),
                'default' => __('Pague instantaneamente com PIX via nossa API Letztech', 'wc-zoop-payments')
            ]
        ];
    }

    public function payment_fields()
    {
        ?>
        <div id="zoop-pix-form">
            <p><?php echo esc_html($this->description); ?></p>
            <p><?php _e('Após realizar o pedido, você receberá um QR Code para completar o pagamento via PIX.', 'wc-zoop-payments'); ?>
            </p>
            <div class="form-row">
                <label for="customer_cpf"><?php _e('CPF do Titular', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="customer_cpf" name="customer_cpf" placeholder="123.456.789-00" maxlength="14" required>
            </div>
        </div>
        <script>
            jQuery(function ($) {
                $('#customer_cpf').on('input', function () {
                    let v = this.value.replace(/\D/g, '').slice(0, 11);
                    if (v.length > 9) v = v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6, 9) + '-' + v.slice(9);
                    else if (v.length > 6) v = v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6);
                    else if (v.length > 3) v = v.slice(0, 3) + '.' + v.slice(3);
                    this.value = v;
                });
            });
        </script>
        <?php
    }

    public function enqueue_scripts()
    {
        if (!is_wc_endpoint_url('order-received')) {
            return;
        }

        $order_id = get_query_var('order-received');
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        wp_enqueue_script(
            'zoop-pix-qrcode',
            plugins_url('../assets/qrcode.min.js', __FILE__),
            [],
            '1.4.4',
            true
        );
        wp_add_inline_script(
            'zoop-pix-qrcode',
            'if (typeof qrcode === "undefined") { console.error("WC Letztech PIX: Falha ao carregar qrcode.min.js"); }'
        );
        wp_enqueue_script(
            'zoop-pix-script',
            plugins_url('../assets/pix-script.js', __FILE__),
            ['jquery', 'zoop-pix-qrcode'],
            '1.0.0',
            true
        );
        $emv          = $order->get_meta('_zoop_pix_emv');
        $id_transacao = $order->get_meta('_zoop_pix_id_transacao');
        wp_localize_script(
            'zoop-pix-script',
            'zoopPixData',
            [
                'emv' => $emv,
                'idTransacao' => $id_transacao,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_zoop_check_order_status'),
                'orderId' => $order_id,
                'i18n' => [
                    'error_qrcode' => __('Erro: Não foi possível gerar o QR Code. Use o código PIX abaixo.', 'wc-zoop-payments'),
                    'copy_success' => __('Código PIX copiado para a área de transferência!', 'wc-zoop-payments'),
                    'copy_error' => __('Erro ao copiar o código PIX. Copie manualmente.', 'wc-zoop-payments'),
                    'status_pending' => __('Aguardando pagamento...', 'wc-zoop-payments'),
                    'status_completed' => __('Pagamento confirmado!', 'wc-zoop-payments'),
                    'status_failed' => __('Pagamento falhou. Entre em contato com o suporte.', 'wc-zoop-payments'),
                    'status_error' => __('Erro ao verificar o status do pagamento.', 'wc-zoop-payments')
                ]
            ]
        );
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Erro: Pedido não encontrado.', 'wc-zoop-payments'), 'error');
            return;
        }

        if (empty($_POST['customer_cpf'])) {
            wc_add_notice(__('Por favor, preencha o CPF.', 'wc-zoop-payments'), 'error');
            return;
        }

        $seller_info = wc_zoop_get_seller_id_for_order($order);
        $seller_id   = $seller_info['seller_id'];
        if (empty($seller_id)) {
            wc_add_notice(__('Erro: Seller ID não configurado.', 'wc-zoop-payments'), 'error');
            return;
        }

        // Routes through the Letztech gateway (api.letstech.com.br); see
        // class-wc-gateway-zoop-credit-card-interest.php for the migration this
        // followed and wc_letztech_gateway_request() for the shared request helper.
        $payload = [
            'sellerId' => $seller_id,
            'orderId' => (string) $order_id,
            'method' => 'pix',
            'amount' => floatval($order->get_total()),
            'description' => 'PIX #' . $order_id,
            'customer' => [
                'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'document' => preg_replace('/\D/', '', $_POST['customer_cpf']),
                'email' => sanitize_email($order->get_billing_email()),
                'phone' => preg_replace('/\D/', '', $order->get_billing_phone() ?: ''),
            ],
        ];

        $result = wc_letztech_gateway_request('/woocommerce/payment', $payload);

        if (!$result['ok']) {
            wc_add_notice(__('Erro ao processar o pagamento. Tente novamente.', 'wc-zoop-payments'), 'error');
            return;
        }

        $code = $result['code'];
        $body = $result['body'];

        if ($code == 201 && isset($body['emv'], $body['idTransacao'])) {
            $order->update_meta_data('_zoop_pix_emv', sanitize_text_field($body['emv']));
            $order->update_meta_data('_zoop_pix_id_transacao', sanitize_text_field($body['idTransacao']));
            $order->update_meta_data('_letztech_resolved_seller_id', $seller_id);
            $order->save();
            $order->update_status('on-hold', 'Aguardando PIX');
            $sku_note = !empty($seller_info['sku_used']) ? " (SKU: {$seller_info['sku_used']})" : '';
            $order->add_order_note('PIX gerado. ID: ' . $body['idTransacao']);
            $order->add_order_note("Pagamento processado com Seller ID: {$seller_id}{$sku_note}");
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        } else {
            $error = $body['error']['message'] ?? '';
            wc_add_notice(__('Pagamento falhou: ', 'wc-zoop-payments') . esc_html($error), 'error');
            return;
        }
    }

    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $emv = $order->get_meta('_zoop_pix_emv');

        if (empty($emv)) {
            echo '<p>' . esc_html__('Erro: Código PIX não disponível.', 'wc-zoop-payments') . '</p>';
            return;
        }
        ?>
        <div class="zoop-pix-qrcode" style="margin: 20px 0; text-align: center;">
            <h2><?php _e('Pague com PIX', 'wc-zoop-payments'); ?></h2>
            <p><?php _e('Escaneie o QR Code abaixo:', 'wc-zoop-payments'); ?></p>
            <div id="zoop-pix-qrcode" style="display: inline-block; margin: 10px auto; min-height: 200px;"></div>
            <p><?php _e('Ou copie o código:', 'wc-zoop-payments'); ?></p>
            <textarea id="zoop-pix-emv" readonly style="width: 100%; max-width: 500px; height: 100px; margin: 10px auto;"><?php echo esc_textarea($emv); ?></textarea>
            <button onclick="zoopPixData.copyPixCode()" style="padding: 10px 20px; cursor: pointer;"><?php _e('Copiar Código PIX', 'wc-zoop-payments'); ?></button>
            <p id="zoop-pix-status" style="margin-top: 20px; font-weight: bold;"><?php _e('Aguardando pagamento...', 'wc-zoop-payments'); ?></p>
        </div>
        <?php
    }

    // KNOWN GAP: process_payment() now stores the Letztech gateway's own payment ID
    // (pay_...), but this still polls the legacy backend, which has never heard of
    // that ID -- status polling is broken until the gateway exposes a status/webhook
    // endpoint (tracked separately; same gap as the credit card gateway).
    public function check_transaction_status($transaction_id)
    {
        $response = wp_remote_get("http://186.249.36.174/api/transactions/{$transaction_id}", [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_site_url(),
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest'
            ],
            'timeout' => 60,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!in_array($response_code, [200, 201]) || !$body || !isset($body['status'])) {
            return false;
        }

        return [
            'status' => sanitize_text_field(strtolower($body['status'])),
            'amount' => isset($body['amount']) ? sanitize_text_field($body['amount']) : ''
        ];
    }

    public function update_order_status($order_id, $status = null)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $transaction_id = $order->get_meta('_zoop_pix_id_transacao');
        if (empty($transaction_id)) {
            return false;
        }

        if ($status === null) {
            $transaction_data = $this->check_transaction_status($transaction_id);
            if (!$transaction_data) {
                return false;
            }
            $status = $transaction_data['status'];
        }

        switch (strtolower($status)) {
            case 'succeeded':
                if (!in_array($order->get_status(), ['processing', 'completed'])) {
                    $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';
                    $status = $completed_as_processing ? 'processing' : 'completed';
                    $order->update_status($status, __('Pagamento aprovado via Letztech PIX.', 'wc-zoop-payments'));
                    $order->payment_complete($transaction_id);
                    $order->add_order_note('Status atualizado para Concluído. Transação ID: ' . $transaction_id);
                }
                break;

            case 'failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', __('Pagamento recusado via Letztech PIX.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Falhado. Transação ID: ' . $transaction_id);
                }
                break;

            case 'pending':
                if (!in_array($order->get_status(), ['pending', 'on-hold'])) {
                    $order->update_status('on-hold', __('Pagamento pendente via Letztech PIX.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Aguardando (on-hold). Transação ID: ' . $transaction_id);
                }
                break;
        }

        return true;
    }
}

// KNOWN GAP: same legacy-backend mismatch as check_transaction_status() above.
function zoop_verificar_pagamento_pix()
{
    $orders = wc_get_orders([
        'status'         => 'on-hold',
        'payment_method' => 'zoop_pix',
        'meta_key'       => '_zoop_pix_id_transacao',
        'meta_compare'   => 'EXISTS',
    ]);

    if (empty($orders)) {
        wp_clear_scheduled_hook('zoop_verifica_pagamento_pix');
        return;
    }

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $id_transacao = $order->get_meta('_zoop_pix_id_transacao');

        if (!$id_transacao) {
            continue;
        }

        $response = wp_remote_get("http://186.249.36.174/api/transactions/{$id_transacao}", [
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            continue;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['status'])) {
            continue;
        }

        switch ($body['status']) {
            case 'succeeded':
                $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';
                $status = $completed_as_processing ? 'processing' : 'completed';
                $order->update_status($status, 'PIX confirmado via pulling.');
                $order->add_order_note('PIX confirmado via pulling.');
                break;
            case 'cancelled':
            case 'failed':
            case 'canceled':
                $order->update_status('cancelled', 'PIX cancelado via pulling.');
                break;
        }
    }
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['every_five_minutes'] = [
        'interval' => 60,
        'display' => __('A cada 60 segundos'),
    ];
    return $schedules;
});

add_action('init', function () {
    if (!wp_next_scheduled('zoop_verifica_pagamento_pix')) {
        wp_schedule_event(time(), 'every_five_minutes', 'zoop_verifica_pagamento_pix');
    }
});

add_action('zoop_verifica_pagamento_pix', 'zoop_verificar_pagamento_pix');
