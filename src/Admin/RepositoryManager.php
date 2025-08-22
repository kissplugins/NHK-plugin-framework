<?php
/**
 * Repository Manager admin page.
 *
 * @package SBI\Admin
 */

namespace SBI\Admin;

use SBI\Services\GitHubService;
use SBI\Services\PluginDetectionService;
use SBI\Services\StateManager;
use SBI\Admin\RepositoryListTable;

/**
 * Repository Manager class.
 */
class RepositoryManager {

    /**
     * GitHub service.
     *
     * @var GitHubService
     */
    private GitHubService $github_service;

    /**
     * Plugin detection service.
     *
     * @var PluginDetectionService
     */
    private PluginDetectionService $detection_service;

    /**
     * State manager.
     *
     * @var StateManager
     */
    private StateManager $state_manager;

    /**
     * List table instance.
     *
     * @var RepositoryListTable
     */
    private RepositoryListTable $list_table;

    /**
     * Constructor.
     *
     * @param GitHubService           $github_service    GitHub service.
     * @param PluginDetectionService  $detection_service Plugin detection service.
     * @param StateManager           $state_manager     State manager.
     */
    public function __construct( 
        GitHubService $github_service, 
        PluginDetectionService $detection_service, 
        StateManager $state_manager 
    ) {
        $this->github_service = $github_service;
        $this->detection_service = $detection_service;
        $this->state_manager = $state_manager;
        
        $this->list_table = new RepositoryListTable( 
            $this->github_service, 
            $this->detection_service, 
            $this->state_manager 
        );
    }

    /**
     * Render the repository manager page.
     */
    public function render(): void {
        // Handle form submissions
        $this->handle_form_submission();
        
        // Get current organization setting
        $organization = get_option( 'sbi_github_organization', '' );
        
        // Set organization for list table
        if ( ! empty( $organization ) ) {
            $this->list_table->set_organization( $organization );
        }
        
        // Prepare list table items
        $this->list_table->prepare_items();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'KISS Smart Batch Installer', 'kiss-smart-batch-installer' ); ?></h1>
            
            <?php $this->render_organization_form( $organization ); ?>
            
            <?php if ( ! empty( $organization ) ): ?>
                <?php $this->render_repository_list(); ?>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Please configure a GitHub organization to get started.', 'kiss-smart-batch-installer' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }

    /**
     * Handle form submissions.
     */
    private function handle_form_submission(): void {
        if ( ! isset( $_POST['sbi_action'] ) || ! wp_verify_nonce( $_POST['sbi_nonce'], 'sbi_admin_action' ) ) {
            return;
        }
        
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( __( 'Insufficient permissions.', 'kiss-smart-batch-installer' ) );
        }
        
