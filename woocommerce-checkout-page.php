<?php
/*
Plugin Name: Управление страницей оформления заказа
Description: Добавляет возможность настраивать страницу оформления заказа
Version: 1.0
Author: Cdc
License: GPL2
*/


// if defined...
if (!defined('ABSPATH')) {
    exit;
}

function custom_wc_woocommerce_checkout() {
    add_submenu_page(
        'woocommerce',
        __('Оформление заказов', 'wc-order-status-manage'),
        __('Оформление заказов', 'wc-order-status-manage'),
        'manage_options',
        'woocommerce-checkout-page',
        'display_shipping_settings_page'
    );
}
add_action('admin_menu', 'custom_wc_woocommerce_checkout');

// Регистрация и подключение скриптов
function shipping_cost_enqueue_scripts() {
    // Подключаем jQuery (если он еще не подключен)
    wp_enqueue_script('jquery');
    // Подключаем JavaScript-файл только на странице оформления заказа
    if (is_checkout()) {
        $billing_address_fields = get_option('billing_address_fields');
        // $version = rand(1, 1000000);
        $version = 1.0;
        wp_enqueue_script('checkout-script', plugin_dir_url(__FILE__) . 'js/checkout.js', array('jquery'), $version, true);
        wp_localize_script('checkout-script', 'billing_address_fields', $billing_address_fields);
    }
}
add_action('wp_enqueue_scripts', 'shipping_cost_enqueue_scripts');

// enqueue css to the client side from /css/checkout.css
function shipping_cost_enqueue_styles() {
    if (is_checkout()) {
        wp_enqueue_style('checkout-style', plugin_dir_url(__FILE__) . 'css/checkout.css');
    }
}
add_action('wp_enqueue_scripts', 'shipping_cost_enqueue_styles');

function admin_enqueue_scripts() {
    wp_enqueue_script('jquery'); // Подключаем jQuery
    // Подключаем скрипт селект2
    wp_enqueue_script('select2', plugins_url('/js/select2.min.js', __FILE__), array('jquery'), null, true);
    // подключаем файл стилей селект2
    wp_enqueue_style('select2', plugins_url('/css/select2.min.css', __FILE__));
    // Подключаем скрипт для создания множественного выбора из папки с плагином
    wp_enqueue_script('custom-script', plugins_url('/js/custom-script.js', __FILE__), array('jquery'), null, true);
}

add_action('admin_enqueue_scripts', 'admin_enqueue_scripts');

// Регистрация группы настроек и функции обработки данных
function shipping_cost_settings_init() {

    register_setting(
        'shipping_settings_group', // Имя группы настроек
        'shipping_cost_settings',     // Имя опции для хранения данных
        'shipping_cost_settings_sanitize' // Функция обработки данных
    );

    // Добавление секции настроек
    add_settings_section(
        'shipping_settings_section', // ID секции настроек
        'Настройки доставки',       // Заголовок секции
        'shipping_settings_section_callback', // Функция отображения секции
        'shipping-settings'          // Страница настроек
    );

    // Получаем список методов доставки из WooCommerce
    $shipping_methods = get_shipping_methods();

    // Создаем поля ввода для каждого метода доставки
    foreach ($shipping_methods as $method_id => $method) {
        $title = isset($method->instance_settings['title']) ? $method->instance_settings['title'] : '';
        add_settings_field(
            "${method_id}_shipping_cost_field",
            $title . ' - Стоимость доставки',
            'shipping_cost_field_callback',
            'shipping-settings',
            'shipping_settings_section',
            [
                'method_id' => $method_id,
            ]
        );
    }
}
add_action('admin_init', 'shipping_cost_settings_init');

// Функция отображения секции настроек
function shipping_settings_section_callback() {
    echo 'Здесь вы можете настроить параметры доставки.';
}

