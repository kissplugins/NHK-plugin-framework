<?php
namespace NHK\Poc\Core\FSM;

/**
 * Minimal finite state machine implementation.
 * Acts as the SSoT for any feature that flows through it.
 */
class StateMachine {
    /** @var array<string, array<int, string>> */
    private $transitions;

    /** @var string */
    private $state;

    /** @var array<int, string> */
    private $history = [];

    public function __construct( array $transitions, string $initial ) {
        $this->transitions = $transitions;
        $this->state       = $initial;
        $this->history[]  = $initial;
    }

    /**
     * Move to a new state if the transition is allowed.
     */
    public function transition( string $to ): bool {
        $allowed = $this->transitions[ $this->state ] ?? [];
        if ( in_array( $to, $allowed, true ) ) {
            $this->state      = $to;
            $this->history[]  = $to;
            return true;
        }
        return false;
    }

    /**
     * Current state getter.
     */
    public function state(): string {
        return $this->state;
    }

    /**
     * Returns the transition history for debugging and traceability.
     */
    public function history(): array {
        return $this->history;
    }

    /**
     * Serialized representation for passing to the frontend.
     */
    public function serialize(): array {
        return [
            'state'       => $this->state,
            'history'     => $this->history,
            'transitions' => $this->transitions,
        ];
    }
}
