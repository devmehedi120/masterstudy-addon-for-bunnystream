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
        // add_action( 'init', [$this, 'enqueue_video_upload_script'] );
        add_action('wp_ajax_get_playlists', array($this, 'handle_get_playlists'));
        add_action('wp_ajax_upload_video_to_playlist', [$this,'handle_video_upload']);
        add_action( 'wp_ajax_delete_video',[$this, 'handle_delete_video'] );
        
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

    function handle_delete_video() {
        // Verify the nonce
        check_ajax_referer('bunnystream_nonce', 'nonce');
    
        // Get current user ID
        $user_id = get_current_user_id();
    
        // Retrieve the required parameters from the AJAX request
        $video_id = sanitize_text_field($_POST['videoId']);
        $library_id = sanitize_text_field($_POST['libraryId']);
    
        // Check if required data is present
        if (empty($video_id) || empty($library_id)) {
            wp_send_json_error(['message' => 'Missing videoId or libraryId.']);
            return;
        }
    
        // Retrieve the playlists from user meta
        $playlists = get_user_meta($user_id, 'bunnystream_playlists', true);
    
        // Ensure the playlist exists and find the library API key
        $library_api_key = null;
        if (is_array($playlists) && !empty($playlists['bunnyLibraryId']) && $playlists['bunnyLibraryId'] === $library_id) {
            $library_api_key = $playlists['libraryApiKey'];
        }
    
        if (empty($library_api_key)) {
            wp_send_json_error(['message' => 'Library API Key not found for the given library ID.']);
            return;
        }
    
        // API URL for the DELETE request
        $api_url = "https://video.bunnycdn.com/library/{$library_id}/videos/{$video_id}";
    
        // Initialize CURL
        $ch = curl_init();
    
        // Set CURL options
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "AccessKey: {$library_api_key}",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        // Execute the CURL request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        // Close CURL
        curl_close($ch);
    
        // Handle the response
        if ($http_code === 200) {
           
            wp_send_json_success(['message' => 'Video deleted successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete video. Please try again.']);
        }

        $this->render_dashboard();
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
            $playlist = get_user_meta($user_id, 'bunnystream_playlists', true);
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
    
    function get_bunny_video_list($user_id) {
        // Retrieve the playlists from user meta
        $playlist = get_user_meta($user_id, 'bunnystream_playlists', true);
    
        // Check if the playlist is not an array or is empty
        if (empty($playlist) || !is_array($playlist)) {
            return false; // Return false if no valid playlist exists
        }
    
        // Retrieve the library API key and library ID from the playlist
        $library_api_key = isset($playlist['libraryApiKey']) ? $playlist['libraryApiKey'] : '';
        $library_id = isset($playlist['bunnyLibraryId']) ? $playlist['bunnyLibraryId'] : '';
    
        // Check if library API key and library ID are available
        if (empty($library_api_key) || empty($library_id)) {
            return false; // Return false if required data is missing
        }
    
        // Initialize cURL for fetching video list
        $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://video.bunnycdn.com/library/$library_id/videos?page=1&itemsPerPage=100&orderBy=date",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "AccessKey: $library_api_key",
                "accept: application/json"
            ],
        ]);
    
        // Execute cURL request and capture response
        $response = curl_exec($curl);
        $err = curl_error($curl);
    
        curl_close($curl);
    
        // Handle errors if cURL fails
        if ($err) {
            return ['error' => "cURL Error #: " . $err];
        } else {
            // Decode JSON response from the API
            $response_data = json_decode($response, true);
            
            // Check if decoding was successful
            if (json_last_error() === JSON_ERROR_NONE) {
                return $response_data; // Return decoded JSON as an array
            } else {
                return ['error' => 'Failed to decode JSON response'];
            }
        }
    }
    

    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return 'Please log in to access the dashboard.';
        }
        $user_id = get_current_user_id();
        $settings = get_user_meta($user_id, 'bunnystream_settings', true);
        $videoLists=$this->get_bunny_video_list($user_id); 
        if($videoLists!==false) {
            $videoData= $videoLists['items']?$videoLists['items']:[] ;
        }
      
        $playlists = get_user_meta($user_id, 'bunnystream_playlists', true) ?: '';        
        if (empty($settings['stream_api_key'])) {
            return 'Please configure your Bunny Stream API settings first.';
        }

        ob_start();
        ?>
       <div class="bunnystream-dashboard">
    <div class="dashboard-header">
        <h2> Video Dashboard</h2>
    </div>

    <?php
    $user_id = get_current_user_id();
    $existing_playlists = get_user_meta($user_id, 'bunnystream_playlists', true);
    $show_creation_section = empty($existing_playlists) || !is_array($existing_playlists);
    ?>

    <?php if ($show_creation_section): ?>
        <div class="playlist-creation">
            <h3>Create New Playlist</h3>
            <div class="playlist-form">
                <input type="text" id="playlist-name" placeholder="Enter playlist name">
                <button id="create-playlist" class="button">Create Playlist</button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (is_array($playlists) && $videoLists !== false) : ?>
        <div class="playlists-section">
            <h3> Playlist</h3>
            <div class="playlist-header">
                <h4><?php echo esc_html($playlists['name']); ?></h4>
                <div class="playlist-actions">
                    <button class="upload-video btn-primary" data-playlist-id="<?php echo esc_attr($playlists['bunnyLibraryId']); ?>">Upload Video</button>
                    <button class="delete-playlist btn-danger" data-playlist-id="<?php echo esc_attr($playlists['bunnyLibraryId']); ?>">Delete Playlist</button>
                </div>
            </div>
            <div class="playlist-table-wrapper">
                <table class="playlist-table">
                    <thead>
                        <tr>
                            <th>Video Title</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videoData as $item): ?>
                            <?php $videoUrl = "https://video.bunnycdn.com/play/{$item['videoLibraryId']}/{$item['guid']}"; ?>
                            <tr class="video-row">
        <td class="video-title">
            <?php echo esc_html($item['title']); ?>
        </td>
        <td class="video-actions">
            <div class="action-buttons">
                <button class="play-button" data-video-url="<?php echo esc_url($videoUrl); ?>">
                    <svg class="play-icon" viewBox="0 0 512 512" width="32" height="32">
                        <circle cx="256" cy="256" r="256" fill="#00CC96"/>
                        <path d="M213.304 159.101l120.483 92.589c5.493 4.077 5.493 12.44 0 16.621L213.304 360.9c-6.013 4.493-15.959 2.454-15.959-5.701V164.802c0-8.155 9.946-10.298 15.959-5.701z" fill="#fff"/>
                    </svg>
                </button>
                <button id="deleteVideo" class="delete-button" 
                        data-videoid="<?php echo esc_attr($item['guid']); ?>" 
                        data-libraryid="<?php echo esc_attr($item['videoLibraryId']); ?>">
                    <svg class="delete-icon" viewBox="0 0 24 24" width="32" height="32">
                        <path d="M5.755,20.283,4,8H20L18.245,20.283A2,2,0,0,1,16.265,22H7.735A2,2,0,0,1,5.755,20.283ZM21,4H16V3a1,1,0,0,0-1-1H9A1,1,0,0,0,8,3V4H3A1,1,0,0,0,3,6H21a1,1,0,0,0,0-2Z" fill="#ff6161"/>
                    </svg>
                </button>
            </div>
        </td>
    </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="video-upload" style="display: none;">
        <h3>Upload Video</h3>
        <form id="video-upload-form" method="post" enctype="multipart/form-data">
            <table class="upload-table">
                <tr>
                    <td><label for="video-title">Video Title</label></td>
                    <td><input type="text" id="video-title" name="video-title" required></td>
                </tr>
                <tr>
                    <td><label for="video-file">Choose Video</label></td>
                    <td><input type="file" id="video-file" name="video-file" accept="video/*" required></td>
                </tr>
            </table>
            
            <input type="hidden" name="action" value="upload_video_to_playlist">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('upload_video_nonce'); ?>">
            
            <button type="submit">Upload Video</button>
        </form>
        <div id="upload-status"></div>
    </div>
