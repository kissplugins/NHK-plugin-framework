<?php
/**
 * Generic Finite State Machine service for NHK Framework
 *
 * @package NHK\Framework\Core
 * @since 1.0.0
 */

namespace NHK\Framework\Core;

use InvalidArgumentException;

/**
 * Simple Finite State Machine implementation.
 *
 * Allows defining states, transitions and callbacks for state changes.
 */
class FiniteStateMachine {
    /**
     * Registered states.
     *
     * @var array
     */
    protected array $states = [];

    /**
     * Transition map of allowed target states for each state.
     *
     * @var array
     */
    protected array $transitions = [];

    /**
     * Current state.
     *
     * @var string
     */
    protected string $current_state = '';

    /**
     * Callbacks executed when entering a state.
     *
     * @var array
     */
    protected array $callbacks = [];

    /**
     * Define available states.
     *
     * @param array $states List of states.
     * @return void
     */
    public function set_states(array $states): void {
        $this->states = $states;
    }

    /**
     * Define allowed transitions.
     *
     * Example: ['pending' => ['scheduled']].
     *
     * @param array $transitions Transition map.
     * @return void
     */
    public function set_transitions(array $transitions): void {
        $this->transitions = $transitions;
    }

    /**
     * Set the initial state.
     *
     * @param string $state Initial state.
     * @return void
     *
     * @throws InvalidArgumentException If state is invalid.
     */
    public function set_initial_state(string $state): void {
        if (!in_array($state, $this->states, true)) {
            throw new InvalidArgumentException("Invalid initial state: {$state}");
        }
        $this->current_state = $state;
    }

    /**
     * Register a callback for a state entry.
     *
     * @param string   $state    State name.
     * @param callable $callback Callback executed on entry.
     * @return void
     */
    public function on(string $state, callable $callback): void {
        $this->callbacks[$state][] = $callback;
    }

    /**
     * Get current state.
     *
     * @return string
     */
    public function get_state(): string {
        return $this->current_state;
    }

    /**
     * Determine if transition is allowed.
     *
     * @param string $to Target state.
     * @return bool
     */
    public function can_transition(string $to): bool {
        $allowed = $this->transitions[$this->current_state] ?? [];
        return in_array($to, $allowed, true);
    }

    /**
     * Transition to a new state.
     *
     * Executes registered callbacks on state entry.
     *
     * @param string $to Target state.
     * @return void
     *
     * @throws InvalidArgumentException If transition is invalid.
     */
    public function transition_to(string $to): void {
        if (!$this->can_transition($to)) {
            throw new InvalidArgumentException(
                sprintf('Invalid transition from %s to %s', $this->current_state, $to)
            );
        }

        $this->current_state = $to;

        if (!empty($this->callbacks[$to])) {
            foreach ($this->callbacks[$to] as $callback) {
                call_user_func($callback, $to);
            }
        }
    }
}
