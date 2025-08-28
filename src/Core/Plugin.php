<?php
namespace NHK\Poc\Core;

use NHK\Poc\Core\FSM\StateMachine;
use NHK\Poc\Admin\Menu;
use NHK\Poc\Api\AjaxHandler;

/**
 * Main plugin bootstrapper.
 * Orchestrates FSM and acts as the SSoT container for global state.
 */
class Plugin {
    /** @var self */
    private static $instance;

    /** @var StateMachine */
    private $sm;

    private function __construct() {
        // Define a simple FSM shared across plugin features.
        $definition = [
            'init'    => ['ready'],
            'ready'   => ['running', 'error'],
            'running' => ['ready', 'error'],
            'error'   => ['ready'],
        ];
        $this->sm = new StateMachine( $definition, 'init' );
    }

    /**
     * Singleton accessor.
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Boot plugin features.
     */
    public function boot(): void {
        // Transition from init -> ready once plugin loads.
        $this->sm->transition( 'ready' );

        // Register admin UI & AJAX handlers through FSM-aware classes.
        Menu::init( $this->sm );
        AjaxHandler::init( $this->sm );

        // Optional on-screen debug panel in admin when query arg present.
        if ( isset( $_GET['nhk_poc_debug'] ) ) {
            add_action( 'admin_notices', [ $this, 'debug_panel' ] );
        }
    }

    /**
     * Expose current state machine.
     */
    public function sm(): StateMachine {
        return $this->sm;
    }

    /**
     * Render minimal debug info showing state history.
     */
    public function debug_panel(): void {
        echo '<div class="notice notice-info"><p>FSM History: ' . esc_html( implode( ' → ', $this->sm->history() ) ) . '</p></div>';
    }
}
