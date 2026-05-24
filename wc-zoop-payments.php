<?php
/*
Plugin Name: WooCommerce Letztech Gateway
Description: Custom payment gateways for Letztech (Credit Card, PIX, Recurrence, Boleto)
Version: 1.4.7
Author: Softkuka
Text Domain: wc-zoop-payments
*/

if (!defined('ABSPATH')) {
    error_log('WC Letztech-payment: ABSPATH not defined, exiting');
    exit;
}

add_action('plugins_loaded', 'wc_zoop_payment_init');
function wc_zoop_payment_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        error_log('WC Letztech-payment: WooCommerce not detected');
        return;
    }

    error_log('WC Letztech-payment: Initializing plugin');

    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-zoop-pix.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-zoop-recurrence.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-zoop-boleto.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-zoop-credit-card-interest.php';

    add_filter('woocommerce_payment_gateways', 'wc_zoop_add_gateways');
    function wc_zoop_add_gateways($gateways) {
        error_log('WC Letztech-payment: Adding gateways');
        $gateways[] = 'WC_Gateway_Zoop_Credit_Card_Interest';
        $gateways[] = 'WC_Gateway_Zoop_PIX';
        $gateways[] = 'WC_Gateway_Zoop_Recurrence';
        $gateways[] = 'WC_Gateway_Zoop_Boleto';
        error_log('WC Letztech-payment: Gateways added: ' . print_r($gateways, true));
        return $gateways;
    }

  add_action('wp_enqueue_scripts', function() {
    if (!is_checkout() && !is_wc_endpoint_url('order-received')) {
        return;
    }

    // JsBarcode (boleto)
    wp_enqueue_script(
        'zoop-jsbarcode',
        plugin_dir_url(__FILE__) . 'assets/jsbarcode.min.js',
        [],
        '3.11.5',
        true
    );
    error_log('WC Letztech-payment: Enqueued JsBarcode script for checkout or thank you page');

    // Boleto script
    wp_enqueue_script(
        'zoop-boleto-script',
        plugin_dir_url(__FILE__) . 'assets/zoop-boleto-script.js',
        ['jquery'],
        '1.0.0',
        true
    );
    error_log('WC Letztech-payment: Enqueued zoop-boleto-script for checkout or thank you page');

    // QRCode (PIX)
    wp_enqueue_script(
        'zoop-qrcode',
        plugin_dir_url(__FILE__) . 'assets/qrcode.min.js',
        [],
        '1.0.0',
        true
    );
    error_log('WC Letztech-payment: Enqueued qrcode.min.js for PIX');

    // LIMPEZA DE CAMPOS DUPLICADOS (ADICIONADO AQUI)
    wp_enqueue_script(
        'wc-letztech-checkout-cleanup',
        plugin_dir_url(__FILE__) . 'assets/checkout-cleanup.js',
        ['jquery', 'wc-checkout'],
        '1.0.1',
        true
    );

    wp_localize_script('wc-letztech-checkout-cleanup', 'wcLetztechCleanup', [
        'fields' => [
            'billing_first_name',
            'billing_last_name',
            'billing_email',
            'billing_phone',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
        ],
    ]);
    error_log('WC Letztech-payment: Enqueued checkout-cleanup.js para remover campos duplicados');
});
    add_filter('woocommerce_settings_tabs_array', 'wc_zoop_add_settings_tab', 0);
    function wc_zoop_add_settings_tab($tabs) {
        $tabs['letztech_settings'] = __('Letztech Settings', 'wc-zoop-payments');
        error_log('WC Letztech-payment: Added Letztech Settings tab');
        return $tabs;
    }

    add_action('woocommerce_settings_letztech_settings', 'wc_zoop_render_settings_page');
    function wc_zoop_render_settings_page() {
        error_log('WC Letztech-payment: Starting to render Letztech Settings page');
        if (!current_user_can('manage_options')) {
            error_log('WC Letztech-payment: User lacks manage_options capability');
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-zoop-payments'));
        }
        ?>
        <div class="wrap">
            <h2><?php _e('Letztech Global Settings', 'wc-zoop-payments'); ?></h2>
            <?php settings_errors('wc_zoop_settings_group'); ?>
            <form method="post" action="options.php" id="wc_zoop_settings_form">
                <?php
                settings_fields('wc_zoop_settings_group');
                do_settings_sections('wc_zoop_settings');
                submit_button(__('Salvar Mudanças', 'wc-zoop-payments'));
                error_log('WC Letztech-payment: Rendered settings form');
                ?>
            </form>
        </div>
        <?php
        error_log('WC Letztech-payment: Letztech Settings page fully rendered');
    }

    add_action('admin_init', 'wc_zoop_register_settings');
