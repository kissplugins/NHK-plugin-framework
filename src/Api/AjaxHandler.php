<?php
namespace NHK\Poc\Api;

use NHK\Poc\Core\FSM\StateMachine;

/**
 * Central AJAX handler.
 * Provides discrete error responses highlighting failing steps.
 */
class AjaxHandler {
    public static function init( StateMachine $sm ): void {
        add_action( 'wp_ajax_nhk_poc_transition', function () use ( $sm ) {
            try {
                $next = sanitize_text_field( $_POST['next'] ?? '' );

                if ( ! $sm->transition( $next ) ) {
                    throw new \Exception( 'Invalid transition to ' . $next );
                }

                wp_send_json_success( [
                    'state'   => $sm->state(),
                    'history' => $sm->history(),
                ] );
            } catch ( \Throwable $e ) {
                error_log( 'NHK PoC AJAX error: ' . $e->getMessage() );
                wp_send_json_error( [
                    'message' => $e->getMessage(),
                    'history' => $sm->history(),
                ] );
            }
        } );
    }
}
