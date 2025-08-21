<?php
/**
 * State manager handling plugin states for repositories.
 *
 * @package SBI\Services
 */

namespace SBI\Services;

use SBI\Enums\PluginState;

/**
 * Central arbiter for repository states.
 */
class StateManager {
    /**
     * Repository states.
     *
     * @var array<string, PluginState>
     */
    protected array $states = [];

    /**
     * Get repository state.
     */
    public function get_state(string $repository): PluginState {
        return $this->states[$repository] ?? PluginState::UNKNOWN;
    }

    /**
     * Set repository state.
     */
    public function set_state(string $repository, PluginState $state): void {
        $this->states[$repository] = $state;
    }
}
