/**
 * Event Form Alpine.js Component
 * 
 * Provides reactive event form functionality with validation and state management.
 */

import { eventFormMachine } from '../machines/eventFormMachine.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('eventForm', (config = {}) => ({
        // State machine service
        service: null,
        
        // Reactive state
        state: null,
        formData: {},
        errors: {},
        isSubmitting: false,
        isDirty: false,
        
        // Configuration
        config: {
            eventId: null,
            redirectAfterSave: true,
            showSuccessMessage: true,
            autoSave: false,
            autoSaveInterval: 30000,
            ...config
        },
        
        // Auto-save timer
        autoSaveTimer: null,
        
        init() {
            console.log('📝 Initializing Event Form component');
            
            // Create state machine service
            this.service = window.createStateMachine(eventFormMachine);
            
            // Subscribe to state changes
            this.service.subscribe((state) => {
                this.updateFromState(state);
            });
            
            // Start the service
            this.service.start();
            
            // Load existing event if editing
            if (this.config.eventId) {
                this.loadEvent(this.config.eventId);
            }
            
            // Set up auto-save if enabled
            if (this.config.autoSave) {
                this.setupAutoSave();
            }
            
            // Set up form validation
            this.setupValidation();
            
            // Set up unsaved changes warning
            this.setupUnsavedChangesWarning();
        },
        
        destroy() {
            if (this.service) {
                this.service.stop();
            }
            
            if (this.autoSaveTimer) {
                clearInterval(this.autoSaveTimer);
            }
        },
        
        updateFromState(state) {
            this.state = state;
            this.formData = { ...state.context.formData };
            this.errors = { ...state.context.errors };
            this.isSubmitting = state.context.isSubmitting;
            this.isDirty = state.context.isDirty;
            
            // Handle success state
            if (state.matches('success')) {
                this.handleSuccess();
            }
        },
        
        setupAutoSave() {
            this.autoSaveTimer = setInterval(() => {
                if (this.isDirty && !this.isSubmitting && this.isFormValid()) {
                    this.saveDraft();
                }
            }, this.config.autoSaveInterval);
        },
        
        setupValidation() {
            // Real-time validation on blur
            this.$nextTick(() => {
                const inputs = this.$el.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.addEventListener('blur', () => {
                        this.validateField(input.name, input.value);
                    });
                });
            });
        },
        
        setupUnsavedChangesWarning() {
            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        },
        
        // Event loading
        loadEvent(eventId) {
            this.service.send('LOAD_EVENT', { eventId });
        },
        
        // Form field updates
        updateField(field, value) {
            this.service.send('UPDATE_FIELD', { field, value });
        },
        
        validateField(field, value) {
            this.service.send('VALIDATE_FIELD', { field, value });
        },
        
        // Form submission
        submitForm() {
            this.service.send('SUBMIT');
        },
        
        saveDraft() {
            // Save as draft without full validation
            const draftData = { ...this.formData, status: 'draft' };
            this.service.send('SUBMIT', { formData: draftData });
        },
        
        // Validation helpers
        isFormValid() {
            return Object.keys(this.errors).length === 0;
        },
        
        hasFieldError(field) {
            return !!this.errors[field];
        },
        
        getFieldError(field) {
            return this.errors[field] || '';
        },
        
        // Success handling
        handleSuccess() {
            if (this.config.showSuccessMessage) {
                window.showNotification(
                    this.config.eventId ? 'Event updated successfully' : 'Event created successfully',
                    'success'
                );
            }
            
            if (this.config.redirectAfterSave && !this.config.eventId) {
                // Redirect to edit page for new events
                const eventId = this.state.context.eventId;
                if (eventId) {
                    window.location.href = `/wp-admin/post.php?post=${eventId}&action=edit`;
                }
            }
        },
        
        // Form reset
        resetForm() {
            if (this.isDirty && !confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) {
                return;
            }
            
            this.service.send('RESET');
        },
        
        // Date/time helpers
        formatDateForInput(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toISOString().split('T')[0];
        },
        
        formatTimeForInput(timeString) {
            if (!timeString) return '';
            return timeString.substring(0, 5); // HH:MM format
        },
        
        // Category and venue management
        addCategory(category) {
            const categories = [...(this.formData.categories || [])];
            if (!categories.includes(category)) {
                categories.push(category);
                this.updateField('categories', categories);
            }
        },
        
        removeCategory(category) {
            const categories = (this.formData.categories || []).filter(c => c !== category);
            this.updateField('categories', categories);
        },
        
        addVenue(venue) {
            const venues = [...(this.formData.venues || [])];
            if (!venues.includes(venue)) {
                venues.push(venue);
                this.updateField('venues', venues);
            }
        },
        
        removeVenue(venue) {
            const venues = (this.formData.venues || []).filter(v => v !== venue);
            this.updateField('venues', venues);
        },
        
        // File upload handling
        async handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Validate file type
            if (!file.type.startsWith('image/')) {
                window.showNotification('Please select an image file', 'error');
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                window.showNotification('Image file size must be less than 5MB', 'error');
                return;
            }
            
            try {
                // Create FormData for upload
                const formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'upload-attachment');
                formData.append('_wpnonce', window.nhkEventManager.nonce);
                
                // Upload to WordPress media library
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.updateField('featured_image', result.data.id);
                    window.showNotification('Image uploaded successfully', 'success');
                } else {
                    throw new Error(result.data || 'Upload failed');
                }
                
            } catch (error) {
                console.error('Image upload error:', error);
                window.showNotification('Failed to upload image', 'error');
            }
        },
        
        // Preview functionality
        previewEvent() {
            if (!this.isFormValid()) {
                window.showNotification('Please fix form errors before previewing', 'warning');
                return;
            }
            
            // Open preview in new window/tab
            const previewData = encodeURIComponent(JSON.stringify(this.formData));
            const previewUrl = `/wp-admin/admin.php?page=nhk-event-preview&data=${previewData}`;
            window.open(previewUrl, '_blank');
        },
        
        // Duplicate event
        duplicateEvent() {
            const duplicateData = {
                ...this.formData,
                title: `${this.formData.title} (Copy)`,
                status: 'draft'
            };
            
            // Remove ID to create new event
            delete duplicateData.id;
            
            // Update form with duplicate data
            Object.keys(duplicateData).forEach(field => {
                this.updateField(field, duplicateData[field]);
            });
            
            window.showNotification('Event duplicated. Make your changes and save.', 'info');
        }
    }));
});

export default 'eventForm';
