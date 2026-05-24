<?php
if (!defined('ABSPATH')) {
    error_log('WC Letztech-payment Cartão de Crédito: ABSPATH não definido, encerrando');
    exit;
}

class WC_Gateway_Zoop_Credit_Card extends WC_Payment_Gateway
{
    public function __construct()
    {
        error_log('WC Letztech-payment Cartão de Crédito: Entrando no construtor');
        $this->id = 'zoop_credit_card';
        $this->method_title = __('Cartão de Crédito Letztech-payment', 'wc-zoop-payments');
        $this->method_description = __('Pague com cartão de crédito via API Letztech-payment', 'wc-zoop-payments');
        $this->title = $this->get_option('title', __('Cartão de Crédito', 'wc-zoop-payments'));
        $this->has_fields = true;
        $this->supports = ['products'];

        error_log('WC Letztech-payment Cartão de Crédito: ID do gateway: ' . $this->id);
        error_log('WC Letztech-payment Cartão de Crédito: Título: ' . $this->title);
        error_log('WC Letztech-payment Cartão de Crédito: Possui campos: ' . ($this->has_fields ? 'true' : 'false'));

        $this->init_form_fields();
        error_log('WC Letztech-payment Cartão de Crédito: Campos de formulário inicializados');

        $this->init_settings();
        error_log('WC Letztech-payment Cartão de Crédito: Configurações inicializadas');

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->description = $this->get_option('description', __('Pague com cartão de crédito via nossa API Letztech-payment segura', 'wc-zoop-payments'));
        error_log('WC Letztech-payment Cartão de Crédito: Habilitado: ' . $this->enabled);
        error_log('WC Letztech-payment Cartão de Crédito: Descrição: ' . $this->description);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_footer', [$this, 'add_payment_scripts']);
        error_log('WC Letztech-payment Cartão de Crédito: Ações registradas');
    }

    public function init_form_fields()
    {
        error_log('WC Letztech-payment Cartão de Crédito: Inicializando campos de formulário');
        $this->form_fields = [
            'enabled' => [
                'title' => __('Ativar/Desativar', 'wc-zoop-payments'),
                'type' => 'checkbox',
                'label' => __('Ativar Cartão de Crédito Zoop', 'wc-zoop-payments'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Título', 'wc-zoop-payments'),
                'type' => 'text',
                'description' => __('Título exibido no checkout', 'wc-zoop-payments'),
                'default' => __('Cartão de Crédito', 'wc-zoop-payments')
            ],
            'description' => [
                'title' => __('Descrição', 'wc-zoop-payments'),
                'type' => 'textarea',
                'description' => __('Descrição exibida no checkout', 'wc-zoop-payments'),
                'default' => __('Pague com cartão de crédito via nossa API Zoop segura', 'wc-zoop-payments')
            ]
        ];
        error_log('WC Letztech-payment Cartão de Crédito: Campos de formulário definidos: ' . print_r($this->form_fields, true));
    }

    public function add_payment_scripts()
    {
        error_log('WC Letztech-payment Cartão de Crédito: Verificando se está na página de checkout');
        error_log('WC Letztech-payment Cartão de Crédito: Resultado de is_checkout(): ' . (is_checkout() ? 'true' : 'false'));
        if (!is_checkout()) {
            error_log('WC Letztech-payment Cartão de Crédito: Não está na página de checkout, ignorando scripts');
            return;
        }
        error_log('WC Letztech-payment Cartão de Crédito: Adicionando scripts ao checkout');
        ?>
        <style>
            #zoop-credit-card-form .form-row {
                margin-bottom: 15px;
            }

            #zoop-credit-card-form input,
            #zoop-credit-card-form select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }

            #zoop-credit-card-form .form-row-inline {
                display: flex;
                gap: 10px;
            }

