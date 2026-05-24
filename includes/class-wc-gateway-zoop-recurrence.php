<?php
if (!defined('ABSPATH')) {
    error_log('WC Letztech-payment Recorrência: ABSPATH não carregado, encerrando');
    exit;
}

class WC_Gateway_Zoop_Recurrence extends WC_Payment_Gateway {
    public function __construct() {
        error_log('WC Letztech-payment Recorrência: Entrando no construtor');
        $this->id = 'zoop_recurrence';
        $this->method_title = __('Recorrência Letztech-payment', 'wc-zoop-payments');
        $this->method_description = __('Pague com cartão de crédito recorrente via API Letztech-payment', 'wc-zoop-payments');
        $this->title = $this->get_option('title', __('Pagamento Recorrente', 'wc-zoop-payments'));
        $this->has_fields = true;
        $this->supports = ['products', 'subscriptions'];

        error_log('WC Letztech-payment Recorrência: ID do gateway: ' . $this->id);
        error_log('WC Letztech-payment Recorrência: Título: ' . $this->title);

        $this->init_form_fields();
        error_log('WC Letztech-payment Recorrência: Campos de formulário inicializados');

        $this->init_settings();
        error_log('WC Letztech-payment Recorrência: Configurações inicializadas');

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->description = $this->get_option('description', __('Configure um pagamento recorrente com cartão de crédito via API Letztech segura', 'wc-zoop-payments'));
        error_log('WC Letztech-payment Recorrência: Habilitado: ' . $this->enabled);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_footer', [$this, 'add_payment_scripts']);
        error_log('WC Letztech-payment Recorrência: Ações registradas');
    }

    public function is_available() {
        $is_available = parent::is_available();
        error_log('WC Letztech-payment Recorrência: Gateway disponível? ' . ($is_available ? 'Sim' : 'Não'));
        return $is_available;
    }

