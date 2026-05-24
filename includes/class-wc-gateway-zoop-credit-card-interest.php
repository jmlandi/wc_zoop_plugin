<?php
if (!defined('ABSPATH')) {
    error_log('WC Letztech-payment Cartão de Crédito: ABSPATH não definido, encerrando');
    exit;
}

class WC_Gateway_Zoop_Credit_Card_Interest extends WC_Payment_Gateway
{
    public function __construct()
    {
        error_log('WC Letztech-payment Cartão de Crédito: Entrando no construtor');
        $this->id = 'zoop_credit_card';
        $this->method_title = __('Cartão de Crédito Letztech', 'wc-zoop-payments');
        $this->method_description = __('Pague com cartão de crédito via API Letztech', 'wc-zoop-payments');
        $this->title = $this->get_option('title', __('Cartão de Crédito', 'wc-zoop-payments'));
        $this->has_fields = true;
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->description = $this->get_option('description', __('Pague com cartão de crédito via nossa API Letztech segura', 'wc-zoop-payments'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_footer', [$this, 'add_payment_scripts']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Ativar/Desativar', 'wc-zoop-payments'),
                'type' => 'checkbox',
                'label' => __('Ativar Cartão de Crédito Letztech', 'wc-zoop-payments'),
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
                'default' => __('Pague com cartão de crédito via nossa API Letztech segura', 'wc-zoop-payments')
            ]
        ];
    }

    public function add_payment_scripts()
    {
        if (!is_checkout()) return;

        ?>
        <style>
            #zoop-credit-card-form .form-row { margin-bottom: 15px; }
            #zoop-credit-card-form input, #zoop-credit-card-form select {
                width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ccc;
                border-radius: 4px; box-sizing: border-box; font-size: 16px;
            }
            #zoop-credit-card-form .form-row-inline { display: flex; gap: 15px; flex-wrap: wrap; }
            #zoop-credit-card-form .form-col { flex: 1; min-width: 120px; }
            #zoop-credit-card-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                $('#card_number').on('input', function () {
                    let v = $(this).val().replace(/\D/g, '').match(/(\d{0,4})(\d{0,4})(\d{0,4})(\d{0,4})/);
                    $(this).val(!v[2] ? v[1] : v[1] + ' ' + v[2] + (v[3] ? ' ' + v[3] + (v[4] ? ' ' + v[4] : '') : ''));
                });
                $('#card_expiry_month').on('input', function () { this.value = this.value.replace(/\D/g, '').slice(0,2); });
                $('#card_expiry_year').on('input', function () { this.value = this.value.replace(/\D/g, '').slice(0,4); });
                $('#card_security_code').on('input', function () { this.value = this.value.replace(/\D/g, '').slice(0,4); });
                $('#enderCEP').on('input', function () {
                    let v = this.value.replace(/\D/g, '');
                    if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5,8);
                    this.value = v;
                });

                $('<input>').attr({type:'hidden', name:'device_color_depth', value: screen.colorDepth || 24}).appendTo('#zoop-credit-card-form');
                $('<input>').attr({type:'hidden', name:'device_language', value: navigator.language || 'pt-BR'}).appendTo('#zoop-credit-card-form');
                $('<input>').attr({type:'hidden', name:'device_screen_height', value: screen.height || 1080}).appendTo('#zoop-credit-card-form');
                $('<input>').attr({type:'hidden', name:'device_screen_width', value: screen.width || 1920}).appendTo('#zoop-credit-card-form');
                $('<input>').attr({type:'hidden', name:'device_time_zone', value: new Date().getTimezoneOffset()}).appendTo('#zoop-credit-card-form');
            });
        </script>
        <?php
    }

    public function payment_fields()
    {
        error_log('WC Letztech-payment: Renderizando campos de cartão');

        $total = WC()->cart->get_total('edit');
        if (is_a($total, 'WC_Price')) $total = floatval($total->get_amount());
        $total = floatval($total);

        $min_installment = floatval(get_option('wc_zoop_min_installment', 0));
        $installment_options = [];

        $interest_1 = floatval(get_option('wc_zoop_interest_1', 0));
        $total_1x = $total * (1 + $interest_1 / 100);
        $per_1x = $total_1x;

        $installment_options[1] = [
            'installment' => number_format($per_1x, 2, '.', ''),
            'total'       => number_format($total_1x, 2, '.', ''),
            'interest'    => number_format($interest_1, 1, '.', '')
        ];
        error_log("WC Letztech-payment: 1x → juros lido: {$interest_1}% | parcela: R$ " . $installment_options[1]['installment']);

        for ($i = 2; $i <= 12; $i++) {
            $interest = floatval(get_option("wc_zoop_interest_{$i}", 0));
            $total_with = $total * (1 + $interest / 100);
            $per_installment = $total_with / $i;

            if ($per_installment >= $min_installment || $min_installment <= 0) {
                $installment_options[$i] = [
                    'installment' => number_format($per_installment, 2, '.', ''),
                    'total'       => number_format($total_with, 2, '.', ''),
                    'interest'    => number_format($interest, 1, '.', '')
                ];
                error_log("WC Letztech-payment: {$i}x → juros lido: {$interest}% | parcela: R$ " . $installment_options[$i]['installment']);
            }
        }

        if (empty($installment_options)) {
            echo '<p>' . __('Nenhuma opção de parcelamento disponível.', 'wc-zoop-payments') . '</p>';
            return;
        }

        ?>
        <div id="zoop-credit-card-form">
            <p><?php echo esc_html($this->description); ?></p>
            <div class="form-row">
                <label for="card_holder_name"><?php _e('Nome do Titular', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="card_holder_name" name="card_holder_name" placeholder="João Silva" required>
            </div>
            <div class="form-row">
                <label for="card_number"><?php _e('Número do Cartão', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
            </div>
            <div class="form-row form-row-inline">
                <div class="form-col">
                    <label for="card_expiry_month"><?php _e('Mês', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="card_expiry_month" name="card_expiry_month" placeholder="MM" maxlength="2" required>
                </div>
                <div class="form-col">
                    <label for="card_expiry_year"><?php _e('Ano', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="card_expiry_year" name="card_expiry_year" placeholder="AAAA" maxlength="4" required>
                </div>
                <div class="form-col">
                    <label for="card_security_code"><?php _e('CVV', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="card_security_code" name="card_security_code" placeholder="123" maxlength="4" required>
                </div>
            </div>
            <div class="form-row">
                <label for="number_installments"><?php _e('Parcelas', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <select id="number_installments" name="number_installments" required>
                    <?php foreach ($installment_options as $num => $opt): ?>
                        <?php
                        $juros = floatval($opt['interest']);
                        if ($juros < 0.001) { 
                            $label = sprintf(__('%dx de R$ %s sem juros', 'wc-zoop-payments'), $num, $opt['installment']);
                        } else {
                            $label = sprintf(__('%dx de R$ %s (total R$ %s com %s%% juros)', 'wc-zoop-payments'), $num, $opt['installment'], $opt['total'], $opt['interest']);
                        }
                        ?>
                        <option value="<?php echo esc_attr($num); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="enderCEP"><?php _e('CEP', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="enderCEP" name="enderCEP" placeholder="12345-678" maxlength="9" required>
            </div>
        </div>
        <?php
    }

    public function process_payment($order_id)
    {
        error_log("WC Letztech-payment: Iniciando process_payment para pedido #$order_id");

        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice('Pedido não encontrado.', 'error');
            return;
        }

        $fields = ['card_holder_name', 'card_number', 'card_expiry_month', 'card_expiry_year', 'card_security_code', 'number_installments', 'enderCEP'];
        foreach ($fields as $f) {
            if (empty($_POST[$f])) {
                wc_add_notice("Preencha o campo: $f", 'error');
                return;
            }
        }

        $seller_id = get_option('wc_zoop_seller_id') ?: get_option('wc_letztech_seller_id');
        if (empty($seller_id)) {
            wc_add_notice('Erro interno: Seller ID não configurado.', 'error');
            return;
        }

        $total = floatval($order->get_total());
        $num_installments = intval($_POST['number_installments']);

        // AMOUNT EM REAIS (INTEIRO) - API LETZTECH NÃO USA CENTAVOS
        $interest_percent = floatval(get_option("wc_zoop_interest_{$num_installments}", 0));
        error_log("WC Letztech-payment: Aplicando juros de {$interest_percent}% para {$num_installments}x");

        $total_com_juros = $total * (1 + $interest_percent / 100);
        $total_com_juros = round($total_com_juros, 2);
        $amount_in_reais = (int) round($total_com_juros);

        error_log("WC Letztech-payment: Total original: R$ {$total} | Total com juros: R$ {$total_com_juros} | Amount enviado: {$amount_in_reais} (REAIS)");

        $payload = [
            'seller_id' => $seller_id,
            'amount' => $amount_in_reais,
            'description' => "Pedido WooCommerce #$order_id",
            'number_installments' => $num_installments,
            'enderCEP' => sanitize_text_field($_POST['enderCEP']),
            'card' => [
                'holder_name' => sanitize_text_field($_POST['card_holder_name']),
                'expiration_month' => str_pad(sanitize_text_field($_POST['card_expiry_month']), 2, '0', STR_PAD_LEFT),
                'expiration_year' => sanitize_text_field($_POST['card_expiry_year']),
                'card_number' => preg_replace('/\s+/', '', $_POST['card_number']),
                'security_code' => sanitize_text_field($_POST['card_security_code'])
            ],
            'three_d_secure' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'WooCommerce',
                'device' => [
                    'color_depth' => $_POST['device_color_depth'] ?? 24,
                    'java_enabled' => false,
                    'language' => $_POST['device_language'] ?? 'pt-BR',
                    'screen_height' => $_POST['device_screen_height'] ?? 1080,
                    'screen_width' => $_POST['device_screen_width'] ?? 1920,
                    'time_zone_offset' => $_POST['device_time_zone'] ?? -180
                ]
            ]
        ];

        $split_seller = get_option('wc_zoop_seller_id_split1');
        $split_perc = floatval(get_option('wc_zoop_percentage_split1', 0));
        if (!empty($split_seller) && $split_perc > 0 && $split_perc <= 100) {
            $payload['seller_id_split1'] = $split_seller;
            $payload['percentage_split1'] = $split_perc;
            error_log("WC Letztech-payment: Split ativado → seller: $split_seller | porcentagem: $split_perc%");
        }

        $payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // <<< JSON COMENTADO - DESCOMENTE SE PRECISAR VER NOVAMENTE >>>
        // error_log('WC Letztech-payment: JSON enviado → ' . $payload_json);
        // <<< FIM >>>

        error_log('WC Letztech-payment: AMOUNT CORRETO: ' . $amount_in_reais . ' | number_installments: ' . $num_installments);

        // Teste com URL alternativa da API Letztech
        // $response = wp_remote_post('http://186.249.36.174/api/transactions?teste=true&test=true', [
        //     'body' => $payload_json,
        //     'headers' => ['Content-Type' => 'application/json'],
        //     'timeout' => 45
        // ]);

        $response = wp_remote_post('http://186.249.36.174/api/transactions', [
            'body' => $payload_json,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            $msg = $response->get_error_message();
            wc_add_notice("Erro de conexão: $msg", 'error');
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        error_log("WC Letztech-payment: Resposta API (código $code): $body_raw");

        if ($code == 201 && !empty($body['idTransacao'])) {
            update_post_meta($order_id, '_letztech_transaction_id', $body['idTransacao']);
            // $order->update_status('completed', 'Pagamento aprovado via Letztech'); // Modo antigo
            
            // Verifica se deve marcar como processing ou completed
            $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

            $status = $completed_as_processing ? 'processing' : 'completed';

            $order->update_status($status, 'Pagamento aprovado via Letztech');
            // final do bloco

            $order->payment_complete($body['idTransacao']);
            $order->add_order_note("Transação Letztech: {$body['idTransacao']}");
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        } else {
            $error_msg = $body['error']['message'] ?? $body['message'] ?? 'Erro';

            $full_error = "Pagamento recusado: {$error_msg}";

            // Ainda mostra o JSON no erro (útil para debug)
            $pretty_json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      /*       $full_error .= '<br><br><strong>JSON enviado para a API:</strong><br><pre style="background:#f1f1f1;padding:15px;border:1px solid #ccc;overflow-x:auto;max-height:400px;">'
                         . htmlspecialchars($pretty_json) . '</pre>';
 */
            $order->update_status('failed', "Letztech: $error_msg");
            wc_add_notice($full_error, 'error');
        }
    }

    public function check_transaction_status($transaction_id)
    {
        error_log("WC Letztech-payment: Consultando status da transação $transaction_id");

        $response = wp_remote_get("http://186.249.36.174/api/transactions/{$transaction_id}", [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_site_url(),
                'Accept' => 'application/json'
            ],
            'timeout' => 60,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            error_log('WC Letztech-payment: Erro WP Remote GET: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        error_log("WC Letztech-payment: Resposta status (código $code): $body_raw");

        if (!in_array($code, [200, 201]) || !$body || !isset($body['status'])) {
            return false;
        }

        return [
            'status' => strtolower(sanitize_text_field($body['status'])),
            'amount' => isset($body['amount']) ? sanitize_text_field($body['amount']) : ''
        ];
    }

    public function update_order_status($order_id, $status = null)
    {
        $order = wc_get_order($order_id);
        if (!$order) return false;

        $transaction_id = $order->get_meta('_letztech_transaction_id');
        if (empty($transaction_id)) return false;

        if ($status === null) {
            $data = $this->check_transaction_status($transaction_id);
            if (!$data) return false;
            $status = $data['status'];
        }

        switch (strtolower($status)) {
            case 'succeeded':
            case 'completed':
                if ($order->get_status() !== 'completed') {
                    // $order->update_status('completed', 'Pagamento aprovado via Letztech');
                    // Verifica se deve marcar como processing ou completed
                    $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

                    $status = $completed_as_processing ? 'processing' : 'completed';

                    $order->update_status($status, 'Pagamento aprovado via Letztech');
                    // final do bloco
                    $order->payment_complete($transaction_id);
                    wc_reduce_stock_levels($order_id);
                }
                return true;

            case 'failed':
            case 'request failed':
                $order->update_status('failed', 'Pagamento recusado via Letztech');
                return true;

            case 'pending':
                $order->update_status('pending', 'Pagamento pendente via Letztech');
                return true;

            default:
                return false;
        }
    }
}
?>