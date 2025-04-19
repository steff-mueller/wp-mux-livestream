<?php

namespace WpMuxLivestream;

if ( ! function_exists( __NAMESPACE__ . '\get_playback_id' ) ) {
    function get_playback_id($attributes)
    {
        $stream_id = $attributes['streamId'];
        if ( !isset( $stream_id ) || empty( $stream_id ) )
        {
            return '';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mux_livestreams';
        return $wpdb->get_var( 
            $wpdb->prepare( 
                "SELECT playback_id FROM $table_name WHERE stream_id = %s",
                $stream_id
            )
        );
    }
}

$playback_id = get_playback_id($attributes);

?>
<p <?php echo get_block_wrapper_attributes(); ?>>
    <?php if ( ! empty( $playback_id ) ) : ?>
        <script src="https://cdn.jsdelivr.net/npm/@mux/mux-player"></script>
        <mux-player
            playback-id="<?php echo esc_attr( $playback_id ); ?>"
            metadata-video-title="Placeholder (optional)"
            metadata-viewer-user-id="Placeholder (optional)"
            primary-color="#ffffff"
            secondary-color="#000000"
            accent-color="#fa50b5"
        ></mux-player>
    <?php else : ?>
        <span>No playback ID available for this stream.</span>
    <?php endif; ?>
</p>
