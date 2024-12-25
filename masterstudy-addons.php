<?php
/**
 * Plugin Name: MasterStudy LMS - Bunny Stream Integration
 * Description: Integrates Bunny Stream video service with MasterStudy LMS
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;




// Initialize the plugin
new MS_BunnyStream_Integration();
class MS_BunnyStream_Integration {
    private $api_key;
    private $library_id;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 10);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook) {
       

        wp_enqueue_script('jquery');
        wp_enqueue_style('ms-bunnystream-admin', false);
        wp_add_inline_style('ms-bunnystream-admin', '
            .bunny-stream-form { max-width: 800px; }
            .bunny-stream-form select { min-width: 200px; }
            .bunny-settings-section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc; }
        ');
    }
   
  
    public function add_admin_menu() {
        add_menu_page(
            'Manage Instructors', // Page title - changed from 'Bunny Stream'
            'Manage Instructors', // Menu title
            'manage_options',
            'ms-bunnystream-instructors',
            array($this, 'instructor_management_page'),
            'dashicons-video-alt3',
            10
        );  

      
    }
   
    public function instructor_management_page() {
        $instructor_id = isset($_POST['instructor_id']) ? intval($_POST['instructor_id']) : 0;
        $instructors = $this->get_all_instructors();
        
        // Save settings if form is submitted
        if (isset($_POST['save_instructor_settings']) && check_admin_referer('save_instructor_bunny_settings')) {
            $this->save_instructor_bunny_settings($instructor_id);
            echo '<div class="notice notice-success"><p>Instructor settings saved successfully!</p></div>';
        }

        $settings = $this->get_instructor_bunny_settings($instructor_id);
        ?>
        <div class="wrap">
            <h1>Manage Instructor Bunny Stream Settings</h1>
            
            <form method="post" class="bunny-stream-form">
                <?php wp_nonce_field('save_instructor_bunny_settings'); ?>
                
                <div class="bunny-settings-section">
                    <h2>Select Instructor</h2>
                    <select name="instructor_id" id="instructor_id">
                        <option value="">Select an instructor</option>
                        <?php foreach ($instructors as $instructor) : ?>
                            <option value="<?php echo esc_attr($instructor->ID); ?>" 
                                    <?php selected($instructor_id, $instructor->ID); ?>>
                                <?php echo esc_html($instructor->display_name); ?>                                 
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bunny-settings-section">
                    <h2>API Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="bunnystream_api_key">Bunny Stream API Key</label></th>
                            <td>
                                <input type="password" 
                                       name="bunnystream_api_key" 
                                       id="bunnystream_api_key" 
                                       value="<?php echo esc_attr($settings['stream_api_key']); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="bunnystream_video_api">Video Library API Key</label></th>
                            <td>
                                <input type="password" 
                                       name="bunnystream_video_api" 
                                       id="bunnystream_video_api" 
                                       value="<?php echo esc_attr($settings['video_api_key']); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="bunny-settings-section">
                    <h2>Usage Limits</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="bunnystream_storage_limit">Storage Limit (GB)</label></th>
                            <td>
                                <input type="number" 
                                       name="bunnystream_storage_limit" 
                                       id="bunnystream_storage_limit" 
                                       value="<?php echo esc_attr($settings['storage_limit']); ?>" 
                                       min="0" 
                                       class="regular-text">
                                <p class="description">Enter 0 for unlimited storage</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="bunnystream_bandwidth_limit">Bandwidth Limit (GB/year)</label></th>
                            <td>
                                <input type="number" 
                                       name="bunnystream_bandwidth_limit" 
                                       id="bunnystream_bandwidth_limit" 
                                       value="<?php echo esc_attr($settings['bandwidth_limit']); ?>" 
                                       min="0" 
                                       class="regular-text">
                                <p class="description">Enter 0 for unlimited bandwidth</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" 
                           name="save_instructor_settings" 
                           class="button button-primary" 
                           value="Save Instructor Settings">
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#instructor_id').on('change', function() {
                if ($(this).val()) {
                    $(this).closest('form').submit();
                }
            });
        });
        </script>
        <?php
    }

    private function get_all_instructors() {
        $args = array(
            'role' => 'stm_lms_instructor',
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        return get_users($args);
    }

    private function save_instructor_bunny_settings($instructor_id) {
        if (!$instructor_id || !current_user_can('manage_options')) {
            return;
        }

        $settings = array(
            'stream_api_key' => sanitize_text_field($_POST['bunnystream_api_key']),
            'video_api_key' => sanitize_text_field($_POST['bunnystream_video_api']),
            'storage_limit' => intval($_POST['bunnystream_storage_limit']),
            'bandwidth_limit' => intval($_POST['bunnystream_bandwidth_limit'])
        );

        update_user_meta($instructor_id, 'bunnystream_settings', $settings);
    }

    public function get_instructor_bunny_settings($instructor_id) {
        $defaults = array(
            'stream_api_key' => '',
            'video_api_key' => '',
            'storage_limit' => 0,
            'bandwidth_limit' => 0
        );

        if (!$instructor_id) {
            return $defaults;
        }

        $settings = get_user_meta($instructor_id, 'bunnystream_settings', true);
        return wp_parse_args($settings, $defaults);
    }

}

require_once plugin_dir_path( __FILE__ ).'bunny-dashboard.php';
