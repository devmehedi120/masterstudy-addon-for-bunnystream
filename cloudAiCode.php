<?php
/**
 * Plugin Name: Bunny Stream Bandwidth Manager
 * Description: Manage Bunny Stream bandwidth usage and limits
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BunnyStreamManager {
    private $api_key;
    private $library_id;
    private $api_base_url = 'https://api.bunny.net/videolibrary';

    public function __construct() {
        // Initialize settings
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Get settings
        $this->api_key = get_option('bunny_stream_api_key');
        $this->library_id = get_option('bunny_library_id');
    }

    public function add_admin_menu() {
        add_menu_page(
            'Bunny Stream Manager',
            'Bunny Stream',
            'manage_options',
            'bunny-stream',
            array($this, 'display_admin_page'),
            'dashicons-video-alt3'
        );
    }

    public function register_settings() {
        register_setting('bunny_stream_options', 'bunny_stream_api_key');
        register_setting('bunny_stream_options', 'bunny_library_id');
    }

    public function get_bandwidth_statistics() {
        if (empty($this->api_key) || empty($this->library_id)) {
            return new WP_Error('missing_credentials', 'API key and Library ID are required');
        }

        $endpoint = "/{$this->library_id}/statistics";
        $args = array(
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Accept' => 'application/json',
            ),
        );

        $response = wp_remote_get($this->api_base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function get_library_settings() {
        if (empty($this->api_key) || empty($this->library_id)) {
            return new WP_Error('missing_credentials', 'API key and Library ID are required');
        }

        $endpoint = "/{$this->library_id}";
        $args = array(
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Accept' => 'application/json',
            ),
        );

        $response = wp_remote_get($this->api_base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function update_library_settings($settings) {
        if (empty($this->api_key) || empty($this->library_id)) {
            return new WP_Error('missing_credentials', 'API key and Library ID are required');
        }

        $endpoint = "/{$this->library_id}";
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($settings)
        );

        $response = wp_remote_request($this->api_base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function display_admin_page() {
        // Get current statistics and settings
        $stats = $this->get_bandwidth_statistics();
        $library_settings = $this->get_library_settings();
        
        // Handle form submission for updating settings
        if (isset($_POST['update_library_settings']) && 
            check_admin_referer('update_library_settings', 'library_settings_nonce')) {
            
            $new_settings = array(
                'WatermarkPositionLeft' => intval($_POST['watermark_position_left'] ?? 10),
                'WatermarkPositionTop' => intval($_POST['watermark_position_top'] ?? 10),
                'EnableTokenAuthentication' => !empty($_POST['enable_token_auth']),
                'TokenSecurityKey' => sanitize_text_field($_POST['token_security_key'] ?? ''),
                // Add other settings as needed
            );
            
            $update_result = $this->update_library_settings($new_settings);
            
            if (!is_wp_error($update_result)) {
                echo '<div class="notice notice-success"><p>Library settings updated successfully!</p></div>';
                $library_settings = $this->get_library_settings(); // Refresh settings
            } else {
                echo '<div class="notice notice-error"><p>Error updating settings: ' . 
                     esc_html($update_result->get_error_message()) . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Bunny Stream Manager</h1>

            <!-- Settings Section -->
            <form method="post" action="options.php">
                <?php settings_fields('bunny_stream_options'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="bunny_stream_api_key" 
                                value="<?php echo esc_attr(get_option('bunny_stream_api_key')); ?>" 
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Library ID</th>
                        <td>
                            <input type="text" name="bunny_library_id" 
                                value="<?php echo esc_attr(get_option('bunny_library_id')); ?>" 
                                class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save API Settings'); ?>
            </form>

            <!-- Bandwidth Statistics -->
            <h2>Bandwidth Statistics</h2>
            <?php if (!is_wp_error($stats)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Total Bandwidth</th>
                            <th>Total Minutes Streamed</th>
                            <th>Total Storage</th>
                            <th>Video Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo esc_html(number_format($stats['TotalBandwidth'] / 1024 / 1024 / 1024, 2)); ?> GB</td>
                            <td><?php echo esc_html(number_format($stats['TotalMinutesStreamed'], 0)); ?> minutes</td>
                            <td><?php echo esc_html(number_format($stats['TotalStorage'] / 1024 / 1024 / 1024, 2)); ?> GB</td>
                            <td><?php echo esc_html($stats['VideoCount'] ?? 'N/A'); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="error">Error fetching statistics: <?php echo esc_html($stats->get_error_message()); ?></p>
            <?php endif; ?>

            <!-- Library Settings -->
            <h2>Library Settings</h2>
            <?php if (!is_wp_error($library_settings)): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('update_library_settings', 'library_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Token Authentication</th>
                            <td>
                                <input type="checkbox" name="enable_token_auth" 
                                    <?php checked(!empty($library_settings['EnableTokenAuthentication'])); ?> />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Token Security Key</th>
                            <td>
                                <input type="text" name="token_security_key" 
                                    value="<?php echo esc_attr($library_settings['TokenSecurityKey'] ?? ''); ?>" 
                                    class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Watermark Position Left</th>
                            <td>
                                <input type="number" name="watermark_position_left" 
                                    value="<?php echo esc_attr($library_settings['WatermarkPositionLeft'] ?? 10); ?>" 
                                    class="small-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Watermark Position Top</th>
                            <td>
                                <input type="number" name="watermark_position_top" 
                                    value="<?php echo esc_attr($library_settings['WatermarkPositionTop'] ?? 10); ?>" 
                                    class="small-text" />
                            </td>
                        </tr>
                    </table>
                    <input type="hidden" name="update_library_settings" value="1" />
                    <?php submit_button('Update Library Settings'); ?>
                </form>
            <?php else: ?>
                <p class="error">Error fetching library settings: <?php echo esc_html($library_settings->get_error_message()); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize the plugin
$bunny_stream_manager = new BunnyStreamManager();