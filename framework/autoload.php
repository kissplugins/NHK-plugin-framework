<?php
/**
 * NHK Framework Autoloader
 * 
 * Simple PSR-4 autoloader for the NHK Framework classes.
 * 
 * @package NHK\Framework
 * @since 1.0.0
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * PSR-4 autoloader for NHK Framework classes
 */
spl_autoload_register( function ( $class ) {
    $prefix   = 'NHK\\Framework\\';
    $base_dir = __DIR__ . '/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );
