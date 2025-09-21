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
        return $wpdb->get_row( 
            $wpdb->prepare( 
                "SELECT playback_id, is_live FROM $table_name WHERE stream_id = %s",
                $stream_id
            )
        );
    }
}

$info = get_playback_id($attributes);

?>
<p <?php echo get_block_wrapper_attributes(); ?>>
    <?php if ( ! is_null( $info ) ) : ?>
        <script src="https://cdn.jsdelivr.net/npm/@mux/mux-player"></script>
        <mux-player
            playback-id="<?php echo esc_attr( $info->playback_id ); ?>"
            poster="https://image.mux.com/<?php echo esc_attr( $info->playback_id ); ?>/thumbnail.jpg<?php echo $info->is_live ? '?latest=true' : ''; ?>" 
        ></mux-player>
    <?php else : ?>
        <span>No playback ID available for this stream.</span>
    <?php endif; ?>
</p>
