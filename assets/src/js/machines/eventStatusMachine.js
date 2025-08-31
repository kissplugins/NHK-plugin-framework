/**
 * Event Status State Machine
 * 
 * Manages event status transitions and business rules using XState.
 */

import { createMachine, assign } from 'xstate';

export const eventStatusMachine = createMachine({
    id: 'eventStatus',
    initial: 'draft',
    context: {
        eventId: null,
        statusHistory: [],
        lastStatusChange: null,
        canEdit: true,
        canDelete: true,
        canPublish: true
    },
    states: {
        draft: {
            on: {
                PUBLISH: {
                    target: 'published',
                    actions: 'recordStatusChange'
                },
                DELETE: {
                    target: 'deleted',
                    actions: 'recordStatusChange'
                }
            }
        },
        
        published: {
            on: {
                UNPUBLISH: {
                    target: 'draft',
                    actions: 'recordStatusChange'
                },
                CANCEL: {
                    target: 'cancelled',
                    actions: 'recordStatusChange'
                },
                COMPLETE: {
                    target: 'completed',
                    actions: 'recordStatusChange',
                    cond: 'eventHasEnded'
                }
            }
        },
        
        cancelled: {
            on: {
                REPUBLISH: {
                    target: 'published',
                    actions: 'recordStatusChange',
                    cond: 'canRepublish'
                }
            }
        },
        
        completed: {
            type: 'final'
        },
        
        deleted: {
            type: 'final'
        }
    }
}, {
    actions: {
        recordStatusChange: assign({
            statusHistory: (context, event) => [
                ...context.statusHistory,
                {
                    from: context.currentStatus,
                    to: event.type.toLowerCase(),
                    timestamp: new Date().toISOString(),
                    reason: event.reason || ''
                }
            ],
            lastStatusChange: () => new Date().toISOString()
        })
    },
    
    guards: {
        eventHasEnded: (context, event) => {
            // Check if event end date has passed
            if (event.eventData?.end_date) {
                return new Date(event.eventData.end_date) < new Date();
            }
            return false;
        },
        
        canRepublish: (context, event) => {
            // Check if event can be republished (e.g., not too late)
            if (event.eventData?.start_date) {
                return new Date(event.eventData.start_date) > new Date();
            }
            return true;
        }
    }
});

export default eventStatusMachine;