// Функция отображения поля ввода для стоимости доставки и условий
function shipping_cost_field_callback($args) {
    $method_id = $args['method_id'];
    $option_name = "${method_id}_shipping_settings";

    $option = get_option('shipping_cost_settings');
    $settings = isset($option[$option_name]) ? $option[$option_name] : array();

    $cost = isset($settings['cost']) ? esc_attr($settings['cost']) : '';
    $condition = isset($settings['condition']) ? esc_attr($settings['condition']) : '';
    $operator = isset($settings['operator']) ? esc_attr($settings['operator']) : '';
    $discount_type = isset($settings['discount_type']) ? esc_attr($settings['discount_type']) : '';
    $discount_amount = isset($settings['discount_amount']) ? esc_attr($settings['discount_amount']) : '';
    $second_condition_arg = isset($settings['second_condition_arg']) ? esc_attr($settings['second_condition_arg']) : '';

    $shipping_method_instance = WC_Shipping_Zones::get_shipping_method($method_id);

    if ($shipping_method_instance) {
        // Получаем стоимость из инстанса и устанавливаем как значение по умолчанию
        $instance_settings = $shipping_method_instance->instance_settings;
        $instance_cost = isset($instance_settings['cost']) ? esc_attr($instance_settings['cost']) : '';
        if (!empty($instance_cost) && empty($cost)) {
            $cost = $instance_cost;
        }
    }

    echo '<div style="display: flex; gap: 8px;">';
        // Вывод поля ввода для стоимости доставки
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="' . esc_attr($option_name) . '_cost">Стоимость доставки:</label>';
        echo '<input type="text" id="' . esc_attr($option_name) . '_cost" name="shipping_cost_settings[' . esc_attr($option_name) . '][cost]" value="' . $cost . '" />';
        echo '</div>';

        // Вывод селекта для выбора условия
        
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="' . esc_attr($option_name) . '_condition">Выберите условие:</label>';
        echo '<select id="' . esc_attr($option_name) . '_condition" name="shipping_cost_settings[' . esc_attr($option_name) . '][condition]">';
        echo '<option value="order_total" ' . selected('order_total', $condition, false) . '>По общей сумме заказа</option>';
        echo '<option value="item_count" ' . selected('item_count', $condition, false) . '>По количеству товаров в корзине</option>';
        echo '</select>';
        echo '</div>';

        // Вывод селекта для выбора оператора условия
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="' . esc_attr($option_name) . '_operator">Оператор условия:</label>';
        echo '<select id="' . esc_attr($option_name) . '_operator" name="shipping_cost_settings[' . esc_attr($option_name) . '][operator]">';
        echo '<option value="greater_than" ' . selected('greater_than', $operator, false) . '>></option>';
        echo '<option value="less_than" ' . selected('less_than', $operator, false) . '><</option>';
        echo '</select>';
        echo '</div>';

        // Вывод поля ввода для второй части условия (аргумент)
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="' . esc_attr($option_name) . '_second_condition_arg">Значение условия:</label>';
        echo '<input type="text" id="' . esc_attr($option_name) . '_second_condition_arg" name="shipping_cost_settings[' . esc_attr($option_name) . '][second_condition_arg]" value="' . $second_condition_arg . '" />';
        echo '</div>';

        // Вывод селекта для выбора варианта скидки
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="' . esc_attr($option_name) . '_discount_type">Вариант скидки:</label>';
        echo '<select id="' . esc_attr($option_name) . '_discount_type" name="shipping_cost_settings[' . esc_attr($option_name) . '][discount_type]">';
        echo '<option value="fixed_amount" ' . selected('fixed_amount', $discount_type, false) . '>Фиксированная сумма</option>';
        echo '<option value="percentage" ' . selected('percentage', $discount_type, false) . '>Проценты (%)</option>';
        echo '</select>';
        echo '</div>';

        // Вывод поля ввода для величины скидки
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="' . esc_attr($option_name) . '_discount_amount">Величина скидки:</label>';
        echo '<input type="text" id="' . esc_attr($option_name) . '_discount_amount" name="shipping_cost_settings[' . esc_attr($option_name) . '][discount_amount]" value="' . $discount_amount . '" />';
        echo '</div>';
    echo '</div>';
}


function shipping_cost_settings_sanitize($input) {
    $sanitized_input = array();

    foreach ($input as $method_id => $settings) {
        $sanitized_settings = array();

        // Очищаем и проверяем стоимость доставки
        if (isset($settings['cost'])) {
            $sanitized_settings['cost'] = sanitize_text_field($settings['cost']);
        }

        // Очищаем и проверяем условие
        if (isset($settings['condition']) && in_array($settings['condition'], array('item_count', 'order_total'))) {
            $sanitized_settings['condition'] = $settings['condition'];
        }

        // Очищаем и проверяем оператор условия
        if (isset($settings['operator']) && in_array($settings['operator'], array('greater_than', 'less_than'))) {
            $sanitized_settings['operator'] = $settings['operator'];
        }

        // Очищаем и проверяем вторую часть условия (аргумент)
        if (isset($settings['second_condition_arg'])) {
            $sanitized_settings['second_condition_arg'] = sanitize_text_field($settings['second_condition_arg']);
        }

        // Очищаем и проверяем вариант скидки
        if (isset($settings['discount_type']) && in_array($settings['discount_type'], array('percentage', 'fixed_amount'))) {
            $sanitized_settings['discount_type'] = $settings['discount_type'];
        }

        // Очищаем и проверяем величину скидки
        if (isset($settings['discount_amount'])) {
            $sanitized_settings['discount_amount'] = sanitize_text_field($settings['discount_amount']);
        }

        $sanitized_input[$method_id] = $sanitized_settings;
    }

    return $sanitized_input;
}