</div>
<div id="videoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="videoContainer"></div>
            </div>
        </div>
    </div>

    <style>
                .modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.8);
                    z-index: 1000;
                    opacity: 0;
                    transition: opacity 0.3s ease-in-out;
                }

                .modal.show {
                    display: block;
                    opacity: 1;
                }

                .modal-content {
                    position: relative;
                    width: 90%;
                    max-width: 800px;
                    margin: 40px auto;
                    background-color: #fff;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    transform: translateY(-20px);
                    transition: transform 0.3s ease-in-out;
                }

                .modal.show .modal-content {
                    transform: translateY(0);
                }

                .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 15px 20px;
                    border-bottom: 1px solid #eee;
                }

                .modal-title {
                    margin: 0;
                    font-size: 1.25rem;
                    color: #333;
                }

                .close-modal {
                    background: none;
                    border: none;
                    font-size: 30px;
                    font-weight: bold;
                    cursor: pointer;
                    color: #f90000;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    transition: background-color 0.2s;
                    /* justify-self: end; */
                    /* display: flex; */
                }

                .close-modal:hover {
                    background-color: #f0f0f0;
                }

                .modal-body {
                    padding: 20px;
                    aspect-ratio: 16/9;
                    position: relative;
                }

                #videoContainer {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                }

                #videoContainer iframe {
                    width: 100%;
                    height: 100%;
                    border: none;
                    border-radius: 4px;
                }

                @media (max-width: 768px) {
                    .modal-content {
                        width: 95%;
                        margin: 20px auto;
                    }

                    .modal-body {
                        padding: 10px;
                    }
                }
    </style>

    <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('videoModal');
                const videoContainer = document.getElementById('videoContainer');
                const modalTitle = document.querySelector('.modal-title');
                const playButtons = document.querySelectorAll('.play-button');
                const closeButton = document.querySelector('.close-modal');

                function openModal(videoUrl, title) {
                    const iframe = document.createElement('iframe');
                    iframe.src = videoUrl;
                    iframe.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
                    iframe.allowFullscreen = true;
                    videoContainer.innerHTML = '';
                    videoContainer.appendChild(iframe);
                    modalTitle.textContent = title;

                    modal.classList.add('show');
                    document.body.style.overflow = 'hidden';
                }

                function closeModal() {
                    modal.classList.remove('show');
                    document.body.style.overflow = '';
                    setTimeout(() => {
                        videoContainer.innerHTML = '';
                    }, 300);
                }

                playButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const videoUrl = this.dataset.videoUrl;
                        const title = this.closest('tr').querySelector('.video-title').textContent.trim();
                        openModal(videoUrl, title);
                    });
                });

                closeButton.addEventListener('click', closeModal);

                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && modal.classList.contains('show')) {
                        closeModal();
                    }
                });
            });
    </script>
