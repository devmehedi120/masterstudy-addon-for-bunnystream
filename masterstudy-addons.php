<?php
/**
 * Plugin Name: MasterStudy LMS - Bunny Stream Integration
 * Description: Integrates Bunny Stream video service with MasterStudy LMS
 * Version: 1.0.0
 */



if (!defined('ABSPATH')) exit;

// add_filter( 'ms_lms_course_builder_additional_styles', function($styles){
//     $styles[] = "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css";
//     return $styles;
// } );

add_filter( 'ms_lms_course_builder_additional_scripts', function($scripts){
    $scripts[] = "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js";
    $scripts[] = plugin_dir_url( __FILE__ )."assets/js/test.js";
    return $scripts;
} );

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
            'Bunny Stream', // Page title
            'Bunny Stream', // Menu title
            'manage_options',     // Capability
            'ms-bunnystream-instructors', // Menu slug
            array($this, 'instructor_management_page'), // Callback function
            'dashicons-video-alt3', // Icon
            10 // Position
        );
        
        add_submenu_page(
            'ms-bunnystream-instructors', // Parent slug
            'Instructor Details',        // Page title
            'Instructor Details',        // Menu title
            'manage_options',            // Capability (fixed typo)
            'instructor-details',        // Menu slug
            [$this, 'instructor_details_html'] // Callback function
        );
        

      
    }
     function instructor_details_html(){
        ?>
          <h4> There is instractor table</h4>
        <?php
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
            'storage_limit' => intval($_POST['bunnystream_storage_limit']),
            'bandwidth_limit' => intval($_POST['bunnystream_bandwidth_limit'])
        );

        update_user_meta($instructor_id, 'bunnystream_settings', $settings);
    }

    public function get_instructor_bunny_settings($instructor_id) {
        $defaults = array(
            'stream_api_key' => '',
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
//require_once plugin_dir_path( __FILE__ ).'bandwidthFunctionality.php';

// Add custom source to video sources dropdown
// add_filter('ms_plugin_video_sources', function($sources) {
//     $sources['custom_source'] = __('Custom Source', 'masterstudy-lms-learning-management-system');
//     return $sources;
// }, 20);



class MStudy_Custom_Video {
    
    public function __construct() {
        // Add custom video source
        add_filter('ms_plugin_video_sources', [$this, 'add_custom_video_source'], 20);
        
        // Add URL field via JavaScript
        add_action('admin_footer', [$this, 'add_custom_field_script']);
        
        // Handle video display
        add_filter('masterstudy_lms_lesson_video', [$this, 'handle_custom_video'], 10, 2);
        
        // Add AJAX handler for saving video URL
        add_action('wp_ajax_save_custom_video_url', [$this, 'save_custom_video_url']);
    }

    /**
     * Add custom video source to available sources
     */
    public function add_custom_video_source($sources) {
        $sources['custom_source'] = __('Custom Source', 'mstudy-custom-video');
        return $sources;
    }

    /**
     * Add JavaScript to inject custom field
     */
    public function add_custom_field_script() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to add the URL input field
            function addCustomVideoField() {
                // Wait for the source type field to be present
                const sourceTypeContainer = document.querySelector('[name="video_type"]').closest('.chakra-form-control');
                
                if (!sourceTypeContainer) return;
                
                // Check if our field already exists
                if (document.getElementById('custom-video-url-field')) return;
                
                // Create new field using Chakra UI classes
                const fieldHTML = `
                    <div role="group" class="chakra-form-control css-1kxonj9" id="custom-video-url-field" style="display: none;">
                        <label class="chakra-form__label css-1ulakw4">Video URL</label>
                        <div class="chakra-input__group css-bx0blc">
                            <input 
                                placeholder="Enter video URL" 
                                name="custom_video_url" 
                                class="chakra-input css-1uhejcr" 
                                value=""
                            >
                        </div>
                    </div>
                `;
                
                // Insert after source type field
                sourceTypeContainer.insertAdjacentHTML('afterend', fieldHTML);
                
                // Add change event listener to source type field
                const sourceTypeInput = document.querySelector('[name="video_type"]');
                sourceTypeInput.addEventListener('change', function() {
                    const urlField = document.getElementById('custom-video-url-field');
                    if (this.value === 'custom_source') {
                        urlField.style.display = 'block';
                    } else {
                        urlField.style.display = 'none';
                    }
                });
                
                // Check initial value
                if (sourceTypeInput.value === 'custom_source') {
                    document.getElementById('custom-video-url-field').style.display = 'block';
                }
            }

            // Initial call
            addCustomVideoField();
            
            // Watch for DOM changes (in case of dynamic loading)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        addCustomVideoField();
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
        </script>
        <?php
    }

    /**
     * Save custom video URL via AJAX
     */
    public function save_custom_video_url() {
        if (!isset($_POST['post_id']) || !isset($_POST['video_url'])) {
            wp_send_json_error('Missing required fields');
        }
        
        $post_id = intval($_POST['post_id']);
        $video_url = sanitize_text_field($_POST['video_url']);
        
        update_post_meta($post_id, 'custom_video_url', $video_url);
        wp_send_json_success();
    }

    /**
     * Handle custom video display
     */
    public function handle_custom_video($content, $post_id) {
        $video_type = get_post_meta($post_id, 'video_type', true);
        
        if ($video_type !== 'custom_source') {
            return $content;
        }

        $video_url = get_post_meta($post_id, 'custom_video_url', true);
        
        if (empty($video_url)) {
            return $content;
        }

        return sprintf(
            '<div class="stm-lms-course-player">
                <video class="stm-lms-video" controls>
                    <source src="%s" type="video/mp4">
                    %s
                </video>
            </div>',
            esc_url($video_url),
            esc_html__('Your browser does not support the video tag.', 'mstudy-custom-video')
        );
    }
}

// Initialize the plugin
//new MStudy_Custom_Video();



// add_filter('ms_plugin_video_sources', function($sources) {
//     $sources['bunny_stream'] = __('Bunny Stream', 'masterstudy-lms-learning-management-system');
//     return $sources;
// }, 20);

// // Add fields for Bunny Stream
// add_filter('masterstudy_lms_lesson_video_fields', function($fields, $source) {
//     if ($source === 'bunny_stream') {
//         $fields[] = [
//             'id' => 'video_url',
//             'type' => 'text',
//             'label' => __('Bunny Stream URL', 'masterstudy-lms-learning-management-system'),
//             'placeholder' => __('Enter Bunny Stream URL', 'masterstudy-lms-learning-management-system'),
//         ];
//     }
//     return $fields;
// }, 10, 2);

// // Handle the video URL
// add_filter('masterstudy_lms_get_video_url', function($url, $post_id, $source) {
//     if ($source === 'bunny_stream') {
//         $video_url = get_post_meta($post_id, 'video_url', true);
//         return !empty($video_url) ? $video_url : $url;
//     }
//     return $url;
// }, 10, 3);



/**
 * Add Bunny Stream source to video sources
 */
// add_filter( 'ms_plugin_video_sources', function ( $sources ) {
//     $sources['bunny_stream_source_type'] = __( 'Bunny Stream', 'text-domain' );
//     return $sources;
// },20 );

add_filter( 'masterstudy_lms_lesson_custom_fields', function( $fields ) {
    // Add your custom field
    $fields[] = array(
        'name'  => 'bunny_stream_url',  // Unique field identifier
        'label' => 'Bunny Stream URL', // Field label shown in the editor
        'type'  => 'url',              // Field type (e.g., text, textarea, select)
    );
    return $fields;
} );

add_filter('stm_lms_template_file', function($base_path, $template_name) {
    $lesson_id = get_query_var( 'lesson_id'  );
    $custom_url = get_post_meta($lesson_id, 'bunny_stream_url', true);

    if(!empty($custom_url)){
        if ($template_name === '/stm-lms-templates/components/video-media.php') {
            $base_path = plugin_dir_path(__FILE__);
        }
    }
    return $base_path;
}, 10, 2);
