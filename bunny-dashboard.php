<?php
class BunnyStream_Dashboard {
    private $plugin_path;
    private $plugin_url;
   
    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        
        add_shortcode('bunnystream_dashboard', array($this, 'render_dashboard'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_create_playlist', array($this, 'handle_create_playlist'));
        add_action('wp_ajax_nopriv_create_playlist', array($this, 'handle_create_playlist'));
        add_action('wp_ajax_delete_playlist', array($this, 'handle_delete_playlist'));
        add_action( 'init', [$this, 'enqueue_video_upload_script'] );
        add_action('wp_ajax_get_playlists', array($this, 'handle_get_playlists'));
         add_action('wp_ajax_upload_video_to_playlist', [$this,'handle_video_upload']);
        
    }

    public function enqueue_scripts() {
        wp_enqueue_style('bunnystream-dashboard', $this->plugin_url . 'assets/css/dashboard.css');
        wp_enqueue_style('bunnystream-annim', $this->plugin_url . 'assets/css/loading-annim.css');
        wp_enqueue_script('bunnystream-dashboard', $this->plugin_url . 'assets/js/dashboard.js', array('jquery'), '1.0', true);
        wp_localize_script('bunnystream-dashboard', 'bunnyStreamAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bunnystream_nonce')
        ));

       
        
    }
    function enqueue_video_upload_script() {
        wp_enqueue_script('video-upload-ajax', get_template_directory_uri() . '/js/video-upload.js', array('jquery'), null, true);
    
        // Localize script to pass Ajax URL
        wp_localize_script('video-upload-ajax', 'videoUploadParams', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('upload_video_nonce')
        ));
    }



function handle_video_upload() {
    // Verify nonce for security
    check_ajax_referer('upload_video_nonce', 'nonce');

    // Check for required data
    if (empty($_POST['video-title']) || empty($_FILES['video-file'])) {
        wp_send_json_error(['message' => 'Video title and file are required.']);
    }

    // Extract variables from the form
    $video_title = sanitize_text_field($_POST['video-title']);
    $user_id = get_current_user_id();

    // Retrieve user's Bunny Stream playlist credentials
    $playlist = get_user_meta($user_id, 'bunnystream_playlists', true);

  
    
    $library_id = isset($playlist['bunnyLibraryId']) ? $playlist['bunnyLibraryId'] : null;
    $api_key = isset($playlist['libraryApiKey']) ? $playlist['libraryApiKey'] : null;

    if (!$library_id || !$api_key) {
        wp_send_json_error(['message' => 'Missing Bunny Library credentials.']);
    }

    try {
        // First, create the video entry in Bunny CDN
        $guid = $this->create_bunny_video($library_id, $api_key, $video_title);
    // Fetch the existing playlist from user meta
      
            // Ensure the playlist is an array
            if (!is_array($playlist)) {
                $playlist = [
                    'videos' => [] // Initialize 'videos' key as an empty array
                ];
            }

            // Ensure the 'videos' key exists and is an array
            if (!isset($playlist['videos']) || !is_array($playlist['videos'])) {
                $playlist['videos'] = [];
            }

            // Add the new video to the 'videos' array
            $playlist['videos'][] = [
                'videoId'    => $guid,
                'videoTitle' => $video_title
            ];

            // Save the updated playlist back to user meta
            update_user_meta($user_id, 'bunnystream_playlists', $playlist);

        // Then upload the video file
        $upload_response = $this->upload_video_to_bunny($library_id, $api_key, $guid, $_FILES['video-file']);
        //$video_url=$this->get_bunny_video_url($library_id, $api_key,$guid);
        wp_send_json_success([
            'guid' => $guid,
            'upload_response' => $upload_response
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }

    wp_die();
}
function get_bunny_video_url($library_id, $api_key, $guid) {
    // Construct the API URL
    $api_url = "https://video.bunnycdn.com/library/$library_id/videos/$guid";

    // Make the API request
    $response = wp_remote_get($api_url, [
        'headers' => [
            'AccessKey' => $api_key,
            'Accept'    => 'application/json',
        ],
        'timeout' => 30,
    ]);

    // Check for errors
    if (is_wp_error($response)) {
        throw new Exception('Failed to fetch video details: ' . $response->get_error_message());
    }

    // Get the response code
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        throw new Exception('Failed to fetch video details. Server returned: ' . $response_code);
    }

    // Decode the response body
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['playbackUrl'])) {
        throw new Exception('Playback URL not found in the Bunny Stream response.');
    }

    // Return the playback URL
    return $body['playbackUrl'];
}

