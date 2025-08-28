/**
 * Shared FSM implementation in TypeScript.
 * Mirrors PHP backend to maintain SSoT across contexts.
 */

export interface MachineDefinition {
    [state: string]: string[];
}

export class StateMachine {
    private state: string;
    private history: string[] = [];

    constructor(private transitions: MachineDefinition, initial: string) {
        this.state = initial;
        this.history.push(initial);
    }

    public transition(to: string): boolean {
        const allowed = this.transitions[this.state] || [];
        if (allowed.includes(to)) {
            this.state = to;
            this.history.push(to);
            return true;
        }
        return false;
    }

    public getState(): string {
        return this.state;
    }

    public getHistory(): string[] {
        return this.history;
    }
}

// Example FSM instance for dashboard visualiser.
const definition: MachineDefinition = {
    init: ['ready'],
    ready: ['running', 'error'],
    running: ['ready', 'error'],
    error: ['ready'],
};

const machine = new StateMachine(definition, 'init');

// Expose machine for debugging in admin screens.
(window as any).nhkPocMachine = machine;