            #zoop-credit-card-form .form-col {
                flex: 1;
            }

            #zoop-credit-card-form label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
        </style>
        <script>
            console.log('WC Letztech-payment Cartão de Crédito: JavaScript carregado na página de checkout');
            jQuery(document).ready(function ($) {
                console.log('WC Letztech-payment Cartão de Crédito: jQuery pronto, inicializando manipuladores de formulário');
                const cardNumber = $('#card_number');
                const expiryMonth = $('#card_expiry_month');
                const expiryYear = $('#card_expiry_year');
                const securityCode = $('#card_security_code');
                const cep = $('#enderCEP');

                if (cardNumber.length) {
                    console.log('WC Letztech-payment Cartão de Crédito: Campo de número do cartão encontrado');
                    cardNumber.on('input', function () {
                        let value = $(this).val().replace(/\D/g, '');
                        value = value.replace(/(\d{4})/g, '$1 ').trim();
                        $(this).val(value);
                        console.log('WC Letztech-payment Cartão de Crédito: Entrada do número do cartão: ' + value);
                    });
                } else {
                    console.log('WC Letztech-payment Cartão de Crédito: Campo de número do cartão NÃO encontrado');
                }

                expiryMonth.on('input', function () {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 2) value = value.slice(0, 2);
                    $(this).val(value);
                    console.log('WC Letztech-payment Cartão de Crédito: Entrada do mês de expiração: ' + value);
                });

                expiryYear.on('input', function () {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 4) value = value.slice(0, 4);
                    $(this).val(value);
                    console.log('WC Letztech-payment Cartão de Crédito: Entrada do ano de expiração: ' + value);
                });

                securityCode.on('input', function () {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 4) value = value.slice(0, 4);
                    $(this).val(value);
                    console.log('WC Letztech-payment Cartão de Crédito: Entrada do CVV: ' + value);
                });

                cep.on('input', function () {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 5) {
                        value = value.slice(0, 5) + '-' + value.slice(5, 8);
                    }
                    $(this).val(value);
                    console.log('WC Letztech-payment Cartão de Crédito: Entrada do CEP: ' + value);
                });

                console.log('WC Letztech-payment Cartão de Crédito: Adicionando campos ocultos de dispositivo');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_color_depth',
                    value: screen.colorDepth || 24
                }).appendTo('#zoop-credit-card-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_language',
                    value: navigator.language || 'pt-BR'
                }).appendTo('#zoop-credit-card-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_screen_height',
                    value: screen.height || 1080
                }).appendTo('#zoop-credit-card-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_screen_width',
                    value: screen.width || 1920
                }).appendTo('#zoop-credit-card-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_time_zone',
                    value: new Date().getTimezoneOffset()
                }).appendTo('#zoop-credit-card-form');
                console.log('WC Letztech-payment Cartão de Crédito: Campos ocultos de dispositivo adicionados');
            });
        </script>
        <?php
    }

    public function payment_fields()
    {
        error_log('WC Letztech-payment Cartão de Crédito: Renderizando campos de pagamento');
        error_log('WC Letztech-payment Cartão de Crédito: ID da página atual: ' . get_the_ID());
        error_log('WC Letztech-payment Cartão de Crédito: É página de checkout: ' . (is_checkout() ? 'true' : 'false'));
        ?>
        <div id="zoop-credit-card-form">
            <p><?php echo esc_html($this->description); ?></p>
            <div class="form-row">
                <label for="card_holder_name"><?php _e('Nome do Titular do Cartão', 'wc-zoop-payments'); ?> <span
                        class="required">*</span></label>
                <input type="text" id="card_holder_name" name="card_holder_name" placeholder="João Silva" required>
            </div>
            <div class="form-row">
                <label for="card_number"><?php _e('Número do Cartão', 'wc-zoop-payments'); ?> <span
                        class="required">*</span></label>
                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19"
                    required>
            </div>
            <div class="form-row form-row-inline">
                <div class="form-col">
                    <label for="card_expiry_month"><?php _e('Mês de Expiração', 'wc-zoop-payments'); ?> <span
                            class="required">*</span></label>
                    <input type="text" id="card_expiry_month" name="card_expiry_month" placeholder="MM" maxlength="2" required>
                </div>
                <div class="form-col">
                    <label for="card_expiry_year"><?php _e('Ano de Expiração', 'wc-zoop-payments'); ?> <span
                            class="required">*</span></label>
                    <input type="text" id="card_expiry_year" name="card_expiry_year" placeholder="AAAA" maxlength="4" required>
                </div>
                <div class="form-col">
                    <label for="card_security_code"><?php _e('CVV', 'wc-zoop-payments'); ?> <span
                            class="required">*</span></label>
                    <input type="text" id="card_security_code" name="card_security_code" placeholder="123" maxlength="4"
                        required>
                </div>
            </div>
            <div class="form-row">
                <label for="number_installments"><?php _e('Número de Parcelas', 'wc-zoop-payments'); ?> <span
                        class="required">*</span></label>
                <select id="number_installments" name="number_installments" required>
                    <option value="1"><?php _e('1x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="2"><?php _e('2x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="3"><?php _e('3x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="4"><?php _e('4x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="5"><?php _e('5x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="6"><?php _e('6x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="7"><?php _e('7x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="8"><?php _e('8x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="9"><?php _e('9x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="10"><?php _e('10x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="11"><?php _e('11x sem juros', 'wc-zoop-payments'); ?></option>
                    <option value="12"><?php _e('12x sem juros', 'wc-zoop-payments'); ?></option>
                </select>
            </div>
            <div class="form-row">
                <label for="enderCEP"><?php _e('CEP', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="enderCEP" name="enderCEP" placeholder="12345-678" maxlength="9" required>
            </div>
        </div>
        <?php
        error_log('WC Letztech-payment Cartão de Crédito: Campos de pagamento renderizados');
    }

public function process_payment($order_id)
{
    error_log('WC Letztech-payment Boleto: Processando pagamento para o pedido #' . $order_id);
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' não encontrado');
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    $required_fields = [
        'billing_first_name', 'billing_last_name', 'customer_cpf', 'customer_birthdate',
        'billing_email', 'billing_phone', 'billing_address_1', 'billing_address_2',
        'billing_neighborhood', 'billing_city', 'billing_state', 'billing_postcode'
    ];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            error_log('WC Letztech-payment Boleto: Campo ausente ou vazio: ' . $field);
            wc_add_notice(__('Por favor, preencha todos os campos obrigatórios.', 'wc-zoop-payments'), 'error');
            return;
        }
    }

    $seller_id = get_option('wc_zoop_seller_id', '');
    if (empty($seller_id)) {
        error_log('WC Letztech-payment Boleto: Seller ID não configurado');
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    $total = $order->get_total();
    error_log('WC Letztech-payment Boleto: Valor total do pedido (BRL): ' . $total);
    $amount = floatval($total); // Match Credit Card class: send amount as float in BRL
    error_log('WC Letztech-payment Boleto: Valor enviado para API: ' . $amount . ' (BRL)');
    $expiration_date = date('Y-m-d', strtotime('+7 days'));

    $payload = [
        'amount' => $amount,
        'description' => 'Compra para o pedido #' . $order_id,
        'expiration_date' => $expiration_date,
        'seller_id' => $seller_id,
        'buyer' => [
            'first_name' => sanitize_text_field($_POST['billing_first_name']),
            'last_name' => sanitize_text_field($_POST['billing_last_name']),
            'email' => sanitize_email($_POST['billing_email']),
            'phone_number' => sanitize_text_field(str_replace(['+', ' ', '-', '(', ')'], '', $_POST['billing_phone'])),
            'taxpayer_id' => sanitize_text_field(str_replace(['.', '-'], '', $_POST['customer_cpf'])),
            'birthdate' => sanitize_text_field($_POST['customer_birthdate']),
            'address' => [
                'line1' => sanitize_text_field($_POST['billing_address_1']),
                'line2' => sanitize_text_field($_POST['billing_address_2']),
                'line3' => sanitize_text_field($_POST['billing_address_3'] ?? ''),
                'neighborhood' => sanitize_text_field($_POST['billing_neighborhood']),
                'city' => sanitize_text_field($_POST['billing_city']),
                'state' => sanitize_text_field($_POST['billing_state']),
                'postal_code' => sanitize_text_field(str_replace('-', '', $_POST['billing_postcode']))
            ]
        ]
    ];

    $payload_json = json_encode($payload, JSON_PRETTY_PRINT);
    error_log('WC Letztech-payment Boleto: Payload preparado: ' . $payload_json);
    wc_add_notice(__('Payload enviado para a API: <pre>' . esc_html($payload_json) . '</pre>'), 'notice');

    $response = wp_remote_post('http://186.249.36.174/api/transactions/boleto', [
        'body' => $payload_json,
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('WC Letztech-payment Boleto: Erro WP na API: ' . $error_message);
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    error_log('WC Letztech-payment Boleto: Código de resposta da API: ' . $response_code);
    error_log('WC Letztech-payment Boleto: Corpo da resposta da API: ' . $response_body);
    error_log('WC Letztech-payment Boleto: Resposta completa da API: ' . print_r(json_decode($response_body, true), true));

    $body = json_decode($response_body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('WC Letztech-payment Boleto: Erro ao decodificar JSON: ' . json_last_error_msg());
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    if ($response_code == 201) {
        $transaction_id = isset($body['idTransacao']) ? sanitize_text_field($body['idTransacao']) : 'boleto-' . time();
        $barcode = isset($body['barcode']) ? sanitize_text_field($body['barcode']) : '';
        $boleto_url = isset($body['url']) ? esc_url_raw($body['url']) : '';
        $document_number = isset($body['document_number']) ? sanitize_text_field($body['document_number']) : '';
        $boleto_expiration = isset($body['expiration_date']) ? sanitize_text_field($body['expiration_date']) : $expiration_date;
        $boleto_amount = isset($body['amount']) ? floatval($body['amount']) : $total; // Assume API returns amount in BRL
        error_log('WC Letztech-payment Boleto: Valor retornado pela API (BRL): ' . $boleto_amount);
        $formatted_boleto_amount = 'R$ ' . number_format($boleto_amount, 2, ',', '.');

        $order->update_meta_data('_letztech_transaction_id', $transaction_id);
        $order->update_meta_data('_letztech_boleto_barcode', $barcode);
        $order->update_meta_data('_letztech_boleto_url', $boleto_url);
        $order->update_meta_data('_letztech_boleto_document_number', $document_number);
        $order->update_meta_data('_letztech_boleto_expiration', $boleto_expiration);
        $order->update_meta_data('_letztech_boleto_amount', $boleto_amount);
        $order->set_transaction_id($transaction_id);
        $order->update_status('on-hold', __('Aguardando pagamento via Boleto.', 'wc-zoop-payments'));
        $order->add_order_note(sprintf(
            'Boleto gerado. Transação ID: %s, Código de Barras: %s, URL: %s, Número do Documento: %s, Vencimento: %s, Valor: %s',
            $transaction_id, $barcode, $boleto_url, $document_number, $boleto_expiration, $formatted_boleto_amount
        ));
        $order->save();

        WC()->session->set('zoop_boleto_details', [
            'barcode' => $barcode,
            'url' => $boleto_url,
            'document_number' => $document_number,
            'expiration_date' => $boleto_expiration,
            'amount' => $boleto_amount, // Store in BRL
            'idTransacao' => $transaction_id
        ]);

        wc_add_notice(__('Boleto gerado com sucesso. Veja os detalhes abaixo e no e-mail enviado.', 'wc-zoop-payments'), 'success');
        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    } else {
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Erro ao realizar pagamento.';
        error_log('WC Letztech-payment Boleto: Pagamento recusado: ' . $error_message);
        $order->update_status('failed', __('Pagamento recusado via Letztech-payment.', 'wc-zoop-payments'));
        $order->add_order_note('Pagamento recusado via Letztech-payment.');
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }
}
    public function check_transaction_status($transaction_id)
    {
        error_log('WC Letztech-payment Cartão de Crédito: Iniciando consulta de status da transação: ' . $transaction_id);

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
            error_log('WC Letztech-payment Cartão de Crédito: Erro ao consultar status da transação: ' . $error_message);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('WC Letztech-payment Cartão de Crédito: Resposta da API - Código: ' . $response_code . ', Corpo: ' . $response_body);

        if (!in_array($response_code, [200, 201])) {
            error_log('WC Letztech-payment Cartão de Crédito: Erro na consulta da API, código: ' . $response_code);
            return false;
        }

        $body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($body['status'])) {
            error_log('WC Letztech-payment Cartão de Crédito: Erro ao decodificar JSON ou status ausente: ' . json_last_error_msg());
            return false;
        }

        error_log('WC Letztech-payment Cartão de Crédito: Status da transação retornado: ' . $body['status']);
        return [
            'status' => sanitize_text_field(strtolower($body['status'])),
            'amount' => isset($body['amount']) ? sanitize_text_field($body['amount']) : ''
        ];
    }

    public function update_order_status($order_id, $status = null)
    {
        error_log('WC Letztech-payment Cartão de Crédito: Iniciando update_order_status para pedido #' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('WC Letztech-payment Cartão de Crédito: Pedido #' . $order_id . ' não encontrado');
            return false;
        }
        error_log('WC Letztech-payment Cartão de Crédito: Pedido #' . $order_id . ' carregado. Método: ' . $order->get_payment_method() . ', Status: ' . $order->get_status());

        $transaction_id = $order->get_meta('_letztech_transaction_id');
        if (empty($transaction_id)) {
            error_log('WC Letztech-payment Cartão de Crédito: ID da transação não encontrado para o pedido #' . $order_id);
            return false;
        }
        error_log('WC Letztech-payment Cartão de Crédito: ID da transação encontrado: ' . $transaction_id);

        if ($status === null) {
            $transaction_data = $this->check_transaction_status($transaction_id);
            if (!$transaction_data) {
                error_log('WC Letztech-payment Cartão de Crédito: Falha ao consultar status da transação para o pedido #' . $order_id);
                return false;
            }
            $status = $transaction_data['status'];
            error_log('WC Letztech-payment Cartão de Crédito: Status retornado pela API: ' . $status);
        }

        error_log('WC Letztech-payment Cartão de Crédito: Processando status da transação para o pedido #' . $order_id . ': ' . $status);

        switch (strtolower($status)) {
            case 'succeeded':
                if (!in_array($order->get_status(), ['completed', 'completed'])) {
                    // $order->update_status('completed', __('Pagamento aprovado via Letztech-payment.', 'wc-zoop-payments'));

                    // Verifica se deve marcar como processing ou completed
                    $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

                    $status = $completed_as_processing ? 'processing' : 'completed';

                    $order->update_status($status, __('Pagamento aprovado via Letztech-payment.', 'wc-zoop-payments'));
                    // final do bloco
                    $order->payment_complete($transaction_id);
                    wc_reduce_stock_levels($order_id);
                    $order->add_order_note('Status atualizado para Processando. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Cartão de Crédito: Pedido #' . $order_id . ' atualizado para Processando');
                    return true;
                } else {
                    error_log('WC Letztech-payment Cartão de Crédito: Pedido #' . $order_id . ' já está em Processando ou Concluído');
                    return true;
                }
            case 'Request Failed':
                if ($order->get_status() !== 'Request Failed') {
                    $order->update_status('failed', __('Pagamento recusado via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Falhado. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Cartão de Crédito: Pedido #' . $order_id . ' atualizado para Falhado');
                    return true;
                }
                break;
            case 'failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', __('Pagamento recusado via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Falhado. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Cartão de Crédito: Pedido #' . $order_id . ' atualizado para Falhado');
                    return true;
                }
                break;
            case 'pending':
                if ($order->get_status() !== 'pending') {
                    $order->update_status('pending', __('Pagamento pendente via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status mantido como Pendente. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Cartão de Crédito: Pedido #' . $order_id . ' mantido como Pendente');
                    return true;
                }
                break;
            default:
                error_log('WC Letztech-payment Cartão de Crédito: Status desconhecido para o pedido #' . $order_id . ': ' . $status);
                return false;
        }

        return true;
    }
}
?>