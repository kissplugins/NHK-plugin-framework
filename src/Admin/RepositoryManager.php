<?php
/**
 * Repository Manager admin page.
 *
 * @package SBI\Admin
 *
 * IMPORTANT: This class uses FSM-first architecture. For plugin state management:
 * - Use StateManager methods instead of direct WordPress functions
 * - Use RepositoryFSM for frontend state management
 * - See docs/DEPRECATION-GUIDE.md for migration patterns
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

        // For progressive loading, we don't prepare items here
        // Items will be loaded via AJAX

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'KISS Smart Batch Installer', 'kiss-smart-batch-installer' ); ?></h1>

            <p>
                <a href="<?php echo esc_url( admin_url( 'plugins.php?page=sbi-self-tests' ) ); ?>" class="button">
                    <?php esc_html_e( 'Run Self Tests', 'kiss-smart-batch-installer' ); ?>
                </a>
            </p>

            <?php $this->render_organization_form( $organization ); ?>

            <?php if ( ! empty( $organization ) ): ?>
                <?php $this->render_repository_list(); ?>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Please configure a GitHub organization to get started.', 'kiss-smart-batch-installer' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

            <script>
            (function(){
                document.addEventListener('click', function(e){
                    var btn = e.target && e.target.closest && e.target.closest('#sbi-test-sse');
                    if (!btn) return;
                    e.preventDefault();
                    if (!window.sbiAjax) return;
                    // Use current organization or default test repo
                    var org = (document.getElementById('github_organization')||{}).value || 'kissplugins';
                    var repo = org + '/SSE-Diagnostics';
                    var data = { action:'sbi_test_sse', repository: repo, nonce: sbiAjax.nonce };
                    try { if (window.sbiDebug) window.sbiDebug.addEntry('info','SSE Test','Triggering SSE test for ' + repo); } catch(_){ }
                    fetch(sbiAjax.ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(data)
                    }).then(function(r){ return r.text(); }).then(function(txt){
                        try { var resp = JSON.parse(txt); if (!resp.success) throw new Error(resp.data && resp.data.message || 'SSE test failed');
                            if (window.sbiDebug) window.sbiDebug.addEntry('success','SSE Test', resp.data && resp.data.message || 'OK');
                            if (window.SBI && window.SBI.logSSE) window.SBI.logSSE('test', 'Triggered test; watch for state_changed events');
                        } catch(e){
                            if (window.sbiDebug) window.sbiDebug.addEntry('error','SSE Test Error', String(e));
                        }
                    }).catch(function(err){ if (window.sbiDebug) window.sbiDebug.addEntry('error','SSE Test Fetch', String(err)); });
                });
            })();
            </script>
        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }

    /**
     * Handle form submissions.
     */
    private function handle_form_submission(): void {
        // Handle regular form submissions
        if ( isset( $_POST['sbi_action'] ) && wp_verify_nonce( $_POST['sbi_nonce'], 'sbi_admin_action' ) ) {
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

        // Handle bulk actions
        if ( isset( $_POST['action'] ) && wp_verify_nonce( $_POST['sbi_bulk_nonce'], 'sbi_bulk_action' ) ) {
            $this->handle_bulk_action();
        }
    }

    /**
     * Handle bulk actions from the list table.
     */
    private function handle_bulk_action(): void {
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( __( 'Insufficient permissions.', 'kiss-smart-batch-installer' ) );
        }

        $action = sanitize_text_field( $_POST['action'] );
        $repositories = $_POST['repositories'] ?? [];

        if ( empty( $repositories ) || ! is_array( $repositories ) ) {
            add_settings_error( 'sbi_messages', 'no_repositories', __( 'No repositories selected.', 'kiss-smart-batch-installer' ), 'error' );
            return;
        }

        // Sanitize repository names
        $repositories = array_map( 'sanitize_text_field', $repositories );

        switch ( $action ) {
            case 'install':
                $this->bulk_install_repositories( $repositories );
                break;
            case 'activate':
                $this->bulk_activate_repositories( $repositories );
                break;
            case 'deactivate':
                $this->bulk_deactivate_repositories( $repositories );
                break;
            case 'refresh':
                $this->bulk_refresh_repositories( $repositories );
                break;
            default:
                add_settings_error( 'sbi_messages', 'invalid_action', __( 'Invalid bulk action.', 'kiss-smart-batch-installer' ), 'error' );
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

        // Also save fetch method if provided
        if ( isset( $_POST['fetch_method'] ) ) {
            $fetch_method = sanitize_text_field( $_POST['fetch_method'] );
            if ( in_array( $fetch_method, [ 'api_with_fallback', 'web_only', 'api_only' ], true ) ) {
                update_option( 'sbi_fetch_method', $fetch_method );
            }
        }

        // Also save repository limit if provided
        if ( isset( $_POST['repository_limit'] ) ) {
            $repository_limit = (int) $_POST['repository_limit'];
            if ( $repository_limit >= 1 && $repository_limit <= 50 ) {
                update_option( 'sbi_repository_limit', $repository_limit );
            }
        }

        // Also save skip plugin detection setting
        $skip_detection = isset( $_POST['skip_plugin_detection'] ) ? 1 : 0;
        update_option( 'sbi_skip_plugin_detection', $skip_detection );

        // Also save debug AJAX setting
        $debug_ajax = isset( $_POST['debug_ajax'] ) ? 1 : 0;
        update_option( 'sbi_debug_ajax', $debug_ajax );

        // SSE diagnostics toggle
        $sse_diag = isset( $_POST['sbi_sse_diagnostics'] ) ? 1 : 0;
        update_option( 'sbi_sse_diagnostics', $sse_diag );

        add_settings_error( 'sbi_messages', 'organization_saved', __( 'GitHub organization and settings saved successfully.', 'kiss-smart-batch-installer' ), 'success' );
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
     * Bulk install selected repositories.
     *
     * @param array $repositories Array of repository full names.
     */
    private function bulk_install_repositories( array $repositories ): void {
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ( $repositories as $repo_full_name ) {
            // Parse owner/repo from full name
            $parts = explode( '/', $repo_full_name );
            if ( count( $parts ) !== 2 ) {
                $error_count++;
                $errors[] = sprintf( __( 'Invalid repository format: %s', 'kiss-smart-batch-installer' ), $repo_full_name );
                continue;
            }

            $owner = $parts[0];
            $repo = $parts[1];

            // Get the installation service from the container
            $installation_service = $this->get_installation_service();
            if ( ! $installation_service ) {
                $error_count++;
                $errors[] = sprintf( __( 'Installation service not available for: %s', 'kiss-smart-batch-installer' ), $repo_full_name );
                continue;
            }

            // Install the plugin
            $result = $installation_service->install_plugin( $owner, $repo );

            if ( is_wp_error( $result ) ) {
                $error_count++;
                $errors[] = sprintf( __( '%s: %s', 'kiss-smart-batch-installer' ), $repo, $result->get_error_message() );
            } else {
                $success_count++;
            }
        }

        // Display results
        if ( $success_count > 0 ) {
            add_settings_error(
                'sbi_messages',
                'bulk_install_success',
                sprintf( __( 'Successfully installed %d plugins.', 'kiss-smart-batch-installer' ), $success_count ),
                'success'
            );
        }

        if ( $error_count > 0 ) {
            $error_message = sprintf( __( 'Failed to install %d plugins:', 'kiss-smart-batch-installer' ), $error_count );
            $error_message .= '<br>' . implode( '<br>', array_slice( $errors, 0, 5 ) ); // Show first 5 errors
            if ( count( $errors ) > 5 ) {
                $error_message .= '<br>' . sprintf( __( '... and %d more errors.', 'kiss-smart-batch-installer' ), count( $errors ) - 5 );
            }
            add_settings_error( 'sbi_messages', 'bulk_install_errors', $error_message, 'error' );
        }
    }

    /**
     * Bulk activate selected repositories.
     *
     * @param array $repositories Array of repository full names.
     */
    private function bulk_activate_repositories( array $repositories ): void {
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ( $repositories as $repo_full_name ) {
            // Get plugin file from state manager
            $plugin_file = $this->state_manager->get_plugin_file( $repo_full_name );

            if ( empty( $plugin_file ) ) {
                $error_count++;
                $errors[] = sprintf( __( 'Plugin file not found for: %s', 'kiss-smart-batch-installer' ), $repo_full_name );
                continue;
            }

            // Get the installation service
            $installation_service = $this->get_installation_service();
            if ( ! $installation_service ) {
                $error_count++;
                $errors[] = sprintf( __( 'Installation service not available for: %s', 'kiss-smart-batch-installer' ), $repo_full_name );
                continue;
            }

            // Activate the plugin
            $result = $installation_service->activate_plugin( $plugin_file );

            if ( is_wp_error( $result ) ) {
                $error_count++;
                $errors[] = sprintf( __( '%s: %s', 'kiss-smart-batch-installer' ), basename( $repo_full_name ), $result->get_error_message() );
            } else {
                $success_count++;
            }
        }

        // Display results
        if ( $success_count > 0 ) {
            add_settings_error(
                'sbi_messages',
                'bulk_activate_success',
                sprintf( __( 'Successfully activated %d plugins.', 'kiss-smart-batch-installer' ), $success_count ),
                'success'
            );
        }

        if ( $error_count > 0 ) {
            $error_message = sprintf( __( 'Failed to activate %d plugins:', 'kiss-smart-batch-installer' ), $error_count );
            $error_message .= '<br>' . implode( '<br>', array_slice( $errors, 0, 5 ) );
            if ( count( $errors ) > 5 ) {
                $error_message .= '<br>' . sprintf( __( '... and %d more errors.', 'kiss-smart-batch-installer' ), count( $errors ) - 5 );
            }
            add_settings_error( 'sbi_messages', 'bulk_activate_errors', $error_message, 'error' );
        }
    }

    /**
     * Bulk deactivate selected repositories.
     *
     * @param array $repositories Array of repository full names.
     */
    private function bulk_deactivate_repositories( array $repositories ): void {
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ( $repositories as $repo_full_name ) {
            // Get plugin file from state manager
            $plugin_file = $this->state_manager->get_plugin_file( $repo_full_name );

            if ( empty( $plugin_file ) ) {
                $error_count++;
                $errors[] = sprintf( __( 'Plugin file not found for: %s', 'kiss-smart-batch-installer' ), $repo_full_name );
                continue;
            }

            // Get the installation service
            $installation_service = $this->get_installation_service();
            if ( ! $installation_service ) {
                $error_count++;
                $errors[] = sprintf( __( 'Installation service not available for: %s', 'kiss-smart-batch-installer' ), $repo_full_name );
                continue;
            }

            // Deactivate the plugin
            $result = $installation_service->deactivate_plugin( $plugin_file );

            if ( is_wp_error( $result ) ) {
                $error_count++;
                $errors[] = sprintf( __( '%s: %s', 'kiss-smart-batch-installer' ), basename( $repo_full_name ), $result->get_error_message() );
            } else {
                $success_count++;
            }
        }

        // Display results
        if ( $success_count > 0 ) {
            add_settings_error(
                'sbi_messages',
                'bulk_deactivate_success',
                sprintf( __( 'Successfully deactivated %d plugins.', 'kiss-smart-batch-installer' ), $success_count ),
                'success'
            );
        }

        if ( $error_count > 0 ) {
            $error_message = sprintf( __( 'Failed to deactivate %d plugins:', 'kiss-smart-batch-installer' ), $error_count );
            $error_message .= '<br>' . implode( '<br>', array_slice( $errors, 0, 5 ) );
            if ( count( $errors ) > 5 ) {
                $error_message .= '<br>' . sprintf( __( '... and %d more errors.', 'kiss-smart-batch-installer' ), count( $errors ) - 5 );
            }
            add_settings_error( 'sbi_messages', 'bulk_deactivate_errors', $error_message, 'error' );
        }
    }

    /**
     * Bulk refresh selected repositories.
     *
     * @param array $repositories Array of repository full names.
     */
    private function bulk_refresh_repositories( array $repositories ): void {
        foreach ( $repositories as $repo_full_name ) {
            // Clear cache for this specific repository
            $this->detection_service->clear_repository_cache( $repo_full_name );
            $this->state_manager->clear_repository_cache( $repo_full_name );
        }

        add_settings_error(
            'sbi_messages',
            'bulk_refresh_success',
            sprintf( __( 'Successfully refreshed %d repositories.', 'kiss-smart-batch-installer' ), count( $repositories ) ),
            'success'
        );
    }

    /**
     * Get installation service from container.
     *
     * @return PluginInstallationService|null
     */
    private function get_installation_service() {
        // Access the global container
        $container = $GLOBALS['sbi_container'] ?? null;
        if ( ! $container ) {
            return null;
        }

        try {
            return $container->get( \SBI\Services\PluginInstallationService::class );
        } catch ( Exception $e ) {
            return null;
        }
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
                                <br><strong><?php esc_html_e( 'Note:', 'kiss-smart-batch-installer' ); ?></strong>
                                <?php esc_html_e( 'The system will automatically fall back to web scraping if GitHub API rate limits are exceeded.', 'kiss-smart-batch-installer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fetch_method"><?php esc_html_e( 'Fetch Method', 'kiss-smart-batch-installer' ); ?></label>
                        </th>
                        <td>
                            <?php $fetch_method = get_option( 'sbi_fetch_method', 'web_only' ); ?>
                            <select id="fetch_method" name="fetch_method">
                                <option value="web_only" <?php selected( $fetch_method, 'web_only' ); ?>>
                                    <?php esc_html_e( 'Web Scraping Only (Recommended - No Rate Limits)', 'kiss-smart-batch-installer' ); ?>
                                </option>
                                <option value="api_with_fallback" <?php selected( $fetch_method, 'api_with_fallback' ); ?>>
                                    <?php esc_html_e( 'GitHub API with Web Fallback (May Be Unreliable)', 'kiss-smart-batch-installer' ); ?>
                                </option>
                                <option value="api_only" <?php selected( $fetch_method, 'api_only' ); ?>>
                                    <?php esc_html_e( 'GitHub API Only (Not Recommended)', 'kiss-smart-batch-installer' ); ?>
                                </option>
                            </select>
                            <?php if ( get_option('sbi_fetch_method', 'web_only') === 'web_only' && get_option('sbi_web_only_tip_dismissed', 0 ) != 1 ): ?>
                                <div class="notice notice-warning" style="margin:10px 0;">
                                    <p>
                                        <?php echo wp_kses_post( sprintf(
                                            /* translators: 1: link open, 2: link close */
                                            __( 'Tip: If you encounter DNS/network errors when fetching repos via Web Scraping Only (e.g., “Could not resolve host: github.com”), switch Fetch Method to %1$sAuto (API with web fallback)%2$s or %1$sAPI Only%2$s temporarily.', 'kiss-smart-batch-installer' ),
                                            '<strong>',
                                            '</strong>'
                                        ) ); ?>
                                        <button type="button" class="button-link" id="sbi-dismiss-webonly-tip" style="margin-left:8px;"><?php esc_html_e('Dismiss', 'kiss-smart-batch-installer'); ?></button>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <p class="description">
                                <?php esc_html_e( 'Web scraping is now the recommended method due to GitHub API reliability issues. It bypasses rate limits and provides consistent performance.', 'kiss-smart-batch-installer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="repository_limit"><?php esc_html_e( 'Repository Limit (Testing)', 'kiss-smart-batch-installer' ); ?></label>
                        </th>
                        <td>
                            <?php $repository_limit = get_option( 'sbi_repository_limit', 1 ); ?>
                            <input type="number"
                                   id="repository_limit"
                                   name="repository_limit"
                                   value="<?php echo esc_attr( $repository_limit ); ?>"
                                   min="1"
                                   max="50"
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'Limit the number of repositories to process for testing. Start with 1, then gradually increase (e.g., 2, 5, 10) once stable.', 'kiss-smart-batch-installer' ); ?>
                                <br><strong><?php esc_html_e( 'Recommended:', 'kiss-smart-batch-installer' ); ?></strong>
                                <?php esc_html_e( 'Use 1 for initial testing, then increase to 5-10 for normal use.', 'kiss-smart-batch-installer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="skip_plugin_detection"><?php esc_html_e( 'Skip Plugin Detection (Testing)', 'kiss-smart-batch-installer' ); ?></label>
                        </th>
                        <td>
                            <?php $skip_detection = get_option( 'sbi_skip_plugin_detection', false ); ?>
                            <label>
                                <input type="checkbox"
                                       id="skip_plugin_detection"
                                       name="skip_plugin_detection"
                                       value="1"
                                       <?php checked( $skip_detection ); ?> />
                                <?php esc_html_e( 'Skip plugin detection to test basic repository loading', 'kiss-smart-batch-installer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Enable this to bypass plugin detection and prevent hanging. Useful for testing basic repository fetching.', 'kiss-smart-batch-installer' ); ?>
                                <br><strong><?php esc_html_e( 'Note:', 'kiss-smart-batch-installer' ); ?></strong>
                                <?php esc_html_e( 'When enabled, all repositories will show as "Unknown" type.', 'kiss-smart-batch-installer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="debug_ajax"><?php esc_html_e( 'Debug AJAX (Development)', 'kiss-smart-batch-installer' ); ?></label>
                        </th>
                        <td>
                            <?php $debug_ajax = get_option( 'sbi_debug_ajax', false ); ?>
                            <label>
                                <input type="checkbox"
                                       id="debug_ajax"
                                       name="debug_ajax"
                                       value="1"
                                       <?php checked( $debug_ajax ); ?> />
                                <?php esc_html_e( 'Show AJAX debug panel with detailed logging', 'kiss-smart-batch-installer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Enable this to show a detailed debug panel that logs all AJAX requests, responses, and errors in real-time.', 'kiss-smart-batch-installer' ); ?>
                                <br><strong><?php esc_html_e( 'Developer Tool:', 'kiss-smart-batch-installer' ); ?></strong>
                        <tr>
                            <th scope="row">
                                <label for="sbi_sse_diagnostics"><?php esc_html_e( 'Enable SSE Diagnostics (Experimental)', 'kiss-smart-batch-installer' ); ?></label>
                            </th>
                            <td>
                                <?php $sse_diag = get_option( 'sbi_sse_diagnostics', false ); ?>
                                <label>
                                    <input type="checkbox"
                                           id="sbi_sse_diagnostics"
                                           name="sbi_sse_diagnostics"
                                           value="1"
                                           <?php checked( $sse_diag ); ?> />
                                    <?php esc_html_e( 'Enable Server-Sent Events (SSE) diagnostics stream (admin only)', 'kiss-smart-batch-installer' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Experimental: Streams state_changed events to the browser for real-time diagnostics. Disable if you experience admin page performance issues.', 'kiss-smart-batch-installer' ); ?>
                                </p>
                            </td>
                        </tr>
                                <?php esc_html_e( 'Useful for troubleshooting AJAX issues and monitoring system performance.', 'kiss-smart-batch-installer' ); ?>
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
                            <button type="button" id="sbi-test-sse" class="button button-secondary" style="margin-left: 10px;">
                                <?php esc_html_e( 'Test SSE', 'kiss-smart-batch-installer' ); ?>
                            </button>
                               value="<?php esc_attr_e( 'Refresh Cache', 'kiss-smart-batch-installer' ); ?>">
                        <button type="button" id="debug-detection" class="button button-secondary" style="margin-left: 10px;">
                            <?php esc_html_e( 'Debug Detection', 'kiss-smart-batch-installer' ); ?>
                        </button>
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
                <span id="sbi-loading-progress" style="font-size: 14px; font-weight: normal; margin-left: 10px; display: none;">
                    <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                    <span id="sbi-progress-text"><?php esc_html_e( 'Loading repositories...', 'kiss-smart-batch-installer' ); ?></span>
                </span>
            </h2>

            <div id="sbi-initial-loading" style="text-align: center; padding: 40px;">
                <span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
                <?php esc_html_e( 'Fetching repository list...', 'kiss-smart-batch-installer' ); ?>
            </div>

            <!-- AJAX Debug Panel (only show if debug setting is enabled) -->
            <?php if ( get_option( 'sbi_debug_ajax', false ) ): ?>
            <div id="sbi-debug-panel" style="display: none; margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #856404;">🔍 AJAX Debug Information</h4>
                <div id="sbi-debug-log" style="font-family: monospace; font-size: 12px; height: 300px; min-height: 120px; max-height: 70vh; resize: vertical; overflow: auto; background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 3px;">
                    <div class="debug-entry">Debug panel initialized...</div>
                </div>
                <button type="button" id="sbi-clear-debug" style="margin-top: 10px; padding: 5px 10px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;">Clear Debug Log</button>
                <button type="button" id="sbi-toggle-debug" style="margin-top: 10px; margin-left: 10px; padding: 5px 10px; background: #17a2b8; color: white; border: none; border-radius: 3px; cursor: pointer;">Hide Debug</button>
            </div>
            <?php endif; ?>

            <form method="post" id="sbi-repository-form" style="display: none;">
                <?php wp_nonce_field( 'sbi_bulk_action', 'sbi_bulk_nonce' ); ?>
                <div style="width: 100%; overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <?php foreach ( $this->list_table->get_columns() as $column_id => $column_name ): ?>
                                    <th scope="col" class="manage-column column-<?php echo esc_attr( $column_id ); ?>">
                                        <?php echo $column_name; ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="sbi-repository-tbody">
                            <!-- Repository rows will be inserted here progressively -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <?php foreach ( $this->list_table->get_columns() as $column_id => $column_name ): ?>
                                    <th scope="col" class="manage-column column-<?php echo esc_attr( $column_id ); ?>">
                                        <?php echo $column_name; ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="tablenav bottom">
                        <div class="alignleft actions bulkactions">
                            <?php $this->list_table->bulk_actions(); ?>
                        </div>
                        <div class="tablenav-pages">
                            <span class="displaying-num" id="sbi-item-count">0 items</span>
                        </div>
                    </div>
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

        /* Progressive loading styles */
        .sbi-loading-row {
            opacity: 0.7;
        }

        .sbi-loading-indicator {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .sbi-status-scanning {
            color: #0073aa;
            font-size: 12px;
        }

        .sbi-actions-loading {
            color: #666;
            font-style: italic;
            font-size: 12px;
        }

        .sbi-error-row {
            background-color: #fef7f7;
        }

        #sbi-loading-progress {
            color: #0073aa;
        }

        #sbi-initial-loading {
            color: #666;
            font-size: 16px;
        }

        /* Smooth transitions for row updates */
        .sbi-loading-row td {
            transition: all 0.3s ease;
        }

        /* Spinner adjustments */
        .sbi-loading-indicator .spinner,
        .sbi-status-scanning .spinner {
            width: 16px;
            height: 16px;
        }

        /* Debug panel styles */
        .debug-entry {
            margin: 2px 0;
            padding: 2px 0;
            border-bottom: 1px solid #eee;
        }

        .debug-entry:last-child {
            border-bottom: none;
        }

        .debug-info {
            color: #0073aa;
        }

        .debug-success {
            color: #46b450;
        }

        .debug-warning {
            color: #ffb900;
        }

        .debug-error {
            color: #dc3232;
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
            // IMPORTANT: This JavaScript uses FSM-first patterns
            // - Use RepositoryFSM for state management instead of ad-hoc variables
            // - Use data-repository attributes for DOM targeting
            // - See docs/DEPRECATION-GUIDE.md for migration patterns

            // AJAX nonce
            var ajaxNonce = '<?php echo wp_create_nonce( 'sbi_ajax_nonce' ); ?>';

            // Progressive loading variables (FSM-driven)
            var repositories = [];
            var currentIndex = 0;
            var totalRepositories = 0;
            var repositoryLimit = 0;

            // Initialize FSM for state management
            var fsm = null;
            try {
                if (window.SBIts && window.SBIts.repositoryFSM) {
                    fsm = window.SBIts.repositoryFSM;
                    // Initialize SSE connection for real-time updates
                    fsm.initSSE(window);
                    debugLog('✅ FSM initialized with SSE support');
                } else {
                    debugLog('⚠️ FSM not available, falling back to legacy state management');
                }
            } catch(e) {
                debugLog('❌ FSM initialization failed: ' + e.message, 'error');
            }

            // FSM-based state management helpers
            function isSystemLoading() {
                // Check if any repository is in CHECKING state (system-wide loading)
                if (fsm) {
                    // Check if we have any repositories in loading states
                    for (var i = 0; i < repositories.length; i++) {
                        var repo = repositories[i];
                        if (fsm.isInAnyState(repo.full_name, ['checking', 'installing'])) {
                            return true;
                        }
                    }
                    return false;
                }
                // Fallback: use a simple flag
                return window.sbiSystemLoading || false;
            }

            function setSystemLoading(loading) {
                if (fsm) {
                    // FSM manages state automatically through transitions
                    debugLog(loading ? '🔄 System loading started' : '✅ System loading completed');
                } else {
                    // Fallback: use a simple flag
                    window.sbiSystemLoading = loading;
                }
            }

            function isRepositoryProcessing(repoFullName) {
                if (fsm) {
                    return fsm.isInAnyState(repoFullName, ['checking', 'installing']);
                }
                // Fallback: check if there's an active request for this repo
                return window.sbiActiveRequests && window.sbiActiveRequests[repoFullName];
            }

            function setRepositoryProcessing(repoFullName, processing) {
                if (fsm) {
                    // FSM manages state automatically through transitions
                    debugLog((processing ? '🔄' : '✅') + ' Repository ' + repoFullName + ' processing: ' + processing);
                } else {
                    // Fallback: track active requests
                    window.sbiActiveRequests = window.sbiActiveRequests || {};
                    if (processing) {
                        window.sbiActiveRequests[repoFullName] = true;
                    } else {
                        delete window.sbiActiveRequests[repoFullName];
                    }
                }
            }

            // Debug functions (only if debug is enabled)
            var debugEnabled = <?php echo get_option( 'sbi_debug_ajax', false ) ? 'true' : 'false'; ?>;

            function debugLog(message, type = 'info') {
                if (!debugEnabled) return;

                var timestamp = new Date().toLocaleTimeString();
                var typeClass = 'debug-' + type;
                var typeIcon = type === 'error' ? '❌' : type === 'success' ? '✅' : type === 'warning' ? '⚠️' : 'ℹ️';

                var entry = $('<div class="debug-entry ' + typeClass + '">')
                    .html('<span style="color: #666;">[' + timestamp + ']</span> ' + typeIcon + ' ' + message);

                $('#sbi-debug-log').append(entry);
                $('#sbi-debug-log').scrollTop($('#sbi-debug-log')[0].scrollHeight);

                // Also log to console
                console.log('SBI Debug [' + timestamp + ']:', message);

                // Show debug panel if not visible
                if (!$('#sbi-debug-panel').is(':visible')) {
                    $('#sbi-debug-panel').show();
                }
            }

            function debugAjaxCall(action, data, description) {
                if (!debugEnabled) return;
                debugLog('🔄 Starting AJAX call: ' + description + ' (action: ' + action + ')');
                debugLog('📤 Request data: ' + JSON.stringify(data, null, 2));
            }

            function debugAjaxResponse(response, description) {
                if (!debugEnabled) return;
                if (response.success) {
                    debugLog('✅ AJAX success: ' + description);
                    debugLog('📥 Response data: ' + JSON.stringify(response.data, null, 2), 'success');
                } else {
                    debugLog('❌ AJAX error: ' + description + ' - ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    debugLog('📥 Error response: ' + JSON.stringify(response, null, 2), 'error');
                }
            }

            function debugAjaxFail(xhr, status, error, description) {
                if (!debugEnabled) return;
                debugLog('💥 AJAX failed: ' + description, 'error');
                debugLog('📥 XHR status: ' + status + ', Error: ' + error, 'error');
                debugLog('📥 Response text: ' + xhr.responseText, 'error');
            }

            // Debug panel controls (only if debug enabled)
            if (debugEnabled) {
                $('#sbi-clear-debug').click(function() {
                    $('#sbi-debug-log').html('<div class="debug-entry">Debug log cleared...</div>');
                });

                $('#sbi-toggle-debug').click(function() {
                    if ($('#sbi-debug-panel').is(':visible')) {
                        $('#sbi-debug-panel').hide();
                        $(this).text('Show Debug');
                    } else {
                        $('#sbi-debug-panel').show();
                        $(this).text('Hide Debug');
                    }
                });

                // Initialize debug
                debugLog('🚀 KISS Smart Batch Installer debug initialized');

                // Create global debug object for progress updates
                window.sbiDebug = {
                    addEntry: function(status, step, message) {
                        var icon = status === 'error' ? '❌' : status === 'success' ? '✅' : status === 'info' ? 'ℹ️' : '⚠️';
                        var fullMessage = step + ': ' + message;
                        debugLog(icon + ' ' + fullMessage, status === 'error' ? 'error' : status === 'success' ? 'success' : 'info');
                    }
                };
            }

            // Start progressive loading if organization is set
            var organization = '<?php echo esc_js( get_option( 'sbi_github_organization', '' ) ); ?>';
            if (organization) {
                startProgressiveLoading(organization);
            }

            function startProgressiveLoading(org) {
                // Check if already loading using FSM or fallback
                if (isSystemLoading()) {
                    debugLog('⏸️ Already loading, skipping', 'warning');
                    return;
                }
                setSystemLoading(true);

                debugLog('🚀 Starting progressive loading for organization: ' + org);
                $('#sbi-initial-loading').show();
                $('#sbi-repository-form').hide();

                // First, fetch the repository list (using saved limit setting)
                repositoryLimit = <?php echo (int) get_option( 'sbi_repository_limit', 1 ); ?>;
                var requestData = {
                    action: 'sbi_fetch_repository_list',
                    organization: org,
                    limit: repositoryLimit,
                    nonce: ajaxNonce
                };

                debugAjaxCall('sbi_fetch_repository_list', requestData, 'Fetch repository list');

                $.post(ajaxurl, requestData)
                .done(function(response) {
                    debugAjaxResponse(response, 'Fetch repository list');
                    if (response.success) {
                        repositories = response.data.repositories;
                        totalRepositories = repositories.length;
                        var totalAvail = (response.data && (response.data.total_available||response.data.total_available===0)) ? response.data.total_available : null;
                        var limitUsed = response.data && response.data.limit_used ? response.data.limit_used : repositoryLimit;
                        if (totalAvail !== null) {
                            debugLog('📊 Found ' + totalRepositories + ' repositories to process (limit ' + limitUsed + '), GitHub total available: ' + totalAvail);
                        } else {
                            debugLog('📊 Found ' + totalRepositories + ' repositories to process (limit ' + limitUsed + ')');
                        }

                        if (totalRepositories === 0) {
                            debugLog('⚠️ No repositories found', 'warning');
                            showNoRepositories();
                            return;
                        }

                        // Hide initial loading and show the table
                        $('#sbi-initial-loading').hide();
                        $('#sbi-repository-form').show();
                        $('#sbi-loading-progress').show();

                        // Reset system state
                        setSystemLoading(false);
                        debugLog('🛑 Reset system state for new loading cycle');

                        // Start processing repositories one by one (truly sequential)
                        currentIndex = 0;
                        debugLog('🔄 Starting progressive loading of ' + totalRepositories + ' repositories (limited to ' + repositoryLimit + ')');

                        // Show user how many repositories we're processing
                        $('#sbi-progress-text').text('Processing ' + totalRepositories + ' repositories (limited to ' + repositoryLimit + ')...');

                        processNextRepository();
                    } else {
                        debugLog('❌ Failed to fetch repositories: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                        showError('Failed to fetch repositories: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                })
                .fail(function(xhr, status, error) {
                    debugAjaxFail(xhr, status, error, 'Fetch repository list');
                    showError('Failed to fetch repositories. Please try again.');
                });
            }

            function processNextRepository() {
                // Prevent multiple simultaneous processing using FSM state
                if (isSystemLoading()) {
                    debugLog('⏸️ Skipping processNextRepository - system already processing', 'warning');
                    return;
                }

                if (currentIndex >= totalRepositories) {
                    // All repositories processed
                    debugLog('🎉 All repositories processed successfully', 'success');
                    $('#sbi-loading-progress').hide();
                    updateItemCount();
                    setSystemLoading(false);
                    return;
                }

                setRepositoryProcessing(repositories[currentIndex].full_name, true);
                var repo = repositories[currentIndex];
                var progress = Math.round(((currentIndex + 1) / totalRepositories) * 100);

                debugLog('🔄 Processing repository ' + (currentIndex + 1) + '/' + totalRepositories + ': ' + repo.name);

                // Update progress
                $('#sbi-progress-text').text('Processing ' + repo.name + ' (' + (currentIndex + 1) + '/' + totalRepositories + ')');

                // Add loading row for current repository only
                addLoadingRow(repo);

                // Process the repository (strictly one at a time)
                var requestData = {
                    action: 'sbi_process_repository',
                    repository: repo,
                    nonce: ajaxNonce,
                    timeout: 60000 // Increased to 60 second timeout
                };

                debugAjaxCall('sbi_process_repository', requestData, 'Process repository: ' + repo.name);

                $.post(ajaxurl, requestData)
                .done(function(response) {
                    debugAjaxResponse(response, 'Process repository: ' + repo.name);
                    // Processing request has completed
                    setRepositoryProcessing(repo.full_name, false);

                    var advanceAfter = function(delayMs) {
                        // Move to the next repository only after optional render completes
                        debugLog('🏁 Finished processing repository: ' + repo.full_name);
                        currentIndex++;
                        var ms = delayMs || 5000;
                        debugLog('⏳ Waiting ' + (ms/1000) + ' seconds before next repository...');
                        setTimeout(function() { processNextRepository(); }, ms);
                    };

                    if (response.success) {
                        debugLog('✅ Successfully processed repository: ' + repo.name, 'success');
                        // Replace loading row with actual data and wait for render
                        var isLast = (currentIndex + 1) >= totalRepositories;
                        var renderXhr = replaceLoadingRow(repo.full_name, response.data.repository, isLast, totalRepositories, repositoryLimit);
                        if (renderXhr && typeof renderXhr.always === 'function') {
                            renderXhr.always(function(){ advanceAfter(5000); });
                        } else {
                            // In case of unexpected missing jqXHR, advance conservatively
                            advanceAfter(5000);
                        }
                    } else {
                        debugLog('❌ Error processing repository: ' + repo.name + ' - ' + (response.data.message || 'Unknown error'), 'error');
                        // Show error in the row then advance
                        showRepositoryError(repo.full_name, response.data.message || 'Unknown error');
                        advanceAfter(5000);
                    }
                })
                .fail(function(xhr, status, error) {
                    debugAjaxFail(xhr, status, error, 'Process repository: ' + repo.name);
                    // Processing request failed
                    setRepositoryProcessing(repo.full_name, false);

                    var errorMsg = 'Request failed';
                    if (status === 'timeout') {
                        errorMsg = 'Request timed out after 60 seconds';
                        debugLog('⏰ Repository processing timed out: ' + repo.name, 'error');
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    showRepositoryError(repo.full_name, errorMsg);

                    // Advance after failure without waiting for render
                    debugLog('🏁 Finished processing repository: ' + repo.full_name);
                    currentIndex++;
                    debugLog('⏳ Waiting 5 seconds before next repository...');
                    setTimeout(function() { processNextRepository(); }, 5000);
                });
            }

            function addLoadingRow(repo) {
                var rowId = 'repo-' + repo.full_name.replace(/[^a-zA-Z0-9]/g, '-');
                var loadingRow = $('<tr>')
                    .attr('id', rowId)
                    .addClass('sbi-loading-row')
                    .attr('data-repository', repo.full_name)
                    .attr('data-repo-name', repo.name)
                    .attr('data-repo-owner', repo.full_name.split('/')[0] || '');

                // Add cells for each column
                var columns = <?php echo json_encode( array_keys( $this->list_table->get_columns() ) ); ?>;
                columns.forEach(function(column) {
                    var cell = $('<td>').addClass('column-' + column);

                    if (column === 'cb') {
                        // Empty checkbox cell
                    } else if (column === 'name') {
                        cell.html('<strong>' + escapeHtml(repo.name) + '</strong><div class="sbi-loading-indicator"><span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Scanning for WordPress plugin...</div>');
                    } else if (column === 'description') {
                        cell.text(repo.description || 'No description available');
                    } else if (column === 'plugin_status') {
                        cell.html('<span class="sbi-status-scanning"><span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Scanning...</span>');
                    } else if (column === 'actions') {
                        cell.html('<span class="sbi-actions-loading">Loading...</span>');
                    }

                    loadingRow.append(cell);
                });

                $('#sbi-repository-tbody').append(loadingRow);
                updateItemCount();
            }

            function replaceLoadingRow(repoFullName, processedRepo, isLast, listTotal, limitUsed) {
                // Use data attribute for more resilient targeting
                var targetRow = $('[data-repository="' + repoFullName + '"]');

                debugLog('🔄 Replacing loading row for: ' + repoFullName + ' (found: ' + targetRow.length + ' rows)');

                // Get the rendered row HTML
                var requestData = {
                    action: 'sbi_render_repository_row',
                    repository: processedRepo,
                    nonce: ajaxNonce,
                    is_last: !!isLast,
                    list_total: listTotal || 0,
                    limit_used: limitUsed || 0
                };

                debugAjaxCall('sbi_render_repository_row', requestData, 'Render row for: ' + repoFullName);

                return $.post(ajaxurl, requestData)
                .done(function(response) {
                    debugAjaxResponse(response, 'Render row for: ' + repoFullName);
                    if (response.success) {
                        debugLog('✅ Successfully rendered row for: ' + repoFullName, 'success');
                        targetRow.replaceWith(response.data.row_html);
                        debugLog('🔄 Row replaced in DOM for: ' + repoFullName);
                        if (response.data && response.data.checksum) {
                            var cs = response.data.checksum;
                            debugLog('📦 Checksum — account: ' + (cs.account||'?') + ', total_available: ' + (cs.total_available!=null?cs.total_available:'?') + ', list_total: ' + (cs.list_total||0) + ', limit_used: ' + (cs.limit_used||0));
                        }
                    } else {
                        debugLog('❌ Failed to render row for: ' + repoFullName + ' - ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                        showRepositoryError(repoFullName, 'Failed to render repository row');
                    }
                })
                .fail(function(xhr, status, error) {
                    debugAjaxFail(xhr, status, error, 'Render row for: ' + repoFullName);
                    showRepositoryError(repoFullName, 'AJAX error rendering row');
                });
            }

            function showRepositoryError(repoFullName, errorMessage) {
                var rowId = 'repo-' + repoFullName.replace(/[^a-zA-Z0-9]/g, '-');
                var errorRow = $('#' + rowId);

                errorRow.find('.sbi-loading-indicator, .sbi-status-scanning, .sbi-actions-loading').html(
                    '<span style="color: #d63638;">Error: ' + escapeHtml(errorMessage) + '</span>'
                );
                errorRow.removeClass('sbi-loading-row').addClass('sbi-error-row');
            }

            function showNoRepositories() {
                $('#sbi-initial-loading').hide();
                $('#sbi-repository-form').show();
                $('#sbi-repository-tbody').html('<tr><td colspan="5" style="text-align: center; padding: 40px;">No repositories found for this organization.</td></tr>');
                setSystemLoading(false);
            }

            function showError(message) {
                $('#sbi-initial-loading').hide();
                $('#sbi-loading-progress').hide();

                var errorHtml = '<p>' + escapeHtml(message) + '</p>';

                // Add helpful message for rate limiting
                if (message.indexOf('rate limit') !== -1) {
                    errorHtml += '<p><strong>Tip:</strong> GitHub limits unauthenticated requests to 60 per hour. ';
                    errorHtml += 'Try again in a few minutes, or consider setting up a GitHub token for higher limits.</p>';
                    errorHtml += '<p><a href="https://docs.github.com/en/rest/overview/resources-in-the-rest-api#rate-limiting" target="_blank">Learn more about GitHub rate limits</a></p>';
                }

                var errorDiv = $('<div class="notice notice-error">' + errorHtml + '</div>');
                $('.sbi-repository-list h2').after(errorDiv);
                setSystemLoading(false);
            }

            function updateItemCount() {
                var count = $('#sbi-repository-tbody tr').length;
                $('#sbi-item-count').text(count + ' items');
            }

            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            // Install plugin button - handled by admin.js

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

            // Bulk actions handling
            $('#doaction, #doaction2').click(function(e) {
                e.preventDefault();

                var action = $(this).siblings('select').val();
                if (action === '-1') {
                    alert('<?php esc_html_e( 'Please select an action.', 'kiss-smart-batch-installer' ); ?>');
                    return;
                }

                var checkedBoxes = $('input[name="repositories[]"]:checked');
                if (checkedBoxes.length === 0) {
                    alert('<?php esc_html_e( 'Please select at least one repository.', 'kiss-smart-batch-installer' ); ?>');
                    return;
                }

                var repositories = [];
                checkedBoxes.each(function() {
                    repositories.push({
                        full_name: $(this).val(),
                        owner: $(this).data('owner'),
                        repo: $(this).data('repo'),
                        plugin_file: $(this).data('plugin-file')
                    });
                });

                // Disable the button and show progress
                var button = $(this);
                var originalText = button.val();
                button.prop('disabled', true).val('<?php esc_html_e( 'Processing...', 'kiss-smart-batch-installer' ); ?>');

                // Show progress message
                var progressDiv = $('<div class="notice notice-info"><p><?php esc_html_e( 'Processing bulk action, please wait...', 'kiss-smart-batch-installer' ); ?></p></div>');
                $('.sbi-repository-list h2').after(progressDiv);

                if (action === 'install') {
                    performBulkInstall(repositories, button, originalText, progressDiv);
                } else if (action === 'activate') {
                    performBulkActivate(repositories, button, originalText, progressDiv);
                } else if (action === 'deactivate') {
                    performBulkDeactivate(repositories, button, originalText, progressDiv);
                } else if (action === 'refresh') {
                    performBulkRefresh(repositories, button, originalText, progressDiv);
                }
            });

            function performBulkInstall(repositories, button, originalText, progressDiv) {
                var repoData = repositories.map(function(repo) {
                    return {
                        owner: repo.owner,
                        repo: repo.repo,
                        branch: 'main'
                    };
                });

                $.post(ajaxurl, {
                    action: 'sbi_batch_install',
                    repositories: repoData,
                    activate: false,
                    nonce: ajaxNonce
                }, function(response) {
                    handleBulkResponse(response, button, originalText, progressDiv);
                }).fail(function() {
                    handleBulkError(button, originalText, progressDiv);
                });
            }

            function performBulkActivate(repositories, button, originalText, progressDiv) {
                var pluginFiles = repositories.map(function(repo) {
                    return {
                        plugin_file: repo.plugin_file,
                        repository: repo.repo
                    };
                });

                $.post(ajaxurl, {
                    action: 'sbi_batch_activate',
                    plugin_files: pluginFiles,
                    nonce: ajaxNonce
                }, function(response) {
                    handleBulkResponse(response, button, originalText, progressDiv);
                }).fail(function() {
                    handleBulkError(button, originalText, progressDiv);
                });
            }

            function performBulkDeactivate(repositories, button, originalText, progressDiv) {
                var pluginFiles = repositories.map(function(repo) {
                    return {
                        plugin_file: repo.plugin_file,
                        repository: repo.repo
                    };
                });

                $.post(ajaxurl, {
                    action: 'sbi_batch_deactivate',
                    plugin_files: pluginFiles,
                    nonce: ajaxNonce
                }, function(response) {
                    handleBulkResponse(response, button, originalText, progressDiv);
                }).fail(function() {
                    handleBulkError(button, originalText, progressDiv);
                });
            }

            function performBulkRefresh(repositories, button, originalText, progressDiv) {
                // For refresh, we'll just reload the page after a short delay
                setTimeout(function() {
                    progressDiv.remove();
                    button.prop('disabled', false).val(originalText);
                    location.reload();
                }, 1000);
            }

            function handleBulkResponse(response, button, originalText, progressDiv) {
                progressDiv.remove();
                button.prop('disabled', false).val(originalText);

                if (response.success) {
                    var message = response.data.message || '<?php esc_html_e( 'Bulk action completed successfully.', 'kiss-smart-batch-installer' ); ?>';
                    var successDiv = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
                    $('.sbi-repository-list h2').after(successDiv);

                    // Refresh the page after 2 seconds to update the status
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    var errorMessage = response.data.message || '<?php esc_html_e( 'Bulk action failed.', 'kiss-smart-batch-installer' ); ?>';
                    var errorDiv = $('<div class="notice notice-error is-dismissible"><p>' + errorMessage + '</p></div>');
                    $('.sbi-repository-list h2').after(errorDiv);
                }
            }

            function handleBulkError(button, originalText, progressDiv) {
                progressDiv.remove();
                button.prop('disabled', false).val(originalText);

                var errorDiv = $('<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'An error occurred while processing the bulk action.', 'kiss-smart-batch-installer' ); ?></p></div>');
                $('.sbi-repository-list h2').after(errorDiv);
            }

            // Debug detection button

                // One-time dismiss for web-only DNS tip
                $(document).on('click', '#sbi-dismiss-webonly-tip', function(e){
                    e.preventDefault();
                    var notice = $(this).closest('.notice');
                    notice.fadeOut(200);
                    // Persist dismissal
                    $.post(ajaxurl, { action: 'sbi_dismiss_webonly_tip', nonce: ajaxNonce });
                });
            $('#debug-detection').click(function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php esc_html_e( 'Running Debug...', 'kiss-smart-batch-installer' ); ?>');

                $.post(ajaxurl, {
                    action: 'sbi_debug_detection',
                    nonce: ajaxNonce
                }, function(response) {
                    button.prop('disabled', false).text('<?php esc_html_e( 'Debug Detection', 'kiss-smart-batch-installer' ); ?>');

                    if (response.success) {
                        console.log('Debug Detection Results:', response.data.results);
                        alert('Debug completed! Check browser console for detailed results.');
                    } else {
                        alert('Debug failed: ' + response.data.message);
                    }
                }).fail(function() {
                    button.prop('disabled', false).text('<?php esc_html_e( 'Debug Detection', 'kiss-smart-batch-installer' ); ?>');
                    alert('Debug request failed.');
                });
            });
        });
        </script>
        <?php
    }
}
