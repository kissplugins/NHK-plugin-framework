<?php
/**
 * WordPress List Table for displaying GitHub repositories.
 *
 * @package SBI\Admin
 */

namespace SBI\Admin;

use WP_List_Table;
use SBI\Services\GitHubService;
use SBI\Services\PluginDetectionService;
use SBI\Services\StateManager;
use SBI\Enums\PluginState;

/**
 * Repository List Table class.
 */
class RepositoryListTable extends WP_List_Table {

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
     * Current organization.
     *
     * @var string
     */
    private string $organization = '';

    /**
     * Helper: derive canonical plugin boolean from FSM state (SSoT).
     */
    private function is_plugin_from_state($state): bool {
        return in_array(
            $state,
            [ PluginState::AVAILABLE, PluginState::INSTALLED_ACTIVE, PluginState::INSTALLED_INACTIVE ],
            true
        );
    }

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

        parent::__construct( [
            'singular' => 'repository',
            'plural'   => 'repositories',
            'ajax'     => true,
        ] );
    }

    /**
     * Set organization for repository fetching.
     *
     * @param string $organization GitHub organization name.
     */
    public function set_organization( string $organization ): void {
        $this->organization = $organization;
    }

    /**
     * Get table columns.
     *
     * @return array
     */
    public function get_columns(): array {
        return [
            'cb'          => '<input type="checkbox" />',
            'name'        => __( 'Repository', 'kiss-smart-batch-installer' ),
            'description' => __( 'Description', 'kiss-smart-batch-installer' ),
            'plugin_status' => __( 'Plugin Status', 'kiss-smart-batch-installer' ),
            'state'       => __( 'Installation State', 'kiss-smart-batch-installer' ),
            'updated'     => __( 'Last Updated', 'kiss-smart-batch-installer' ),
            'actions'     => __( 'Actions', 'kiss-smart-batch-installer' ),
        ];
    }

    /**
     * Get sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns(): array {
        return [
            'name'    => [ 'name', false ],
            'updated' => [ 'updated', true ],
        ];
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions(): array {
        return [
            'install'   => __( 'Install Selected', 'kiss-smart-batch-installer' ),
            'activate'  => __( 'Activate Selected', 'kiss-smart-batch-installer' ),
            'deactivate' => __( 'Deactivate Selected', 'kiss-smart-batch-installer' ),
            'refresh'   => __( 'Refresh Status', 'kiss-smart-batch-installer' ),
        ];
    }

    /**
     * Prepare table items.
     */
    public function prepare_items(): void {
        if ( empty( $this->organization ) ) {
            $this->items = [];
            return;
        }

        // Fetch repositories from GitHub
        $repositories = $this->github_service->fetch_repositories_for_account( $this->organization );
        
        if ( is_wp_error( $repositories ) ) {
            $this->items = [];
            return;
        }

        // Process repositories with plugin detection and state
        $processed_items = [];
        foreach ( $repositories as $repo ) {
            $processed_items[] = $this->process_repository( $repo );
        }

        // Handle sorting
        $orderby = $_GET['orderby'] ?? 'updated';
        $order = $_GET['order'] ?? 'desc';
        
        usort( $processed_items, function( $a, $b ) use ( $orderby, $order ) {
            $result = 0;
            
            switch ( $orderby ) {
                case 'name':
                    $result = strcmp( $a['name'], $b['name'] );
                    break;
                case 'updated':
                    $result = strtotime( $a['updated_at'] ) - strtotime( $b['updated_at'] );
                    break;
            }
            
            return ( $order === 'asc' ) ? $result : -$result;
        });

        // Handle pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count( $processed_items );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );

        $this->items = array_slice( $processed_items, ( $current_page - 1 ) * $per_page, $per_page );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    /**
     * Process repository data with plugin detection and state.
     *
     * IMPORTANT: The FSM (StateManager) is the Single Source of Truth (SSoT).
     * - UI must derive "plugin vs not" from state, not raw detection flags
     * - Detection is used only to enrich metadata (name/version) and to help
     *   StateManager converge during refreshes
     *
     * @param array $repo Repository data from GitHub.
     * @return array Processed repository data.
     */
    private function process_repository( array $repo ): array {
        $repo_name = $repo['full_name'];

        // Enrich with detection (best-effort; may be skipped via option) via StateManager wrapper for SSoT
        $detection_result = $this->state_manager->detect_plugin_info( $repo );
        $detected_is_plugin = ! is_wp_error( $detection_result ) && ( $detection_result['is_plugin'] ?? false );

        // Get FSM state
        $state = $this->state_manager->get_state( $repo_name );

        // SAFEGUARD: Normalize state conservatively if detection strongly contradicts
        // only for non-installed states. Installed states always win.
        if ( ! in_array( $state, [ PluginState::INSTALLED_ACTIVE, PluginState::INSTALLED_INACTIVE ], true ) ) {
            if ( $detected_is_plugin && $state === PluginState::NOT_PLUGIN ) {
                $state = PluginState::AVAILABLE; // prefer "can install" over "not plugin"
            }
        }

        // Derive canonical is_plugin from FSM state (SSoT)
        $is_plugin_by_state = in_array( $state, [ PluginState::AVAILABLE, PluginState::INSTALLED_ACTIVE, PluginState::INSTALLED_INACTIVE ], true );

        return array_merge( $repo, [
            // Canonical flag is derived from FSM state only
            'is_plugin' => $this->is_plugin_from_state($state),
            'plugin_data' => $detected_is_plugin ? ( $detection_result['plugin_data'] ?? [] ) : [],
            'installation_state' => $state,
        ] );
    }

    /**
     * Default column output.
     *
     * @param array  $item        Repository item.
     * @param string $column_name Column name.
     * @return string
     */
    public function column_default( $item, $column_name ): string {
        switch ( $column_name ) {
            case 'description':
                return esc_html( $item['description'] ?: __( 'No description available', 'kiss-smart-batch-installer' ) );
            case 'updated':
                return esc_html( human_time_diff( strtotime( $item['updated_at'] ) ) . ' ago' );
            default:
                return '';
        }
    }

    /**
     * Checkbox column.
     *
     * @param array $item Repository item.
     * @return string
     */
    public function column_cb( $item ): string {
        // Only show checkbox for WordPress plugins (FSM SSoT)
        if ( ! $this->is_plugin_from_state( $item['installation_state'] ) ) {
            return '';
        }

        // Extract owner from full_name
        $owner = '';
        if ( isset( $item['full_name'] ) && strpos( $item['full_name'], '/' ) !== false ) {
            $owner = explode( '/', $item['full_name'] )[0];
        }

        return sprintf(
            '<input type="checkbox" name="repositories[]" value="%s" data-owner="%s" data-repo="%s" data-plugin-file="%s" />',
            esc_attr( $item['full_name'] ),
            esc_attr( $owner ),
            esc_attr( $item['name'] ),
            esc_attr( $item['plugin_file'] ?? '' )
        );
    }

    /**
     * Name column with repository link.
     *
     * @param array $item Repository item.
     * @return string
     */
    public function column_name( $item ): string {
        $name = esc_html( $item['name'] );
        $url = esc_url( $item['html_url'] );
        
        $output = sprintf(
            '<strong><a href="%s" target="_blank" rel="noopener">%s</a></strong>',
            $url,
            $name
        );
        
        // Add language badge if available
        if ( ! empty( $item['language'] ) ) {
            $output .= sprintf(
                ' <span class="language-badge" style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 8px;">%s</span>',
                esc_html( $item['language'] )
            );
        }
        
        return $output;
    }

    /**
     * Plugin status column.
     *
     * @param array $item Repository item.
     * @return string
     */
    public function column_plugin_status( $item ): string {
        $state = $item['installation_state'];

        // Render based on FSM state only (SSoT)
        if ( in_array( $state, [ PluginState::UNKNOWN, PluginState::CHECKING ], true ) ) {
            return '<span class="sbi-status-scanning"><span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' . esc_html__( 'Scanning...', 'kiss-smart-batch-installer' ) . '</span>';
        }

        if ( $state === PluginState::NOT_PLUGIN ) {
            return '<span style="color: #999;">❌ ' . esc_html__( 'No plugin detected', 'kiss-smart-batch-installer' ) . '</span>';
        }

        if ( ! $this->is_plugin_from_state( $state ) ) {
            return '<span style="color: #999;">❓ ' . esc_html__( 'Unknown', 'kiss-smart-batch-installer' ) . '</span>';
        }

        $plugin_name = $item['plugin_data']['Plugin Name'] ?? $item['name'];
        $version = $item['plugin_data']['Version'] ?? '';

        $output = '<span style="color: #46b450;">✅ ' . esc_html__( 'WordPress Plugin', 'kiss-smart-batch-installer' ) . '</span>';
        $output .= '<br><strong>' . esc_html( $plugin_name ) . '</strong>';

        if ( $version ) {
            $output .= '<br><small>v' . esc_html( $version ) . '</small>';
        }

        return $output;
    }

    /**
     * Installation state column.
     *
     * @param array $item Repository item.
     * @return string
     */
    public function column_state( $item ): string {
        $state = $item['installation_state'];
        
        switch ( $state ) {
            case PluginState::INSTALLED_ACTIVE:
                return '<span style="color: #46b450;">🟢 ' . esc_html__( 'Active', 'kiss-smart-batch-installer' ) . '</span>';
            case PluginState::INSTALLED_INACTIVE:
                return '<span style="color: #ffb900;">🟡 ' . esc_html__( 'Installed', 'kiss-smart-batch-installer' ) . '</span>';
            case PluginState::AVAILABLE:
                return '<span style="color: #0073aa;">🔵 ' . esc_html__( 'Available', 'kiss-smart-batch-installer' ) . '</span>';
            case PluginState::NOT_PLUGIN:
                return '<span style="color: #999;">⚪ ' . esc_html__( 'No plugin detected', 'kiss-smart-batch-installer' ) . '</span>';
            case PluginState::ERROR:
                return '<span style="color: #d63638;">🔴 ' . esc_html__( 'Error', 'kiss-smart-batch-installer' ) . '</span>';
            default:
                return '<span style="color: #999;">❓ ' . esc_html__( 'Unknown', 'kiss-smart-batch-installer' ) . '</span>';
        }
    }

    /**
     * Actions column.
     *
     * @param array $item Repository item.
     * @return string
     */
    public function column_actions( $item ): string {
        $actions = [];

        if ( ! $this->is_plugin_from_state( $item['installation_state'] ) ) {
            // Not a plugin: show info text, but still render Refresh button
            $actions[] = '<span style="color: #999;">' . esc_html__( 'No actions available', 'kiss-smart-batch-installer' ) . '</span>';
        } else {
            $state = $item['installation_state'];
            $repo_full_name = $item['full_name'];
            $repo_name = $item['name'];

            // Extract owner from full_name (owner/repo)
            $owner = '';
            if ( isset( $item['full_name'] ) && strpos( $item['full_name'], '/' ) !== false ) {
                list($owner, $repo_name) = explode( '/', $item['full_name'], 2 );
            }

            switch ( $state ) {
                case PluginState::AVAILABLE:
                    $actions[] = sprintf(
                        '<button type="button" class="button button-primary sbi-install-plugin" data-repo="%s" data-owner="%s">%s</button>',
                        esc_attr( $repo_name ),
                        esc_attr( $owner ),
                        esc_html__( 'Install', 'kiss-smart-batch-installer' )
                    );
                    break;
                case PluginState::INSTALLED_INACTIVE:
                    $plugin_file = $item['plugin_file'] ?? '';
                    if ( empty( $plugin_file ) ) {
                        // Query FSM for installed plugin file (fallback included)
                        $plugin_file = $this->state_manager->getInstalledPluginFile( $repo_full_name );
                    }
                    $actions[] = sprintf(
                        '<button type="button" class="button button-secondary sbi-activate-plugin" data-repo="%s" data-owner="%s" data-plugin-file="%s">%s</button>',
                        esc_attr( $repo_name ),
                        esc_attr( $owner ),
                        esc_attr( $plugin_file ),
                        esc_html__( 'Activate', 'kiss-smart-batch-installer' )
                    );
                    break;
                case PluginState::INSTALLED_ACTIVE:
                    $plugin_file = $item['plugin_file'] ?? '';
                    if ( empty( $plugin_file ) ) {
                        // Query FSM for installed plugin file (fallback included)
                        $plugin_file = $this->state_manager->getInstalledPluginFile( $repo_full_name );
                    }
                    $actions[] = sprintf(
                        '<button type="button" class="button button-secondary sbi-deactivate-plugin" data-repo="%s" data-owner="%s" data-plugin-file="%s">%s</button>',
                        esc_attr( $repo_name ),
                        esc_attr( $owner ),
                        esc_attr( $plugin_file ),
                        esc_html__( 'Deactivate', 'kiss-smart-batch-installer' )
                    );
                    break;
            }
        }

        // Always add refresh action
        $actions[] = sprintf(
            '<button type="button" class="button button-small sbi-refresh-status" data-repo="%s">%s</button>',
            esc_attr( $item['full_name'] ?? '' ),
            esc_html__( 'Refresh', 'kiss-smart-batch-installer' )
        );

        return implode( ' ', $actions );
    }

    /**
     * @deprecated 1.0.31 Unused legacy method. Use StateManager methods for plugin identification.
     *
     * Normalize strings for robust slug comparisons.
     */
    private function normalize_slug_str(string $s): string {
        $s = strtolower($s);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    /**
     * @deprecated 1.0.31 Use StateManager::getInstalledPluginFile instead.
     * @see StateManager::getInstalledPluginFile()
     *
     * This method bypasses the FSM and should not be used in new code.
     * The StateManager provides FSM-aware plugin file resolution.
     */
    private function find_installed_plugin( string $plugin_slug ): string {
        $repo = $this->organization ? $this->organization . '/' . $plugin_slug : $plugin_slug;
        return $this->state_manager->getInstalledPluginFile( $repo );
    }

    /**
     * Display when no items found.
     */
    public function no_items(): void {
        if ( empty( $this->organization ) ) {
            esc_html_e( 'Please configure a GitHub organization to display repositories.', 'kiss-smart-batch-installer' );
        } else {
            printf(
                esc_html__( 'No repositories found for organization: %s', 'kiss-smart-batch-installer' ),
                '<strong>' . esc_html( $this->organization ) . '</strong>'
            );
        }
    }

    /**
     * Override single_row to add data attributes for FSM DOM targeting.
     *
     * @param array $item Repository item data.
     */
    public function single_row( $item ): void {
        $row_id = 'repo-' . sanitize_html_class( $item['full_name'] );

        // Extract owner from full_name
        $owner = '';
        if ( isset( $item['full_name'] ) && strpos( $item['full_name'], '/' ) !== false ) {
            $owner = explode( '/', $item['full_name'] )[0];
        }

        printf(
            '<tr id="%s" data-repository="%s" data-repo-name="%s" data-repo-owner="%s" data-repo-state="%s">',
            esc_attr( $row_id ),
            esc_attr( $item['full_name'] ),
            esc_attr( $item['name'] ),
            esc_attr( $owner ),
            esc_attr( $item['installation_state']->value ?? 'unknown' )
        );

        $this->single_row_columns( $item );

        echo '</tr>';
    }

    /**
     * Render a single repository row for progressive loading.
     *
     * @param array $item Repository item data.
     * @return string HTML for the table row.
     */
    public function render_single_row( array $item ): string {
        ob_start();
        $this->single_row( $item );
        return ob_get_clean();
    }

    /**
     * Render a loading placeholder row.
     *
     * @param array $repository Basic repository data.
     * @return string HTML for the loading row.
     */
    public function render_loading_row( array $repository ): string {
        $columns = $this->get_columns();
        $row_id = 'repo-' . sanitize_html_class( $repository['full_name'] );

        ob_start();
        ?>
        <tr id="<?php echo esc_attr( $row_id ); ?>"
            class="sbi-loading-row"
            data-repository="<?php echo esc_attr( $repository['full_name'] ); ?>"
            data-repo-name="<?php echo esc_attr( $repository['name'] ); ?>"
            data-repo-owner="<?php echo esc_attr( explode('/', $repository['full_name'])[0] ?? '' ); ?>">
            <?php foreach ( $columns as $column_name => $column_display_name ): ?>
                <td class="<?php echo esc_attr( $column_name ); ?> column-<?php echo esc_attr( $column_name ); ?>">
                    <?php if ( $column_name === 'cb' ): ?>
                        <!-- Empty checkbox cell -->
                    <?php elseif ( $column_name === 'name' ): ?>
                        <strong><?php echo esc_html( $repository['name'] ); ?></strong>
                        <div class="sbi-loading-indicator">
                            <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                            <?php esc_html_e( 'Scanning for WordPress plugin...', 'kiss-smart-batch-installer' ); ?>
                        </div>
                    <?php elseif ( $column_name === 'description' ): ?>
                        <?php echo esc_html( $repository['description'] ?: __( 'No description available', 'kiss-smart-batch-installer' ) ); ?>
                    <?php elseif ( $column_name === 'plugin_status' ): ?>
                        <span class="sbi-status-scanning">
                            <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                            <?php esc_html_e( 'Scanning...', 'kiss-smart-batch-installer' ); ?>
                        </span>
                    <?php elseif ( $column_name === 'actions' ): ?>
                        <span class="sbi-actions-loading">
                            <?php esc_html_e( 'Loading...', 'kiss-smart-batch-installer' ); ?>
                        </span>
                    <?php else: ?>
                        <!-- Empty cell for other columns -->
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
        <?php
        return ob_get_clean();
    }
}
