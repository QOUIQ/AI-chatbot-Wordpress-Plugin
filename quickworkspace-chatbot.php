<?php
/**
 * Plugin Name: Quick Workspace Chatbot
 * Description: A plugin to integrate Quick Workspace AI chatbot.
 * Version: 1.0
 * Author: RYAN B.Hassine
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'qwc_add_admin_menu');
function qwc_add_admin_menu() {
    add_menu_page('Quick Workspace Chatbot', 'Chatbot Settings', 'manage_options', 'quick_workspace_chatbot', 'qwc_settings_page');
}

add_action('admin_enqueue_scripts', 'qwc_enqueue_scripts');
add_action('wp_enqueue_scripts', 'qwc_enqueue_scripts');
function qwc_enqueue_scripts() {
    wp_enqueue_style('qwc-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('qwc-script', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), null, true);
}

function qwc_settings_page() {
    ?>
    <div class="wrap">
        <h1>Quick Workspace Chatbot Settings</h1>
        <form method="post" action="">
            <label for="api_key">API Key:</label>
            <input type="text" name="api_key" id="api_key" value="<?php echo esc_attr(get_option('qwc_api_key')); ?>" required>
            <br>
            <small>To get your API key, please visit <a href="https://quickworkspace.ai/app/account" target="_blank">this link</a> and generate your API key.</small>
            <br>
            <label for="allowed_domain">Allowed Domain URL:</label>
            <input type="text" name="allowed_domain" id="allowed_domain" value="<?php echo esc_attr(get_option('qwc_allowed_domain')); ?>" required>
            <br>
            <label for="instructions">Instructions:</label>
            <textarea name="instructions" id="instructions" required><?php echo esc_textarea(get_option('qwc_instructions')); ?></textarea>
            <br>
            <input type="submit" name="submit" value="Save Settings" class="button button-primary">
        </form>
    </div>
    <?php
    if (isset($_POST['submit'])) {
        update_option('qwc_api_key', sanitize_text_field($_POST['api_key']));
        update_option('qwc_allowed_domain', sanitize_text_field($_POST['allowed_domain']));
        update_option('qwc_instructions', sanitize_textarea_field($_POST['instructions']));
        qwc_fetch_account_info();
    }
}

function qwc_fetch_account_info() {
    $api_key = get_option('qwc_api_key');
    $response = wp_remote_get('https://quickworkspace.ai/api/account', [
        'headers' => [
            'X-Api-Key' => $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    update_option('qwc_account_info', $data);
}

function qwc_create_conversation($workspace_id, $user_api_key) {
    $response = wp_remote_post('https://quickworkspace.ai/api/ai/conversations', [
        'headers' => [
            'X-Workspace-Id' => $workspace_id,
            'X-Api-Key' => $user_api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['uuid'] ?? null;
}

function qwc_send_message($conversation_uuid, $workspace_id, $user_api_key, $content, $parent_id = '') {
    $instructions = get_option('qwc_instructions');
    $full_content = $instructions . "\n" . $content;

    $response = wp_remote_post("https://quickworkspace.ai/api/ai/conversations/{$conversation_uuid}/messages", [
        'headers' => [
            'X-Workspace-Id' => $workspace_id,
            'X-Api-Key' => $user_api_key,
        ],
        'body' => [
            'content' => $full_content,
            'parent_id' => $parent_id,
        ],
    ]);

    if (is_wp_error($response)) {
        return;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

add_shortcode('quick_workspace_chatbot', 'qwc_chatbot_shortcode');
function qwc_chatbot_shortcode() {
    ob_start();
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet"> <!-- Google Font -->
    <div id="chat-icon" style="position: fixed; bottom: 20px; right: 20px; cursor: pointer; z-index: 1000; background-color: black; border-radius: 50%; padding: 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-filled icon-tabler-message-2">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M8 9h8" />
            <path d="M8 13h6" />
            <path d="M9 18h-3a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-3l-3 3l-3 -3z" />
        </svg>
    </div>
    <div id="chatbot-container" style="display: none; position: fixed; bottom: 100px; right: 20px; width: 400px; height: 400px; border: 1px solid #ccc; background-color: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.5); z-index: 1000; border-radius: 30px;">
        <div style="padding: 10px; background-color: black; color: white; border-top-left-radius: 30px; border-top-right-radius: 30px; font-family: 'Roboto', sans-serif;">
            AI Support
        </div>
        <div id="chatbot-messages" style="height: calc(100% - 80px); overflow-y: auto; padding: 10px; font-family: 'Roboto', sans-serif; font-size: 14px;"></div>
        <div style="display: flex; padding: 10px; align-items: center;">
            <input type="text" id="chatbot-input" placeholder="Type your message..." style="flex: 1; padding: 10px; border-radius: 20px; border: 1px solid #ccc;">
            <button id="chatbot-send" style="margin-left: 10px; padding: 10px; border-radius: 20px; background-color: black; color: white; border: none;">Send</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_qwc_send_message', 'qwc_handle_send_message');
function qwc_handle_send_message() {
    if (!isset($_POST['message'])) {
        wp_send_json_error('No message provided.');
        return;
    }

    $api_key = get_option('qwc_api_key');
    $workspace_id = get_option('qwc_account_info')['workspace_id'];
    $instructions = get_option('qwc_instructions');
    
    $conversation_uuid = qwc_create_conversation($workspace_id, $api_key);
    $parent_id = isset($_SESSION['last_message_id']) ? $_SESSION['last_message_id'] : '';

    $response = qwc_send_message($conversation_uuid, $workspace_id, $api_key, $_POST['message'], $parent_id);

    if ($response && isset($response['content'])) {
        $_SESSION['last_message_id'] = $response['id'];
        wp_send_json_success($response['content']);
    } else {
        wp_send_json_error('Failed to get a valid response.');
    }
}
