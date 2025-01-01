
jQuery(document).ready(function ($) {
    let intervalExists = false;

    $(document).on('click',  function (e) {
      
        if (!intervalExists) {
            intervalExists = true; // Prevent multiple intervals
            const inval = setInterval(function () {
                const videoTypeInput = $('input[name="video_type"]');
                const videoTypeValue = videoTypeInput.val();
                if (videoTypeValue !== "bunny_stream" && $('#aditionalLink').length>0) {
                    $('#aditionalLink').remove();
                }
              
                if (videoTypeValue === "bunny_stream") {
                    if (!$('input[name="additional_info"]').length) {
                        // Append the input field after the detected input field
                        videoTypeInput.parent('.css-fyq6mk-container').after('<input type="text" name="additional_info" id="aditionalLink" placeholder="Enter additional info">');
                    }
                    clearInterval(inval); // Stop the interval
                    intervalExists = false; // Allow new intervals if needed
                }
            }, 30);
        }
    });
});