    public function init_form_fields() {
        error_log('WC Letztech-payment Recorrência: Inicializando campos de formulário');
        $this->form_fields = [
            'enabled' => [
                'title' => __('Ativar/Desativar', 'wc-zoop-payments'),
                'type' => 'checkbox',
                'label' => __('Ativar Recorrência Zoop', 'wc-zoop-payments'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Título', 'wc-zoop-payments'),
                'type' => 'text',
                'description' => __('Título exibido no checkout', 'wc-zoop-payments'),
                'default' => __('Pagamento Recorrente', 'wc-zoop-payments')
            ],
            'description' => [
                'title' => __('Descrição', 'wc-zoop-payments'),
                'type' => 'textarea',
                'description' => __('Descrição exibida no checkout', 'wc-zoop-payments'),
                'default' => __('Configure um pagamento recorrente com cartão de crédito via API Zoop segura', 'wc-zoop-payments')
            ]
        ];
        error_log('WC Letztech-payment Recorrência: Campos de formulário definidos');
    }

    public function add_payment_scripts() {
        error_log('WC Letztech-payment Recorrência: Verificando se está na página de checkout');
        if (!is_checkout() || WC()->session->get('chosen_payment_method') !== $this->id) {
            error_log('WC Letztech-payment Recorrência: Não está na página de checkout ou gateway não selecionado, ignorando scripts');
            return;
        }
        error_log('WC Letztech-payment Recorrência: Adicionando scripts ao checkout');
        ?>
        <style>
            #zoop-recurrence-form .form-row {
                margin-bottom: 15px;
            }
            #zoop-recurrence-form input, #zoop-recurrence-form select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }
            #zoop-recurrence-form .form-row-inline {
                display: flex;
                gap: 10px;
            }
            #zoop-recurrence-form .form-col {
                flex: 1;
            }
            #zoop-recurrence-form label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
        </style>
        <script>
            console.log('WC Letztech-payment Recorrência: JavaScript carregado na página de checkout');
            jQuery(document).ready(function($) {
                console.log('WC Letztech-payment Recorrência: jQuery pronto, inicializando manipuladores');
                const cardNumber = $('#card_number_recurrence');
                const expiryMonth = $('#card_expiry_month_recurrence');
                const expiryYear = $('#card_expiry_year_recurrence');
                const securityCode = $('#card_security_code_recurrence');
                const cep = $('#enderCEP_recurrence');
                const cpf = $('#taxpayer_id_recurrence');
                const phone = $('#phone_number_recurrence');
                const birthdate = $('#birthdate_recurrence');
                const dueDate = $('#due_date_recurrence');
                const expirationDate = $('#expiration_date_recurrence');
                const planName = $('#plan_name_recurrence');

                if (cardNumber.length) {
                    console.log('WC Letztech-payment Recorrência: Campo de número do cartão encontrado');
                    cardNumber.on('input', function() {
                        let value = $(this).val().replace(/\D/g, '');
                        value = value.replace(/(\d{4})/g, '$1 ').trim();
                        $(this).val(value);
                    });
                } else {
                    console.log('WC Letztech-payment Recorrência: Campo de número do cartão NÃO encontrado');
                }

                expiryMonth.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 2) value = value.slice(0, 2);
                    $(this).val(value);
                });

                expiryYear.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 4) value = value.slice(0, 4);
                    $(this).val(value);
                });

                securityCode.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 4) value = value.slice(0, 4);
                    $(this).val(value);
                });

                cep.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 5) {
                        value = value.slice(0, 5) + '-' + value.slice(5, 8);
                    }
                    $(this).val(value);
                });

                cpf.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 11) value = value.slice(0, 11);
                    if (value.length === 11) {
                        value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                    }
                    $(this).val(value);
                });

                phone.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 11) value = value.slice(0, 11);
                    if (value.length >= 10) {
                        value = value.replace(/(\d{2})(\d{4,5})(\d{4})/, '($1) $2-$3');
                    }
                    $(this).val(value);
                });

                birthdate.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 8) value = value.slice(0, 8);
                    if (value.length === 8) {
                        value = value.replace(/(\d{2})(\d{2})(\d{4})/, '$1/$2/$3');
                    }
                    $(this).val(value);
                });

                dueDate.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 8) value = value.slice(0, 8);
                    if (value.length === 8) {
                        value = value.replace(/(\d{2})(\d{2})(\d{4})/, '$1/$2/$3');
                    }
                    $(this).val(value);
                });

                expirationDate.on('input', function() {
                    let value = $(this).val().replace(/\D/g, '');
                    if (value.length > 12) value = value.slice(0, 12);
                    if (value.length === 12) {
                        value = value.replace(/(\d{2})(\d{2})(\d{4})(\d{2})(\d{2})/, '$1/$2/$3 $4:$5');
                    }
                    $(this).val(value);
                });

                console.log('WC Letztech-payment Recorrência: Adicionando campos ocultos de dispositivo');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_color_depth_recurrence',
                    value: screen.colorDepth || 24
                }).appendTo('#zoop-recurrence-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_language_recurrence',
                    value: navigator.language || 'pt-BR'
                }).appendTo('#zoop-recurrence-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_screen_height_recurrence',
                    value: screen.height || 1080
                }).appendTo('#zoop-recurrence-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_screen_width_recurrence',
                    value: screen.width || 1920
                }).appendTo('#zoop-recurrence-form');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'device_time_zone_recurrence',
                    value: new Date().getTimezoneOffset()
                }).appendTo('#zoop-recurrence-form');
                console.log('WC Letztech-payment Recorrência: Campos ocultos de dispositivo adicionados');
            });
        </script>
        <?php
    }

    public function payment_fields() {
        error_log('WC Letztech-payment Recorrência: Renderizando campos de pagamento');
        ?>
        <div id="zoop-recurrence-form">
            <p><?php echo esc_html($this->description); ?></p>
            <h4><?php _e('Informações do Cartão', 'wc-zoop-payments'); ?></h4>
            <div class="form-row">
                <label for="card_holder_name_recurrence"><?php _e('Nome do Titular do Cartão', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="card_holder_name_recurrence" name="card_holder_name_recurrence" placeholder="João Silva" required>
            </div>
            <div class="form-row">
                <label for="card_number_recurrence"><?php _e('Número do Cartão', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="card_number_recurrence" name="card_number_recurrence" placeholder="1234 5678 9012 3456" maxlength="19" required>
            </div>
            <div class="form-row form-row-inline">
                <div class="form-col">
                    <label for="card_expiry_month_recurrence"><?php _e('Mês de Expiração', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="card_expiry_month_recurrence" name="card_expiry_month_recurrence" placeholder="MM" maxlength="2" required>
                </div>
                <div class="form-col">
                    <label for="card_expiry_year_recurrence"><?php _e('Ano de Expiração', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="card_expiry_year_recurrence" name="card_expiry_year_recurrence" placeholder="AAAA" maxlength="4" required>
                </div>
                <div class="form-col">
                    <label for="card_security_code_recurrence"><?php _e('CVV', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="card_security_code_recurrence" name="card_security_code_recurrence" placeholder="123" maxlength="4" required>
                </div>
            </div>
            <h4><?php _e('Informações do Comprador', 'wc-zoop-payments'); ?></h4>
            <div class="form-row form-row-inline">
                <div class="form-col">
                    <label for="first_name_recurrence"><?php _e('Nome', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="first_name_recurrence" name="first_name_recurrence" placeholder="Fulano" required>
                </div>
                <div class="form-col">
                    <label for="last_name_recurrence"><?php _e('Sobrenome', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="last_name_recurrence" name="last_name_recurrence" placeholder="Ciclano" required>
                </div>
            </div>
            <div class="form-row">
                <label for="email_recurrence"><?php _e('E-mail', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="email" id="email_recurrence" name="email_recurrence" placeholder="fulano@ciclano.com" required>
            </div>
            <div class="form-row form-row-inline">
                <div class="form-col">
                    <label for="phone_number_recurrence"><?php _e('Telefone', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="phone_number_recurrence" name="phone_number_recurrence" placeholder="(51) 99999-9999" maxlength="15" required>
                </div>
                <div class="form-col">
                    <label for="taxpayer_id_recurrence"><?php _e('CPF', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="taxpayer_id_recurrence" name="taxpayer_id_recurrence" placeholder="999.999.999-99" maxlength="14" required>
                </div>
            </div>
            <div class="form-row">
                <label for="birthdate_recurrence"><?php _e('Data de Nascimento', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="birthdate_recurrence" name="birthdate_recurrence" placeholder="DD/MM/AAAA" maxlength="10" required>
            </div>
            <h4><?php _e('Endereço', 'wc-zoop-payments'); ?></h4>
            <div class="form-row">
                <label for="enderCEP_recurrence"><?php _e('CEP', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="enderCEP_recurrence" name="enderCEP_recurrence" placeholder="99999-999" maxlength="9" required>
            </div>
            <div class="form-row">
                <label for="address_line1_recurrence"><?php _e('Endereço', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="address_line1_recurrence" name="address_line1_recurrence" placeholder="Rua Exemplo, 123" required>
            </div>
            <div class="form-row">
                <label for="address_line2_recurrence"><?php _e('Complemento', 'wc-zoop-payments'); ?></label>
                <input type="text" id="address_line2_recurrence" name="address_line2_recurrence" placeholder="Apto 999">
            </div>
            <div class="form-row">
                <label for="address_line3_recurrence"><?php _e('Referência', 'wc-zoop-payments'); ?></label>
                <input type="text" id="address_line3_recurrence" name="address_line3_recurrence" placeholder="Próximo ao mercado">
            </div>
            <div class="form-row form-row-inline">
                <div class="form-col">
                    <label for="neighborhood_recurrence"><?php _e('Bairro', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="neighborhood_recurrence" name="neighborhood_recurrence" placeholder="Centro" required>
                </div>
                <div class="form-col">
                    <label for="city_recurrence"><?php _e('Cidade', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <input type="text" id="city_recurrence" name="city_recurrence" placeholder="Porto Alegre" required>
                </div>
            </div>
            <div class="form-row form-row-inline">
                <div class="form-col">
                    <label for="state_recurrence"><?php _e('Estado', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                    <select id="state_recurrence" name="state_recurrence" required>
                        <option value=""><?php _e('Selecione', 'wc-zoop-payments'); ?></option>
                        <option value="AC">Acre</option>
                        <option value="AL">Alagoas</option>
                        <option value="AP">Amapá</option>
                        <option value="AM">Amazonas</option>
                        <option value="BA">Bahia</option>
                        <option value="CE">Ceará</option>
                        <option value="DF">Distrito Federal</option>
                        <option value="ES">Espírito Santo</option>
                        <option value="GO">Goiás</option>
                        <option value="MA">Maranhão</option>
                        <option value="MT">Mato Grosso</option>
                        <option value="MS">Mato Grosso do Sul</option>
                        <option value="MG">Minas Gerais</option>
                        <option value="PA">Pará</option>
                        <option value="PB">Paraíba</option>
                        <option value="PR">Paraná</option>
                        <option value="PE">Pernambuco</option>
                        <option value="PI">Piauí</option>
                        <option value="RJ">Rio de Janeiro</option>
                        <option value="RN">Rio Grande do Norte</option>
                        <option value="RS">Rio Grande do Sul</option>
                        <option value="RO">Rondônia</option>
                        <option value="RR">Roraima</option>
                        <option value="SC">Santa Catarina</option>
                        <option value="SP">São Paulo</option>
                        <option value="SE">Sergipe</option>
                        <option value="TO">Tocantins</option>
                    </select>
                </div>
            </div>
            <h4><?php _e('Plano de Recorrência', 'wc-zoop-payments'); ?></h4>
            <div class="form-row">
                <label for="plan_name_recurrence"><?php _e('Nome do Plano', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="plan_name_recurrence" name="plan_name_recurrence" placeholder="Plano de Assinatura" required>
            </div>
            <div class="form-row">
                <label for="due_date_recurrence"><?php _e('Data de Vencimento', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="due_date_recurrence" name="due_date_recurrence" placeholder="DD/MM/AAAA" maxlength="10" required>
            </div>
            <div class="form-row">
                <label for="expiration_date_recurrence"><?php _e('Data de Expiração do Plano', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="text" id="expiration_date_recurrence" name="expiration_date_recurrence" placeholder="DD/MM/AAAA HH:MM" maxlength="16" required>
            </div>
            <div class="form-row">
                <label for="recurrence_frequency"><?php _e('Frequência de Recorrência', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <select id="recurrence_frequency" name="recurrence_frequency" required>
                    <option value="daily"><?php _e('Diária', 'wc-zoop-payments'); ?></option>
                    <option value="weekly"><?php _e('Semanal', 'wc-zoop-payments'); ?></option>
                    <option value="monthly"><?php _e('Mensal', 'wc-zoop-payments'); ?></option>
                    <option value="annually"><?php _e('Anual', 'wc-zoop-payments'); ?></option>
                </select>
            </div>
            <div class="form-row">
                <label for="recurrence_interval"><?php _e('Intervalo (em períodos)', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="number" id="recurrence_interval" name="recurrence_interval" min="1" value="1" required>
            </div>
            <div class="form-row">
                <label for="recurrence_duration"><?php _e('Duração (em meses)', 'wc-zoop-payments'); ?> <span class="required">*</span></label>
                <input type="number" id="recurrence_duration" name="recurrence_duration" min="1" value="12" required>
            </div>
        </div>
        <?php
        error_log('WC Letztech-payment Recorrência: Campos de pagamento renderizados');
    }

public function process_payment($order_id) {
    error_log('WC Letztech-payment Recorrência: Processando pagamento para o pedido #' . $order_id);
    error_log('WC Letztech-payment Recorrência: Dados POST recebidos: ' . print_r($_POST, true));

    try {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' não encontrado');
            wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
            return;
        }
        error_log('WC Letztech-payment Recorrência: Total do pedido: ' . $order->get_total());

        $seller_info = wc_zoop_get_seller_id_for_order($order);
        $seller_id   = $seller_info['seller_id'];
        error_log('WC Letztech-payment Recorrência: Seller ID retrieved: ' . ($seller_id ? $seller_id : 'Não configurado'));
        if (empty($seller_id)) {
            error_log('WC Letztech-payment Recorrência: Seller ID não configurado');
            wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
            return;
        }

        $required_fields = [
            'card_holder_name_recurrence',
            'card_number_recurrence',
            'card_expiry_month_recurrence',
            'card_expiry_year_recurrence',
            'card_security_code_recurrence',
            'first_name_recurrence',
            'last_name_recurrence',
            'email_recurrence',
            'phone_number_recurrence',
            'taxpayer_id_recurrence',
            'birthdate_recurrence',
            'enderCEP_recurrence',
            'address_line1_recurrence',
            'neighborhood_recurrence',
            'city_recurrence',
            'state_recurrence',
            'plan_name_recurrence',
            'due_date_recurrence',
            'expiration_date_recurrence',
            'recurrence_frequency',
            'recurrence_interval',
            'recurrence_duration'
        ];

        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                error_log('WC Letztech-payment Recorrência: Campo ausente ou vazio: ' . $field);
                wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
                return;
            }
        }

        $amount_in_cents = floatval($order->get_total()) * 100;
        $due_date = $this->convert_date_format(sanitize_text_field($_POST['due_date_recurrence']));
        $expiration_date = sanitize_text_field($_POST['expiration_date_recurrence']);
        $birthdate = $this->convert_date_format(sanitize_text_field($_POST['birthdate_recurrence']));
        $taxpayer_id = str_replace(['.', '-'], '', sanitize_text_field($_POST['taxpayer_id_recurrence']));
        $phone_number = str_replace(['(', ')', '-', ' '], '', sanitize_text_field($_POST['phone_number_recurrence']));
        $cep = str_replace('-', '', sanitize_text_field($_POST['enderCEP_recurrence']));

        error_log('WC Letztech-payment Recorrência: expiration_date enviado raw: ' . $expiration_date);

        $payload = [
            'seller_id' => sanitize_text_field($seller_id),
            'amount' => $amount_in_cents,
            'description' => 'Pagamento recorrente para o pedido #' . $order_id,
            'due_date' => $due_date,
            'expiration_date' => $expiration_date,
            'card' => [
                'holder_name' => sanitize_text_field($_POST['card_holder_name_recurrence']),
                'expiration_month' => sanitize_text_field($_POST['card_expiry_month_recurrence']),
                'expiration_year' => sanitize_text_field($_POST['card_expiry_year_recurrence']),
                'card_number' => str_replace(' ', '', sanitize_text_field($_POST['card_number_recurrence'])),
                'security_code' => sanitize_text_field($_POST['card_security_code_recurrence'])
            ],
            'buyer' => [
                'address' => [
                    'line1' => sanitize_text_field($_POST['address_line1_recurrence']),
                    'neighborhood' => sanitize_text_field($_POST['neighborhood_recurrence']),
                    'city' => sanitize_text_field($_POST['city_recurrence']),
                    'state' => sanitize_text_field($_POST['state_recurrence']),
                    'postal_code' => $cep,
                    'country_code' => 'BR',
                    'line2' => sanitize_text_field($_POST['address_line2_recurrence'] ?? ''),
                    'line3' => sanitize_text_field($_POST['address_line3_recurrence'] ?? '')
                ],
                'first_name' => sanitize_text_field($_POST['first_name_recurrence']),
                'last_name' => sanitize_text_field($_POST['last_name_recurrence']),
                'email' => sanitize_email($_POST['email_recurrence']),
                'phone_number' => $phone_number,
                'taxpayer_id' => $taxpayer_id,
                'birthdate' => $birthdate
            ],
            'plan' => [
                'frequency' => sanitize_text_field($_POST['recurrence_frequency']),
                'interval' => intval($_POST['recurrence_interval']),
                'amount' => $amount_in_cents,
                'setup_amount' => 10,
                'description' => 'Plano de recorrência para o pedido #' . $order_id,
                'name' => sanitize_text_field($_POST['plan_name_recurrence']),
                'duration' => intval($_POST['recurrence_duration'])
            ],
            'device' => [
                'color_depth' => isset($_POST['device_color_depth_recurrence']) ? intval($_POST['device_color_depth_recurrence']) : 24,
                'language' => isset($_POST['device_language_recurrence']) ? sanitize_text_field($_POST['device_language_recurrence']) : 'pt-BR',
                'screen_height' => isset($_POST['device_screen_height_recurrence']) ? intval($_POST['device_screen_height_recurrence']) : 1080,
                'screen_width' => isset($_POST['device_screen_width_recurrence']) ? intval($_POST['device_screen_width_recurrence']) : 1920,
                'time_zone_offset' => isset($_POST['device_time_zone_recurrence']) ? intval($_POST['device_time_zone_recurrence']) : -180
            ]
        ];

      

        $split_seller     = $seller_info['seller_id_split1'];
        $split_percentage = floatval($seller_info['percentage_split1']);

        if (!empty($split_seller) && $split_percentage > 0 && $split_percentage <= 100) {
            $payload['seller_id_split1']  = sanitize_text_field($split_seller);
            $payload['percentage_split1'] = $split_percentage;
            error_log("WC Letztech Recorrência: Split ativado → seller_id_split1: {$split_seller}, percentage_split1: {$split_percentage}%");
        } else {
            $payload['seller_id_split1']  = '';
            $payload['percentage_split1'] = 0.0;
            error_log('WC Letztech Recorrência: Split desativado');
        }
        error_log('WC Letztech-payment Recorrência: Payload final preparado: ' . json_encode($payload, JSON_PRETTY_PRINT));

        $endpoint = 'http://186.249.36.174/api/transactions/recurrent';
        $response = wp_remote_post($endpoint, [
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        error_log('WC Letztech-payment Recorrência: Requisição API enviada para ' . $endpoint);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('WC Letztech-payment Recorrência: Erro WP na API: ' . $error_message);
            wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('WC Letztech-payment Recorrência: Código de resposta da API: ' . $response_code);
        error_log('WC Letztech-payment Recorrência: Corpo da resposta da API: ' . $response_body);

        $body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WC Letztech-payment Recorrência: Erro ao decodificar JSON: ' . json_last_error_msg());
            wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
            return;
        }

        if ($response_code == 201) {
            $transaction_id = isset($body['id']) ? $body['id'] : 'unknown';
            $order->update_meta_data('_letztech_transaction_id', $transaction_id);
            $order->update_meta_data('_letztech_resolved_seller_id', $seller_id);
            $order->save();

            error_log('WC Letztech-payment Recorrência: Pagamento iniciado para o pedido #' . $order_id . ', Transação ID: ' . $transaction_id);
            // $order->update_status('completed', __('Plano recorrente aprovado via Letztech-payment.', 'wc-zoop-payments'));
            // Verifica se deve marcar como processing ou completed
            $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

            $status = $completed_as_processing ? 'processing' : 'completed';

            $order->update_status($status, __('Plano recorrente aprovado via Letztech-payment.', 'wc-zoop-payments'));
            // final do bloco
            $order->payment_complete();
            wc_reduce_stock_levels($order_id);
            $sku_note = !empty($seller_info['sku_used']) ? " (SKU: {$seller_info['sku_used']})" : '';
            $order->add_order_note('Plano recorrente aprovado via Letztech-payment. Transação: ' . $transaction_id);
            $order->add_order_note("Pagamento processado com Seller ID: {$seller_id}{$sku_note}");
            error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' atualizado para Concluído');
            WC()->cart->empty_cart();
            wc_add_notice(__('Pagamento realizado.', 'wc-zoop-payments'), 'success');
            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        } else {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : (isset($body['errors']) ? implode(', ', $body['errors']) : __('Erro desconhecido', 'wc-zoop-payments'));
            error_log('WC Letztech-payment Recorrência: Pagamento recusado: ' . $error_message);
            $order->update_status('failed', __('Plano recorrente recusado via Letztech-payment.', 'wc-zoop-payments'));
            $order->add_order_note('Plano recorrente recusado via Letztech-payment: ' . $error_message);
            error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' atualizado para Falhado');
            wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
            return;
        }
    } catch (Exception $e) {
        error_log('WC Letztech-payment Recorrência: Exceção em process_payment: ' . $e->getMessage());
        wc_add_notice(__('Falha ao realizar pagamento.', 'wc-zoop-payments'), 'error');
        return;
    }
}

    private function convert_date_format($date) {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }
        error_log('WC Letztech-payment Recorrência: Formato de data inválido: ' . $date);
        return $date;
    }

    public function check_transaction_status($transaction_id) {
        error_log('WC Letztech-payment Recorrência: Iniciando consulta de status da transação: ' . $transaction_id);
        
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
            error_log('WC Letztech-payment Recorrência: Erro ao consultar status da transação: ' . $error_message);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('WC Letztech-payment Recorrência: Resposta da API - Código: ' . $response_code . ', Corpo: ' . $response_body);

        if (!in_array($response_code, [200, 201])) {
            error_log('WC Letztech-payment Recorrência: Erro na consulta da API, código: ' . $response_code);
            return false;
        }

        $body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($body['status'])) {
            error_log('WC Letztech-payment Recorrência: Erro ao decodificar JSON ou status ausente: ' . json_last_error_msg());
            return false;
        }

        error_log('WC Letztech-payment Recorrência: Status da transação retornado: ' . $body['status']);
        return [
            'status' => sanitize_text_field(strtolower($body['status'])),
            'amount' => isset($body['amount']) ? sanitize_text_field($body['amount']) : ''
        ];
    }

    public function update_order_status($order_id, $status = null) {
        error_log('WC Letztech-payment Recorrência: Iniciando update_order_status para pedido #' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' não encontrado');
            return false;
        }
        error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' carregado. Método: ' . $order->get_payment_method() . ', Status: ' . $order->get_status());

        $transaction_id = $order->get_meta('_letztech_transaction_id');
        if (empty($transaction_id)) {
            error_log('WC Letztech-payment Recorrência: ID da transação não encontrado para o pedido #' . $order_id);
            return false;
        }
        error_log('WC Letztech-payment Recorrência: ID da transação encontrado: ' . $transaction_id);

        if ($status === null) {
            $transaction_data = $this->check_transaction_status($transaction_id);
            if (!$transaction_data) {
                error_log('WC Letztech-payment Recorrência: Falha ao consultar status da transação para o pedido #' . $order_id);
                return false;
            }
            $status = $transaction_data['status'];
            error_log('WC Letztech-payment Recorrência: Status retornado pela API: ' . $status);
        }

        error_log('WC Letztech-payment Recorrência: Processando status da transação para o pedido #' . $order_id . ': ' . $status);

        switch (strtolower($status)) {
            case 'succeeded':
                if (!in_array($order->get_status(), ['processing', 'completed'])) {
                    // $order->update_status('completed', __('Plano recorrente aprovado via Letztech-payment.', 'wc-zoop-payments'));
                    // Verifica se deve marcar como processing ou completed
                    $completed_as_processing = get_option('wc_zoop_completed_as_processing') === 'yes';

                    $status = $completed_as_processing ? 'processing' : 'completed';

                    $order->update_status($status, __('Plano recorrente aprovado via Letztech-payment.', 'wc-zoop-payments'));
                    // final do bloco
                    $order->payment_complete($transaction_id);
                    wc_reduce_stock_levels($order_id);
                    $order->add_order_note('Status atualizado para Concluído. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' atualizado para Concluído');
                    return true;
                } else {
                    error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' já está em Processando ou Concluído');
                    return true;
                }
            case 'failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', __('Plano recorrente recusado via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status atualizado para Falhado. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' atualizado para Falhado');
                    return true;
                }
                break;
            case 'pending':
                if ($order->get_status() !== 'pending') {
                    $order->update_status('pending', __('Plano recorrente pendente via Letztech-payment.', 'wc-zoop-payments'));
                    $order->add_order_note('Status mantido como Pendente. Transação ID: ' . $transaction_id);
                    error_log('WC Letztech-payment Recorrência: Pedido #' . $order_id . ' mantido como Pendente');
                    return true;
                }
                break;
            default:
                error_log('WC Letztech-payment Recorrência: Status desconhecido para o pedido #' . $order_id . ': ' . $status);
                return false;
        }

        return true;
    }
}
?>