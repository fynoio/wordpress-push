<?php
/*
Plugin Name: Fyno Web Push
Description: Integrates the Fyno WebPush SDK into WordPress with customizable settings for push notifications.
Version: 1.1.0
Author: Sai Vinod K
*/

// Enqueue the SDK and inline initialization script
function fyno_web_push_enqueue_scripts() {
    global $popupSettingsDefaults;
    wp_enqueue_script('fyno-web-push-sdk', 'https://cdn.jsdelivr.net/npm/@fyno/websdk@latest/dist/cdn_bundle.min.js', array(), '1.0', true);
    $path = plugin_dir_url(__FILE__);
    $wsid = get_option('fyno_web_push_wsid');
    $integration = get_option('fyno_web_push_integration');
    $vapidKey = get_option('fyno_web_push_vapid_key');
    $env = get_option('fyno_web_push_env');
    $user_login = "";
    $popupConfig = [];
    foreach ($popupSettingsDefaults as $setting => $default) {
        $popupConfig[$setting] = get_option('fyno_web_push_' . $setting, $default);
    }
    $popupConfigJson = wp_json_encode($popupConfig);

    if (is_user_logged_in()) {
        $user_login .= "You are logged in.";
    } else { 
        $user_login .= "Log in to access content.";
    }
    wp_localize_script('fyno-web-push-sdk', 'fynoWebPushAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fynoWebPushAjax')
    ));
    $inlineScript = "
        window.addEventListener('load', function() {
            var FynoWP = fyno.default;
            FynoWP.setCustomPopupConfig($popupConfigJson);
            FynoWP.init('{$wsid}', '{$integration}', '{$env}').then(async (res) => {
            window.FynoReset = res.reset;
        ";

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $user_name = wp_get_current_user()->display_name;
        $inlineScript .= "
            FynoWP.identify('{$user_id}', '{$user_name}').then((res) => {
                    FynoWP.register_push('{$vapidKey}');
                    if (!localStorage.getItem('fynoIdentified')) {
                        localStorage.setItem('fynoIdentified', 'true');
                        jQuery.post(fynoWebPushAjax.ajax_url, {
                            'action': 'increment_identify_counter',
                            '_ajax_nonce': fynoWebPushAjax.nonce,
                        }, function(response) {
                            console.log('Identify counter incremented:', response);
                        });
                    }
                });
			});
        });
        ";
    } else {
        
        $inlineScript .= "
                if (document.cookie.split(';').some((item) => item.trim().startsWith('fyno_web_push_reset='))) {
                    FynoWP.reset().then(() => {
                        FynoWP.register_push('{$vapidKey}');
                        if (!localStorage.getItem('fynoIdentified')) {
                            localStorage.setItem('fynoIdentified', 'true');
                            jQuery.post(fynoWebPushAjax.ajax_url, {
                                'action': 'increment_identify_counter',
                                '_ajax_nonce': fynoWebPushAjax.nonce,
                            }, function(response) {
                                console.log('Identify counter incremented:', response);
                            });
                        }
                    });
                    document.cookie = 'fyno_web_push_reset=; Path='/'; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                } else {
                    FynoWP.register_push('{$vapidKey}');
                    if (!localStorage.getItem('fynoIdentified')) {
                        localStorage.setItem('fynoIdentified', 'true');
                        jQuery.post(fynoWebPushAjax.ajax_url, {
                            'action': 'increment_identify_counter',
                            '_ajax_nonce': fynoWebPushAjax.nonce,
                        }, function(response) {
                            console.log('Identify counter incremented:', response);
                        });
                    }
                }
		    });
        });";
    }

    wp_add_inline_script('fyno-web-push-sdk', $inlineScript, 'after');
}

add_action('wp_enqueue_scripts', 'fyno_web_push_enqueue_scripts');

// Register SDK settings menu and fields
add_action('admin_menu', 'fyno_web_push_add_admin_menu');
function fyno_web_push_add_admin_menu() {
    add_options_page('Fyno Web Push Settings', 'Fyno Web Push', 'manage_options', 'fyno_web_push', 'fyno_web_push_settings_page');
}

