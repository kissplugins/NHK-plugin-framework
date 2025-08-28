"use strict";
/**
 * Shared FSM implementation in TypeScript.
 * Mirrors PHP backend to maintain SSoT across contexts.
 */
Object.defineProperty(exports, "__esModule", { value: true });
exports.StateMachine = void 0;
class StateMachine {
    constructor(transitions, initial) {
        this.transitions = transitions;
        this.history = [];
        this.state = initial;
        this.history.push(initial);
    }
    transition(to) {
        const allowed = this.transitions[this.state] || [];
        if (allowed.includes(to)) {
            this.state = to;
            this.history.push(to);
            return true;
        }
        return false;
    }
    getState() {
        return this.state;
    }
    getHistory() {
        return this.history;
    }
}
exports.StateMachine = StateMachine;
// Example FSM instance for dashboard visualiser.
const definition = {
    init: ['ready'],
    ready: ['running', 'error'],
    running: ['ready', 'error'],
    error: ['ready'],
};
const machine = new StateMachine(definition, 'init');
// Expose machine for debugging in admin screens.
window.nhkPocMachine = machine;
