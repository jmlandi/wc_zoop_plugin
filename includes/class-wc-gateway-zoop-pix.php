<?php
if (!defined('ABSPATH')) {
    error_log('WC Letztech PIX: ABSPATH não definido, encerrando');
    exit;
}

class WC_Gateway_Zoop_PIX extends WC_Payment_Gateway
{
    public function __construct()
    {
        error_log('WC Letztech PIX: Entrando no construtor');
        $this->id = 'zoop_pix';
        $this->method_title = __('PIX Letztech', 'wc-zoop-payments');
        $this->method_description = __('Pague com PIX via API Letztech', 'wc-zoop-payments');
        $this->title = $this->get_option('title', __('PIX', 'wc-zoop-payments'));
        $this->has_fields = true;
        $this->supports = ['products'];

        error_log('WC Letztech PIX: ID do gateway: ' . $this->id);
        error_log('WC Letztech PIX: Título: ' . $this->title);

        $this->init_form_fields();
        error_log('WC Letztech PIX: Campos de formulário inicializados');

        $this->init_settings();
        error_log('WC Letztech PIX: Configurações inicializadas');

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->description = $this->get_option('description', __('Pague instantaneamente com PIX via nossa API Letztech segura', 'wc-zoop-payments'));
        error_log('WC Letztech PIX: Habilitado: ' . $this->enabled);
        error_log('WC Letztech PIX: Descrição: ' . $this->description);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        error_log('WC Letztech PIX: Ações registradas');
    }

    public function init_form_fields()
    {
        error_log('WC Letztech PIX: Inicializando campos de formulário');
        $this->form_fields = [
            'enabled' => [
                'title' => __('Ativar/Desativar', 'wc-zoop-payments'),
                'type' => 'checkbox',
                'label' => __('Ativar PIX Zoop', 'wc-zoop-payments'),
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
                'default' => __('Pague instantaneamente com PIX via nossa API Letztech segura', 'wc-zoop-payments')
            ]
        ];
        error_log('WC Letztech PIX: Campos de formulário definidos: ' . print_r($this->form_fields, true));
    }

    public function payment_fields()
    {
        error_log('WC Letztech PIX: Renderizando campos de pagamento');
        ?>
        <div id="zoop-pix-form">
            <p><?php echo esc_html($this->description); ?></p>
            <p><?php _e('Após realizar o pedido, você receberá um QR Code para completar o pagamento via PIX.', 'wc-zoop-payments'); ?>
            </p>
        </div>
        <?php
        error_log('WC Letztech PIX: Campos de pagamento renderizados');
    }

    public function enqueue_scripts()
    {
        if (!is_wc_endpoint_url('order-received')) {
            error_log('WC Letztech PIX: Não está na página de agradecimento, ignorando scripts');
            return;
        }

        $order_id = get_query_var('order-received');
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id) {
            error_log('WC Letztech PIX: Pedido #' . $order_id . ' não é PIX ou inválido, ignorando scripts');
            return;
        }

        error_log('WC Letztech PIX: Enfileirando scripts para a página de agradecimento do pedido #' . $order_id);
        $qrcode_script_url = plugins_url('../assets/qrcode.min.js', __FILE__);
        error_log('WC Letztech PIX: URL do qrcode.min.js: ' . $qrcode_script_url);
        wp_enqueue_script(
            'zoop-pix-qrcode',
            $qrcode_script_url,
            [],
            '1.4.4',
            true
        );
        wp_add_inline_script(
            'zoop-pix-qrcode',
            'if (typeof qrcode === "undefined") { console.error("WC Letztech PIX: Falha ao carregar qrcode.min.js"); } else { console.log("WC Letztech PIX: qrcode.min.js carregado com sucesso"); }'
        );
        $pix_script_url = plugins_url('../assets/pix-script.js', __FILE__);
        error_log('WC Letztech PIX: URL do pix-script.js: ' . $pix_script_url);
        wp_enqueue_script(
            'zoop-pix-script',
            $pix_script_url,
            ['jquery', 'zoop-pix-qrcode'],
            '1.0.0',
            true
        );
        $emv = get_post_meta($order_id, '_zoop_pix_emv', true);
        $id_transacao = get_post_meta($order_id, '_zoop_pix_id_transacao', true);
        error_log('WC Letztech PIX: EMV para o pedido #' . $order_id . ': ' . ($emv ? $emv : 'Não encontrado'));
        error_log('WC Letztech PIX: ID Transação para o pedido #' . $order_id . ': ' . ($id_transacao ? $id_transacao : 'Não encontrado'));
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
        error_log('WC Letztech PIX: Scripts enfileirados e dados localizados para o pedido #' . $order_id);
    }