function fyno_web_push_settings_page() {
    // Fetch counters or any dynamic data you need for the Dashboard
    $identifyCounter = get_option('fyno_identify_counter', 0);
    $dailyCounter = get_option('fyno_identify_daily_counter', 0);
    ?>
    <div class="wrap">
        <h2>Fyno Web Push Settings</h2>
        
        <div class="fyno-settings-accordion">
            <div>
                <!-- <p>Fyno Branding Information</p> -->
                <div class="container">
                    <div class="header">
                        <h1>Welcome to Fyno WebPush Dashboard</h1>
                    </div>
                    <div class="content">
                        <p>With Fyno WebPush, you can send instant notifications directly to users' web browsers, enhancing engagement and communication.</p>
                        <p>Get started now and start engaging your audience effectively!</p>
                        <a href="https://docs.fyno.io/docs/fyno-web-push" class="button">Get Started</a>
                    </div>
                     <p>Total Users Created: <strong><?php echo esc_html($identifyCounter); ?></strong></p>
                    <p>Users created on <?php echo date("Y/m/d")?>: <strong><?php echo esc_html($dailyCounter); ?></strong></p>
                </div>
            </div>
            
            <h3 class="accordion">Configuration</h3>
            <div class="panel">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('fyno_web_push_settings_group');
                    do_settings_sections('fyno_web_push_configuration');
                    submit_button();
                    ?>
                </form>
            </div>
            
            <h3 class="accordion">Customization</h3>
            <div class="panel">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('fyno_web_push_customization_group');
                    do_settings_sections('fyno_web_push_customization');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var acc = document.getElementsByClassName("accordion");
                var i;

                for (i = 0; i < acc.length; i++) {
                    acc[i].addEventListener("click", function() {
                        this.classList.toggle("active");
                        var panel = this.nextElementSibling;
                        panel.style.display = panel.style.display === "block" ? "none" : "block";
                    });
                }
            });
        </script>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
            }

            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }

            .header {
                text-align: center;
                margin-bottom: 30px;
            }

            h1 {
                color: #333;
            }

            .content {
                background-color: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            p {
                color: #555;
            }

            .button {
                display: inline-block;
                background-color: #007bff;
                color: #fff;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s ease;
            }

            .button:hover {
                background-color: #0056b3;
            }

            .accordion {
                cursor: pointer;
                padding: 18px;
                width: 100%;
                border: none;
                text-align: left;
                outline: none;
                transition: 0.4s;
            }

            .active, .accordion:hover {
                background-color: #ccc; 
            }

            .panel {
                display: none;
                background-color: white;
                overflow: hidden;
                padding: 0 18px;
            }
        </style>
    </div>
    <?php
}
$settings = [
        'fyno_web_push_wsid',
        'fyno_web_push_integration',
        'fyno_web_push_vapid_key',
        'fyno_web_push_env'
    ];
$popupSettingsDefaults = [
        'popupPadding' => '20px',
        'popupMarginTop' => '50px',
        'popupBorderRadius' => '8px',
        'popupTextAlign' => 'center',
        'popupMaxWidth' => '500px',
        'popupWidth' => '400px',
        'popupzIndex' => '999',
        'closeIconText' => '✖',
        'closeIconFontSize' => '20px',
        'messageText' => 'We would like to send you notifications for the latest news and updates.',
        'buttonColor' => '#3F51B5',
        'allowButtonText' => 'Allow',
        'denyButtonText' => 'Deny',
        'remindLaterText' => 'Remind me later',
    ];
