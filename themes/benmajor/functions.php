<?php

micro_register_shortcode('test_shortcode', function( $attributes, $content ) {
    return '<p><b>Wow, this is pretty cool!!!</b></p>';
});