function display_shipping_settings_page() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        // Настройки успешно обновлены, выполняем редирект на страницу настроек
        // redirect to the settings page that we want
        $current_tab = (isset($_GET['tab'])) ? $_GET['tab'] : 'shipping-cost-settings';
        wp_redirect(admin_url('admin.php?page=woocommerce-checkout-page&tab=' . $current_tab));
        exit;
    }
    
    ?>
    <div class="wrap">
        <h2 class="nav-tab-wrapper">
            <a href="#a" class="nav-tab nav-tab-active" data-tab="shipping-cost-settings">Стоимость доставки</a>
            <a href="#a" class="nav-tab" data-tab="shipping-field-settings">Поля оформления заказа</a>
        </h2>
        <?php
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?settings-updated=1')); ?>">
            <?php
            if (isset($_GET['tab']) && $_GET['tab'] == 'shipping-field-settings') {
                // Выводим настройки полей оформления заказа
                display_shipping_field_settings();
                ?>
                <input type="hidden" name="action" value="save_billing_address_fields">
                <?php
            } else {
                settings_fields('shipping_settings_group');
                do_settings_sections('shipping-settings');
                ?>
                <input type="hidden" name="action" value="save_shipping_cost_settings">
                <?php
            }
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Функция для обработки сохранения данных
function shipping_cost_save_settings() {
    if (isset($_POST['shipping_cost_settings'])) {
        $new_settings = $_POST['shipping_cost_settings'];

        // Очистка и проверка данных перед сохранением
        $sanitized_settings = array();
        foreach ($new_settings as $method_id => $settings) {
            $sanitized_settings[$method_id] = array(
                'cost' => isset($settings['cost']) ? sanitize_text_field($settings['cost']) : '',
                'condition' => isset($settings['condition']) ? sanitize_text_field($settings['condition']) : '',
                'operator' => isset($settings['operator']) ? sanitize_text_field($settings['operator']) : '',
                'second_condition_arg' => isset($settings['second_condition_arg']) ? sanitize_text_field($settings['second_condition_arg']) : '',
                'discount_type' => isset($settings['discount_type']) ? sanitize_text_field($settings['discount_type']) : '',
                'discount_amount' => isset($settings['discount_amount']) ? sanitize_text_field($settings['discount_amount']) : '',
            );
        }

        // Сохранение данных в опции
        update_option('shipping_cost_settings', $sanitized_settings);

        // Выполняем редирект на страницу настроек
        wp_redirect(admin_url('admin.php?page=woocommerce-checkout-page&settings-updated=true'));
        exit;
    }
    if (isset($_POST['billing_address_fields'])) {

        $new_settings = $_POST['billing_address_fields'];
        $sanitized_settings = array();
        foreach ($new_settings as $method_id => $settings) {
            $sanitized_settings[$method_id] = array();
            foreach ($settings as $field) {
                $sanitized_settings[$method_id][] = sanitize_text_field($field);
            }
        }

        // Сохранение данных в опции
        update_option('billing_address_fields', $sanitized_settings);

        // Выполняем редирект на страницу настроек
        wp_redirect(admin_url('admin.php?page=woocommerce-checkout-page&settings-updated=true&tab=shipping-field-settings'));
        exit;
    }
}
add_action('admin_post_save_shipping_cost_settings', 'shipping_cost_save_settings');
add_action('admin_post_save_billing_address_fields', 'shipping_cost_save_settings');


// Функция для получения списка методов доставки из WooCommerce, учитывая зоны доставки
function get_shipping_methods() {
    $shipping_zones = WC_Shipping_Zones::get_zones();

    $active_shipping_methods = array();

    foreach ($shipping_zones as $zone) {
        $zone_id = $zone['zone_id'];
        $methods = WC_Shipping_Zones::get_zone($zone_id)->get_shipping_methods();

        foreach ($methods as $method_id => $method) {
            // Проверяем, активен ли метод доставки
            if ($method->is_enabled()) {
                $active_shipping_methods[$method_id] = $method;
            }
        }
    }

    return $active_shipping_methods;
}