add_action('admin_init', 'fyno_web_push_settings_init');
function fyno_web_push_settings_init() {
    global $settings, $popupSettingsDefaults;
    // foreach ($settings as $setting) {
    //     register_setting('fyno_web_push_settings_group', $setting, 'sanitize_text_field');
    //     add_settings_section('fyno_web_push_settings_section', 'Fyno Web Push Settings', null, 'fyno_web_push');
    //     add_settings_field($setting, ucwords(str_replace('_', ' ', $setting)), function () use ($setting) {
    //         $value = get_option($setting);
    //         if($setting == 'fyno_web_push_env'){
    //             echo "<select name=\"{$setting}\" id=\"{$setting}\">" .
    //                 "<option value=\"live\"" . ($value == 'live' ? ' selected' : '') . ">Live</option>" .
    //                 "<option value=\"test\"" . ($value == 'test' ? ' selected' : '') . ">Test</option>" .
    //                 "</select>";
    //         } else {
    //             echo "<input type='text' name='{$setting}' value='" . esc_attr($value) . "' class='regular-text'>";
    //         }
    //     }, 'fyno_web_push_configuration', 'fyno_web_push_settings_section');
    // }

    // foreach ($popupSettingsDefaults as $setting => $default) {
    //     register_setting('fyno_web_push_settings_group', 'fyno_web_push_' . $setting, 'sanitize_text_field');
    //     add_settings_field(
    //         'fyno_web_push_' . $setting,
    //         ucwords(str_replace('_', ' ', $setting)),
    //         'fyno_web_push_render_settings_field',
    //         'fyno_web_push_customization',
    //         'fyno_web_push_settings_section',
    //         [ 
    //             'label_for' => 'fyno_web_push_' . $setting,
    //             'default' => $default,
    //         ]
    //     );
    // }
    foreach ($settings as $setting) {
        register_setting('fyno_web_push_settings_group', $setting, 'sanitize_text_field');
        add_settings_section('fyno_web_push_configuration_section', 'Configuration', null, 'fyno_web_push_configuration');
        add_settings_field($setting, ucwords(str_replace('_', ' ', $setting)), 'fyno_web_push_render_settings_field', 'fyno_web_push_configuration', 'fyno_web_push_configuration_section', [ 'label_for' => $setting ]);
    }
    
    foreach ($popupSettingsDefaults as $setting => $default) {
        $label = 'fyno_web_push_' . $setting;
        register_setting('fyno_web_push_customization_group', $label, 'sanitize_text_field');
        add_settings_section('fyno_web_push_customization_section', 'Customization', null, 'fyno_web_push_customization');
        add_settings_field($label, ucwords(str_replace('_', ' ', $setting)), 'fyno_web_push_render_settings_field', 'fyno_web_push_customization', 'fyno_web_push_customization_section', [ 'label_for' => $label, 'default' => $default ]);
    }
}
function fyno_web_push_render_settings_field($args) {
    $colorFields = ['fyno_web_push_buttonColor'];
    $value = get_option($args['label_for'], $args['default']);
    if (in_array($args['label_for'], $colorFields)) {
        echo "<input type='color' id='{$args['label_for']}' name='{$args['label_for']}' value='" . esc_attr($value) . "' class='fyno-web-push-color-picker'>";
    } else if ($args['label_for'] == "fyno_web_push_env") {
        echo "<select name=\"{$args['label_for']}\" id=\"{$args['label_for']}\">" .
             "<option value=\"live\"" . ($value == 'live' ? ' selected' : '') . ">Live</option>" .
             "<option value=\"test\"" . ($value == 'test' ? ' selected' : '') . ">Test</option>" .
             "</select>";
    } else {
        echo "<input type='text' id='{$args['label_for']}' name='{$args['label_for']}' value='" . esc_attr($value) . "' class='regular-text'>";
    }
}

// Reset SDK on logout
add_action('wp_logout', function () {
    setcookie('fyno_web_push_reset', '1', time() + 30, '/');
});

add_action('wp_ajax_increment_identify_counter', 'handle_increment_identify_counter');
add_action('wp_ajax_nopriv_increment_identify_counter', 'handle_increment_identify_counter'); // For logged-out users

function handle_increment_identify_counter() {
    $option_key_total = 'fyno_identify_counter';
    $option_key_daily = 'fyno_identify_daily_counter';
    $option_key_last_call = 'fyno_last_identify_call';

    $current_date = current_time('Y-m-d');
    $last_call_date = get_option($option_key_last_call, "1999-01-01");

    $current_total = (int) get_option($option_key_total, 0);
    update_option($option_key_total, ++$current_total);

    if ($last_call_date !== $current_date) {
        update_option($option_key_daily, 1);
        update_option($option_key_last_call, $current_date);
    } else {
        $current_daily = (int) get_option($option_key_daily, 0);
        update_option($option_key_daily, ++$current_daily);
    }

    wp_send_json_success([
        'total' => $current_total,
        'daily' => get_option($option_key_daily, 1)
    ]);
    wp_die();
}

?>