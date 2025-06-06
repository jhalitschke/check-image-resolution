<?php
/*
Plugin Name: Check Image Resolution
Description: Checks if the uploaded image has a DPI greater than 96 and if it is not in RGB color model.
Version: 1.0
Author: jhalitschke
*/

// Check for Gmagick extension on plugin activation
register_activation_hook(__FILE__, 'check_gmagick_extension_on_activation');

function check_gmagick_extension_on_activation() {
    if (!extension_loaded('gmagick')) {
        update_option('check_image_resolution_gmagick_missing', 1);
    } else {
        delete_option('check_image_resolution_gmagick_missing');
    }
}

// Show admin notice if Gmagick is missing
add_action('admin_notices', 'show_gmagick_missing_admin_notice');
function show_gmagick_missing_admin_notice() {
    if (get_option('check_image_resolution_gmagick_missing')) {
        echo '<div class="notice notice-error"><p><strong>Check Image Resolution:</strong> The <code>Gmagick</code> PHP extension is not installed or enabled. This plugin will not function correctly without it.</p></div>';
    }
}

// Optionally, clear the flag once the notice has been shown and Gmagick is available
add_action('admin_init', function() {
    if (extension_loaded('gmagick') && get_option('check_image_resolution_gmagick_missing')) {
        delete_option('check_image_resolution_gmagick_missing');
    }
});

add_action( 'wp_handle_upload_prefilter', 'check_image_properties' );

function check_image_properties( $image ) {
    $dpi_check = check_image_dpi( $image );
    if ( is_wp_error( $dpi_check ) ) {
        add_upload_error_notice( $dpi_check->get_error_message() );
        return $dpi_check;
    }

    $color_model_check = check_image_color_model( $image );
    if ( is_wp_error( $color_model_check ) ) {
        add_upload_error_notice( $color_model_check->get_error_message() );
        return $color_model_check;
    }

    return $image;
}

function check_image_dpi( $image ) {
    if (!extension_loaded('gmagick')) {
        return new WP_Error( 'gmagick_missing', 'Gmagick PHP extension is not loaded.' );
    }
    $gmagick = new Gmagick( $image['file'] );
    // Try to get density property, fallback to 0 if not available
    $density = $gmagick->getimageproperty('density'); // e.g. "300x300"
    if ($density) {
        $dpi = explode('x', $density);
        $x = isset($dpi[0]) ? (int)$dpi[0] : 0;
        $y = isset($dpi[1]) ? (int)$dpi[1] : 0;
    } else {
        $x = $y = 0;
    }
    if ( $x > 96 || $y > 96 ) {
        return new WP_Error( 'dpi_error', 'Image DPI resolution is greater than 96.' );
    }

    return $image;
}

function check_image_color_model( $image ) {
    if (!extension_loaded('gmagick')) {
        return new WP_Error( 'gmagick_missing', 'Gmagick PHP extension is not loaded.' );
    }
    $gmagick = new Gmagick( $image['file'] );
    $colorSpace = $gmagick->getimagecolorspace();
    if ( $colorSpace != Gmagick::COLORSPACE_RGB ) {
        return new WP_Error( 'cmyk_error', 'Image is not in RGB color model.' );
    }

    return $image;
}

function add_upload_error_notice( $message ) {
    add_action( 'admin_notices', function() use ( $message ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
    } );
}
