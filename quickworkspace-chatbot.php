<?php
/**
 * Plugin Name: Quick Workspace Chatbot
 * Description: A plugin to integrate Quick Workspace AI chatbot.
 * Version: 1.0
 * Author: Ryan BenHassine
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create a menu item in the admin panel
add_action('admin_menu', 'qwc_add_admin_menu');
function qwc_add_admin_menu() {
    add_menu_page('Quick Workspace Chatbot', 'Chatbot Settings', 'manage_options', 'quick_workspace_chatbot', 'qwc_settings_page');
}

// Enqueue scripts and styles
add_action('admin_enqueue_scripts', 'qwc_enqueue_scripts');
function qwc_enqueue_scripts() {
    wp_enqueue_style('qwc-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('qwc-script', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), null, true);
}

// Settings page
function qwc_settings_page() {
    ?>
    <div class="wrap">
        <h1>Quick Workspace Chatbot Settings</h1>
        <form method="post" action="">
            <label for="api_key">API Key:</label>
            <input type="text" name="api_key" id="api_key" value="<?php echo esc_attr(get_option('qwc_api_key')); ?>" required>
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
    // Handle form submission
    if (isset($_POST['submit'])) {
        update_option('qwc_api_key', sanitize_text_field($_POST['api_key']));
        update_option('qwc_allowed_domain', sanitize_text_field($_POST['allowed_domain']));
        update_option('qwc_instructions', sanitize_textarea_field($_POST['instructions']));
        qwc_fetch_account_info();
    }
}

// Fetch account information
function qwc_fetch_account_info() {
    $api_key = get_option('qwc_api_key');
    $response = wp_remote_get('https://quickworkspace.ai/api/account', [
        'headers' => [
            'X-Api-Key' => $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        return; // Handle error
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    // Store data in the database
    update_option('qwc_account_info', $data);
}

// Create a conversation
function qwc_create_conversation($workspace_id, $user_api_key) {
    $response = wp_remote_post('https://quickworkspace.ai/api/ai/conversations', [
        'headers' => [
            'X-Workspace-Id' => $workspace_id,
            'X-Api-Key' => $user_api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        return; // Handle error
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['uuid'] ?? null; // Return the conversation UUID
}

// Send a message
function qwc_send_message($conversation_uuid, $workspace_id, $user_api_key, $content, $parent_id = '') {
    $instructions = get_option('qwc_instructions'); // Get instructions from settings
    $full_content = $instructions . "\n" . $content; // Combine instructions with user content

    $response = wp_remote_post("https://quickworkspace.ai/api/ai/conversations/{$conversation_uuid}/messages", [
        'headers' => [
            'X-Workspace-Id' => $workspace_id,
            'X-Api-Key' => $user_api_key,
        ],
        'body' => [
            'content' => $full_content, // Only send content
            'parent_id' => $parent_id, // Attach the parent_id if provided
        ],
    ]);

    if (is_wp_error($response)) {
        return; // Handle error
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

// Shortcode to display the chatbot
add_shortcode('quick_workspace_chatbot', 'qwc_chatbot_shortcode');
function qwc_chatbot_shortcode() {
    ob_start();
    ?>
    <div id="chatbot-container">
        <h2>Chat with our AI</h2>
        <div id="chatbot-messages"></div>
        <input type="text" id="chatbot-input" placeholder="Type your message...">
        <button id="chatbot-send">Send</button>
    </div>
    <?php
    return ob_get_clean();
}

// Add AJAX action for logged-in users
add_action('wp_ajax_qwc_send_message', 'qwc_handle_send_message');
function qwc_handle_send_message() {
    // Check if the message is set
    if (!isset($_POST['message'])) {
        wp_send_json_error('No message provided.');
        return;
    }

    // Get the necessary options
    $api_key = get_option('qwc_api_key');
    $workspace_id = get_option('qwc_account_info')['workspace_id']; // Assuming this is stored
    $instructions = get_option('qwc_instructions');
    
    // Create a conversation if needed (you may want to manage conversation UUIDs)
    $conversation_uuid = qwc_create_conversation($workspace_id, $api_key);

    // Get the parent_id from the session or a temporary storage (you can implement this as needed)
    $parent_id = isset($_SESSION['last_message_id']) ? $_SESSION['last_message_id'] : '';

    // Send the message
    $response = qwc_send_message($conversation_uuid, $workspace_id, $api_key, $_POST['message'], $parent_id);

    // Check if the response is valid
    if ($response && isset($response['content'])) {
        // Store the current message ID as the last message ID for the next request
        $_SESSION['last_message_id'] = $response['id']; // Assuming the response contains the message ID
        wp_send_json_success($response['content']);
    } else {
        wp_send_json_error('Failed to get a valid response.');
    }
}
