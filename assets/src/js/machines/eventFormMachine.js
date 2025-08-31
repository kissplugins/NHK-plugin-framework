/**
 * Event Form State Machine
 * 
 * Manages the state of event creation and editing forms using XState.
 */

import { createMachine, assign } from 'xstate';

export const eventFormMachine = createMachine({
    id: 'eventForm',
    initial: 'idle',
    context: {
        formData: {
            title: '',
            content: '',
            start_date: '',
            end_date: '',
            start_time: '',
            end_time: '',
            venue: '',
            capacity: '',
            price: '',
            organizer_name: '',
            organizer_email: '',
            registration_url: '',
            categories: [],
            venues: []
        },
        errors: {},
        isSubmitting: false,
        isDirty: false,
        eventId: null
    },
    states: {
        idle: {
            on: {
                LOAD_EVENT: 'loading',
                UPDATE_FIELD: {
                    actions: ['updateField', 'markDirty']
                },
                VALIDATE_FIELD: {
                    actions: 'validateField'
                },
                SUBMIT: 'validating'
            }
        },
        
        loading: {
            invoke: {
                id: 'loadEvent',
                src: 'loadEvent',
                onDone: {
                    target: 'idle',
                    actions: assign({
                        formData: (context, event) => ({
                            ...context.formData,
                            ...event.data
                        }),
                        eventId: (context, event) => event.data.id
                    })
                },
                onError: {
                    target: 'error',
                    actions: assign({
                        error: (context, event) => event.data
                    })
                }
            }
        },
        
        validating: {
            always: [
                {
                    target: 'submitting',
                    cond: 'isFormValid'
                },
                {
                    target: 'idle',
                    actions: 'showValidationErrors'
                }
            ]
        },
        
        submitting: {
            entry: assign({ isSubmitting: true }),
            invoke: {
                id: 'submitForm',
                src: 'submitForm',
                onDone: {
                    target: 'success',
                    actions: assign({
                        isSubmitting: false,
                        isDirty: false,
                        eventId: (context, event) => event.data.id || context.eventId
                    })
                },
                onError: {
                    target: 'idle',
                    actions: assign({
                        isSubmitting: false,
                        errors: (context, event) => event.data.errors || {}
                    })
                }
            }
        },
        
        success: {
            after: {
                3000: 'idle'
            },
            on: {
                RESET: 'idle',
                EDIT_ANOTHER: {
                    target: 'idle',
                    actions: 'resetForm'
                }
            }
        },
        
        error: {
            on: {
                RETRY: 'loading',
                RESET: 'idle'
            }
        }
    }
}, {
    actions: {
        updateField: assign({
            formData: (context, event) => ({
                ...context.formData,
                [event.field]: event.value
            })
        }),
        
        markDirty: assign({
            isDirty: true
        }),
        
        validateField: assign({
            errors: (context, event) => {
                const newErrors = { ...context.errors };
                const validation = validateField(event.field, event.value, context.formData);
                
                if (validation.isValid) {
                    delete newErrors[event.field];
                } else {
                    newErrors[event.field] = validation.message;
                }
                
                return newErrors;
            }
        }),
        
        showValidationErrors: assign({
            errors: (context) => validateForm(context.formData)
        }),
        
        resetForm: assign({
            formData: {
                title: '',
                content: '',
                start_date: '',
                end_date: '',
                start_time: '',
                end_time: '',
                venue: '',
                capacity: '',
                price: '',
                organizer_name: '',
                organizer_email: '',
                registration_url: '',
                categories: [],
                venues: []
            },
            errors: {},
            isDirty: false,
            eventId: null
        })
    },
    
    guards: {
        isFormValid: (context) => {
            const errors = validateForm(context.formData);
            return Object.keys(errors).length === 0;
        }
    },
    
    services: {
        loadEvent: async (context, event) => {
            const api = window.nhkEventApi;
            return await api.getEvent(event.eventId);
        },
        
        submitForm: async (context) => {
            const api = window.nhkEventApi;
            
            if (context.eventId) {
                return await api.updateEvent(context.eventId, context.formData);
            } else {
                return await api.createEvent(context.formData);
            }
        }
    }
});

/**
 * Validate individual field
 */
function validateField(field, value, formData) {
    switch (field) {
        case 'title':
            if (!value.trim()) {
                return { isValid: false, message: 'Title is required' };
            }
            break;
            
        case 'start_date':
            if (!value) {
                return { isValid: false, message: 'Start date is required' };
            }
            break;
            
        case 'end_date':
            if (value && formData.start_date && new Date(value) < new Date(formData.start_date)) {
                return { isValid: false, message: 'End date must be after start date' };
            }
            break;
            
        case 'organizer_email':
            if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return { isValid: false, message: 'Invalid email format' };
            }
            break;
            
        case 'capacity':
            if (value && (isNaN(value) || parseInt(value) < 0)) {
                return { isValid: false, message: 'Capacity must be a positive number' };
            }
            break;
    }
    
    return { isValid: true };
}

/**
 * Validate entire form
 */
function validateForm(formData) {
    const errors = {};
    
    // Required fields
    if (!formData.title?.trim()) {
        errors.title = 'Title is required';
    }
    
    if (!formData.start_date) {
        errors.start_date = 'Start date is required';
    }
    
    // Date validation
    if (formData.end_date && formData.start_date && 
        new Date(formData.end_date) < new Date(formData.start_date)) {
        errors.end_date = 'End date must be after start date';
    }
    
    // Email validation
    if (formData.organizer_email && 
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.organizer_email)) {
        errors.organizer_email = 'Invalid email format';
    }
    
    // Capacity validation
    if (formData.capacity && (isNaN(formData.capacity) || parseInt(formData.capacity) < 0)) {
        errors.capacity = 'Capacity must be a positive number';
    }
    
    return errors;
}

export { validateField, validateForm };
