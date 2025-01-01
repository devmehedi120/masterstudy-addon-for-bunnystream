jQuery(document).ready(function($) {
   
    // Load playlists on page load
    
 
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
        $('body').append(`
          <style>
                        .loader-container {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.5);
                            z-index: 9999;
                        }

                        .spinner {
                            width: 50px;
                            height: 50px;
                            border: 8px solid #f3f3f3;
                            border-top: 8px solid #0ac6f5;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                        }

                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
        <div class="loader-container" >
           <div class="spinner"></div>
       </div>
       `);
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
                   
                  
                }
                location.reload()
                $(window).on('load', function() {
                    // Set a delay if needed after the page reload
                        $('.loader-container').hide().remove();
                     // Wait 5 seconds after the page has loaded before removing the loader
                });
            }
        });
    });

    // Delete playlist
    $(document).on('click', '.delete-playlist', function() {
        const playlistId = $(this).data('playlist-id');
        if (!confirm('Are you sure you want to delete this playlist?')) return;
        $('body').append(`
            <style>
                        .loader-container {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.5);
                            z-index: 9999;
                        }

                        .spinner {
                            width: 50px;
                            height: 50px;
                            border: 8px solid #f3f3f3;
                            border-top: 8px solid #0ac6f5;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                        }

                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
        <div class="loader-container" >
           <div class="spinner"></div>
       </div>
       `);
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
                    location.reload()                   
                }
                location.reload()
                $(window).on('load', function() {
                    // Set a delay if needed after the page reload
                        $('.loader-container').hide().remove();
                     // Wait 5 seconds after the page has loaded before removing the loader
                });
            }
        })
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
                        ${renderVideos(playlist)}
                    </div>
                </div>
            `;
            container.append(playlistHtml);
    }

    function renderVideos(playlist) {
        console.log(playlist);
    
        // Check if the playlist has videos
        if (!playlist.videos || !playlist.videos.length) {
            return '<p>No videos in this playlist</p>';
        }
    
        // Include the playlist title
        const playlistTitle = `<h3>${playlist.name}</h3>`;
    
        // Render video items
        const videosHtml = playlist.videos.map(function (video) {
            const videoUrl = `https://video.bunnycdn.com/play/${playlist.bunnyLibraryId}/${video.videoId}`;
            return `
                <div class="bunny_video-item">                    
                     <div class="bunny_video-player"> 
                     <span class="bunnyVideoHeading">${video.videoTitle}</span>                   
                    <a href="${videoUrl}" target="_blank"> 
                    <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="20"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <circle style="fill:#00CC96;" cx="255.95" cy="255.95" r="255.95"></circle> <path style="fill:#07B587;" d="M509.923,223.123c1.351,10.803,2.077,21.71,2.077,32.928C512,397.426,397.426,512,256.052,512 c-11.115,0-22.126-0.727-32.928-2.077L81.957,368.756V143.347h348.189L509.923,223.123z"></path> <rect x="81.85" y="191.34" style="fill:#F7F7F8;" width="348.19" height="129.32"></rect> <path style="fill:#E6E6E6;" d="M430.043,255.948v64.714H81.853v-64.714H430.043L430.043,255.948z"></path> <path style="fill:#006775;" d="M430.043,191.338v-47.991H81.853v47.991h40.096v129.325H81.853v47.99h348.189v-47.99h-40.096V191.338 H430.043z M290.539,153.423h28.566v25.969h-28.566V153.423z M361.381,191.338v129.325H151.45V191.338H361.381z M242.029,153.423 h28.566v25.969h-28.566V153.423z M193.519,153.423h28.566v25.969h-28.566V153.423z M145.009,153.423h28.566v25.969h-28.566V153.423z M124.546,358.577H95.98v-25.969h28.566C124.546,332.608,124.546,358.577,124.546,358.577z M124.546,179.392H95.98v-25.969h28.566 C124.546,153.423,124.546,179.392,124.546,179.392z M173.368,358.577h-28.566v-25.969h28.566V358.577z M221.877,358.577h-28.566 v-25.969h28.566V358.577z M270.386,358.577H241.82v-25.969h28.566V358.577z M318.896,358.577H290.33v-25.969h28.566V358.577z M367.406,358.577H338.84v-25.969h28.566V358.577z M367.613,179.392h-28.566v-25.969h28.566V179.392z M415.916,332.608v25.969 H387.35v-25.969H415.916z M387.557,179.392v-25.969h28.566v25.969H387.557z"></path> <path style="fill:#E84F4F;" d="M233.304,209.101l50.483,42.589c2.493,2.077,2.493,6.44,0,8.621L233.304,302.9 c-3.013,2.493-6.959,1.454-6.959-2.701c0-28.774,0-59.625,0-88.397C226.343,207.646,230.291,206.503,233.304,209.101z"></path> <path style="fill:#CA4545;" d="M285.657,255.948c0,1.662-0.623,3.22-1.87,4.259l-50.483,42.589 c-3.013,2.493-6.959,1.454-6.959-2.701v-44.147H285.657L285.657,255.948z"></path> </g></svg>
                    </a>
                     <span id="deleteVideo" data-videoId="${video.videoId}" data-libraryId="${playlist.bunnyLibraryId}" > 
                     <svg fill="#ff6161" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="#ff6161"><g id="SVGRepo_bgCarrier" stroke-width="20"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M5.755,20.283,4,8H20L18.245,20.283A2,2,0,0,1,16.265,22H7.735A2,2,0,0,1,5.755,20.283ZM21,4H16V3a1,1,0,0,0-1-1H9A1,1,0,0,0,8,3V4H3A1,1,0,0,0,3,6H21a1,1,0,0,0,0-2Z"></path></g></svg>
                     </span>
                </div>
               
                </div>
            `;
        }).join('');
    
        return playlistTitle + videosHtml;
    }
    

    // Show upload form when clicking upload button
    $(document).on('click', '.upload-video', function() {
        const playlistId = $(this).data('playlist-id');
        $('.video-upload').data('playlist-id', playlistId).show();
    });
});