function calculate_shipping_cost_f($settings, $method_cost) {
    $cost = $method_cost;
    $a = $method_cost;
    $b = $settings['discount_amount'];

    if ($settings['condition'] === 'order_total') {
        $order_total = WC()->cart->get_cart_contents_total();

        if ($settings['operator'] === 'greater_than') {

            if ($order_total > $settings['second_condition_arg']) {
                if ($settings['discount_type'] === '%') {
                    $cost = $a * $b / 100;
                } elseif ($settings['discount_type'] === 'fixed_amount') {
                    $cost = $a - $b;
                }
            }
        } elseif ($settings['operator'] === 'less_than') {
            if ($order_total < $settings['second_condition_arg']) {
                if ($settings['discount_type'] === '%') {
                    $cost = $a * $b / 100;
                } elseif ($settings['discount_type'] === 'fixed_amount') {
                    $cost = $a - $b;
                }
            }
        }
    }
    return $cost;
}

add_filter( 'woocommerce_package_rates', 'hide_shipping_method_based_on_shipping_class', 20, 2 );

function hide_shipping_method_based_on_shipping_class( $rates, $package ) {
    if (is_checkout()) {
        $shipping_zones = WC_Shipping_Zones::get_zones();
        $shipping_cost_settings = get_option('shipping_cost_settings');

        foreach ($shipping_zones as $zone) {
            $methods = $zone['shipping_methods'];
            foreach ($methods as $method) {
                $method_instance = $method->instance_id;
                $method_title = $method->title;
                $option_name = "${method_instance}_shipping_settings";
                $shipping_settings = isset($shipping_cost_settings[$option_name]) ? $shipping_cost_settings[$option_name] : array();

                $rate_id = $method->id . ':' . $method_instance;

        
                if ($shipping_settings && $shipping_settings['second_condition_arg'] > 0) {
                    if (
                        isset($rates[$rate_id]->cost) && !empty($rates[$rate_id]->cost)
                        && isset($rates[$rate_id]->label) && !empty($rates[$rate_id]->label)
                    ) {
                        $d = calculate_shipping_cost_f($shipping_settings, $rates[$rate_id]->cost);
                        if ($d > 0) {
                            $rates[$rate_id]->cost = $d;
                        } else {
                            $rates[$rate_id]->label = $rates[$rate_id]->label . ' - Бесплатно';
                        }
                        $rates[$rate_id]->cost = $d;
                    }
                }
            }
        }
    }
    return $rates;
}

function fields_list($method_name) {
    $billing_address_fields = WC()->countries->get_address_fields( '', 'billing_' );
    $settings = get_option('billing_address_fields');
    echo '<select name="billing_address_fields[' . $method_name . '][]" id="billing_address_fields[' . $method_name . '][]" class="postform" multiple style="width: 400px;">';
    foreach ( $billing_address_fields as $field_key => $field ) {
        $selected = in_array($field_key, isset($settings[$method_name]) ? $settings[$method_name] : array()) ? 'selected' : '';
        echo '<option value="' . esc_attr( $field_key ) . '"' . $selected . '>' . esc_html( $field['label'] ) . '</option>';
    }
    echo '</select>';
}

function display_shipping_field_settings() {
    $settings = get_option('billing_address_fields');
    $all_shipping_methods = get_shipping_methods();
    ?>
    <div style="display: flex; flex-direction: column; gap: 16px;">
    <div style="display: flex; gap: 8px; align-items: center;">
            <div style="max-width: 50%; width: 320px;">
                <h4>Метод доставки</h4>
            </div>
            <div style="max-width: 50%;">
                <h4>Поля адреса для метода доставки</h4>
            </div>
        </div>
    <?php
    foreach ($all_shipping_methods as $shipping_method) {

        $method_name = $shipping_method->id . ':' . $shipping_method->get_instance_id();

        ?><div style="display: flex; gap: 8px; align-items: center;">
            <div style="max-width: 50%; width: 320px;">
                <?php echo $shipping_method->get_title(); ?>
            </div>
            <div style="max-width: 50%;">
                <?php
                    fields_list($method_name);
                ?>
            </div>
        </div><?php
    }
    ?>
    </div>
    <?php
}
?>