add_action('admin_init', 'wc_zoop_register_settings');
function wc_zoop_register_settings() {
    error_log('WC Letztech-payment: Registering settings');
    add_settings_section(
        'wc_zoop_global_settings',
        __('Configuração global', 'wc-zoop-payments'),
        function() {
            echo '<p>' . __('Configure as definições globais para o Letztech Payment Gateway.', 'wc-zoop-payments') . '</p>';
            error_log('WC Letztech-payment: Settings section callback executed');
        },
        'wc_zoop_settings'
    );

    register_setting('wc_zoop_settings_group', 'wc_zoop_min_installment', [
        'type' => 'number',
        'sanitize_callback' => function($value) {
            return floatval($value) > 0 ? floatval($value) : 0;
        },
        'default' => 0,
        'min' => 0,
        'step' => 0.01
    ]);
    add_settings_field(
        'wc_zoop_seller_id',
        __('Seller ID', 'wc-zoop-payments'),
        'wc_zoop_seller_id_callback',
        'wc_zoop_settings',
        'wc_zoop_global_settings',
        ['label_for' => 'wc_zoop_seller_id']
    );


    add_settings_field(
        'wc_zoop_seller_id_split1',
        __('Seller ID Split 1', 'wc-zoop-payments'),
        'wc_zoop_seller_id_split1_callback',
        'wc_zoop_settings',
        'wc_zoop_global_settings',
        ['label_for' => 'wc_zoop_seller_id_split1']
    );


    add_settings_field(
        'wc_zoop_percentage_split1',
        __('Porcentagem Split 1 (%)', 'wc-zoop-payments'),
        'wc_zoop_percentage_split1_callback',
        'wc_zoop_settings',
        'wc_zoop_global_settings',
        ['label_for' => 'wc_zoop_percentage_split1']
    );


    add_settings_field(
        'wc_zoop_min_installment',
        __('Valor Mínimo da Parcela (R$)', 'wc-zoop-payments'),
        'wc_zoop_min_installment_callback',
        'wc_zoop_settings',
        'wc_zoop_global_settings',
        ['label_for' => 'wc_zoop_min_installment']
    );


    for ($i = 1; $i <= 12; $i++) {
        add_settings_field(
            "wc_zoop_interest_{$i}",
            sprintf(__('Juros %dx (%%)', 'wc-zoop-payments'), $i),
            'wc_zoop_interest_callback',
            'wc_zoop_settings',
            'wc_zoop_global_settings',
            ['label_for' => "wc_zoop_interest_{$i}", 'installment' => $i]
        );
        register_setting('wc_zoop_settings_group', "wc_zoop_interest_{$i}", [
            'type' => 'number',
            'sanitize_callback' => 'floatval',
            'default' => ($i == 1) ? 0 : 2,
            'min' => 0,
            'max' => 100,
            'step' => 0.1
        ]);
    }

    // Register the setting for completed_as_processing
    register_setting('wc_zoop_settings_group', 'wc_zoop_completed_as_processing', [
        'type' => 'string', // ✅ CORRETO
        'sanitize_callback' => function ($value) {
            return $value === 'yes' ? 'yes' : 'no';
        },
        'default' => 'no',
    ]);


    add_settings_field(
        'wc_zoop_completed_as_processing',
        __('Status Completed como Processing', 'wc-zoop-payments'),
        'wc_zoop_completed_as_processing_callback',
        'wc_zoop_settings',
        'wc_zoop_global_settings',
        ['label_for' => 'wc_zoop_completed_as_processing']
    );

    register_setting('wc_zoop_settings_group', 'wc_zoop_seller_id', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);

    register_setting('wc_zoop_settings_group', 'wc_zoop_seller_id_split1', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);

    register_setting('wc_zoop_settings_group', 'wc_zoop_percentage_split1', [
        'type' => 'number',
        'sanitize_callback' => function($value) {
            $value = floatval($value);
            return ($value >= 0 && $value <= 100) ? $value : 0;
        },
        'default' => 0
    ]);

    register_setting('wc_zoop_settings_group', 'wc_zoop_min_installment', [
        'type' => 'number',
        'sanitize_callback' => 'floatval',
        'default' => 0,
        'min' => 0,
        'step' => 0.01
    ]);
    

    error_log('WC Letztech-payment: All settings registered including Split fields (reordered)');
}


    function wc_zoop_seller_id_callback() {
        $seller_id = get_option('wc_zoop_seller_id', '');
        error_log('WC Letztech-payment: Rendering seller_id field, current value: ' . $seller_id);
        ?>
        <input type="text" id="wc_zoop_seller_id" name="wc_zoop_seller_id" value="<?php echo esc_attr($seller_id); ?>" class="regular-text" />
        <p class="description"><?php _e('Insira o ID do vendedor fornecido pela Letztech para solicitações de API.', 'wc-zoop-payments'); ?></p>
        <?php
        error_log('WC Letztech-payment: Seller ID field rendered');
    }

    function wc_zoop_min_installment_callback() {
        $min_installment = get_option('wc_zoop_min_installment', 0);
        error_log('WC Letztech-payment: Rendering min_installment field, current value: ' . $min_installment);
        ?>
        <input type="number" id="wc_zoop_min_installment" name="wc_zoop_min_installment" value="<?php echo esc_attr($min_installment); ?>" class="small-text" min="0" step="0.01" />
        <p class="description"><?php _e('Valor mínimo da parcela em reais (R$) para cartão de crédito.', 'wc-zoop-payments'); ?></p>
        <?php
        error_log('WC Letztech-payment: Min Installment field rendered');
    }

    // Callback for completed_as_processing setting
    function wc_zoop_completed_as_processing_callback() {
        $value = get_option('wc_zoop_completed_as_processing', 'no');
        ?>
        <label>
            <input type="checkbox"
                id="wc_zoop_completed_as_processing"
                name="wc_zoop_completed_as_processing"
                value="yes"
                <?php checked($value, 'yes'); ?> />
            <?php _e('Marcar pedidos pagos como "Processing" em vez de "Completed"', 'wc-zoop-payments'); ?>
        </label>
        <p class="description">
            <?php _e('Se ativado, pedidos aprovados pela Letztech ficarão com status "Processing". Caso contrário, ficarão como "Completed".', 'wc-zoop-payments'); ?>
        </p>
        <?php
    }

    function wc_zoop_interest_callback($args) {
        $installment = $args['installment'];
        $interest = get_option("wc_zoop_interest_{$installment}", ($installment == 1) ? 0 : 2);
        error_log('WC Letztech-payment: Rendering interest field for ' . $installment . 'x, current value: ' . $interest);
        ?>
        <input type="number" id="wc_zoop_interest_<?php echo $installment; ?>" name="wc_zoop_interest_<?php echo $installment; ?>" value="<?php echo esc_attr($interest); ?>" class="small-text" min="0" max="100" step="0.1" />
        <p class="description"><?php printf(__('Taxa de juros para %dx parcelas (0-100%%) no cartão de crédito.', 'wc-zoop-payments'), $installment); ?></p>
        <?php
        error_log('WC Letztech-payment: Interest field rendered for ' . $installment . 'x');
    }

    function wc_zoop_seller_id_split1_callback() {
        $seller_id_split1 = get_option('wc_zoop_seller_id_split1', '');
        error_log('WC Letztech-payment: Rendering seller_id_split1 field, current value: ' . $seller_id_split1);
        ?>
        <input type="text" id="wc_zoop_seller_id_split1" name="wc_zoop_seller_id_split1" value="<?php echo esc_attr($seller_id_split1); ?>" class="regular-text" />
        <p class="description"><?php _e('ID do vendedor secundário que receberá parte do pagamento (split). Deixe vazio para desativar.', 'wc-zoop-payments'); ?></p>
        <?php
        error_log('WC Letztech-payment: Seller ID Split 1 field rendered');
    }

    function wc_zoop_percentage_split1_callback() {
        $percentage_split1 = get_option('wc_zoop_percentage_split1', 0);
        error_log('WC Letztech-payment: Rendering percentage_split1 field, current value: ' . $percentage_split1);
        ?>
        <input type="number" id="wc_zoop_percentage_split1" name="wc_zoop_percentage_split1" value="<?php echo esc_attr($percentage_split1); ?>" class="small-text" min="0" max="100" step="0.01" />
        <p class="description"><?php _e('Percentual (0 a 100%) do valor total que será enviado ao Seller ID Split 1.', 'wc-zoop-payments'); ?></p>
        <?php
        error_log('WC Letztech-payment: Percentage Split 1 field rendered');
    }


    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_zoop_add_settings_link');
    function wc_zoop_add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=letztech_settings') . '">' . __('Settings', 'wc-zoop-payments') . '</a>';
        array_unshift($links, $settings_link);
        error_log('WC Letztech-payment: Settings link added to plugins page');
        return $links;
    }


    add_action('admin_init', function() {
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'wc_zoop_settings_group') {
            error_log('WC Letztech-payment: Form submitted with POST data: ' . print_r($_POST, true));
            if (!isset($_POST['_wpnonce'])) {
                error_log('WC Letztech-payment: Nonce not provided');
                add_settings_error('wc_zoop_settings_group', 'nonce_missing', __('Nonce not provided.', 'wc-zoop-payments'), 'error');
            } elseif (!wp_verify_nonce($_POST['_wpnonce'], 'wc_zoop_settings_group-options')) {
                error_log('WC Letztech-payment: Nonce verification failed');
                add_settings_error('wc_zoop_settings_group', 'nonce_failed', __('Nonce verification failed.', 'wc-zoop-payments'), 'error');
            } else {
                error_log('WC Letztech-payment: Nonce verified');
                if (isset($_POST['wc_zoop_seller_id'])) {
                    update_option('wc_zoop_seller_id', sanitize_text_field($_POST['wc_zoop_seller_id']));
                }
                if (isset($_POST['wc_zoop_min_installment'])) {
                    update_option('wc_zoop_min_installment', floatval($_POST['wc_zoop_min_installment']));
                }

                // ✅ ADICIONE ESTA PARTE AQUI
                // Tratar checkbox - se não estiver marcado, não virá no POST
                $completed_as_processing = isset($_POST['wc_zoop_completed_as_processing']) ? 'yes' : 'no';
                update_option('wc_zoop_completed_as_processing', $completed_as_processing);
                error_log('WC Letztech-payment: completed_as_processing saved as: ' . $completed_as_processing);
                // FIM DA ADIÇÃO

                for ($i = 1; $i <= 12; $i++) {
                    if (isset($_POST["wc_zoop_interest_{$i}"])) {
                        update_option("wc_zoop_interest_{$i}", floatval($_POST["wc_zoop_interest_{$i}"]));
                    }
                }
        
                if (isset($_POST['wc_zoop_seller_id_split1'])) {
                    update_option('wc_zoop_seller_id_split1', sanitize_text_field($_POST['wc_zoop_seller_id_split1']));
                }
                if (isset($_POST['wc_zoop_percentage_split1'])) {
                    $val = floatval($_POST['wc_zoop_percentage_split1']);
                    update_option('wc_zoop_percentage_split1', ($val >= 0 && $val <= 100) ? $val : 0);
                }
            }
        }
    });


    add_action('admin_head', function() {
        if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'letztech_settings') {
            ?>
            <style>
                .woocommerce-settings__content > form:not(#wc_zoop_settings_form), .woocommerce-save-button {
                    display: none !important;
                }
                #wc_zoop_settings_form {
                    display: block !important;
                    margin-top: 20px;
                }
                #wc_zoop_settings_form .form-table {
                    margin-bottom: 20px;
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    $(document).off('submit', 'form:not(#wc_zoop_settings_form)');
                    console.log('WC Letztech-payment: Disabled WooCommerce default form AJAX');
                    $('#wc_zoop_settings_form').on('submit', function(e) {
                        console.log('WC Letztech-payment: Submitting Letztech form');
                        $(this).attr('method', 'post');
                        $(this).attr('action', '<?php echo admin_url('options.php'); ?>');
                    });
                });
            </script>
            <?php
            error_log('WC Letztech-payment: Hid default form and disabled AJAX');
        }
        if (isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
            ?>
            <style>
                .wc_zoop_check_status {
                    background: #007cba !important; color: #fff !important; border: none !important;
                    padding: 5px 10px !important; margin: 0 5px !important; cursor: pointer !important;
                    font-size: 13px !important; line-height: 1.5 !important; border-radius: 3px !important;
                }
                .wc_zoop_check_status:hover { background: #005ea6 !important; }
            </style>
            <?php
        }
    });


    add_action('init', function() {
        if (!wp_next_scheduled('wc_zoop_check_pending_orders')) {
            wp_schedule_event(time(), 'hourly', 'wc_zoop_check_pending_orders');
            error_log('WC Letztech-payment: Cron event scheduled');
        }
    });

    add_action('wc_zoop_check_pending_orders', 'wc_zoop_process_pending_orders');
    function wc_zoop_process_pending_orders() {
        error_log('WC Letztech-payment: Starting pending orders check');
        $args = [
            'status' => ['pending', 'on-hold'],
            'payment_method' => ['zoop_credit_card', 'zoop_credit_card_interest', 'zoop_boleto'],
            'meta_query' => [['key' => '_letztech_transaction_id', 'compare' => 'EXISTS']],
            'limit' => 10
        ];
        $orders = wc_get_orders($args);
        foreach ($orders as $order) {
            $gateway = ($order->get_payment_method() === 'zoop_boleto') ? new WC_Gateway_Zoop_Boleto() : new WC_Gateway_Zoop_Credit_Card_Interest();
            $gateway->update_order_status($order->get_id());
        }
        error_log('WC Letztech-payment: Pending orders check completed');
    }

 
    add_action('wp_ajax_wc_zoop_check_order_status', 'wc_zoop_check_order_status_ajax');
    function wc_zoop_check_order_status_ajax() {
        if (!isset($_POST['order_id']) || !isset($_POST['nonce'])) {
            wp_send_json_error(['message' => __('Erro: ID ou nonce ausentes.', 'wc-zoop-payments')]);
        }
        if (!wp_verify_nonce($_POST['nonce'], 'wc_zoop_check_order_status')) {
            wp_send_json_error(['message' => __('Erro: Nonce inválido.', 'wc-zoop-payments')]);
        }
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Erro: Pedido não encontrado.', 'wc-zoop-payments')]);
        }
        $gateway = ($order->get_payment_method() === 'zoop_boleto') ? new WC_Gateway_Zoop_Boleto() : new WC_Gateway_Zoop_Credit_Card_Interest();
        $result = $gateway->update_order_status($order_id);
        $result ? wp_send_json_success(['message' => __('Status atualizado.', 'wc-zoop-payments')])
                : wp_send_json_error(['message' => __('Erro ao atualizar.', 'wc-zoop-payments')]);
    }


    add_action('rest_api_init', function() {
        register_rest_route('wc_zoop/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => 'wc_zoop_handle_webhook',
            'permission_callback' => '__return_true'
        ]);
    });

    function wc_zoop_handle_webhook($request) {
        $data = $request->get_json_params();
        if (isset($data['idTransacao']) && isset($data['status'])) {
            $orders = wc_get_orders(['meta_query' => [['key' => '_letztech_transaction_id', 'value' => $data['idTransacao']]]]);
            if (!empty($orders)) {
                $order = $orders[0];
                $gateway = ($order->get_payment_method() === 'zoop_boleto') ? new WC_Gateway_Zoop_Boleto() : new WC_Gateway_Zoop_Credit_Card_Interest();
                $gateway->update_order_status($order->get_id(), $data['status']);
            }
        }
        return new WP_REST_Response(['status' => 'success'], 200);
    }


    function wc_zoop_add_order_actions($actions, $order) {
        if (in_array($order->get_payment_method(), ['zoop_credit_card', 'zoop_credit_card_interest', 'zoop_boleto'])
            && in_array($order->get_status(), ['pending', 'on-hold'])
            && $order->get_meta('_letztech_transaction_id')) {
            $actions['wc_zoop_check_status'] = [
                'action' => 'wc_zoop_check_status',
                'name' => __('Verificar Status Letztech', 'wc-zoop-payments'),
                'url' => wp_nonce_url(admin_url('admin-ajax.php?action=wc_zoop_check_order_status&order_id=' . $order->get_id()), 'wc_zoop_check_order_status', 'nonce'),
                'attributes' => ['data-order-id' => $order->get_id(), 'class' => 'button wc_zoop_check_status']
            ];
        }
        return $actions;
    }
    add_filter('woocommerce_admin_order_actions', 'wc_zoop_add_order_actions', 10, 2);
    add_filter('woocommerce_order_list_table_actions', 'wc_zoop_add_order_actions', 10, 2);


    add_action('admin_menu', function() {
        add_submenu_page('woocommerce', __('Testar API Letztech', 'wc-zoop-payments'), __('Testar API Letztech', 'wc-zoop-payments'), 'manage_woocommerce', 'wc_zoop_test_api', 'wc_zoop_test_api_page');
    });

    function wc_zoop_test_api_page() {
        $result = null;
        if (isset($_POST['transaction_id']) && !empty($_POST['transaction_id'])) {
            $transaction_id = sanitize_text_field($_POST['transaction_id']);
            $gateway = new WC_Gateway_Zoop_Credit_Card_Interest();
            $result = $gateway->check_transaction_status($transaction_id);
        }
        ?>
        <div class="wrap">
            <h2><?php _e('Testar API Letztech', 'wc-zoop-payments'); ?></h2>
            <?php if ($result) : ?>
                <div class="updated"><p><?php _e('Resposta: ', 'wc-zoop-payments'); ?><?php echo esc_html(json_encode($result)); ?></p></div>
            <?php elseif (isset($_POST['transaction_id'])) : ?>
                <div class="error"><p><?php _e('Erro ao consultar API.', 'wc-zoop-payments'); ?></p></div>
            <?php endif; ?>
            <form method="post">
                <label for="transaction_id"><?php _e('ID da Transação', 'wc-zoop-payments'); ?></label>
                <input type="text" id="transaction_id" name="transaction_id" class="regular-text" required />
                <p class="description"><?php _e('Insira o ID da transação para verificar seu status.', 'wc-zoop-payments'); ?></p>
                <?php submit_button(__('Testar API', 'wc-zoop-payments')); ?>
            </form>
        </div>
        <?php
    }
}
?>