public function process_payment($order_id)
{
    error_log('WC Letztech PIX: Processando pagamento para o pedido #' . $order_id);
    $order = wc_get_order($order_id);
    if (!$order) {
        wc_add_notice(__('Erro: Pedido não encontrado.', 'wc-zoop-payments'), 'error');
        return;
    }

    $seller_id = get_option('wc_zoop_seller_id', '');
    if (empty($seller_id)) {
        wc_add_notice(__('Erro: Seller ID não configurado.', 'wc-zoop-payments'), 'error');
        return;
    }

    $api_url = 'http://186.249.36.174/api/transactions/pix';

    $payload = [
        'seller_id' => sanitize_text_field($seller_id),
        'amount' => floatval($order->get_total()),
        'description' => 'PIX #' . $order_id
    ];

// === SPLIT CORRIGIDO: CAMPOS PLANOS (sem *100) ===
$split_seller = get_option('wc_zoop_seller_id_split1', '');
$split_percentage = get_option('wc_zoop_percentage_split1', 0);

if (!empty($split_seller) && $split_percentage > 0 && $split_percentage <= 100) {
    $payload['seller_id_split1'] = sanitize_text_field($split_seller);
    $payload['percentage_split1'] = floatval($split_percentage); // 40 → 40.0
    error_log("WC Letztech PIX: Split ativado → seller_id_split1: {$split_seller}, percentage_split1: {$split_percentage}%");
} else {
    $payload['seller_id_split1'] = '';
    $payload['percentage_split1'] = 0.0;
    error_log('WC Letztech PIX: Split desativado');
}

    // === SALVA O JSON BRUTO NO PEDIDO ===
    $json_bruto = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    update_post_meta($order_id, '_zoop_pix_json_enviado', $json_bruto);
    error_log('WC Letztech PIX: JSON salvo no pedido #' . $order_id . ': ' . $json_bruto);

    // === ENVIA PARA API ===
    $response = wp_remote_post($api_url, [
        'body' => json_encode($payload),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        $error = $response->get_error_message();
        error_log('WC Letztech PIX: Erro WP: ' . $error);
        wc_add_notice(__('Erro ao processar o pagamento. Tente novamente.', 'wc-zoop-payments'), 'error');
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wc_add_notice(__('Erro ao processar o pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    if ($code == 201 && isset($body['emv'], $body['idTransacao'])) {
        update_post_meta($order_id, '_zoop_pix_emv', sanitize_text_field($body['emv']));
        update_post_meta($order_id, '_zoop_pix_id_transacao', sanitize_text_field($body['idTransacao']));
        $order->update_status('on-hold', 'Aguardando PIX');
        $order->add_order_note('PIX gerado. ID: ' . $body['idTransacao']);
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

    $emv = get_post_meta($order_id, '_zoop_pix_emv', true);
    $json_bruto = get_post_meta($order_id, '_zoop_pix_json_enviado', true);

    if (empty($emv)) {
        echo '<p>' . esc_html__('Erro: Código PIX não disponível.', 'wc-zoop-payments') . '</p>';
        return;
    }

/*  //   === PRINTAR O JSON NA TELA DE AGRADECIMENTO ===
    if ($json_bruto) {
        echo '<div style="background:#fff8e1; border:2px solid #ff9800; padding:15px; margin:20px 0; font-family:Consolas,monospace; white-space:pre-wrap; max-height:400px; overflow:auto; color:#b71c1c; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
        echo '<p style="margin:0 0 10px; font-weight:bold; color:#e65100;">JSON BRUTO ENVIADO PARA A API (Letztech)</p>';
        echo '<pre style="background:#263238; color:#eceff1; padding:15px; border-radius:6px; margin:0; font-size:13px; overflow-x:auto;">';
        echo esc_html($json_bruto);
        echo '</pre>';
        echo '<p style="margin:10px 0 0; font-size:12px; color:#e65100;">Este JSON foi enviado exatamente assim para <code>http://186.249.36.174/api/transactions/pix</code></p>';
        echo '</div>';
    } else {
        echo '<p style="color:#d32f2f; font-weight:bold;">AVISO: JSON não foi salvo (erro no process_payment).</p>';
    } */
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
    public function check_transaction_status($transaction_id)
    {
        error_log('WC Letztech PIX: Iniciando consulta de status da transação: ' . $transaction_id);

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
            $error_message = $response->get_error_message();
            error_log('WC Letztech PIX: Erro ao consultar status da transação: ' . $error_message);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('WC Letztech PIX: Resposta da API - Código: ' . $response_code . ', Corpo: ' . $response_body);

        if (!in_array($response_code, [200, 201])) {
            error_log('WC Letztech PIX: Erro na consulta da API, código: ' . $response_code);
            return false;
        }

        $body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($body['status'])) {
            error_log('WC Letztech PIX: Erro ao decodificar JSON ou status ausente: ' . json_last_error_msg());
            return false;
        }

        error_log('WC Letztech PIX: Status da transação retornado: ' . $body['status']);
        return [
            'status' => sanitize_text_field(strtolower($body['status'])),
            'amount' => isset($body['amount']) ? sanitize_text_field($body['amount']) : ''
        ];
    }

    public function update_order_status($order_id, $status = null)
    {
        error_log('WC Letztech PIX: Iniciando update_order_status para pedido #' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('WC Letztech PIX: Pedido #' . $order_id . ' não encontrado');
            return false;
        }
        error_log('WC Letztech PIX: Pedido #' . $order_id . ' carregado. Método: ' . $order->get_payment_method() . ', Status: ' . $order->get_status());

        $transaction_id = $order->get_meta('_zoop_pix_id_transacao');
        if (empty($transaction_id)) {
            error_log('WC Letztech PIX: ID da transação não encontrado para o pedido #' . $order_id);
            return false;
        }
        error_log('WC Letztech PIX: ID da transação encontrado: ' . $transaction_id);

        if ($status === null) {
            $transaction_data = $this->check_transaction_status($transaction_id);
            if (!$transaction_data) {
                error_log('WC Letztech PIX: Falha ao consultar status da transação para o pedido #' . $order_id);
                return false;
            }
            $status = $transaction_data['status'];
            error_log('WC Letztech PIX: Status retornado pela API: ' . $status);
        }

        error_log('WC Letztech PIX: Processando status da transação para o pedido #' . $order_id . ': ' . $status);

        switch (strtolower($status)) {
            case 'succeeded':
                if (!in_array($order->get_status(), ['processing', 'completed'])) {
                    // $order->update_status('completed', __('Pagamento confirmado via Letztech PIX.', 'wc-zoop-payments'));
                    // Verifica se deve marcar como processing ou completed
                    $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

                    $status = $completed_as_processing ? 'processing' : 'completed';

                    $order->update_status($status, __('Pagamento aprovado via Letztech PIX.', 'wc-zoop-payments'));
                    // final do bloco
                    $order->payment_complete($transaction_id);
                    $order->add_order_note('Status atualizado para Concluído. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech PIX: Pedido #' . $order_id . ' atualizado para Concluído - 1829829823982398');
                } else {
                    error_log('WC Letztech PIX: Pedido #' . $order_id . ' já está em Processando ou Concluído (' . $order->get_status() . ')');
                }
                break;

            case 'failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', __('Pagamento recusado via Letztech PIX.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Falhado. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech PIX: Pedido #' . $order_id . ' atualizado para Falhado');
                } else {
                    error_log('WC Letztech PIX: Pedido #' . $order_id . ' já está como Falhado');
                }
                break;

            case 'pending':
                if (!in_array($order->get_status(), ['pending', 'on-hold'])) {
                    $order->update_status('on-hold', __('Pagamento pendente via Letztech PIX.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Aguardando (on-hold). Transação ID: ' . $transaction_id);
                    error_log('WC Letztech PIX: Pedido #' . $order_id . ' atualizado para On-hold (aguardando)');
                } else {
                    error_log('WC Letztech PIX: Pedido #' . $order_id . ' já está aguardando pagamento (' . $order->get_status() . ')');
                }
                break;

            default:
                error_log('WC Letztech PIX: Status desconhecido "' . $status . '" para o pedido #' . $order_id);
                break;
        }

        return true;
    }
}

function zoop_verificar_pagamento_pix()
{
    error_log("WC Letztech-payment PIX: Verificando pagamentos");

    $orders = wc_get_orders([
        'status' => 'on-hold',
    ]);

    if (empty($orders)) {
        error_log('WC Letztech-payment PIX: Nenhuma transação pendente para verificar');
        wp_clear_scheduled_hook('zoop_verifica_pagamento_pix');
        return;
    }

    error_log('WC Letztech-payment PIX: Encontradas ' . count($orders) . ' transações pendentes para verificar');


    foreach ($orders as $order) {
        $order_id = $order->get_id();
        error_log('WC Letztech-payment PIX: Verificando pagamento para o pedido #' . $order_id);

        $id_transacao = get_post_meta($order_id, '_zoop_pix_id_transacao', true);

        if (!$id_transacao)
            continue;

        $response = wp_remote_get("http://186.249.36.174/api/transactions/{$id_transacao}", [
            'timeout' => 15
        ]);

        error_log('WC Letztech-payment PIX: Status da transação: ' . wp_remote_retrieve_response_code($response));

        if (is_wp_error($response))
            continue;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['status']))
            continue;

        switch ($body['status']) {
            case 'succeeded':
                // $order->update_status('completed', 'PIX confirmado via pulling.');
                // Verifica se deve marcar como processing ou completed
                $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

                $status = $completed_as_processing ? 'processing' : 'completed';

                $order->update_status($status, 'PIX confirmado via pulling.');
                // final do bloco
                $order->add_order_note('PIX confirmado via pulling.');
                break;
            case 'cancelled':
            case 'failed':
            case 'canceled':
                $order->update_status('cancelled', 'PIX cancelado via pulling.');
                break;
        }
        // }
    }
}

// add_action('rest_api_init', function () {
//     register_rest_route('zoop/v1', '/forcar-pix', [
//         'methods' => 'GET',
//         'callback' => function () {
//             zoop_verificar_pagamento_pix();
//             return ['status' => 'executado'];
//         },
//         'permission_callback' => '__return_true'
//     ]);
// });

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