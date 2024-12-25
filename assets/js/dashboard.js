jQuery(document).ready(function($) {
    $('body').append(`
        <div class="loader-overlay">
            <div class="loader"></div>
        </div>
    `);
    // Load playlists on page load
    loadPlaylists();
 
    function showLoader() {
        $('.loader-overlay').fadeIn(200);
    }

    function hideLoader() {
        $('.loader-overlay').fadeOut(200);
    }

    // Create playlist
    $('#create-playlist').on('click', function() {
        const playlistName = $('#playlist-name').val();
        if (!playlistName) return;
        showLoader();
        $.ajax({
            url: bunnyStreamAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'create_playlist',
                nonce: bunnyStreamAjax.nonce,
                playlist_name: playlistName
            },
            success: function(response) {
                if (response.success) {
                    $('#playlist-name').val('');
                    loadPlaylists();
                    hideLoader();
                }
            }
        });
    });

    // Delete playlist
    $(document).on('click', '.delete-playlist', function() {
        const playlistId = $(this).data('playlist-id');
        if (!confirm('Are you sure you want to delete this playlist?')) return;
        showLoader();
        $.ajax({
            url: bunnyStreamAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_playlist',
                nonce: bunnyStreamAjax.nonce,
                playlist_id: playlistId
            },
            success: function(response) {
                if (response.success) {
                    loadPlaylists();
                    hideLoader()
                }
            }
        });
    });

    function loadPlaylists() {
        $.ajax({
            url: bunnyStreamAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_playlists',
                nonce: bunnyStreamAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderPlaylists(response.data);
                }
            }
        });
    }

    function renderPlaylists(playlist) {
        const container = $('#playlists-container');
        container.empty();
console.log(playlist);
        
            const playlistHtml = `
                <div class="playlist-item">
                    <div class="playlist-header">
                        <h4>${playlist.name}</h4>
                        <div class="playlist-actions">
                            <button class="upload-video" data-playlist-id="${playlist.id}">Upload Video</button>
                            <button class="delete-playlist" data-playlist-id="${playlist.id}">Delete Playlist</button>
                        </div>
                    </div>
                    <div class="playlist-videos">
                        ${renderVideos(playlist.videos)}
                    </div>
                </div>
            `;
            container.append(playlistHtml);
    }

    function renderVideos(videos) {
        if (!videos.length) return '<p>No videos in this playlist</p>';
           const videoUrl=`https://video.bunnycdn.com/play/${videos.bunnyLibraryId}/${videos.libraryApiKey}`
        return videos.map(function(video) {
            return `
                <div class="video-item">
                    <h5>${video.videoTitle}</h5>
                    <small>Uploaded: ${video.videoId}</small>
                    <div class="video-player">
                        <a src="${videoUrl}" controls></a>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Show upload form when clicking upload button
    $(document).on('click', '.upload-video', function() {
        const playlistId = $(this).data('playlist-id');
        $('.video-upload').data('playlist-id', playlistId).show();
    });
});

jQuery(document).ready(function ($) {
    $('body').append(`
        <div class="loader-overlay">
            <div class="loader"></div>
        </div>
    `);
   
    $(document).on('submit', '#video-upload-form', async function (e) {
          e.preventDefault();
          function showLoader() {
            $('.loader-overlay').fadeIn(200);
        }
    
        function hideLoader() {
            $('.loader-overlay').fadeOut(200);
        }
     
        // Prepare form data
        var formData = new FormData(this);
        formData.append('action', 'upload_video_to_playlist'); // Add the action parameter
        formData.append('nonce', videoUploadParams.nonce); // Add nonce for security

        // Disable the submit button and show upload status
        $('#upload-status').text('Uploading video...');
        $('#video-upload-form button[type="submit"]').prop('disabled', true);

        try {
            // Perform the AJAX call using fetch
            const response = await fetch(videoUploadParams.ajax_url, {
                method: 'POST',
                body: formData,
            });

            // Parse the JSON response
            const result = await response.json();
          
            
            // Handle the server response
            if (result.success) {
                $('#upload-status').html(
                    `<span style="color: green;">Video uploaded successfully. GUID: ${result.data.guid}</span>`
                );
                
            } else {
                $('#upload-status').html(
                    `<span style="color: red;">Error: ${result.data.message || 'Unknown error occurred.'}</span>`
                );
            }
        } catch (error) {
            // Handle errors during the AJAX request
            $('#upload-status').html(
                `<span style="color: red;">An error occurred: ${error.message || 'Unknown error.'}</span>`
            );
        } finally {
            // Re-enable the submit button after the request is complete
            $('#video-upload-form button[type="submit"]').prop('disabled', false);
        }
       
    });
});