<style>
            .playlist-table-wrapper {
                overflow-x: auto;
                margin: 20px 0;
            }
            button.play-button, button.delete-button{
                all:unset;
            } 
            .playlist-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
            }

            .playlist-table th,
            .playlist-table td {
                padding: 12px;
                border: 1px solid #ddd;
                text-align: left;
            }

            .playlist-table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }

            .video-row:hover {
                background-color: #f9f9f9;
            }

            .video-actions {
                width: 100px;
            }

            .action-buttons {
                display: flex;
                gap: 10px;
                justify-content: flex-start;
                align-items: center;
            }

            .playlist-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding: 10px;
                background-color: #f8f9fa;
                border-radius: 4px;
            }

            .playlist-actions {
                display: flex;
                gap: 10px;
            }

            .btn-primary {
                background-color: #007bff;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .btn-danger {
                background-color: #dc3545;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .btn-primary:hover {
                background-color: #0056b3;
            }

            .btn-danger:hover {
                background-color: #c82333;
            }

            .play-button,
            .delete-button {
                background: none;
                border: none;
                cursor: pointer;
                padding: 0;
            }

            .play-icon,
            .delete-icon {
                transition: transform 0.2s;
            }

            .play-button:hover .play-icon,
            .delete-button:hover .delete-icon {
                transform: scale(1.1);
            }
          

            .upload-table {
                width: 100%;
                margin: 20px 0;
            }

            .upload-table td {
                padding: 10px;
            }

            .upload-table label {
                font-weight: bold;
            }

            @media (max-width: 768px) {
                .playlist-header {
                    flex-direction: column;
                    gap: 10px;
                    text-align: center;
                }
                
                .playlist-actions {
                    width: 100%;
                    justify-content: center;
                }
            }
</style>
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
        $settingSData=get_user_meta($user_id, 'bunnystream_settings', true);
            
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
        $api_key = $settingSData['stream_api_key'];
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
    
        wp_send_json_success($new_playlist);
    }
    
    
    public function handle_delete_playlist() {
      
        // Verify the AJAX request nonce
        check_ajax_referer('bunnystream_nonce', 'nonce');
    
        $user_id = get_current_user_id();
        
        $settingSData=get_user_meta($user_id, 'bunnystream_settings', true);
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
        $api_key = $settingSData['stream_api_key'];
    
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
        $playlists = get_user_meta($user_id, 'bunnystream_playlists', true) ?: '';
        
        wp_send_json_success($playlists);
    }
}

// Initialize the dashboard
new BunnyStream_Dashboard();
?>