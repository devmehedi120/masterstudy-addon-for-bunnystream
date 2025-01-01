<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$lesson_id = get_query_var( 'lesson_id'  );
$video_url = get_post_meta($lesson_id, 'bunny_stream_url', true);
?>

<div class="custom-video-template">
    <?php if ( ! empty( $video_url ) ) : ?>
        <iframe 
        src="<?php echo esc_url( $video_url ); ?>" 
        width="100%" 
        height="500" 
        style="border: none;" 
        allowfullscreen>></iframe>
    <?php else : ?>
        <p>No video available.</p>
    <?php endif; ?>
</div>
