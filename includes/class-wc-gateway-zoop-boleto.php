<?php
if (!defined('ABSPATH')) {
    error_log('WC Letztech-payment Boleto: ABSPATH não definido, encerrando');
    exit;
}

class WC_Gateway_Zoop_Boleto extends WC_Payment_Gateway
{
    public function __construct()
    {
        error_log('WC Letztech-payment Boleto: Entrando no construtor');
        $this->id = 'zoop_boleto';
        $this->method_title = __('Boleto Letztech-payment', 'wc-zoop-payments');
        $this->method_description = __('Pague com Boleto Bancário via API Letztech-payment', 'wc-zoop-payments');
        $this->title = $this->get_option('title', __('Boleto', 'wc-zoop-payments'));
        $this->has_fields = true;
        $this->supports = ['products'];

        error_log('WC Letztech-payment Boleto: ID do gateway: ' . $this->id);
        error_log('WC Letztech-payment Boleto: Título: ' . $this->title);
        error_log('WC Letztech-payment Boleto: Possui campos: ' . ($this->has_fields ? 'true' : 'false'));

        $this->init_form_fields();
        error_log('WC Letztech-payment Boleto: Campos de formulário inicializados');

        $this->init_settings();
        error_log('WC Letztech-payment Boleto: Configurações inicializadas');

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->description = $this->get_option('description', __('Pague com Boleto Bancário via nossa API Letztech-payment segura', 'wc-zoop-payments'));
        error_log('WC Letztech-payment Boleto: Habilitado: ' . $this->enabled);
        error_log('WC Letztech-payment Boleto: Descrição: ' . $this->description);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_footer', [$this, 'add_payment_scripts']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'display_boleto_details']);
        error_log('WC Letztech-payment Boleto: Ações registradas');
    }

