/**
 * Event Status Alpine.js Component
 * 
 * Manages event status transitions and displays status information.
 */

import { eventStatusMachine } from '../machines/eventStatusMachine.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('eventStatus', (config = {}) => ({
        // State machine service
        service: null,
        
        // Reactive state
        state: null,
        currentStatus: 'draft',
        statusHistory: [],
        canTransition: {},
        
        // Configuration
        config: {
            eventId: null,
            eventData: {},
            showHistory: false,
            allowStatusChange: true,
            ...config
        },
        
        // UI state
        showStatusMenu: false,
        isChangingStatus: false,
        
        init() {
            console.log('📊 Initializing Event Status component');
            
            // Create state machine service
            this.service = window.createStateMachine(eventStatusMachine, {
                eventId: this.config.eventId,
                currentStatus: this.config.eventData.status || 'draft'
            });
            
            // Subscribe to state changes
            this.service.subscribe((state) => {
                this.updateFromState(state);
            });
            
            // Start the service
            this.service.start();
            
            // Set initial status
            if (this.config.eventData.status) {
                this.currentStatus = this.config.eventData.status;
            }
            
            // Update transition permissions
            this.updateTransitionPermissions();
        },
        
        destroy() {
            if (this.service) {
                this.service.stop();
            }
        },
        
        updateFromState(state) {
            this.state = state;
            this.currentStatus = state.value;
            this.statusHistory = state.context.statusHistory;
            this.updateTransitionPermissions();
        },
        
        updateTransitionPermissions() {
            if (!this.state) return;
            
            // Check which transitions are available from current state
            const currentStateNode = this.state.meta?.[this.currentStatus];
            this.canTransition = {};
            
            if (this.state.can) {
                this.canTransition.publish = this.state.can('PUBLISH');
                this.canTransition.unpublish = this.state.can('UNPUBLISH');
                this.canTransition.cancel = this.state.can('CANCEL');
                this.canTransition.complete = this.state.can('COMPLETE');
                this.canTransition.delete = this.state.can('DELETE');
                this.canTransition.republish = this.state.can('REPUBLISH');
            }
        },
        
        // Status transition methods
        async changeStatus(newStatus, reason = '') {
            if (!this.config.allowStatusChange) {
                window.showNotification('Status changes are not allowed', 'warning');
                return;
            }
            
            this.isChangingStatus = true;
            
            try {
                // Send transition event to state machine
                const eventType = this.getTransitionEvent(newStatus);
                if (eventType) {
                    this.service.send(eventType, { 
                        reason,
                        eventData: this.config.eventData 
                    });
                }
                
                // Update via API
                if (this.config.eventId) {
                    const api = window.nhkEventApi;
                    await api.updateEventStatus(this.config.eventId, newStatus, reason);
                }
                
                window.showNotification(`Event status changed to ${newStatus}`, 'success');
                this.showStatusMenu = false;
                
            } catch (error) {
                console.error('Error changing status:', error);
                window.showNotification('Failed to change event status', 'error');
            } finally {
                this.isChangingStatus = false;
            }
        },
        
        getTransitionEvent(newStatus) {
            const transitions = {
                'published': 'PUBLISH',
                'draft': 'UNPUBLISH',
                'cancelled': 'CANCEL',
                'completed': 'COMPLETE',
                'deleted': 'DELETE'
            };
            
            return transitions[newStatus];
        },
        
        // Status display helpers
        getStatusLabel(status = null) {
            const statusLabels = {
                'draft': 'Draft',
                'published': 'Published',
                'cancelled': 'Cancelled',
                'completed': 'Completed',
                'deleted': 'Deleted'
            };
            
            return statusLabels[status || this.currentStatus] || status || this.currentStatus;
        },
        
        getStatusColor(status = null) {
            const statusColors = {
                'draft': 'gray',
                'published': 'green',
                'cancelled': 'red',
                'completed': 'blue',
                'deleted': 'red'
            };
            
            return statusColors[status || this.currentStatus] || 'gray';
        },
        
        getStatusIcon(status = null) {
            const statusIcons = {
                'draft': '📝',
                'published': '✅',
                'cancelled': '❌',
                'completed': '🏁',
                'deleted': '🗑️'
            };
            
            return statusIcons[status || this.currentStatus] || '📄';
        },
        
        // Available actions
        getAvailableActions() {
            const actions = [];
            
            if (this.canTransition.publish) {
                actions.push({
                    label: 'Publish',
                    action: () => this.changeStatus('published'),
                    color: 'green',
                    icon: '✅'
                });
            }
            
            if (this.canTransition.unpublish) {
                actions.push({
                    label: 'Unpublish',
                    action: () => this.changeStatus('draft'),
                    color: 'gray',
                    icon: '📝'
                });
            }
            
            if (this.canTransition.cancel) {
                actions.push({
                    label: 'Cancel',
                    action: () => this.promptForReason('cancelled'),
                    color: 'red',
                    icon: '❌'
                });
            }
            
            if (this.canTransition.complete) {
                actions.push({
                    label: 'Mark Complete',
                    action: () => this.changeStatus('completed'),
                    color: 'blue',
                    icon: '🏁'
                });
            }
            
            if (this.canTransition.republish) {
                actions.push({
                    label: 'Republish',
                    action: () => this.changeStatus('published'),
                    color: 'green',
                    icon: '🔄'
                });
            }
            
            if (this.canTransition.delete) {
                actions.push({
                    label: 'Delete',
                    action: () => this.confirmDelete(),
                    color: 'red',
                    icon: '🗑️'
                });
            }
            
            return actions;
        },
        
        // Confirmation dialogs
        promptForReason(newStatus) {
            const reason = prompt(`Please provide a reason for changing status to ${newStatus}:`);
            if (reason !== null) {
                this.changeStatus(newStatus, reason);
            }
        },
        
        confirmDelete() {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                this.changeStatus('deleted', 'Deleted by user');
            }
        },
        
        // Status history
        toggleStatusHistory() {
            this.config.showHistory = !this.config.showHistory;
        },
        
        formatHistoryDate(timestamp) {
            return new Date(timestamp).toLocaleString();
        },
        
        // Bulk status operations
        async bulkChangeStatus(eventIds, newStatus, reason = '') {
            if (!Array.isArray(eventIds) || eventIds.length === 0) {
                return;
            }
            
            try {
                const api = window.nhkEventApi;
                const promises = eventIds.map(eventId => 
                    api.updateEventStatus(eventId, newStatus, reason)
                );
                
                await Promise.all(promises);
                
                window.showNotification(
                    `${eventIds.length} event(s) status changed to ${newStatus}`,
                    'success'
                );
                
            } catch (error) {
                console.error('Error in bulk status change:', error);
                window.showNotification('Failed to change event statuses', 'error');
            }
        },
        
        // Status validation
        canChangeToStatus(newStatus) {
            const eventData = this.config.eventData;
            const now = new Date();
            
            // Business rules for status changes
            switch (newStatus) {
                case 'published':
                    // Can't publish events without required fields
                    if (!eventData.title || !eventData.start_date) {
                        return false;
                    }
                    break;
                    
                case 'completed':
                    // Can only complete events that have ended
                    if (eventData.end_date && new Date(eventData.end_date) > now) {
                        return false;
                    }
                    break;
                    
                case 'cancelled':
                    // Can't cancel completed events
                    if (this.currentStatus === 'completed') {
                        return false;
                    }
                    break;
            }
            
            return true;
        },
        
        // Status notifications
        getStatusMessage() {
            const eventData = this.config.eventData;
            const now = new Date();
            
            switch (this.currentStatus) {
                case 'draft':
                    return 'This event is not yet published and is only visible to administrators.';
                    
                case 'published':
                    if (eventData.start_date && new Date(eventData.start_date) < now) {
                        return 'This event is currently live and visible to the public.';
                    } else {
                        return 'This event is scheduled and will be visible to the public.';
                    }
                    
                case 'cancelled':
                    return 'This event has been cancelled and is no longer accepting registrations.';
                    
                case 'completed':
                    return 'This event has been completed.';
                    
                case 'deleted':
                    return 'This event has been deleted.';
                    
                default:
                    return '';
            }
        }
    }));
});

export default 'eventStatus';