jQuery(document).ready(function ($) {   
   
    $(document).on('submit', '#video-upload-form', async function (e) {
          e.preventDefault();
       
     
        // Prepare form data
        var formData = new FormData(this);
        formData.append('action', 'upload_video_to_playlist'); // Add the action parameter
        formData.append('nonce', videoUploadParams.nonce); // Add nonce for security

        // Disable the submit button and show upload status
        $('#upload-status').text('Uploading video...');
        $('#video-upload-form button[type="submit"]').prop('disabled', true);
        $('body').append(`
            <style>
                        .loader-container {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.5);
                            z-index: 9999;
                        }

                        .spinner {
                            width: 50px;
                            height: 50px;
                            border: 8px solid #f3f3f3;
                            border-top: 8px solid #0ac6f5;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                        }

                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
        <div class="loader-container" >
           <div class="spinner"></div>
       </div>
       `);

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
            location.reload()
            $(window).on('load', function() {
                // Set a delay if needed after the page reload
                    $('.loader-container').hide().remove();
                 // Wait 5 seconds after the page has loaded before removing the loader
            });
           
        }
       
    });

    $(document).on('click', '#deleteVideo', async function (e) {
        e.preventDefault();
        const videoId = $(this).data('videoid'); // Access data attributes
        const libraryId = $(this).data('libraryid'); // Access data attributes
    
        console.log({ videoId, libraryId }); // Log the attributes as an object
        $('body').append(`
            <style>
                        .loader-container {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.5);
                            z-index: 9999;
                        }

                        .spinner {
                            width: 50px;
                            height: 50px;
                            border: 8px solid #f3f3f3;
                            border-top: 8px solid #0ac6f5;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                        }

                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
        <div class="loader-container" >
           <div class="spinner"></div>
       </div>
       `);

        $.ajax({
            url: bunnyStreamAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_video',
                nonce: bunnyStreamAjax.nonce,
                videoId:videoId,
                libraryId:libraryId
            },
            success: function(response) {
                if (response.success) {
                                       
                    location.reload()
                    $(window).on('load', function() {
                       
                            $('.loader-container').hide().remove();
                       
                    });
                    
                }
            }
            
        });
    });
    
    $(document).ready(function() {
        // Using MutationObserver to detect changes in the select value
        const targetNode = document.body;
        const config = { childList: true, subtree: true };
    
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.classList && mutation.target.classList.contains('css-1koxkxg-singleValue')) {
                    const selectedValue = mutation.target.textContent;
                    console.log('Selected value:', selectedValue);
                    
                    // If Bunny Stream is selected
                    if (selectedValue === 'Bunny Stream') {
                        // Add your logic here for when Bunny Stream is selected
                        const inputField = `<div class="bunny-stream-url">
                            <input type="text" 
                                   class="form-control" 
                                   name="bunny_stream_url" 
                                   placeholder="Enter Bunny Stream URL">
                        </div>`;
                        
                        // Insert the input field after the select
                        if (!$('.bunny-stream-url').length) {
                            $('.css-14h82uv-control').closest('div').after(inputField);
                        }
                    }
                }
            });
        });
    
        observer.observe(targetNode, config);
    });

    $(document).ready(function () {
        const parentElement = document.querySelector('.css-14h82uv-control'); // Adjust this selector based on your HTML structure
    
        if (parentElement) {
            // Create a MutationObserver
            const observer = new MutationObserver(() => {
                const targetElement = document.querySelector('.css-1koxkxg-singleValue');
                if (targetElement) {
                    const selectedValue = targetElement.textContent.trim();
                    console.log('Updated Source text:', selectedValue);
                }
            });
    
            // Observe the parent element for child changes
            observer.observe(parentElement, { childList: true, subtree: true });
        } else {
            console.log('Parent element not found.');
        }
    });
    


    // let video = $(".masterstudy-course-player-lesson-video__wrapper").find("video");
    // let source = video.find("source").attr("src");
    // $(".masterstudy-course-player-lesson-video__wrapper").html(`<iframe 
    //     src="${source}" 
    //     width="100%" 
    //     height="500" 
    //     style="border: none;" 
    //     allowfullscreen>
    // </iframe>`);
    
});