    public function init_form_fields()
    {
        error_log('WC Letztech-payment Boleto: Inicializando campos de formulário');
        $this->form_fields = [
            'enabled' => [
                'title' => __('Ativar/Desativar', 'wc-zoop-payments'),
                'type' => 'checkbox',
                'label' => __('Ativar Boleto Zoop', 'wc-zoop-payments'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Título', 'wc-zoop-payments'),
                'type' => 'text',
                'description' => __('Título exibido no checkout', 'wc-zoop-payments'),
                'default' => __('Boleto', 'wc-zoop-payments')
            ],
            'description' => [
                'title' => __('Descrição', 'wc-zoop-payments'),
                'type' => 'textarea',
                'description' => __('Descrição exibida no checkout', 'wc-zoop-payments'),
                'default' => __('Pague com Boleto Bancário via nossa API Zoop segura', 'wc-zoop-payments')
            ]
        ];
        error_log('WC Letztech-payment Boleto: Campos de formulário definidos: ' . print_r($this->form_fields, true));
    }

    public function add_payment_scripts()
    {
        error_log('WC Letztech-payment Boleto: Verificando se está na página de checkout');
        error_log('WC Letztech-payment Boleto: Resultado de is_checkout(): ' . (is_checkout() ? 'true' : 'false'));
        if (!is_checkout()) {
            error_log('WC Letztech-payment Boleto: Não está na página de checkout, ignorando scripts');
            return;
        }
        error_log('WC Letztech-payment Boleto: Adicionando scripts ao checkout');
        ?>
        <style>
            #zoop-boleto-form .form-row {
                margin-bottom: 15px;
            }
            #zoop-boleto-form input,
            #zoop-boleto-form select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }
            #zoop-boleto-form .form-row-inline {
                display: flex;
                gap: 10px;
            }
            #zoop-boleto-form .form-col {
                flex: 1;
            }
            #zoop-boleto-form label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
        </style>
        <script>
            console.log('WC Letztech-payment Boleto: JavaScript carregado na página de checkout');
            jQuery(document).ready(function ($) {
                console.log('WC Letztech-payment Boleto: jQuery pronto, inicializando manipuladores de formulário');

                const cep = $('#billing_postcode');
                if (cep.length) {
                    console.log('WC Letztech-payment Boleto: Campo de CEP encontrado');
                    cep.on('input', function () {
                        let value = $(this).val().replace(/\D/g, '');
                        if (value.length > 5) {
                            value = value.slice(0, 5) + '-' + value.slice(5, 8);
                        }
                        $(this).val(value);
                        console.log('WC Letztech-payment Boleto: Entrada do CEP: ' + value);
                    });
                } else {
                    console.log('WC Letztech-payment Boleto: Campo de CEP NÃO encontrado');
                }

                const cpf = $('#customer_cpf');
                if (cpf.length) {
                    console.log('WC Letztech-payment Boleto: Campo de CPF encontrado');
                    cpf.on('input', function () {
                        let value = $(this).val().replace(/\D/g, '');
                        if (value.length > 11) value = value.slice(0, 11);
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                        $(this).val(value);
                        console.log('WC Letztech-payment Boleto: Entrada do CPF: ' + value);
                    });
                } else {
                    console.log('WC Letztech-payment Boleto: Campo de CPF NÃO encontrado');
                }

                const phone = $('#billing_phone');
                if (phone.length) {
                    console.log('WC Letztech-payment Boleto: Campo de telefone encontrado');
                    phone.on('input', function () {
                        let value = $(this).val().replace(/\D/g, '');
                        if (value.length > 11) value = value.slice(0, 11);
                        $(this).val(value);
                        console.log('WC Letztech-payment Boleto: Entrada do telefone: ' + value);
                    });
                } else {
                    console.log('WC Letztech-payment Boleto: Campo de telefone NÃO encontrado');
                }

                const birthdate = $('#customer_birthdate');
                if (birthdate.length) {
                    console.log('WC Letztech-payment Boleto: Campo de data de nascimento encontrado');
                    birthdate.on('input', function () {
                        let value = $(this).val().replace(/\D/g, '');
                        if (value.length > 8) value = value.slice(0, 8);
                        if (value.length > 4) value = value.slice(0, 4) + '-' + value.slice(4);
                        if (value.length > 7) value = value.slice(0, 7) + '-' + value.slice(7);
                        $(this).val(value);
                        console.log('WC Letztech-payment Boleto: Entrada da data de nascimento: ' + value);
                    });
                } else {
                    console.log('WC Letztech-payment Boleto: Campo de data de nascimento NÃO encontrado');
                }
            });
        </script>
        <?php
    }

    public function payment_fields()
    {
        error_log('WC Letztech-payment Boleto: Renderizando campos de pagamento');
        error_log('WC Letztech-payment Boleto: ID da página atual: ' . get_the_ID());
        error_log('WC Letztech-payment Boleto: É página de checkout: ' . (is_checkout() ? 'true' : 'false'));

  
        $brazilian_states = [
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins'
        ];
        ?>
        <div id="zoop-boleto-form">
            <p><?php echo esc_html($this->description); ?></p>
            <div class="form-row">
                <label for="billing_first_name"><?php _e('Nome', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_first_name" name="billing_first_name" placeholder="Nome" required>
            </div>
            <div class="form-row">
                <label for="billing_last_name"><?php _e('Sobrenome', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_last_name" name="billing_last_name" placeholder="Sobrenome" required>
            </div>
            <div class="form-row">
                <label for="customer_cpf"><?php _e('CPF do Titular', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="customer_cpf" name="customer_cpf" placeholder="123.456.789-00" maxlength="14" required>
            </div>
            <div class="form-row">
                <label for="customer_birthdate"><?php _e('Data de Nascimento', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="customer_birthdate" name="customer_birthdate" placeholder="YYYY-MM-DD" maxlength="10" required>
            </div>
            <div class="form-row">
                <label for="billing_email"><?php _e('E-mail', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="email" id="billing_email" name="billing_email" placeholder="contato@exemplo.com" required>
            </div>
            <div class="form-row">
                <label for="billing_phone"><?php _e('Telefone', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_phone" name="billing_phone" placeholder="51988888888" maxlength="11" required>
            </div>
            <div class="form-row">
                <label for="billing_address_1"><?php _e('Endereço', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_address_1" name="billing_address_1" placeholder="Rua" required>
            </div>
            <div class="form-row">
                <label for="billing_address_2"><?php _e('Número', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_address_2" name="billing_address_2" placeholder="Número" required>
            </div>
            <div class="form-row">
                <label for="billing_address_3"><?php _e('Complemento', 'wc-zoop-payments'); ?></label>
                <input type="text" id="billing_address_3" name="billing_address_3" placeholder="Complemento (opcional)">
            </div>
            <div class="form-row">
                <label for="billing_neighborhood"><?php _e('Bairro', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_neighborhood" name="billing_neighborhood" placeholder="Bairro" required>
            </div>
            <div class="form-row">
                <label for="billing_state"><?php _e('Estado', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <select id="billing_state" name="billing_state" required>
                    <option value=""><?php _e('Selecione um estado', 'wc-zoop-payments'); ?></option>
                    <?php foreach ($brazilian_states as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="billing_city"><?php _e('Cidade', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_city" name="billing_city" placeholder="Cidade" required>
            </div>
            <div class="form-row">
                <label for="billing_postcode"><?php _e('CEP', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="billing_postcode" name="billing_postcode" placeholder="12345-678" maxlength="9" required>
            </div>
        </div>
        <?php
        error_log('WC Letztech-payment Boleto: Campos de pagamento renderizados');
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

    $seller_info = wc_zoop_get_seller_id_for_order($order);
    $seller_id   = $seller_info['seller_id'];
    if (empty($seller_id)) {
        error_log('WC Letztech-payment Boleto: Seller ID não configurado');
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    $total = $order->get_total();
    $amount = (int)($total * 100); // Em centavos
    $expiration_date = date('Y-m-d', strtotime('+7 days'));

    $payload = [
        'amount' => $amount,
        'description' => 'Compra para o pedido #' . $order_id,
        'expiration_date' => $expiration_date,
        'seller_id' => sanitize_text_field($seller_id),
        'buyer' => [
            'first_name' => sanitize_text_field($_POST['billing_first_name']),
            'last_name' => sanitize_text_field($_POST['billing_last_name']),
            'email' => sanitize_email($_POST['billing_email']),
            'phone_number' => preg_replace('/\D/', '', $_POST['billing_phone']),
            'taxpayer_id' => preg_replace('/\D/', '', $_POST['customer_cpf']),
            'birthdate' => sanitize_text_field($_POST['customer_birthdate']),
            'address' => [
                'line1' => sanitize_text_field($_POST['billing_address_1']),
                'line2' => sanitize_text_field($_POST['billing_address_2']),
                'line3' => sanitize_text_field($_POST['billing_address_3'] ?? ''),
                'neighborhood' => sanitize_text_field($_POST['billing_neighborhood']),
                'city' => sanitize_text_field($_POST['billing_city']),
                'state' => sanitize_text_field($_POST['billing_state']),
                'postal_code' => preg_replace('/\D/', '', $_POST['billing_postcode'])
            ]
        ]
    ];


    $split_seller     = $seller_info['seller_id_split1'];
    $split_percentage = floatval($seller_info['percentage_split1']);

    if (!empty($split_seller) && $split_percentage > 0 && $split_percentage <= 100) {
        $payload['seller_id_split1']  = sanitize_text_field($split_seller);
        $payload['percentage_split1'] = $split_percentage;
        error_log("WC Letztech Boleto: Split ativado → seller_id_split1: {$split_seller}, percentage_split1: {$split_percentage}%");
    } else {
        $payload['seller_id_split1']  = '';
        $payload['percentage_split1'] = 0.0;
        error_log('WC Letztech Boleto: Split desativado');
    }
    error_log('WC Letztech-payment Boleto: Payload final: ' . json_encode($payload, JSON_PRETTY_PRINT));

    $response = wp_remote_post('http://186.249.36.174/api/transactions/boleto', [
        'body' => json_encode($payload),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        $error = $response->get_error_message();
        error_log('WC Letztech-payment Boleto: Erro WP: ' . $error);
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('WC Letztech-payment Boleto: JSON inválido na resposta');
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }

    if ($code == 201 && isset($body['idTransacao'])) {
        $transaction_id = sanitize_text_field($body['idTransacao']);
        $barcode = $body['barcode'] ?? '';
        $boleto_url = $body['url'] ?? '';
        $document_number = $body['document_number'] ?? '';
        $boleto_expiration = $body['expiration_date'] ?? $expiration_date;
        $boleto_amount = floatval($body['amount'] ?? $amount) / 100;

        // Salvar metadados
        $order->update_meta_data('_letztech_transaction_id', $transaction_id);
        $order->update_meta_data('_letztech_boleto_barcode', sanitize_text_field($barcode));
        $order->update_meta_data('_letztech_boleto_url', esc_url_raw($boleto_url));
        $order->update_meta_data('_letztech_boleto_document_number', sanitize_text_field($document_number));
        $order->update_meta_data('_letztech_boleto_expiration', sanitize_text_field($boleto_expiration));
        $order->update_meta_data('_letztech_boleto_amount', $boleto_amount);
        $order->update_meta_data('_letztech_resolved_seller_id', $seller_id);
        $order->set_transaction_id($transaction_id);
        $order->update_status('on-hold', 'Aguardando pagamento do boleto');
        $sku_note = !empty($seller_info['sku_used']) ? " (SKU: {$seller_info['sku_used']})" : '';
        $order->add_order_note("Boleto gerado. ID: {$transaction_id}, Valor: R$ " . number_format($boleto_amount, 2, ',', '.'));
        $order->add_order_note("Pagamento processado com Seller ID: {$seller_id}{$sku_note}");
        $order->save();

        // Armazenar na sessão para thank you page
        WC()->session->set('zoop_boleto_details', [
            'barcode' => $barcode,
            'url' => $boleto_url,
            'document_number' => $document_number,
            'expiration_date' => $boleto_expiration,
            'amount' => $boleto_amount,
            'idTransacao' => $transaction_id
        ]);

        wc_add_notice(__('Boleto gerado com sucesso!', 'wc-zoop-payments'), 'success');

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    } else {
        $error = $body['error']['message'] ?? (isset($body['errors']) ? implode(', ', $body['errors']) : 'Erro desconhecido');
        error_log('WC Letztech-payment Boleto: Falha: ' . $error);
        $order->update_status('failed', 'Boleto recusado');
        $order->add_order_note('Boleto recusado: ' . $error);
        wc_add_notice(__('Pagamento falhou:', 'wc-zoop-payments') . ' ' . esc_html($error), 'error');
        return;
    }
}

public function display_boleto_details($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== $this->id) {
        error_log('WC Letztech-payment Boleto: Pedido inválido ou método de pagamento incorreto para #' . $order_id);
        return;
    }

    $boleto_details = WC()->session->get('zoop_boleto_details');
    if (!$boleto_details) {
        $boleto_details = [
            'barcode' => $order->get_meta('_letztech_boleto_barcode'),
            'url' => $order->get_meta('_letztech_boleto_url'),
            'document_number' => $order->get_meta('_letztech_boleto_document_number'),
            'expiration_date' => $order->get_meta('_letztech_boleto_expiration'),
            'amount' => $order->get_meta('_letztech_boleto_amount'),
            'idTransacao' => $order->get_meta('_letztech_transaction_id')
        ];
    }

    if (empty($boleto_details['barcode']) && empty($boleto_details['url'])) {
        error_log('WC Letztech-payment Boleto: Nenhum detalhe de boleto disponível para o pedido #' . $order_id);
        return;
    }




    $amount = 0.0;
    if (is_numeric($boleto_details['amount'])) {
        $amount = floatval($boleto_details['amount']);
    } elseif (is_string($boleto_details['amount']) && !empty($boleto_details['amount'])) {
        $clean_amount = str_replace(['R$', ',', ' '], ['', '.', ''], trim($boleto_details['amount']));
        if (is_numeric($clean_amount)) {
            $amount = floatval($clean_amount);
        } else {
            error_log('WC Letztech-payment Boleto: Invalid amount format: ' . $boleto_details['amount']);
        }
    }


    if ($amount == 0.0) {
        $amount = floatval($order->get_total());
        error_log('WC Letztech-payment Boleto: Falling back to order total: ' . $amount);
    }

    $formatted_amount = 'R$ ' . number_format($amount, 2, ',', '.');

    $expiration_date = $boleto_details['expiration_date'];
    if (strpos($expiration_date, 'T') !== false) {
        $date_obj = DateTime::createFromFormat('Y-m-d\TH:i:sP', $expiration_date);
        $formatted_expiration = $date_obj ? $date_obj->format('d/m/Y') : $expiration_date;
    } else {
        $formatted_expiration = $expiration_date;
    }

    error_log('WC Letztech-payment Boleto: Exibindo detalhes do boleto na página de agradecimento para o pedido #' . $order_id);


    $boleto_data = [
        'barcode' => sanitize_text_field($boleto_details['barcode']),
        'idTransacao' => sanitize_text_field($boleto_details['idTransacao']),
        'i18n' => [
            'error_barcode' => __('Erro ao gerar o código de barras.', 'wc-zoop-payments'),
            'status_pending' => __('Aguardando pagamento.', 'wc-zoop-payments'),
            'status_error' => __('Erro ao verificar o status.', 'wc-zoop-payments'),
            'copy_success' => __('Código de barras copiado com sucesso!', 'wc-zoop-payments'),
            'copy_error' => __('Erro ao copiar o código de barras.', 'wc-zoop-payments')
        ]
    ];
    wp_localize_script('zoop-boleto-script', 'zoopBoletoData', $boleto_data);


    wp_enqueue_script('jsbarcode', 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js', [], '3.11.5', true);
    wp_enqueue_script('zoop-boleto-script', plugins_url('/js/zoop-boleto.js', __FILE__), ['jquery', 'jsbarcode'], '1.0', true);

    ?>
    <div class="zoop-boleto-details">
        <h2><?php _e('Detalhes do Boleto', 'wc-zoop-payments'); ?></h2>
        <div class="boleto-info">
            <div class="boleto-field">
                <strong><?php _e('Código de Barras:', 'wc-zoop-payments'); ?></strong>
                <div class="barcode-wrapper">
                    <canvas id="zoop-boleto-barcode"></canvas>
                    <textarea id="zoop-boleto-code" readonly><?php echo esc_html($boleto_details['barcode']); ?></textarea>
                  <!--   <button id="zoop-boleto-copy" class="button"><?php _e('Copiar Código', 'wc-zoop-payments'); ?></button> -->
                </div>
            </div>
            <div class="boleto-field">
                <strong><?php _e('Número do Documento:', 'wc-zoop-payments'); ?></strong>
                <span><?php echo esc_html($boleto_details['document_number']); ?></span>
            </div>
            <div class="boleto-field">
                <strong><?php _e('Valor:', 'wc-zoop-payments'); ?></strong>
                <span><?php echo esc_html($formatted_amount); ?></span>
            </div>
            <div class="boleto-field">
                <strong><?php _e('Data de Vencimento:', 'wc-zoop-payments'); ?></strong>
                <span><?php echo esc_html($formatted_expiration); ?></span>
            </div>
            <?php if (!empty($boleto_details['url'])) : ?>
                <div class="boleto-field">
                    <strong><?php _e('Link do Boleto:', 'wc-zoop-payments'); ?></strong>
                    <a href="<?php echo esc_url($boleto_details['url']); ?>" target="_blank" class="boleto-link"><?php _e('Para visualizar o boleto clique aqui!', 'wc-zoop-payments'); ?></a>
                </div>
            <?php endif; ?>
            <div class="boleto-field">
                <strong><?php _e('Status do Pagamento:', 'wc-zoop-payments'); ?></strong>
                <span id="zoop-boleto-status"><?php _e('Aguardando pagamento.', 'wc-zoop-payments'); ?></span>
            </div>
        </div>
  <!--       <p class="boleto-instructions"><?php _e('Use o código de barras ou o link acima para realizar o pagamento do boleto. Você também receberá estas informações por e-mail.', 'wc-zoop-payments'); ?></p> -->
    </div>

    <style>
        .zoop-boleto-details {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .zoop-boleto-details h2 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
        }

        .boleto-info {
            display: grid;
            gap: 15px;
        }

        .boleto-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .boleto-field strong {
            font-weight: 600;
            color: #333;
        }

        .boleto-field span, .boleto-field a.boleto-link {
            color: #555;
        }

        .barcode-wrapper {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        #zoop-boleto-barcode {
            max-width: 100%;
            height: 60px;
        }

        #zoop-boleto-code {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            resize: none;
            background: #f9f9f9;
        }

        #zoop-boleto-copy {
            padding: 8px 16px;
            background: #0071a1;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }

        #zoop-boleto-copy:hover {
            background: #005f87;
        }

        .boleto-link {
            color: #0071a1;
            text-decoration: none;
        }

        .boleto-link:hover {
            text-decoration: underline;
        }

        .boleto-instructions {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        #zoop-boleto-status {
            font-weight: 600;
        }

        #zoop-boleto-status.succeeded {
            color: #28a745;
        }

        #zoop-boleto-status.failed {
            color: #dc3545;
        }

        #zoop-boleto-status.pending {
            color: #666;
        }

        @media (max-width: 600px) {
            .zoop-boleto-details {
                margin: 10px;
                padding: 15px;
            }

            .zoop-boleto-details h2 {
                font-size: 1.3em;
            }

            #zoop-boleto-code {
                font-size: 12px;
            }
        }
    </style>
    <?php
    WC()->session->set('zoop_boleto_details', null);
}
    public function check_transaction_status($transaction_id)
    {
        error_log('WC Letztech-payment Boleto: Iniciando consulta de status da transação: ' . $transaction_id);

        $response = wp_remote_get("http://186.249.36.174/api/transactions/boleto/{$transaction_id}", [
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
            error_log('WC Letztech-payment Boleto: Erro ao consultar status da transação: ' . $error_message);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('WC Letztech-payment Boleto: Resposta da API - Código: ' . $response_code . ', Corpo: ' . $response_body);

        if (!in_array($response_code, [200, 201])) {
            error_log('WC Letztech-payment Boleto: Erro na consulta da API, código: ' . $response_code);
            return false;
        }

        $body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($body['status'])) {
            error_log('WC Letztech-payment Boleto: Erro ao decodificar JSON ou status ausente: ' . json_last_error_msg());
            return false;
        }

        error_log('WC Letztech-payment Boleto: Status da transação retornado: ' . $body['status']);
        return [
            'status' => sanitize_text_field(strtolower($body['status'])),
            'amount' => isset($body['amount']) ? sanitize_text_field($body['amount']) : ''
        ];
    }

    public function update_order_status($order_id, $status = null)
    {
        error_log('WC Letztech-payment Boleto: Iniciando update_order_status para pedido #' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' não encontrado');
            return false;
        }
        error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' carregado. Método: ' . $order->get_payment_method() . ', Status: ' . $order->get_status());

        $transaction_id = $order->get_meta('_letztech_transaction_id');
        if (empty($transaction_id)) {
            error_log('WC Letztech-payment Boleto: ID da transação não encontrado para o pedido #' . $order_id);
            return false;
        }
        error_log('WC Letztech-payment Boleto: ID da transação encontrado: ' . $transaction_id);

        if ($status === null) {
            $transaction_data = $this->check_transaction_status($transaction_id);
            if (!$transaction_data) {
                error_log('WC Letztech-payment Boleto: Falha ao consultar status da transação para o pedido #' . $order_id);
                return false;
            }
            $status = $transaction_data['status'];
            error_log('WC Letztech-payment Boleto: Status retornado pela API: ' . $status);
        }

        error_log('WC Letztech-payment Boleto: Processando status da transação para o pedido #' . $order_id . ': ' . $status);

        switch (strtolower($status)) {
            case 'succeeded':
                if (!in_array($order->get_status(), ['completed', 'processing'])) {
                    // $order->update_status('completed', __('Pagamento aprovado via Letztech-payment.', 'wc-zoop-payments'));
                    // Verifica se deve marcar como processing ou completed
                    $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

                    $status = $completed_as_processing ? 'processing' : 'completed';

                    $order->update_status($status, __('Pagamento aprovado via Letztech-payment.', 'wc-zoop-payments'));
                    // final do bloco
                    
                    $order->payment_complete($transaction_id);
                    wc_reduce_stock_levels($order_id);
                    $order->add_order_note('Status atualizado para Processando. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' atualizado para Processando');
                    return true;
                } else {
                    error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' já está em Processando ou Concluído');
                    return true;
                }
            case 'Request Failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', __('Pagamento recusado via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Falhado. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' atualizado para Falhado');
                    return true;
                }
                break;
            case 'failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', __('Pagamento recusado via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Falhado. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' atualizado para Falhado');
                    return true;
                }
                break;
            case 'pending':
                if ($order->get_status() !== 'on-hold') {
                    $order->update_status('on-hold', __('Pagamento pendente via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status mantido como Pendente. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Boleto: Pedido #' . $order_id . ' mantido como Pendente');
                    return true;
                }
                break;
            default:
                error_log('WC Letztech-payment Boleto: Status desconhecido para o pedido #' . $order_id . ': ' . $status);
                return false;
        }

        return true;
    }
}
?>