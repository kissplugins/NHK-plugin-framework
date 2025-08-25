/**
 * KISS Smart Batch Installer - Admin JavaScript
 *
 * @package SBI
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Global SBI object
    window.SBI = window.SBI || {};

    /**
     * Initialize admin functionality
     */
    SBI.init = function() {
        SBI.bindEvents();
        SBI.initProgressiveLoading();
        SBI.initDebugSystem();
    };

    /**
     * Initialize debug system
     */
    SBI.initDebugSystem = function() {
        // Create global debug object if it doesn't exist
        if (typeof window.sbiDebug === 'undefined') {
            window.sbiDebug = {
                addEntry: function(status, step, message) {
                    // Fallback debug function if main debug system isn't available
                    console.log('[SBI Debug]', status, step, message);
                }
            };
        }
    };

    /**
     * Process progress updates from AJAX response
     */
    SBI.processProgressUpdates = function(progressUpdates) {
        if (!progressUpdates || !Array.isArray(progressUpdates)) {
            return;
        }

        progressUpdates.forEach(function(update) {
            if (window.sbiDebug && typeof window.sbiDebug.addEntry === 'function') {
                window.sbiDebug.addEntry(update.status, update.step, update.message);
            }
        });
    };

    /**
     * Bind event handlers
     */
    SBI.bindEvents = function() {
        // Form submissions
        $(document).on('submit', '.sbi-form', SBI.handleFormSubmit);

        // Button clicks
        $(document).on('click', '.sbi-button', SBI.handleButtonClick);

        // Repository actions
        $(document).on('click', '.sbi-install-plugin', SBI.installPlugin);
        $(document).on('click', '.sbi-activate-plugin', SBI.activatePlugin);
        $(document).on('click', '.sbi-deactivate-plugin', SBI.deactivatePlugin);

        // Refresh actions (support both legacy and new selectors)
        $(document).on('click', '.sbi-refresh-repository, .sbi-refresh-status', SBI.refreshRepository);
    };

    /**
     * Handle form submissions
     */
    SBI.handleFormSubmit = function(e) {
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"], button[type="submit"]');

        // Add loading state
        $submitButton.prop('disabled', true);
        $form.addClass('sbi-loading');

        // Form will submit normally, this just provides visual feedback
    };

    /**
     * Handle button clicks
     */
    SBI.handleButtonClick = function(e) {
        var $button = $(this);

        // Skip if button is disabled
        if ($button.prop('disabled')) {
            e.preventDefault();
            return false;
        }

        // Add loading state for AJAX buttons
        if ($button.hasClass('sbi-ajax-button')) {
            $button.prop('disabled', true);
            $button.addClass('sbi-loading');
        }
    };

    /**
     * Install plugin
     */
    SBI.installPlugin = function(e) {
        e.preventDefault();

        var $button = $(this);
        var repository = $button.data('repo');
        var owner = $button.data('owner');

        if (!repository || !owner) {
            SBI.showMessage('Repository and owner information required', 'error');
            return;
        }

        $button.prop('disabled', true).text('Installing...');

        // Add debug entry for install start
        if (window.sbiDebug) {
            window.sbiDebug.addEntry('info', 'Install Started',
                'Starting installation for ' + owner + '/' + repository);
        }


            // Attempt to use TypeScript handler if available; fallback to jQuery AJAX
            if (window.SBIts && typeof window.SBIts.installPlugin === 'function') {
                window.SBIts.installPlugin(window, owner, repository, false)
                    .then(function(response) {
                        try {
                            // Process progress updates first
                            if (window.sbiDebug && response && response.data && response.data.progress_updates) {
                                response.data.progress_updates.forEach(function(update) {
                                    window.sbiDebug.addEntry(update.status, update.step, update.message);
                                });
                            }

                            // Add debug information
                            if (window.sbiDebug && response && response.data && response.data.debug_steps) {
                                response.data.debug_steps.forEach(function(step) {
                                    var level = step.status === 'failed' ? 'error' :
                                               step.status === 'completed' ? 'success' : 'info';
                                    var message = step.step + ': ' + (step.message || step.status);
                                    if (step.error) { message += ' - Error: ' + step.error; }
                                    if (step.time) { message += ' (' + step.time + 'ms)'; }
                                    window.sbiDebug.addEntry(level, 'Install Step', message);
                                });
                            }

                            if (response && response.success) {
                                if (window.sbiDebug) {
                                    var totalTime = (response.data && response.data.total_time) || 'unknown';
                                    window.sbiDebug.addEntry('success', 'Install Completed',
                                        'Successfully installed ' + owner + '/' + repository + ' in ' + totalTime + 'ms');
                                }
                                SBI.showMessage('Plugin installed successfully', 'success');
                                $button.text('Installed').removeClass('sbi-install-plugin').removeClass('button-primary').addClass('button-secondary');
                                setTimeout(function(){ location.reload(); }, 1000);
                            } else {
                                if (window.sbiDebug) {
                                    var msg = (response && response.data && response.data.message) || 'Unknown error';
                                    window.sbiDebug.addEntry('error', 'Install Failed',
                                        'Installation failed for ' + owner + '/' + repository + ': ' + msg);
                                    if (response && response.data && response.data.troubleshooting) {
                                        var troubleshooting = response.data.troubleshooting;
                                        if (troubleshooting.check_repository_exists) {
                                            window.sbiDebug.addEntry('info', 'Troubleshooting', 'Check if repository exists: ' + troubleshooting.check_repository_exists);
                                        }
                                        if (troubleshooting.verify_repository_public) {
                                            window.sbiDebug.addEntry('info', 'Troubleshooting', troubleshooting.verify_repository_public);
                                        }
                                        if (troubleshooting.check_spelling) {
                                            window.sbiDebug.addEntry('info', 'Troubleshooting', troubleshooting.check_spelling);
                                        }
                                    }
                                }
                                var errorMessage = (response && response.data && response.data.message) || 'Unknown error';
                                if (errorMessage.indexOf('404') !== -1 || errorMessage.indexOf('not found') !== -1) {
                                    errorMessage += '\n\nTroubleshooting:\n';
                                    errorMessage += '• Check if the repository exists at: https://github.com/' + owner + '/' + repository + '\n';
                                    errorMessage += '• Verify the repository is public (not private)\n';
                                    errorMessage += '• Check that owner and repository names are spelled correctly';
                                }
                                SBI.showMessage(errorMessage, 'error');
                                $button.prop('disabled', false).text('Install');
                            }
                        } catch (e) {
                            if (window.sbiDebug) { window.sbiDebug.addEntry('error', 'Install Handling Error', String(e)); }
                            SBI.showMessage('Installation handling error', 'error');
                            $button.prop('disabled', false).text('Install');
                        }
                    })
                    .catch(function(err) {
                        // Mirror .fail diagnostics as much as possible
                        if (window.sbiDebug) {
                            window.sbiDebug.addEntry('error', 'Install (TS) Failed', String(err && err.serverError && err.serverError.message || err));
                        }
                        var errorMsg = 'Installation request failed. Please try again.';
                        try {
                            if (err && err.serverError && err.serverError.message) { errorMsg = err.serverError.message; }
                        } catch(e2){}
                        SBI.showMessage(errorMsg, 'error');
                        $button.prop('disabled', false).text('Install');
                    });
                return;
            }

        // NOTE TO FUTURE CONTRIBUTORS AND LLMs:
        // The debug output around this AJAX call is essential for diagnosing
        // install issues in the field. Do NOT remove window.sbiDebug entries or
        // reduce error detail without providing an equivalent mechanism.
        $.ajax({
            url: sbiAjax.ajaxurl,
            type: 'POST',
            timeout: 60000, // 60 second timeout
            data: {
                action: 'sbi_install_plugin',
                repository: repository,
                owner: owner,
                activate: false,
                nonce: sbiAjax.nonce
            }
        })
        .done(function(response) {
            // Process progress updates first
            if (window.sbiDebug && response.data && response.data.progress_updates) {
                response.data.progress_updates.forEach(function(update) {
                    window.sbiDebug.addEntry(update.status, update.step, update.message);
                });
            }

            // Add debug information
            if (window.sbiDebug && response.data && response.data.debug_steps) {
                response.data.debug_steps.forEach(function(step) {
                    var level = step.status === 'failed' ? 'error' :
                               step.status === 'completed' ? 'success' : 'info';
                    var message = step.step + ': ' + (step.message || step.status);
                    if (step.error) {
                        message += ' - Error: ' + step.error;
                    }
                    if (step.time) {
                        message += ' (' + step.time + 'ms)';
                    }
                    window.sbiDebug.addEntry(level, 'Install Step', message);
                });
            }

            if (response.success) {
                if (window.sbiDebug) {
                    var totalTime = response.data.total_time || 'unknown';
                    window.sbiDebug.addEntry('success', 'Install Completed',
                        'Successfully installed ' + owner + '/' + repository + ' in ' + totalTime + 'ms');
                }

                SBI.showMessage('Plugin installed successfully', 'success');
                $button.text('Installed').removeClass('sbi-install-plugin').removeClass('button-primary').addClass('button-secondary');
                // Refresh the page to update status
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                if (window.sbiDebug) {
                    window.sbiDebug.addEntry('error', 'Install Failed',
                        'Installation failed for ' + owner + '/' + repository + ': ' + (response.data.message || 'Unknown error'));

                    // Add troubleshooting information if available
                    if (response.data.troubleshooting) {
                        var troubleshooting = response.data.troubleshooting;
                        if (troubleshooting.check_repository_exists) {
                            window.sbiDebug.addEntry('info', 'Troubleshooting',
                                'Check if repository exists: ' + troubleshooting.check_repository_exists);
                        }
                        if (troubleshooting.verify_repository_public) {
                            window.sbiDebug.addEntry('info', 'Troubleshooting',
                                troubleshooting.verify_repository_public);
                        }
                        if (troubleshooting.check_spelling) {
                            window.sbiDebug.addEntry('info', 'Troubleshooting',
                                troubleshooting.check_spelling);
                        }
                    }
                }

                // Enhanced error message for 404 errors
                var errorMessage = response.data.message || 'Unknown error';
                if (errorMessage.indexOf('404') !== -1 || errorMessage.indexOf('not found') !== -1) {
                    errorMessage += '\n\nTroubleshooting:\n';
                    errorMessage += '• Check if the repository exists at: https://github.com/' + owner + '/' + repository + '\n';
                    errorMessage += '• Verify the repository is public (not private)\n';
                    errorMessage += '• Check that owner and repository names are spelled correctly';
                }

                SBI.showMessage(errorMessage, 'error');
                $button.prop('disabled', false).text('Install');
            }
        })
        .fail(function(xhr, status, error) {
            // NOTE TO FUTURE CONTRIBUTORS AND LLMs:
            // This enhanced error handling is critical in real-world debugging.
            // DO NOT remove or reduce detail without ensuring equivalent diagnostics.
            var httpCode = xhr && xhr.status ? xhr.status : 'n/a';
            var statusText = xhr && xhr.statusText ? xhr.statusText : '';
            var respText = '';
            try { respText = (xhr && xhr.responseText) ? xhr.responseText.toString() : ''; } catch(e) {}
            var snippet = respText ? respText.substring(0, 400) : '';

            if (window.sbiDebug) {
                window.sbiDebug.addEntry('error', 'Install AJAX Failed',
                    'AJAX request failed for ' + owner + '/' + repository + ': ' + error + ' (Status: ' + status + ', HTTP: ' + httpCode + ' ' + statusText + ')');
                if (snippet) {
                    window.sbiDebug.addEntry('info', 'AJAX Response Snippet', snippet);
                }
            }

            var errorMsg = 'Installation request failed. Please try again.';
            // Try to extract server-provided JSON message if present
            try {
                var data = JSON.parse(respText);
                if (data && data.data && data.data.message) {
                    errorMsg = data.data.message;
                }
            } catch(parseErr) {}

            if (status === 'timeout') {
                errorMsg = 'Installation timed out. The plugin may still be installing in the background. Please refresh the page to check if it was installed successfully.';
            } else if (httpCode === 403) {
                errorMsg = 'Installation blocked (403). Please verify your WordPress nonce/session is valid and you have install_plugins capability.';
            } else if (httpCode >= 500 && httpCode <= 599) {
                errorMsg = 'Server error (' + httpCode + '). Check PHP error logs for fatals and review SBI INSTALL logs.';
            }

            SBI.showMessage(errorMsg, 'error');
            $button.prop('disabled', false).text('Install');
        });
    };

    /**
     * Activate plugin
     */
    SBI.activatePlugin = function(e) {
        e.preventDefault();

        var $button = $(this);
        var repository = $button.data('repo');
        var pluginFile = $button.data('plugin-file');

        if (!repository || !pluginFile) {
            SBI.showMessage('Plugin information missing', 'error');
            return;
        }

        $button.prop('disabled', true).text('Activating...');

        $.post(sbiAjax.ajaxurl, {
            action: 'sbi_activate_plugin',
            repository: repository,
            plugin_file: pluginFile,
            nonce: sbiAjax.nonce
        })
        .done(function(response) {
            if (response.success) {
                SBI.showMessage('Plugin activated successfully', 'success');
                $button.text('Activated').removeClass('sbi-activate-plugin');
                // Refresh the page to update status
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                SBI.showMessage(response.data.message || 'Activation failed', 'error');
                $button.prop('disabled', false).text('Activate');
            }
        })
        .fail(function() {
            SBI.showMessage('Activation request failed', 'error');
            $button.prop('disabled', false).text('Activate');
        });
    };

    /**
     * Deactivate plugin
     */
    SBI.deactivatePlugin = function(e) {
        e.preventDefault();

        var $button = $(this);
        var repository = $button.data('repo');
        var pluginFile = $button.data('plugin-file');

        if (!repository || !pluginFile) {
            SBI.showMessage('Plugin information missing', 'error');
            return;
        }

        $button.prop('disabled', true).text('Deactivating...');

        $.post(sbiAjax.ajaxurl, {
            action: 'sbi_deactivate_plugin',
            repository: repository,
            plugin_file: pluginFile,
            nonce: sbiAjax.nonce
        })
        .done(function(response) {
            if (response.success) {
                SBI.showMessage('Plugin deactivated successfully', 'success');
                $button.text('Deactivated').removeClass('sbi-deactivate-plugin');
                // Refresh the page to update status
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                SBI.showMessage(response.data.message || 'Deactivation failed', 'error');
                $button.prop('disabled', false).text('Deactivate');
            }
        })
        .fail(function() {
            SBI.showMessage('Deactivation request failed', 'error');
            $button.prop('disabled', false).text('Deactivate');
        });
    };

    /**
     * Refresh repository
     */
    SBI.refreshRepository = function(e) {
        e.preventDefault();

        var $button = $(this);
        var repository = $button.data('repo');

        if (!repository) {
            SBI.showMessage('Repository information missing', 'error');
            return;
        }

        $button.prop('disabled', true).text('Refreshing...');

        $.post(sbiAjax.ajaxurl, {
            action: 'sbi_refresh_repository',
            repository: repository,
            nonce: sbiAjax.nonce
        })
        .done(function(response) {
            if (response.success) {
                SBI.showMessage('Repository refreshed successfully', 'success');
                // Refresh the page to update status
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                SBI.showMessage(response.data.message || 'Refresh failed', 'error');
                $button.prop('disabled', false).text('Refresh');
            }
        })
        .fail(function() {
            SBI.showMessage('Refresh request failed', 'error');
            $button.prop('disabled', false).text('Refresh');
        });
    };

    /**
     * Initialize progressive loading
     */
    SBI.initProgressiveLoading = function() {
        // This would be implemented for the main repository page
        // Currently handled by inline scripts in RepositoryManager
    };

    /**
     * Show message to user
     */
    SBI.showMessage = function(message, type) {
        type = type || 'info';

        var $message = $('<div class="sbi-message ' + type + '">' + message + '</div>');

        // Find a good place to show the message
        var $container = $('.sbi-container').first();
        if ($container.length === 0) {
            $container = $('.wrap').first();
        }

        if ($container.length > 0) {
            $container.prepend($message);

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $message.remove();
                    });
                }, 3000);
            }
        } else {
            // Fallback to alert
            alert(message);
        }
    };

    /**
     * Update progress bar
     */
    SBI.updateProgress = function(current, total) {
        var percentage = total > 0 ? (current / total) * 100 : 0;
        $('.sbi-progress-bar').css('width', percentage + '%');
    };

    /**
     * Show/hide loading spinner
     */
    SBI.toggleLoading = function($element, show) {
        if (show) {
            $element.addClass('sbi-loading');
            if (!$element.find('.sbi-spinner').length) {
                $element.prepend('<span class="sbi-spinner"></span>');
            }
        } else {
            $element.removeClass('sbi-loading');
            $element.find('.sbi-spinner').remove();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SBI.init();
    });

})(jQuery);