function create_bunny_video($library_id, $api_key, $title) {
    $api_url = "https://video.bunnycdn.com/library/$library_id/videos";
    
    $response = wp_remote_post($api_url, [
        'headers' => [
            'AccessKey' => $api_key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode(['title' => $title]),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        throw new Exception('Failed to create video: ' . $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200 && $response_code !== 201) {
        throw new Exception('Failed to create video. Server returned: ' . $response_code);
    }

    $body = json_decode(wp_remote_retrieve_body($response));
    
    if (empty($body->guid)) {
        throw new Exception('Failed to get video GUID from Bunny CDN response.');
    }

    return $body->guid;
}

function upload_video_to_bunny($library_id, $api_key, $guid, $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error with video file upload: ' . get_upload_error_message($file['error']));
    }

    if (!function_exists('curl_init')) {
        throw new Exception('cURL is required for video uploads.');
    }

    $api_url = "https://video.bunnycdn.com/library/$library_id/videos/$guid";
    $file_path = $file['tmp_name'];
    
    // Verify file exists and is readable
    if (!is_readable($file_path)) {
        throw new Exception('Cannot read uploaded file.');
    }

    // Get file size
    $file_size = filesize($file_path);
    if ($file_size === false) {
        throw new Exception('Cannot determine file size.');
    }

    // Initialize cURL
    $ch = curl_init($api_url);
    if ($ch === false) {
        throw new Exception('Failed to initialize cURL.');
    }

    // Open file
    $fp = fopen($file_path, 'rb');
    if ($fp === false) {
        curl_close($ch);
        throw new Exception('Could not open file for upload.');
    }

    // Set cURL options
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_INFILE => $fp,
        CURLOPT_INFILESIZE => $file_size,
        CURLOPT_UPLOAD => true,
        CURLOPT_HTTPHEADER => [
            'AccessKey: ' . $api_key,
            'Content-Type: application/octet-stream',
            'Content-Length: ' . $file_size
        ],
        CURLOPT_TIMEOUT => 3600, // 1 hour timeout
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);

    // Perform the upload
    $response = curl_exec($ch);
    
    // Get HTTP response code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Get any error
    $curl_error = curl_error($ch);
    
    // Close resources
    fclose($fp);
    curl_close($ch);

    // Handle errors
    if ($response === false) {
        throw new Exception('Upload failed: ' . $curl_error);
    }

    if ($http_code !== 200 && $http_code !== 201) {
        throw new Exception('Upload failed with HTTP code ' . $http_code . ': ' . $response);
    }

    $response_data = json_decode($response);
    if (!$response_data || isset($response_data->error)) {
        throw new Exception('API error: ' . ($response_data->error ?? 'Unknown error'));
    }

    return $response_data;
}

function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}



    // For non-logged in users
    

    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return 'Please log in to access the dashboard.';
        }

        $user_id = get_current_user_id();
        $settings = get_user_meta($user_id, 'bunnystream_settings', true);
        
        if (empty($settings['stream_api_key']) || empty($settings['video_api_key'])) {
            return 'Please configure your Bunny Stream API settings first.';
        }

        ob_start();
        ?>
        <div class="bunnystream-dashboard">
            <div class="dashboard-header">
                <h2>My Video Dashboard</h2>
            </div>
                        <?php
            $user_id = get_current_user_id();
            $existing_playlists = get_user_meta($user_id, 'bunnystream_playlists', true);

            // Determine if the creation section should be visible
            $show_creation_section = empty($existing_playlists) || !is_array($existing_playlists);
            ?>
            <?php if ($show_creation_section): ?>
                <!-- Playlist Creation Section -->
                <div class="playlist-creation">
                    <h3>Create New Playlist</h3>
                    <div class="playlist-form">
                        <input type="text" id="playlist-name" placeholder="Enter playlist name">
                        <button id="create-playlist" class="button">Create Playlist</button>
                    </div>
                </div>
            
            <?php endif; ?>

           

            <!-- Playlists Section -->
            <div class="playlists-section">
                <h3>My Playlists</h3>
                <div id="playlists-container"></div>
            </div>

            <!-- Video Upload Section -->
            <div class="video-upload" style="display: none;">
                <h3>Upload Video</h3>
                <form id="video-upload-form" method="post" enctype="multipart/form-data">
                    <label for="video-title">Video Title</label>
                    <input type="text" id="video-title" name="video-title" required>
                    
                    <label for="video-file">Choose Video</label>
                    <input type="file" id="video-file" name="video-file" accept="video/*" required>
                    
                    <input type="hidden" name="action" value="upload_video_to_playlist">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('upload_video_nonce'); ?>">
                    
                    <button type="submit">Upload Video</button>
                </form>

           <div id="upload-status"></div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_create_playlist() {
        // Verify the AJAX request nonce
        check_ajax_referer('bunnystream_nonce', 'nonce');
        
        // Check if the playlist name is provided
        if (empty($_POST['playlist_name'])) {
            wp_send_json_error('Playlist name is required');
            return;
        }
    
        $user_id = get_current_user_id();
    
        // Check if the user already has a playlist
        $existing_playlists = get_user_meta($user_id, 'bunnystream_playlists', true);
        if (!empty($existing_playlists) && is_array($existing_playlists)) {
            wp_send_json_error('You can only create one playlist');
            return;
        }
    
        // Sanitize playlist name
        $playlist_name = sanitize_text_field($_POST['playlist_name']);
    
        // API request to Bunny Stream
        $api_url = 'https://api.bunny.net/videolibrary';
        $api_key = '9711b328-28aa-4ef5-b082-0c55e79e4fc9c377e678-3a57-499c-b59f-e274cefb60c7';
        $response = wp_remote_post($api_url, [
            'body'    => json_encode(['Name' => $playlist_name]),
            'headers' => [
                'AccessKey'     => $api_key,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);
    
        // Handle the API response
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to create playlist on Bunny Stream: ' . $response->get_error_message());
            return;
        }
    
        $response_body = wp_remote_retrieve_body($response);
        
        $decoded_response = json_decode($response_body, true);
       
        if (isset($decoded_response['HttpCode']) && $decoded_response['HttpCode'] !== 200) {
            wp_send_json_error('Error from Bunny Stream: ' . ($decoded_response['Message'] ?? 'Unknown error'));
            return;
        }
    
        // Save the playlist locally
        $new_playlist = [
            'id'         => uniqid('playlist_'),
            'name'       => $playlist_name,
            'videos'     => [],
            'created_at' => current_time('mysql'),
           'bunnyLibraryId' => isset($decoded_response['Id']) ? sanitize_text_field($decoded_response['Id']) : null,
           'libraryApiKey'=> isset($decoded_response['ApiKey'])? sanitize_text_field( $decoded_response['ApiKey'] ):null,

        ];
    
        update_user_meta($user_id, 'bunnystream_playlists', $new_playlist);
    
        // Respond with success
        wp_send_json_success($new_playlist);
    }
    
    
    public function handle_delete_playlist() {
      
        // Verify the AJAX request nonce
        check_ajax_referer('bunnystream_nonce', 'nonce');
    
        $user_id = get_current_user_id();
      
        // Retrieve the user's playlist
        $playlists = get_user_meta($user_id, 'bunnystream_playlists', true);
                   
        
    
        // Assuming the user has only one playlist
        $bunny_id = $playlists['bunnyLibraryId'] ?? null;

    
        if (!$bunny_id) {
            // No Bunny Stream ID found, remove local playlist data
            delete_user_meta($user_id, 'bunnystream_playlists');
            wp_send_json_success('Playlist deleted locally, but no Bunny Stream ID found');
            return;
        }
   
        // Delete the playlist from Bunny Stream
        $api_url = 'https://api.bunny.net/videolibrary/' . $bunny_id;
        $api_key = '9711b328-28aa-4ef5-b082-0c55e79e4fc9c377e678-3a57-499c-b59f-e274cefb60c7';
    
        $response = wp_remote_request($api_url, [
            'method'  => 'DELETE',
            'headers' => [
                'AccessKey' => $api_key,
                'Accept'    => 'application/json',
            ],
        ]);
    
        // Handle API response
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to delete playlist from Bunny Stream: ' . $response->get_error_message());
            return;
        }
    
       
    
        // Remove the playlist locally after successful API deletion
        delete_user_meta($user_id, 'bunnystream_playlists');
    
        // Respond with success
        wp_send_json_success('Playlist deleted successfully');
    }
    
    

    public function handle_get_playlists() {
        check_ajax_referer('bunnystream_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $playlists = get_user_meta($user_id, 'bunnystream_playlists', true) ?: array();
        
        wp_send_json_success($playlists);
    }
}

// Initialize the dashboard
new BunnyStream_Dashboard();
?>