        switch ( $_POST['sbi_action'] ) {
            case 'save_organization':
                $this->save_organization();
                break;
            case 'refresh_repositories':
                $this->refresh_repositories();
                break;
        }
    }

    /**
     * Save GitHub organization setting.
     */
    private function save_organization(): void {
        $organization = sanitize_text_field( $_POST['github_organization'] ?? '' );
        
        if ( empty( $organization ) ) {
            add_settings_error( 'sbi_messages', 'organization_empty', __( 'Organization name cannot be empty.', 'kiss-smart-batch-installer' ), 'error' );
            return;
        }
        
        update_option( 'sbi_github_organization', $organization );
        add_settings_error( 'sbi_messages', 'organization_saved', __( 'GitHub organization saved successfully.', 'kiss-smart-batch-installer' ), 'success' );
    }

    /**
     * Refresh repositories cache.
     */
    private function refresh_repositories(): void {
        $organization = get_option( 'sbi_github_organization', '' );
        
        if ( empty( $organization ) ) {
            add_settings_error( 'sbi_messages', 'no_organization', __( 'No organization configured.', 'kiss-smart-batch-installer' ), 'error' );
            return;
        }
        
        // Clear caches
        $this->github_service->clear_cache( $organization );
        $this->detection_service->clear_cache();
        $this->state_manager->clear_cache();
        
        add_settings_error( 'sbi_messages', 'cache_cleared', __( 'Repository cache refreshed successfully.', 'kiss-smart-batch-installer' ), 'success' );
    }

    /**
     * Render organization configuration form.
     *
     * @param string $organization Current organization.
     */
    private function render_organization_form( string $organization ): void {
        ?>
        <div class="sbi-organization-settings" style="background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; margin-bottom: 20px; width: 100%; box-sizing: border-box;">
            <h2 style="margin-top: 0;"><?php esc_html_e( 'GitHub Organization Settings', 'kiss-smart-batch-installer' ); ?></h2>

            <?php settings_errors( 'sbi_messages' ); ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'sbi_admin_action', 'sbi_nonce' ); ?>
                <input type="hidden" name="sbi_action" value="save_organization">

                <table class="form-table" style="width: 100%;">
                    <tr>
                        <th scope="row" style="width: 200px;">
                            <label for="github_organization"><?php esc_html_e( 'GitHub Organization', 'kiss-smart-batch-installer' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="github_organization"
                                   name="github_organization"
                                   value="<?php echo esc_attr( $organization ); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e( 'e.g., wordpress, facebook, google', 'kiss-smart-batch-installer' ); ?>" />
                            <p class="description">
                                <?php esc_html_e( 'Enter the GitHub organization name to fetch public repositories from.', 'kiss-smart-batch-installer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Organization', 'kiss-smart-batch-installer' ); ?>">

                    <?php if ( ! empty( $organization ) ): ?>
                        <input type="submit" name="sbi_action" value="refresh_repositories" class="button button-secondary"
                               onclick="this.form.elements['sbi_action'].value='refresh_repositories';"
                               style="margin-left: 10px;"
                               value="<?php esc_attr_e( 'Refresh Cache', 'kiss-smart-batch-installer' ); ?>">
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render repository list table.
     */
    private function render_repository_list(): void {
        $organization = get_option( 'sbi_github_organization', '' );
        ?>
        <div class="sbi-repository-list" style="background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; width: 100%; box-sizing: border-box;">
            <h2 style="margin-top: 0;">
                <?php
                printf(
                    esc_html__( 'Repositories from %s', 'kiss-smart-batch-installer' ),
                    '<strong>' . esc_html( $organization ) . '</strong>'
                );
                ?>
            </h2>

            <form method="post" id="sbi-repository-form">
                <?php wp_nonce_field( 'sbi_bulk_action', 'sbi_bulk_nonce' ); ?>
                <div style="width: 100%; overflow-x: auto;">
                    <?php $this->list_table->display(); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render CSS styles for full-width layout.
     */
    private function render_styles(): void {
        ?>
        <style type="text/css">
        /* Full-width layout for SBI components */
        .sbi-organization-settings,
        .sbi-repository-list {
            max-width: none !important;
        }

        /* Ensure table uses full width */
        .sbi-repository-list .wp-list-table {
            width: 100% !important;
            table-layout: auto;
        }

        /* Responsive table wrapper */
        .sbi-repository-list .tablenav {
            width: 100%;
        }

        /* Adjust column widths for better distribution */
        .sbi-repository-list .wp-list-table th,
        .sbi-repository-list .wp-list-table td {
            padding: 12px 8px;
        }

        /* Repository name column - allow more space */
        .sbi-repository-list .wp-list-table .column-name {
            width: 25%;
            min-width: 200px;
        }

        /* Description column - flexible width */
        .sbi-repository-list .wp-list-table .column-description {
            width: 35%;
            min-width: 250px;
        }

        /* Status column - fixed width */
        .sbi-repository-list .wp-list-table .column-status {
            width: 15%;
            min-width: 120px;
        }

        /* Actions column - fixed width */
        .sbi-repository-list .wp-list-table .column-actions {
            width: 25%;
            min-width: 200px;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 1200px) {
            .sbi-repository-list .wp-list-table .column-description {
                width: 30%;
            }
            .sbi-repository-list .wp-list-table .column-actions {
                width: 30%;
            }
        }

        @media screen and (max-width: 900px) {
            .sbi-repository-list {
                overflow-x: auto;
            }
            .sbi-repository-list .wp-list-table {
                min-width: 800px;
            }
        }
        </style>
        <?php
    }

    /**
     * Render JavaScript for AJAX functionality.
     */
    private function render_scripts(): void {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // AJAX nonce
            var ajaxNonce = '<?php echo wp_create_nonce( 'sbi_ajax_nonce' ); ?>';
            
            // Install plugin button
            $(document).on('click', '.sbi-install-plugin', function() {
                var button = $(this);
                var repo = button.data('repo');
                var owner = button.data('owner');

                button.prop('disabled', true).text('<?php esc_html_e( 'Installing...', 'kiss-smart-batch-installer' ); ?>');

                $.post(ajaxurl, {
                    action: 'sbi_install_plugin',
                    repository: repo,
                    owner: owner,
                    activate: false,
                    nonce: ajaxNonce
                }, function(response) {
                    if (response.success) {
                        button.text('<?php esc_html_e( 'Installed', 'kiss-smart-batch-installer' ); ?>').removeClass('button-primary').addClass('button-secondary');
                        // Refresh the page to update status
                        location.reload();
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text('<?php esc_html_e( 'Install', 'kiss-smart-batch-installer' ); ?>');
                    }
                });
            });
            
            // Activate plugin button
            $(document).on('click', '.sbi-activate-plugin', function() {
                var button = $(this);
                var repo = button.data('repo');
                var pluginFile = button.data('plugin-file');

                button.prop('disabled', true).text('<?php esc_html_e( 'Activating...', 'kiss-smart-batch-installer' ); ?>');

                $.post(ajaxurl, {
                    action: 'sbi_activate_plugin',
                    repository: repo,
                    plugin_file: pluginFile,
                    nonce: ajaxNonce
                }, function(response) {
                    if (response.success) {
                        button.text('<?php esc_html_e( 'Activated', 'kiss-smart-batch-installer' ); ?>');
                        // Refresh the page to update status
                        location.reload();
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text('<?php esc_html_e( 'Activate', 'kiss-smart-batch-installer' ); ?>');
                    }
                });
            });

            // Deactivate plugin button
            $(document).on('click', '.sbi-deactivate-plugin', function() {
                var button = $(this);
                var repo = button.data('repo');
                var pluginFile = button.data('plugin-file');

                button.prop('disabled', true).text('<?php esc_html_e( 'Deactivating...', 'kiss-smart-batch-installer' ); ?>');

                $.post(ajaxurl, {
                    action: 'sbi_deactivate_plugin',
                    repository: repo,
                    plugin_file: pluginFile,
                    nonce: ajaxNonce
                }, function(response) {
                    if (response.success) {
                        button.text('<?php esc_html_e( 'Deactivated', 'kiss-smart-batch-installer' ); ?>').removeClass('button-secondary').addClass('button-primary');
                        // Refresh the page to update status
                        location.reload();
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text('<?php esc_html_e( 'Deactivate', 'kiss-smart-batch-installer' ); ?>');
                    }
                });
            });

            // Refresh status button
            $(document).on('click', '.sbi-refresh-status', function() {
                var button = $(this);
                var repo = button.data('repo');
                
                button.prop('disabled', true).text('<?php esc_html_e( 'Refreshing...', 'kiss-smart-batch-installer' ); ?>');
                
                $.post(ajaxurl, {
                    action: 'sbi_refresh_repository',
                    repository: repo,
                    nonce: ajaxNonce
                }, function(response) {
                    if (response.success) {
                        // Refresh the page to update status
                        location.reload();
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text('<?php esc_html_e( 'Refresh', 'kiss-smart-batch-installer' ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
