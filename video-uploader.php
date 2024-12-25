<?php

class BunnyVideoUploader {
    
    public function __construct() {
    
        add_action('wp_ajax_upload_video_to_playlist', [$this,'handle_video_upload']);
    }

    public function handle_video_upload() {
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
        $playlists = get_user_meta($user_id, 'bunnystream_playlists', true);

        if (empty($playlists) || !is_array($playlists)) {
            wp_send_json_error(['message' => 'No playlists found for the current user.']);
        }

        $first_playlist = $playlists[0];
        $library_id = $first_playlist['bunnyLibraryId'] ?? null;
        $api_key = $first_playlist['libraryApiKey'] ?? null;

        if (!$library_id || !$api_key) {
            wp_send_json_error(['message' => 'Missing Bunny Library credentials.']);
        }

        try {
            // Create video and upload
            $guid = $this->create_bunny_video($library_id, $api_key, $video_title);
            $upload_response = $this->upload_video_to_bunny($library_id, $api_key, $guid, $_FILES['video-file']);

            wp_send_json_success([
                'guid' => $guid,
                'upload_response' => $upload_response,
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        wp_die();
    }

    protected function create_bunny_video($library_id, $api_key, $title) {
        $api_url = "https://video.bunnycdn.com/library/$library_id/videos";
        
        $response = wp_remote_post($api_url, [
            'headers' => [
                'AccessKey' => $api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['title' => $title]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Failed to create video: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (!in_array($response_code, [200, 201])) {
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

        $api_url = "https://video.bunnycdn.com/library/$library_id/videos/$guid";
        $file_path = $file['tmp_name'];

        // Verify file exists and is readable
        if (!is_readable($file_path)) {
            throw new Exception('Cannot read uploaded file.');
        }

        // Read the file contents
        $file_contents = file_get_contents($file_path);
        if ($file_contents === false) {
            throw new Exception('Failed to read the uploaded file.');
        }

        // Perform the upload using wp_remote_request
        $response = wp_remote_request($api_url, [
            'method'    => 'PUT',
            'headers'   => [
                'AccessKey'    => $api_key,
                'Content-Type' => 'application/octet-stream',
            ],
            'body'      => $file_contents,
            'timeout'   => 3600, // 1-hour timeout
        ]);

        // Check for request errors
        if (is_wp_error($response)) {
            throw new Exception('Upload failed: ' . $response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code !== 200 && $http_code !== 201) {
            throw new Exception('Upload failed with HTTP code ' . $http_code . ': ' . $response_body);
        }

        $response_data = json_decode($response_body);
        if (!$response_data || isset($response_data->error)) {
            throw new Exception('API error: ' . ($response_data->error ?? 'Unknown error'));
        }

        return $response_data;
}


    protected function get_upload_error_message($error_code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];

        return $messages[$error_code] ?? 'Unknown upload error';
    }
}

// Initialize the class
new BunnyVideoUploader();
