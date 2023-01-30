<?php
/**
 * Plugin Name: Image Colour Extractor
 * Plugin URI: #
 * Description: Plugin to pull the primary colour from an image on upload / save, and store it in postmeta
 * Version: 1.0.0
 * Author: Dave Welch
 * Author URI: https://velocitypartners.com
 */

include_once("colors.inc.php");

$ICE_debugging = false;

function add_color_extractor_scripts( $hook ) {

	wp_register_script( 'color_extractor_js', esc_url( plugins_url( 'js/color_extractor.js', __FILE__ ) ), array( 'jquery' ) );
	wp_register_style( 'color_extractor_css', esc_url( plugins_url( 'css/color_extractor.css', __FILE__ ) ), false, '1.0.0' );

	wp_enqueue_style( 'color_extractor_css' );
	if ( $hook == 'post.php' ){
		wp_enqueue_script( 'color_extractor_js' );
	}
}

add_action( 'admin_enqueue_scripts', 'add_color_extractor_scripts' );


function add_primary_color_after_upload($post_ID) {
    global $ICE_debugging;
    if ($ICE_debugging) _icelog('running add attachment filter');
    $src = wp_get_attachment_image_src( $post_ID, 'thumbnail' )[0];
	$color = generate_primary_color_from_image($src);
    if ($ICE_debugging) _icelog('return from color fetch was ' . $color);
	if ($color) add_post_meta( $post_ID, 'primary_color', $color, true ) || update_post_meta( $post_ID, 'primary_color', $color );
    return $post_ID;
}

add_filter( 'add_attachment', 'add_primary_color_after_upload', 10, 2);


function attachment_field_primary_color( $form_fields, $post ) {

    //_icelog( 'current screen is: ');
    //_icelog( get_current_screen() );
    $current_screen = get_current_screen();
    $color_hex = get_post_meta( $post->ID, 'primary_color', true );
	$form_fields['primary_color'] = array(
		'label' => 'Primary Image Color',
		'input' => 'text',
		'value' => $color_hex,
		'helps'	=> '</p><div class="swatch" style="background-color: #' . $color_hex . '">#'.$color_hex.'</div>'
	);

    if ( $current_screen->parent_base == 'upload' && $current_screen->id == 'attachment' ){
        $src = wp_get_attachment_url( $post->ID );
        $colors = generate_primary_color_from_image( $src, false );
        $form_fields[ 'colors' ] = array(
            'label' => 'Alternative Color Options',
            'input' => 'html',
            'html'  => "<div class='swatch-wrapper'>"
        );
        foreach ( $colors as $color ){
            $form_fields[ 'colors' ]['html'] .= '<div class="swatch" style="background-color: #' . $color . '">#'.$color.'</div>';
        }
        $form_fields[ 'colors' ]['html'] .= '</div>';
    }

	return $form_fields;
}

add_filter( 'attachment_fields_to_edit', 'attachment_field_primary_color', 10, 2 );

function attachment_field_primary_color_save( $post, $attachment ) {
    global $ICE_debugging;
	if( ! empty( $attachment['primary_color'] ) ){
	    if ($ICE_debugging) _icelog( 'storing new color metadata' );
		update_post_meta( $post['ID'], 'primary_color', $attachment['primary_color'] );
	} else {
	    if ($ICE_debugging) _icelog( 'no current color metadata was found' );
		$color = generate_primary_color_from_image( $post['ID'] );
		if ($color) update_post_meta( $post['ID'], 'primary_color', $color );
	}

	return $post;
}

add_filter( 'attachment_fields_to_save', 'attachment_field_primary_color_save', 10, 2 );

function generate_primary_color_from_image( $image, $single = true ){
    global $ICE_debugging;

    if ( $ICE_debugging ) {
        _icelog( 'original image' );
        _icelog( $image );
    }
    // keep a copy of the original so we can log it if something goes wrong
    $image_path = $image;

    // if it's an image id, convert it to the source URL
	if ( is_int( $image_path ) ){
        $image_path = wp_get_attachment_url( $image_path );
	}

    // bail if it's not an image path string
    if ( ! is_string( $image_path ) ) {
        _icelog( 'bad source image during color extraction' );
        _icelog( $image );
		return false;
    }

    $base = wp_upload_dir();
    $home_url = get_home_url();

    // check if it's an absolute path for this site and convert it to a relative path
    if ( substr( $image, 0, 1 ) === $home_url ){
        $image_path = str_replace( $home_url, '/', $image_path );
    }

    // check if it starts with a / (relative paths)
    if( substr( $image_path, 0, 1 ) === "/" ){
        $image_path = str_replace( $base['baseurl'], $base['basedir'], $image_path );
    } else {
        _icelog( 'bad source image during color extraction' );
        _icelog( $image );
        _icelog( $image_path );
        return false;
    }

    // get extension of image path to check if it's valid
    $path_parts = pathinfo( $image_path );
    if ( ! isset( $path_parts['extension'] ) || in_array( $path_parts['extension'], array( 'svg', 'png' ) ) ){
        _icelog( 'skipping processing of image that was .svg or .png' );
        _icelog( $image );
        _icelog( $image_path );
        return false;
    }

    if ( ! $image_path ){
        _icelog( 'bad image path during color extraction' );
        _icelog( $image );
        return false;
    }

    if ( $ICE_debugging ) _icelog( 'fetching colour on : ' . $image_path );

    $num_results = 5;
    $delta = 15;
    $reduce_brightness = 1;
    $reduce_gradients = 1;
    $ex = new GetMostCommonColors();
    $colors = $ex->Get_Color( $image_path, $num_results, $reduce_brightness, $reduce_gradients, $delta );

    if ( empty( $colors ) ){
        _icelog( 'bad result after color extraction' );
        _icelog( $image_path );
        return false;
    }

    if ($ICE_debugging) {
        _icelog( 'using new class - top 5 cols');
        _icelog( $colors );
    }

    if ( $single ){
        $primary_color = array_key_first( $colors );
        _log( 'primary color was : ' . $primary_color );
    } else {
        // return the array
        $primary_color = array_keys( $colors );
    }

	return $primary_color;
}

function get_image_primary_color($image_id){
    global $ICE_debugging;
	if ( $color = get_post_meta( $image_id, 'primary_color', true ) ){
		if ($ICE_debugging) _icelog('using existing meta: ' . $color);
	} else {
		if ($ICE_debugging) _icelog('no existing meta found - generating new meta');
		$color = generate_primary_color_from_image($image_id);
		if ($color) update_post_meta( $image_id, 'primary_color', $color );
	}

    // ensure color is always returned with # prefix
    if ( substr( $color, 0, 1 ) !== "#" ) $color = '#' . $color;

	return $color;
}

function _icelog( $message ) {
    error_log( print_r( $message, true ) );
}
