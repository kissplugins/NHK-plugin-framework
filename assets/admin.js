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
        
        // Refresh actions
        $(document).on('click', '.sbi-refresh-repository', SBI.refreshRepository);
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
        
        if (!repository) {
            SBI.showMessage('Repository information missing', 'error');
            return;
        }
        
        $button.prop('disabled', true).text(sbiAjax.strings.loading);
        
        $.post(sbiAjax.ajaxurl, {
            action: 'sbi_install_plugin',
            repository: repository,
            nonce: sbiAjax.nonce
        })
        .done(function(response) {
            if (response.success) {
                SBI.showMessage('Plugin installed successfully', 'success');
                $button.text('Installed').removeClass('sbi-install-plugin');
                // Refresh the page to update status
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                SBI.showMessage(response.data.message || 'Installation failed', 'error');
                $button.prop('disabled', false).text('Install');
            }
        })
        .fail(function() {
            SBI.showMessage('Installation request failed', 'error');
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
