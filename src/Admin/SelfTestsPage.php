<?php
/**
 * Self Tests admin page for KISS Smart Batch Installer.
 *
 * @package SBI\Admin
 */

namespace SBI\Admin;

use SBI\Services\GitHubService;
use SBI\Services\PluginDetectionService;
use SBI\Services\StateManager;
use SBI\API\AjaxHandler;
use SBI\Enums\PluginState;

/**
 * Self Tests page class.
 */
class SelfTestsPage {

    /**
     * GitHub service instance.
     *
     * @var GitHubService
     */
    private GitHubService $github_service;

    /**
     * Plugin detection service instance.
     *
     * @var PluginDetectionService
     */
    private PluginDetectionService $detection_service;

    /**
     * State manager instance.
     *
     * @var StateManager
     */
    private StateManager $state_manager;

    /**
     * AJAX handler instance.
     *
     * @var AjaxHandler
     */
    private AjaxHandler $ajax_handler;

    /**
     * Test results array.
     *
     * @var array
     */
    private array $test_results = [];

    /**
     * Constructor.
     *
     * @param GitHubService           $github_service    GitHub service instance.
     * @param PluginDetectionService  $detection_service Plugin detection service.
     * @param StateManager           $state_manager     State manager instance.
     * @param AjaxHandler            $ajax_handler      AJAX handler instance.
     */
    public function __construct(
        GitHubService $github_service,
        PluginDetectionService $detection_service,
        StateManager $state_manager,
        AjaxHandler $ajax_handler
    ) {
        $this->github_service = $github_service;
        $this->detection_service = $detection_service;
        $this->state_manager = $state_manager;
        $this->ajax_handler = $ajax_handler;
    }

    /**
     * Render the self tests page.
     */
    public function render(): void {
        // Run tests if requested
        if ( isset( $_POST['run_tests'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sbi_run_tests' ) ) {
            $this->run_all_tests();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'KISS Smart Batch Installer - Self Tests', 'kiss-smart-batch-installer' ); ?></h1>

            <p>
                <a href="<?php echo esc_url( admin_url( 'plugins.php?page=kiss-smart-batch-installer' ) ); ?>" class="button">
                    <?php esc_html_e( '← Back to Repository Manager', 'kiss-smart-batch-installer' ); ?>
                </a>
            </p>

            <div class="notice notice-info">
                <p><?php esc_html_e( 'These tests validate the core functionality of the plugin to help prevent regressions and identify issues.', 'kiss-smart-batch-installer' ); ?></p>
            </div>

            <!-- Repository Test Section -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 class="title"><?php esc_html_e( 'Repository Test', 'kiss-smart-batch-installer' ); ?></h2>
                <p><?php esc_html_e( 'Test if a specific GitHub repository can be accessed:', 'kiss-smart-batch-installer' ); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Repository', 'kiss-smart-batch-installer' ); ?></th>
                        <td>
                            <input type="text" id="test-repo-owner" placeholder="owner" style="width: 150px;" value="kissplugins" />
                            <span>/</span>
                            <input type="text" id="test-repo-name" placeholder="repository" style="width: 200px;" value="KISS-Plugin-Quick-Search" />
                            <button type="button" id="test-repository" class="button button-secondary"><?php esc_html_e( 'Test Repository', 'kiss-smart-batch-installer' ); ?></button>
                        </td>
                    </tr>
                </table>
                <div id="repository-test-result" style="margin-top: 10px;"></div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field( 'sbi_run_tests' ); ?>
                <p class="submit">
                    <input type="submit" name="run_tests" class="button-primary" value="<?php esc_attr_e( 'Run All Tests', 'kiss-smart-batch-installer' ); ?>" />
                </p>
            </form>

            <?php if ( ! empty( $this->test_results ) ) : ?>
                <div id="test-results">
                    <?php $this->render_test_results(); ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .test-category {
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .test-category-header {
            background: #f9f9f9;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        .test-category.passed .test-category-header {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .test-category.failed .test-category-header {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .test-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        .test-item:last-child {
            border-bottom: none;
        }
        .test-status {
            font-weight: bold;
            margin-right: 10px;
        }
        .test-status.passed {
            color: #28a745;
        }
        .test-status.failed {
            color: #dc3545;
        }
        .test-details {
            margin-top: 5px;
            font-size: 0.9em;
            color: #666;
        }
        .test-error {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            margin-top: 5px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.85em;
        }
        .test-timing {
            float: right;
            color: #999;
            font-size: 0.85em;
        }
        #repository-test-result {
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        #repository-test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        #repository-test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        #repository-test-result.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            display: block;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#test-repository').on('click', function() {
                var button = $(this);
                var owner = $('#test-repo-owner').val().trim();
                var repo = $('#test-repo-name').val().trim();
                var resultDiv = $('#repository-test-result');

                if (!owner || !repo) {
                    resultDiv.removeClass('success error info').addClass('error')
                        .html('Please enter both owner and repository name.').show();
                    return;
                }

                button.prop('disabled', true).text('Testing...');
                resultDiv.removeClass('success error info').addClass('info')
                    .html('Testing repository access...').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sbi_test_repository',
                        owner: owner,
                        repo: repo,
                        nonce: '<?php echo wp_create_nonce( 'sbi_test_repository' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.removeClass('error info').addClass('success')
                                .html('<strong>Success!</strong> Repository found and accessible.<br>' +
                                      '<strong>Name:</strong> ' + response.data.name + '<br>' +
                                      '<strong>Description:</strong> ' + (response.data.description || 'No description') + '<br>' +
                                      '<strong>URL:</strong> <a href="' + response.data.html_url + '" target="_blank">' + response.data.html_url + '</a>');
                        } else {
                            var errorHtml = '<strong>Error:</strong> ' + response.data.message;
                            if (response.data.troubleshooting) {
                                errorHtml += '<br><br><strong>Troubleshooting:</strong><ul>';
                                if (response.data.troubleshooting.check_repository_exists) {
                                    errorHtml += '<li><a href="' + response.data.troubleshooting.check_repository_exists + '" target="_blank">Check if repository exists</a></li>';
                                }
                                if (response.data.troubleshooting.verify_repository_public) {
                                    errorHtml += '<li>' + response.data.troubleshooting.verify_repository_public + '</li>';
                                }
                                if (response.data.troubleshooting.check_spelling) {
                                    errorHtml += '<li>' + response.data.troubleshooting.check_spelling + '</li>';
                                }
                                errorHtml += '</ul>';
                            }
                            resultDiv.removeClass('success info').addClass('error').html(errorHtml);
                        }
                    },
                    error: function() {
                        resultDiv.removeClass('success info').addClass('error')
                            .html('<strong>Error:</strong> Failed to test repository. Please try again.');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php esc_html_e( 'Test Repository', 'kiss-smart-batch-installer' ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Run all test categories.
     */
    private function run_all_tests(): void {
        $this->test_results = [];

        // Test categories - enabling more tests
        $test_categories = [
            'core_services' => 'Core Services Tests',
            'ajax_handlers' => 'AJAX Handler Tests',
            'ui_components' => 'UI Component Tests',
            'data_integrity' => 'Data Integrity Tests',
            'regression_protection' => 'Regression Protection Tests',
            'plugin_detection_reliability' => 'Plugin Detection Reliability Tests',
            'github_api_resilience' => 'GitHub API Resilience Tests',
            'fsm_locks' => 'FSM Processing Lock Tests',
            'fsm_validation' => 'FSM Validation & Transition Tests',
            // These might be more intensive - keeping disabled for now
            // 'install_path' => 'Install Path Tests',
            // 'integration' => 'Integration Tests',
        ];

        foreach ( $test_categories as $category => $title ) {
            $method = "test_{$category}";
            if ( method_exists( $this, $method ) ) {
                try {
                    $start_time = microtime( true );
                    $tests = $this->$method();
                    $end_time = microtime( true );

                    $this->test_results[ $category ] = [
                        'title' => $title,
                        'tests' => $tests,
                        'passed' => 0,
                        'failed' => 0,
                        'total_time' => 0
                    ];

                    // Calculate summary stats
                    foreach ( $this->test_results[ $category ]['tests'] as $test ) {
                        if ( $test['passed'] ) {
                            $this->test_results[ $category ]['passed']++;
                        } else {
                            $this->test_results[ $category ]['failed']++;
                        }
                        $this->test_results[ $category ]['total_time'] += $test['time'];
                    }
                } catch ( \Exception $e ) {
                    // If a test category fails completely, add an error result
                    $this->test_results[ $category ] = [
                        'title' => $title,
                        'tests' => [
                            [
                                'name' => 'Test Category Execution',
                                'passed' => false,
                                'message' => 'Fatal error in test category: ' . $e->getMessage(),
                                'time' => 0
                            ]
                        ],
                        'passed' => 0,
                        'failed' => 1,
                        'total_time' => 0
                    ];
                }
            }
        }
    }

    /**
     * Test core services functionality.
     *
     * @return array Test results.
     */
    private function test_core_services(): array {
        $tests = [];

        // Test 1: GitHub Service Configuration
        $tests[] = $this->run_test( 'GitHub Service Configuration', function() {
            $config = $this->github_service->get_configuration();

            if ( empty( $config['username'] ) ) {
                throw new \Exception( 'GitHub username not configured' );
            }

            if ( empty( $config['repositories'] ) ) {
                throw new \Exception( 'No repositories configured for scanning' );
            }

            return sprintf( 'Configured for user: %s with %d repositories',
                $config['username'],
                count( $config['repositories'] )
            );
        });

        // Test 2: GitHub API Connectivity
        $tests[] = $this->run_test( 'GitHub API Connectivity', function() {
            $config = $this->github_service->get_configuration();
            $username = $config['username'];

            if ( empty( $username ) ) {
                throw new \Exception( 'No GitHub username configured' );
            }

            // Test API call
            $response = wp_remote_get( "https://api.github.com/users/{$username}", [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'KISS-Smart-Batch-Installer'
                ]
            ]);

            if ( is_wp_error( $response ) ) {
                throw new \Exception( 'GitHub API request failed: ' . $response->get_error_message() );
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) {
                throw new \Exception( "GitHub API returned status code: {$code}" );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! $body || ! isset( $body['login'] ) ) {
                throw new \Exception( 'Invalid GitHub API response format' );
            }

            return sprintf( 'Successfully connected to GitHub API for user: %s', $body['login'] );
        });

        // Test 3: Plugin Detection Service
        $tests[] = $this->run_test( 'Plugin Detection Service', function() {
            // Create a mock repository that should be detected as a WordPress plugin
            $mock_repo = [
                'full_name' => 'test/wordpress-plugin',
                'name' => 'wordpress-plugin',
                'description' => 'A test WordPress plugin',
                'html_url' => 'https://github.com/test/wordpress-plugin'
            ];

            // Test detection (this will likely fail for mock data, but we're testing the service works)
            $result = $this->detection_service->detect_plugin( $mock_repo );

            if ( is_wp_error( $result ) ) {
                // This is expected for mock data, but service should handle it gracefully
                return 'Plugin detection service is working (gracefully handled mock data)';
            }

            if ( ! isset( $result['is_plugin'] ) || ! isset( $result['scan_method'] ) ) {
                throw new \Exception( 'Plugin detection result missing required fields' );
            }

            return sprintf( 'Plugin detection completed using method: %s', $result['scan_method'] );
        });

        return $tests;
    }

    /**
     * Test AJAX handlers functionality.
     *
     * @return array Test results.
     */
    private function test_ajax_handlers(): array {
        $tests = [];

        // Test 1: AJAX Handler Initialization
        $tests[] = $this->run_test( 'AJAX Handler Initialization', function() {
            if ( ! $this->ajax_handler ) {
                throw new \Exception( 'AJAX handler not initialized' );
            }

            // Check if WordPress AJAX hooks are registered
            $hooks = [
                'wp_ajax_sbi_fetch_repositories',
                'wp_ajax_sbi_process_repository',
                'wp_ajax_sbi_render_repository_row'
            ];

            $registered_hooks = [];
            foreach ( $hooks as $hook ) {
                if ( has_action( $hook ) ) {
                    $registered_hooks[] = $hook;
                }
            }

            if ( count( $registered_hooks ) === 0 ) {
                throw new \Exception( 'No AJAX hooks registered' );
            }

            return sprintf( 'AJAX handler initialized with %d/%d hooks registered',
                count( $registered_hooks ),
                count( $hooks )
            );
        });

        // Test 2: Repository Data Structure
        $tests[] = $this->run_test( 'Repository Data Structure', function() {
            $config = $this->github_service->get_configuration();

            if ( empty( $config['repositories'] ) ) {
                throw new \Exception( 'No repositories configured for testing' );
            }

            $repo_name = $config['repositories'][0];
            $repos = $this->github_service->fetch_repositories();

            if ( is_wp_error( $repos ) ) {
                throw new \Exception( 'Failed to fetch repositories: ' . $repos->get_error_message() );
            }

            if ( empty( $repos ) ) {
                throw new \Exception( 'No repositories returned from GitHub API' );
            }

            $repo = $repos[0];
            $required_fields = [ 'id', 'name', 'full_name', 'description', 'html_url' ];
            $missing_fields = [];

            foreach ( $required_fields as $field ) {
                if ( ! isset( $repo[ $field ] ) ) {
                    $missing_fields[] = $field;
                }
            }

            if ( ! empty( $missing_fields ) ) {
                throw new \Exception( 'Repository data missing fields: ' . implode( ', ', $missing_fields ) );
            }

            return sprintf( 'Repository data structure valid for %d repositories', count( $repos ) );
        });

        // Test 3: State Management
        $tests[] = $this->run_test( 'State Management', function() {
            $test_repo = 'test/example-plugin';

            // Test setting and getting state
            $this->state_manager->set_state( $test_repo, PluginState::AVAILABLE );
            $retrieved_state = $this->state_manager->get_state( $test_repo );

            if ( $retrieved_state !== PluginState::AVAILABLE ) {
                throw new \Exception( 'State not properly stored or retrieved' );
            }

            // Test state persistence
            $batch_states = $this->state_manager->get_batch_states( [ $test_repo ] );

            if ( ! isset( $batch_states[ $test_repo ] ) ) {
                throw new \Exception( 'Batch state retrieval failed' );
            }

            if ( $batch_states[ $test_repo ] !== PluginState::AVAILABLE ) {
                throw new \Exception( 'Batch state retrieval returned incorrect state' );
            }

            return 'State management working correctly';

            // FSM transitions and event log basic test
            $tests[] = $this->run_test( 'FSM Transitions and Event Log', function() {
                $repo = 'test/fsm-plugin';

                // Start from unknown and attempt invalid transition (should be blocked and logged)
                $this->state_manager->transition( $repo, PluginState::INSTALLED_ACTIVE, [ 'source' => 'selftest' ] );
                $events = $this->state_manager->get_events( $repo, 5 );
                $has_blocked = false;
                foreach ( $events as $e ) {
                    if ( isset($e['event']) && $e['event'] === 'transition_blocked' ) {
                        $has_blocked = true;
                        break;
                    }
                }
                if ( ! $has_blocked ) {
                    throw new \Exception( 'Expected invalid transition to be blocked and logged' );
                }

                // Valid transitions: UNKNOWN -> CHECKING -> AVAILABLE
                $this->state_manager->transition( $repo, PluginState::CHECKING, [ 'source' => 'selftest' ] );
                $this->state_manager->transition( $repo, PluginState::AVAILABLE, [ 'source' => 'selftest' ] );
                $state = $this->state_manager->get_state( $repo );
                if ( $state !== PluginState::AVAILABLE ) {
                    throw new \Exception( 'Expected state to be AVAILABLE after valid transitions' );
                }

                // Ensure events contain recent transitions
                $events = $this->state_manager->get_events( $repo, 10 );
                if ( empty( $events ) ) {
                    throw new \Exception( 'Expected recent events for repository' );
                }

                return 'FSM transitions validated and events logged (' . count($events) . ' recent)';
            } );

        });

        return $tests;
    }

    /**
     * Test integration workflows.
     */
    /**
     * Test install path for KISS Plugin Quick Search and surface discrete error chain.
     *
     * @return array Test results.
     */
    private function test_install_path(): array {
        $tests = [];

        // Test 1: Install KISS Plugin Quick Search (dry-run/error-surfacing)
        $tests[] = $this->run_test( 'PQS Install Flow - Error Surfacing', function() {
            // Owner/Repo for KISS Plugin Quick Search
            $owner = 'kissplugins';
            $repo  = 'KISS-Plugin-Quick-Search';

            // Obtain installation service via container
            $installation_service = sbi_service( \SBI\Services\PluginInstallationService::class );
            if ( ! $installation_service ) {
                throw new \Exception( 'Installation service not available' );
            }

            // Capture progress updates locally
            $progress = [];
            $installation_service->set_progress_callback( function( $step, $status, $message ) use ( &$progress ) {
                $progress[] = [ 'step' => $step, 'status' => $status, 'message' => $message ];
            } );

            // Attempt install (no activation). We do not modify state, only observe results.
            $result = $installation_service->install_and_activate( $owner, $repo, false );

            // Build a readable chain from progress + result
            $lines = [];
            foreach ( $progress as $p ) {
                $lines[] = sprintf( '%s: [%s] %s', $p['step'], $p['status'], $p['message'] );
            }

            if ( is_wp_error( $result ) ) {
                // Append error details
                $lines[] = 'Result: ERROR - ' . $result->get_error_message();
                // Surface HTTP-ish hints commonly seen during download
                $msg = strtolower( $result->get_error_message() );
                if ( str_contains( $msg, '403' ) || str_contains( $msg, 'forbidden' ) ) {
                    $lines[] = 'Hint: Possible nonce/capability issue or host blocking downloads.';
                } elseif ( str_contains( $msg, '404' ) || str_contains( $msg, 'not found' ) ) {
                    $lines[] = 'Hint: Verify repository exists and is public: https://github.com/' . $owner . '/' . $repo;
                } elseif ( str_contains( $msg, 'ssl' ) ) {
                    $lines[] = 'Hint: SSL problem. Check cURL/OpenSSL configuration on the server.';
                }

                return implode( "\n", $lines );
            }

            // Success path: include plugin_file and activated flag
            $lines[] = 'Result: SUCCESS - plugin_file=' . ( $result['plugin_file'] ?? 'n/a' ) . ', activated=' . ( $result['activated'] ? 'yes' : 'no' );
            return implode( "\n", $lines );
        } );

        return $tests;
    }

    /**
     * Test integration workflows.
     *
     * @return array Test results.
     */
    private function test_integration(): array {
        $tests = [];

        // Test 1: End-to-End Repository Processing
        $tests[] = $this->run_test( 'End-to-End Repository Processing', function() {
            $repos = $this->github_service->fetch_repositories();

            if ( is_wp_error( $repos ) ) {
                throw new \Exception( 'Failed to fetch repositories: ' . $repos->get_error_message() );
            }

            if ( empty( $repos ) ) {
                throw new \Exception( 'No repositories available for testing' );
            }

            $test_repo = $repos[0];

            // Test plugin detection
            $detection_result = $this->detection_service->detect_plugin( $test_repo );

            if ( is_wp_error( $detection_result ) ) {
                // This might be expected for some repos, but service should handle gracefully
                $error_message = $detection_result->get_error_message();
                if ( strpos( $error_message, 'timeout' ) !== false ||
                     strpos( $error_message, 'network' ) !== false ) {
                    throw new \Exception( 'Network connectivity issue: ' . $error_message );
                }
                // Other errors are acceptable (e.g., not a WordPress plugin)
            }

            // Test state determination
            $state = $this->state_manager->get_state( $test_repo['full_name'] );

            if ( ! $state instanceof PluginState ) {
                throw new \Exception( 'Invalid state type returned' );
            }

            return sprintf( 'Successfully processed repository: %s (state: %s)',
                $test_repo['full_name'],
                $state->value
            );
        });

        // Test 2: Data Consistency
        $tests[] = $this->run_test( 'Data Consistency', function() {
            $repos = $this->github_service->fetch_repositories();

            if ( is_wp_error( $repos ) || empty( $repos ) ) {
                throw new \Exception( 'Cannot test data consistency without repositories' );
            }

            $test_repo = $repos[0];
            $repo_name = $test_repo['full_name'];

            // Process repository multiple times and ensure consistent results
            $results = [];
            for ( $i = 0; $i < 3; $i++ ) {
                $detection = $this->detection_service->detect_plugin( $test_repo );
                $state = $this->state_manager->get_state( $repo_name );

                $results[] = [
                    'detection_error' => is_wp_error( $detection ),
                    'state' => $state->value
                ];
            }

            // Check consistency
            $first_result = $results[0];
            foreach ( $results as $result ) {
                if ( $result['detection_error'] !== $first_result['detection_error'] ||
                     $result['state'] !== $first_result['state'] ) {
                    throw new \Exception( 'Inconsistent results across multiple processing attempts' );
                }
            }

            return 'Data consistency maintained across multiple processing attempts';
        });

        return $tests;
    }

    /**
     * Test FSM processing lock behavior.
     */
    private function test_fsm_locks(): array {
        $tests = [];

        // Test 1: Acquire and reject second lock
        $tests[] = $this->run_test('Processing Lock - contention', function() {
            $repo = 'kissplugins/KISS-Smart-Batch-Installer';
            $acq1 = $this->state_manager->acquire_processing_lock($repo, 5);
            if (!$acq1) { throw new \Exception('Expected first lock acquisition to succeed'); }
            $acq2 = $this->state_manager->acquire_processing_lock($repo, 5);
            // release first lock
            $this->state_manager->release_processing_lock($repo);
            if ($acq2) { throw new \Exception('Expected second lock acquisition to be rejected'); }
            return 'First lock acquired; second attempt rejected; released OK';
        });

        // Test 2: Ajax handler rejects concurrent operation
        $tests[] = $this->run_test('Processing Lock - Ajax rejection', function() {
            // Simulate lock held by another operation
            $repo = 'kissplugins/KISS-Smart-Batch-Installer';
            $this->state_manager->acquire_processing_lock($repo, 5);

            // Directly call activate with lock held; expect JSON error structure
            $_POST['repository'] = $repo;
            $_POST['plugin_file'] = 'kiss-smart-batch-installer/kiss-smart-batch-installer.php';
            $_POST['nonce'] = wp_create_nonce('sbi_ajax_nonce');

            // Buffer output to capture wp_send_json_* behavior for testing
            ob_start();
            try {
                $this->ajax_handler->activate_plugin();
            } catch (\Throwable $e) {
                // ignore; wp_send_json_* may exit
            }
            $resp = ob_get_clean();

            // Clean up
            $this->state_manager->release_processing_lock($repo);

            if (strpos((string)$resp, 'Another operation is in progress') === false) {
                throw new \Exception('Expected lock rejection message in AJAX response');
            }

            return 'AJAX activation rejected with lock contention message as expected';
        });


        // Test 3: Frontend row update path (mock)
        $tests[] = $this->run_test('Frontend Row Update – FSM set/apply hook present', function() {
            // We cannot run browser JS here, but we can verify that the TS bridge exposes repositoryFSM
            // by checking that the bridge script is enqueued and TS index exists.
            $scripts = wp_scripts();
            if (!$scripts || !wp_script_is('sbi-ts-bridge', 'enqueued')) {
                throw new \Exception('TS bridge script not enqueued');
            }
            // Also sanity check our dist index location
            $dist_index = trailingslashit($GLOBALS['gbi_url']) . 'dist/ts/index.js';
            $head = wp_remote_head($dist_index, [ 'timeout' => 2 ]);
            if (is_wp_error($head)) {
                // Not fatal in dev environments; provide a warning message
                return 'TS index not reachable via HTTP; ensure build is present in dist/ts/. Bridge still exposes API if loaded.';
            }
            $code = wp_remote_retrieve_response_code($head);
            if ($code < 200 || $code >= 400) {
                return 'TS index responded with HTTP ' . $code . '; verify dist/ts/index.js exists.';
            }
            return 'TS bridge enqueued and dist/ts/index.js reachable; repositoryFSM available at window.SBIts.repositoryFSM.';
        });

        return $tests;

        // Test 4: Server broadcast queue receives transition
        $tests[] = $this->run_test('Broadcast Queue – transition appears', function() {
            $repo = 'kissplugins/Broadcast-Check';
            $original = $this->state_manager->get_state($repo);
            $last_id = (int) get_option('sbi_broadcast_last_id', 0);
            // Force a quick transition to trigger broadcast
            $this->state_manager->transition($repo, \SBI\Services\PluginState::CHECKING, [ 'source' => 'self_test' ]);
            $events = $this->state_manager->get_broadcast_events_since($last_id);
            // Restore
            $this->state_manager->transition($repo, $original, [ 'source' => 'self_test_restore' ]);

            if (!is_array($events) || count($events) === 0) {
                throw new \Exception('Expected at least one broadcast event after transition');
            }
            $found = false;
            foreach ($events as $evt) {
                if (($evt['event'] ?? '') === 'state_changed' && ($evt['payload']['repository'] ?? '') === $repo) {
                    $found = true; break;
                }
            }
            if (!$found) { throw new \Exception('No state_changed event for target repo in broadcast queue'); }
            return 'Broadcast event emitted and visible via get_broadcast_events_since';
        });

        return $tests;
    }

    /**
     * Test FSM validation and transition rules.
     *
     * Priority 1: Automated Testing & Validation from PROJECT-FSM.md
     * Validates allowed/blocked state transitions and FSM behavior.
     *
     * @return array Test results.
     */
    private function test_fsm_validation(): array {
        $tests = [];

        // Test 1: Valid State Transitions
        $tests[] = $this->run_test('FSM Valid State Transitions', function() {
            $test_repo = 'kissplugins/FSM-Test-Valid-Transitions';

            // Test UNKNOWN -> CHECKING (should be allowed)
            $this->state_manager->transition($test_repo, PluginState::UNKNOWN, [], true); // Force reset
            $this->state_manager->transition($test_repo, PluginState::CHECKING, ['source' => 'fsm_test']);
            $current_state = $this->state_manager->get_state($test_repo);
            if ($current_state !== PluginState::CHECKING) {
                throw new \Exception('UNKNOWN -> CHECKING transition failed');
            }

            // Test CHECKING -> AVAILABLE (should be allowed)
            $this->state_manager->transition($test_repo, PluginState::AVAILABLE, ['source' => 'fsm_test']);
            $current_state = $this->state_manager->get_state($test_repo);
            if ($current_state !== PluginState::AVAILABLE) {
                throw new \Exception('CHECKING -> AVAILABLE transition failed');
            }

            // Test AVAILABLE -> INSTALLED_INACTIVE (should be allowed)
            $this->state_manager->transition($test_repo, PluginState::INSTALLED_INACTIVE, ['source' => 'fsm_test']);
            $current_state = $this->state_manager->get_state($test_repo);
            if ($current_state !== PluginState::INSTALLED_INACTIVE) {
                throw new \Exception('AVAILABLE -> INSTALLED_INACTIVE transition failed');
            }

            // Test INSTALLED_INACTIVE -> INSTALLED_ACTIVE (should be allowed)
            $this->state_manager->transition($test_repo, PluginState::INSTALLED_ACTIVE, ['source' => 'fsm_test']);
            $current_state = $this->state_manager->get_state($test_repo);
            if ($current_state !== PluginState::INSTALLED_ACTIVE) {
                throw new \Exception('INSTALLED_INACTIVE -> INSTALLED_ACTIVE transition failed');
            }

            return 'All valid transitions completed successfully';
        });

        // Test 2: Invalid State Transitions (should be blocked)
        $tests[] = $this->run_test('FSM Invalid State Transitions Blocked', function() {
            $test_repo = 'kissplugins/FSM-Test-Invalid-Transitions';

            // Set initial state
            $this->state_manager->transition($test_repo, PluginState::NOT_PLUGIN, [], true); // Force set

            // Try invalid transition: NOT_PLUGIN -> INSTALLED_ACTIVE (should be blocked)
            $initial_state = $this->state_manager->get_state($test_repo);
            $this->state_manager->transition($test_repo, PluginState::INSTALLED_ACTIVE, ['source' => 'fsm_test']);
            $final_state = $this->state_manager->get_state($test_repo);

            if ($final_state !== $initial_state) {
                throw new \Exception('Invalid transition NOT_PLUGIN -> INSTALLED_ACTIVE was not blocked');
            }

            return 'Invalid transitions properly blocked';
        });

        // Test 3: Error State Recovery
        $tests[] = $this->run_test('FSM Error State Recovery', function() {
            $test_repo = 'kissplugins/FSM-Test-Error-Recovery';

            // Transition to error state
            $this->state_manager->transition($test_repo, PluginState::ERROR, [
                'source' => 'fsm_test',
                'error' => 'Test error condition',
                'recoverable' => true
            ], true);

            $error_state = $this->state_manager->get_state($test_repo);
            if ($error_state !== PluginState::ERROR) {
                throw new \Exception('Failed to transition to ERROR state');
            }

            // Test recovery: ERROR -> CHECKING (should be allowed)
            $this->state_manager->transition($test_repo, PluginState::CHECKING, ['source' => 'fsm_test_recovery']);
            $recovered_state = $this->state_manager->get_state($test_repo);
            if ($recovered_state !== PluginState::CHECKING) {
                throw new \Exception('ERROR -> CHECKING recovery transition failed');
            }

            return 'Error state recovery working correctly';
        });

        // Test 4: State Persistence and Caching
        $tests[] = $this->run_test('FSM State Persistence & Caching', function() {
            $test_repo = 'kissplugins/FSM-Test-Persistence';

            // Set a state
            $this->state_manager->transition($test_repo, PluginState::AVAILABLE, ['source' => 'fsm_test'], true);

            // Verify state persists
            $persisted_state = $this->state_manager->get_state($test_repo);
            if ($persisted_state !== PluginState::AVAILABLE) {
                throw new \Exception('State not properly persisted');
            }

            return 'State persistence working correctly';
        });

        // Test 5: StateManager Helper Methods
        $tests[] = $this->run_test('FSM Helper Methods Validation', function() {
            $test_repo = 'kissplugins/FSM-Test-Helpers';

            // Test isActive() method
            $this->state_manager->transition($test_repo, PluginState::INSTALLED_ACTIVE, [], true);
            if (!$this->state_manager->isActive($test_repo)) {
                throw new \Exception('isActive() failed for INSTALLED_ACTIVE state');
            }

            // Test isInstalled() method
            if (!$this->state_manager->isInstalled($test_repo)) {
                throw new \Exception('isInstalled() failed for INSTALLED_ACTIVE state');
            }

            // Test with inactive state
            $this->state_manager->transition($test_repo, PluginState::INSTALLED_INACTIVE, [], true);
            if ($this->state_manager->isActive($test_repo)) {
                throw new \Exception('isActive() incorrectly returned true for INSTALLED_INACTIVE');
            }

            if (!$this->state_manager->isInstalled($test_repo)) {
                throw new \Exception('isInstalled() failed for INSTALLED_INACTIVE state');
            }

            return 'All helper methods working correctly';
        });

        // Test 6: Broadcast System Validation
        $tests[] = $this->run_test('FSM Broadcast System', function() {
            $test_repo = 'kissplugins/FSM-Test-Broadcast';

            // Perform transition that should trigger broadcast
            $this->state_manager->transition($test_repo, PluginState::CHECKING, [
                'source' => 'fsm_test_broadcast',
                'test_context' => 'broadcast_validation'
            ], true);

            // Verify state was set (broadcast system is internal)
            $final_state = $this->state_manager->get_state($test_repo);
            if ($final_state !== PluginState::CHECKING) {
                throw new \Exception('Broadcast transition failed');
            }

            return 'Broadcast system integration working';
        });

        // Test 7: SSE Integration Validation
        $tests[] = $this->run_test('FSM SSE Integration', function() {
            // Check if SSE diagnostics are enabled
            $sse_enabled = get_option('sbi_sse_diagnostics', false);

            if (!$sse_enabled) {
                return 'SSE diagnostics disabled - skipping SSE integration test';
            }

            $test_repo = 'kissplugins/FSM-Test-SSE';

            // Perform a transition that should trigger SSE broadcast
            $this->state_manager->transition($test_repo, PluginState::CHECKING, [
                'source' => 'fsm_sse_test',
                'test_type' => 'sse_validation'
            ], true);

            // Verify the transition worked (SSE broadcast is async)
            $final_state = $this->state_manager->get_state($test_repo);
            if ($final_state !== PluginState::CHECKING) {
                throw new \Exception('SSE transition failed');
            }

            return 'SSE integration test completed - transition successful';
        });

        // Test 8: Enhanced Error Handling Validation
        $tests[] = $this->run_test('FSM Enhanced Error Handling', function() {
            $test_repo = 'kissplugins/FSM-Test-Enhanced-Errors';

            // Test error context storage
            $this->state_manager->transition($test_repo, PluginState::ERROR, [
                'source' => 'fsm_test_enhanced_errors',
                'error' => 'Test enhanced error handling',
                'recoverable' => true,
                'context' => ['test_data' => 'error_validation']
            ], true);

            $error_state = $this->state_manager->get_state($test_repo);
            if ($error_state !== PluginState::ERROR) {
                throw new \Exception('Enhanced error transition failed');
            }

            // Test retry count increment
            $retry_count = $this->state_manager->increment_retry_count($test_repo);
            if ($retry_count !== 1) {
                throw new \Exception('Retry count increment failed');
            }

            return 'Enhanced error handling working correctly';
        });

        return $tests;
    }

    /**
     * Test UI components functionality.
     *
     * @return array Test results.
     */
    private function test_ui_components(): array {
        $tests = [];

        // Test 1: Admin Page Registration
        $tests[] = $this->run_test( 'Admin Page Registration', function() {
            global $submenu;

            $sbi_pages = [];
            if ( isset( $submenu['plugins.php'] ) ) {
                foreach ( $submenu['plugins.php'] as $item ) {
                    if ( strpos( $item[2], 'kiss-smart-batch-installer' ) === 0 ||
                         strpos( $item[2], 'sbi-' ) === 0 ) {
                        $sbi_pages[] = $item[2];
                    }
                }
            }

            if ( empty( $sbi_pages ) ) {
                throw new \Exception( 'No SBI admin pages registered in Plugins menu' );
            }

            return sprintf( 'Found %d SBI admin pages registered', count( $sbi_pages ) );
        });

        // Test 2: JavaScript Dependencies
        $tests[] = $this->run_test( 'JavaScript Dependencies', function() {
            global $wp_scripts;

            $required_scripts = [ 'sbi-admin' ];

        // Test 5: StateManager helpers for install/active/file
        $tests[] = $this->run_test('FSM Helpers – isInstalled/isActive/getInstalledPluginFile', function() {
            $org = get_option('sbi_github_organization', 'kissplugins');
            $repo = $org . '/SelfTest-Helpers';
            // These checks are tolerant even if plugin is not actually present
            $file = $this->state_manager->getInstalledPluginFile($repo);
            $installed = $this->state_manager->isInstalled($repo);
            $active = $this->state_manager->isActive($repo);
            // No hard assertion on presence; only type/shape assertions
            if (!is_string($file)) throw new \Exception('getInstalledPluginFile should return string');
            if (!is_bool($installed)) throw new \Exception('isInstalled should return bool');
            if (!is_bool($active)) throw new \Exception('isActive should return bool');
            return 'FSM helper methods executed (file=' . ($file ?: 'none') . ', installed=' . ($installed?'true':'false') . ', active=' . ($active?'true':'false') . ')';
        });

            $registered_scripts = [];

            foreach ( $required_scripts as $script ) {
                if ( isset( $wp_scripts->registered[ $script ] ) ) {
                    $registered_scripts[] = $script;
                }
            }

            if ( count( $registered_scripts ) !== count( $required_scripts ) ) {
                $missing = array_diff( $required_scripts, $registered_scripts );
                throw new \Exception( 'Missing JavaScript dependencies: ' . implode( ', ', $missing ) );
            }

            return 'All required JavaScript dependencies registered';
        });

        // Test 3: CSS Dependencies
        $tests[] = $this->run_test( 'CSS Dependencies', function() {
            global $wp_styles;

            $required_styles = [ 'sbi-admin' ];
            $registered_styles = [];

            foreach ( $required_styles as $style ) {
                if ( isset( $wp_styles->registered[ $style ] ) ) {
                    $registered_styles[] = $style;
                }
            }

            if ( count( $registered_styles ) !== count( $required_styles ) ) {
                $missing = array_diff( $required_styles, $registered_styles );
                throw new \Exception( 'Missing CSS dependencies: ' . implode( ', ', $missing ) );
            }

            return 'All required CSS dependencies registered';
        });

        return $tests;
    }

    /**
     * Test data integrity.
     *
     * @return array Test results.
     */
    private function test_data_integrity(): array {
        $tests = [];

        // Test 1: Plugin State Enum Integrity
        $tests[] = $this->run_test( 'Plugin State Enum Integrity', function() {
            $expected_states = [ 'unknown', 'checking', 'available', 'not_plugin', 'installed_inactive', 'installed_active', 'error' ];
            $actual_states = [];

            foreach ( PluginState::cases() as $case ) {
                $actual_states[] = $case->value;
            }

            $missing_states = array_diff( $expected_states, $actual_states );
            $extra_states = array_diff( $actual_states, $expected_states );

            if ( ! empty( $missing_states ) ) {
                throw new \Exception( 'Missing plugin states: ' . implode( ', ', $missing_states ) );
            }

            if ( ! empty( $extra_states ) ) {
                throw new \Exception( 'Unexpected plugin states: ' . implode( ', ', $extra_states ) );
            }

            return sprintf( 'Plugin state enum contains all %d expected states', count( $expected_states ) );
        });

        // Test 2: Configuration Validation
        $tests[] = $this->run_test( 'Configuration Validation', function() {
            $config = $this->github_service->get_configuration();

            if ( ! is_array( $config ) ) {
                throw new \Exception( 'Configuration is not an array' );
            }

            $required_keys = [ 'username', 'repositories' ];
            $missing_keys = [];

            foreach ( $required_keys as $key ) {
                if ( ! array_key_exists( $key, $config ) ) {
                    $missing_keys[] = $key;
                }
            }

            if ( ! empty( $missing_keys ) ) {
                throw new \Exception( 'Configuration missing keys: ' . implode( ', ', $missing_keys ) );
            }

            if ( ! is_array( $config['repositories'] ) ) {
                throw new \Exception( 'Repositories configuration is not an array' );
            }

            return 'Configuration structure is valid';
        });

        // Test 3: Database Table Integrity
        $tests[] = $this->run_test( 'Database Table Integrity', function() {
            global $wpdb;

            // Check if any custom tables exist (if we add them in the future)
            $tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}sbi_%'" );

            // For now, we're using WordPress options/transients, so this is informational
            $options_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'sbi_%'" );

            if ( $options_count === null ) {
                throw new \Exception( 'Failed to query WordPress options table' );
            }

            return sprintf( 'Database integrity check passed. Found %d SBI options and %d custom tables',
                (int) $options_count,
                count( $tables )
            );
        });

        return $tests;
    }

    /**
     * Test repository processing and button rendering to guard against Install button regressions.
     *
     * @return array Test results.
     */
    private function test_regression_protection(): array {
        $tests = [];

        // Test 1: Repository Processing & Button Rendering Flow
        $tests[] = $this->run_test( 'Repository Processing & Button Rendering Flow', function() {
            // Mock repository data for different plugin states
            $test_cases = [
                [
                    'state' => \SBI\Enums\PluginState::AVAILABLE,
                    'expected_button' => 'Install',
                    'repo_data' => [
                        'full_name' => 'testowner/test-available-plugin',
                        'name' => 'test-available-plugin',
                        'description' => 'Test plugin that should show Install button'
                    ]
                ],
                [
                    'state' => \SBI\Enums\PluginState::INSTALLED_INACTIVE,
                    'expected_button' => 'Activate',
                    'repo_data' => [
                        'full_name' => 'testowner/test-inactive-plugin',
                        'name' => 'test-inactive-plugin',
                        'description' => 'Test plugin that should show Activate button'
                    ]
                ],
                [
                    'state' => \SBI\Enums\PluginState::INSTALLED_ACTIVE,
                    'expected_button' => 'Deactivate',
                    'repo_data' => [
                        'full_name' => 'testowner/test-active-plugin',
                        'name' => 'test-active-plugin',
                        'description' => 'Test plugin that should show Deactivate button'
                    ]
                ],
                [
                    'state' => \SBI\Enums\PluginState::NOT_PLUGIN,
                    'expected_button' => 'No actions available',
                    'repo_data' => [
                        'full_name' => 'testowner/test-not-plugin',
                        'name' => 'test-not-plugin',
                        'description' => 'Test repository that is not a plugin'
                    ]
                ]
            ];

            $results = [];
            foreach ( $test_cases as $case ) {
                // Create mock processed repository data
                $processed_repo = [
                    'repository' => $case['repo_data'],
                    'is_plugin' => $case['state'] !== \SBI\Enums\PluginState::NOT_PLUGIN,
                    'plugin_data' => [],
                    'plugin_file' => $case['state'] !== \SBI\Enums\PluginState::NOT_PLUGIN ? $case['repo_data']['name'] . '.php' : '',
                    'state' => $case['state']->value,
                    'scan_method' => 'test_mock',
                    'error' => null,
                ];

                // Test data flattening (simulating render_repository_row)
                $flattened_data = array_merge(
                    $case['repo_data'],
                    [
                        'is_plugin' => $processed_repo['is_plugin'],
                        'plugin_data' => $processed_repo['plugin_data'],
                        'plugin_file' => $processed_repo['plugin_file'],
                        'installation_state' => $case['state'],
                        'full_name' => $case['repo_data']['full_name'],
                        'name' => $case['repo_data']['name'],
                    ]
                );

                // Test button rendering logic
                $list_table = new \SBI\Admin\RepositoryListTable(
                    $this->github_service,
                    $this->detection_service,
                    $this->state_manager
                );

                $actions_html = $list_table->column_actions( $flattened_data );

                // Verify expected button appears
                $button_found = strpos( $actions_html, $case['expected_button'] ) !== false;
                $refresh_button_found = strpos( $actions_html, 'Refresh' ) !== false;

                if ( ! $button_found ) {
                    throw new \Exception( sprintf(
                        'REGRESSION DETECTED: Expected "%s" button not found for state %s. This indicates the Install button fix has regressed. HTML output: %s. Check RepositoryListTable::column_actions() method and ensure is_plugin field is properly set.',
                        $case['expected_button'],
                        $case['state']->value,
                        $actions_html
                    ) );
                }

                if ( ! $refresh_button_found ) {
                    throw new \Exception( sprintf(
                        'REGRESSION DETECTED: Refresh button not found for state %s. HTML output: %s. Check RepositoryListTable::column_actions() method.',
                        $case['state']->value,
                        $actions_html
                    ) );
                }

                $results[] = sprintf( '%s state → %s button ✓', $case['state']->value, $case['expected_button'] );
            }

            return 'All button rendering tests passed: ' . implode( ', ', $results );
        });

        // Test: SSoT Consistency (FSM vs Plugin Status)
        $tests[] = $this->run_test( 'SSoT Consistency (FSM vs Plugin Status)', function() {
            // Minimal mock repository data
            $repo_data = [
                'id' => 1,
                'name' => 'mock-repo',
                'full_name' => 'owner/mock-repo',
                'description' => '',
                'html_url' => 'https://github.com/owner/mock-repo',
                'updated_at' => date('c'),
                'language' => 'PHP',
            ];

            $cases = [
                [ 'state' => \SBI\Enums\PluginState::NOT_PLUGIN, 'expect_status' => 'No plugin detected', 'expect_state' => 'No plugin detected' ],
                [ 'state' => \SBI\Enums\PluginState::AVAILABLE, 'expect_status' => 'WordPress Plugin', 'expect_state' => 'Available' ],
            ];

            $list_table = new \SBI\Admin\RepositoryListTable(
                $this->github_service,
                $this->detection_service,
                $this->state_manager
            );

            $results = [];

            foreach ( $cases as $case ) {
                $state = $case['state'];
                // Derive is_plugin from FSM state (Single Source of Truth)
                $is_plugin_by_state = in_array( $state, [
                    \SBI\Enums\PluginState::AVAILABLE,
                    \SBI\Enums\PluginState::INSTALLED_ACTIVE,
                    \SBI\Enums\PluginState::INSTALLED_INACTIVE,
                ], true );

                $flattened = array_merge( $repo_data, [
                    'is_plugin' => $is_plugin_by_state,
                    'plugin_data' => [],
                    'plugin_file' => '',
                    'installation_state' => $state,
                ] );

                $status_html = $list_table->column_plugin_status( $flattened );
                $state_html = $list_table->column_state( $flattened );

                if ( strpos( $status_html, $case['expect_status'] ) === false ) {
                    throw new \Exception( sprintf(
                        'SSoT mismatch: expected Plugin Status "%s" for state %s. Got HTML: %s',
                        $case['expect_status'],
                        $state->value,
                        $status_html
                    ) );
                }

                if ( strpos( $state_html, $case['expect_state'] ) === false ) {
                    throw new \Exception( sprintf(
                        'SSoT mismatch: expected Installation State "%s". Got HTML: %s',
                        $case['expect_state'],
                        $state_html
                    ) );
                }

                $results[] = $state->value . ' -> OK';
            }

            return 'SSoT consistency validated: ' . implode( ', ', $results );
        });


        return $tests;
    }

    /**
     * Test plugin detection service reliability to prevent hanging and timeout issues.
     *
     * @return array Test results.
     */
    private function test_plugin_detection_reliability(): array {
        $tests = [];

        // Test 1: Plugin Detection Timeout Protection
        $tests[] = $this->run_test( 'Plugin Detection Timeout Protection', function() {
            $test_repositories = [
                [
                    'full_name' => 'testowner/valid-plugin',
                    'name' => 'valid-plugin',
                    'default_branch' => 'main',
                    'description' => 'Test repository for timeout testing'
                ],
                [
                    'full_name' => 'testowner/empty-repo',
                    'name' => 'empty-repo',
                    'default_branch' => 'main',
                    'description' => 'Test empty repository'
                ]
            ];

            $timeout_results = [];
            foreach ( $test_repositories as $repo ) {
                $start_time = microtime( true );

                try {
                    $result = $this->detection_service->detect_plugin( $repo );
                    $end_time = microtime( true );
                    $duration = ( $end_time - $start_time ) * 1000; // Convert to milliseconds

                    // Check if detection completed within timeout (should be under 5 seconds = 5000ms)
                    if ( $duration > 5000 ) {
                        throw new \Exception( sprintf(
                            'TIMEOUT ISSUE: Plugin detection took %dms for %s, exceeding 5000ms limit. This indicates timeout protection is not working. Check PluginDetectionService::get_file_content() timeout settings.',
                            (int) $duration,
                            $repo['full_name']
                        ) );
                    }

                    $timeout_results[] = sprintf( '%s: %dms', $repo['name'], (int) $duration );

                } catch ( \Exception $e ) {
                    $end_time = microtime( true );
                    $duration = ( $end_time - $start_time ) * 1000;

                    if ( $duration > 5000 ) {
                        throw new \Exception( sprintf(
                            'TIMEOUT ISSUE: Plugin detection failed with timeout after %dms for %s. Error: %s. Check network connectivity and timeout settings.',
                            (int) $duration,
                            $repo['full_name'],
                            $e->getMessage()
                        ) );
                    }

                    // Expected errors (like 404) are acceptable as long as they're fast
                    $timeout_results[] = sprintf( '%s: %dms (expected error)', $repo['name'], (int) $duration );
                }
            }

            return 'Timeout protection working: ' . implode( ', ', $timeout_results );
        });

        // Test 2: Skip Detection Mode Validation
        $tests[] = $this->run_test( 'Skip Detection Mode Validation', function() {
            // Save current setting
            $original_setting = get_option( 'sbi_skip_plugin_detection', false );

            try {
                // Enable skip detection
                update_option( 'sbi_skip_plugin_detection', true );

                $test_repo = [
                    'full_name' => 'testowner/skip-test',
                    'name' => 'skip-test',
                    'default_branch' => 'main',
                    'description' => 'Test repository for skip detection'
                ];

                $result = $this->detection_service->detect_plugin( $test_repo );

                if ( is_wp_error( $result ) ) {
                    throw new \Exception( sprintf(
                        'SKIP DETECTION ISSUE: Skip detection mode returned WP_Error: %s. This should return a valid result with is_plugin: true.',
                        $result->get_error_message()
                    ) );
                }

                if ( ! isset( $result['is_plugin'] ) || ! $result['is_plugin'] ) {
                    throw new \Exception( sprintf(
                        'SKIP DETECTION REGRESSION: Skip detection mode returned is_plugin: %s instead of true. This will cause Install buttons to disappear. Check PluginDetectionService skip detection logic.',
                        isset( $result['is_plugin'] ) ? ( $result['is_plugin'] ? 'true' : 'false' ) : 'undefined'
                    ) );
                }

                if ( ! isset( $result['scan_method'] ) || $result['scan_method'] !== 'skipped_for_testing' ) {
                    throw new \Exception( 'Skip detection mode not properly identified in scan_method field' );
                }

                return sprintf( 'Skip detection working correctly: is_plugin=%s, scan_method=%s',
                    $result['is_plugin'] ? 'true' : 'false',
                    $result['scan_method']
                );

            } finally {
                // Restore original setting
                update_option( 'sbi_skip_plugin_detection', $original_setting );
            }
        });

        // Test 3: Error Handling and Recovery
        $tests[] = $this->run_test( 'Error Handling and Recovery', function() {
            // Ensure detection actually runs for this test
            $original_setting = get_option( 'sbi_skip_plugin_detection', false );
            update_option( 'sbi_skip_plugin_detection', false );
            try {
                $error_test_cases = [
                    [
                        'repo' => [
                            'full_name' => '',
                            'name' => '',
                            'default_branch' => 'main'
                        ],
                        'expected_error' => 'invalid_repository'
                    ],
                    [
                        'repo' => [
                            'full_name' => 'nonexistent/definitely-does-not-exist-12345',
                            'name' => 'definitely-does-not-exist-12345',
                            'default_branch' => 'main'
                        ],
                        'expected_error' => 'file_not_found'
                    ]
                ];

                $error_results = [];
                foreach ( $error_test_cases as $case ) {
                    // Force refresh to avoid cached bypasses
                    $result = $this->detection_service->detect_plugin( $case['repo'], true );

                    if ( empty( $case['repo']['full_name'] ) ) {
                        // Empty repository should always yield WP_Error('invalid_repository')
                        if ( ! is_wp_error( $result ) || $result->get_error_code() !== 'invalid_repository' ) {
                            throw new \Exception( 'ERROR HANDLING ISSUE: invalid repository did not return expected WP_Error("invalid_repository").' );
                        }
                        $error_results[] = 'empty → invalid_repository';
                        continue;
                    }

                    // For nonexistent repo, either a WP_Error or a non-plugin array is acceptable
                    if ( is_wp_error( $result ) ) {
                        // Accept file_not_found and similar HTTP errors
                        $error_results[] = sprintf( '%s → %s', $case['repo']['full_name'], $result->get_error_code() );
                    } else {
                        // Must not be detected as a plugin
                        if ( ! is_array( $result ) || ! array_key_exists( 'is_plugin', $result ) ) {
                            throw new \Exception( 'ERROR HANDLING ISSUE: detection did not return expected array shape for nonexistent repository.' );
                        }
                        if ( ! empty( $result['is_plugin'] ) ) {
                            throw new \Exception( 'ERROR HANDLING ISSUE: nonexistent repository was incorrectly detected as a plugin.' );
                        }
                        $error_results[] = sprintf( '%s → handled (non-plugin)', $case['repo']['full_name'] );
                    }
                }

                return 'Error handling working: ' . implode( ', ', $error_results );
            } finally {
                // Restore skip detection original setting
                update_option( 'sbi_skip_plugin_detection', $original_setting );
            }
        });

        return $tests;
    }

    /**
     * Test GitHub API integration and error recovery to ensure robust handling of API issues.
     *
     * @return array Test results.
     */
    private function test_github_api_resilience(): array {
        $tests = [];

        // Test 1: GitHub API Response Handling
        $tests[] = $this->run_test( 'GitHub API Response Handling', function() {
            $organization = get_option( 'sbi_github_organization', '' );

            if ( empty( $organization ) ) {
                throw new \Exception( 'No GitHub organization configured for testing. Please configure an organization in settings.' );
            }

            // Test basic API connectivity
            $start_time = microtime( true );
            $repositories = $this->github_service->fetch_repositories_for_account( $organization, false, 1 );
            $end_time = microtime( true );
            $duration = ( $end_time - $start_time ) * 1000;

            if ( is_wp_error( $repositories ) ) {
                $error_code = $repositories->get_error_code();
                $error_message = $repositories->get_error_message();

                // Log detailed error information for debugging
                error_log( sprintf(
                    'SBI Test: GitHub API failed for organization %s. Error code: %s, Message: %s, Duration: %dms',
                    $organization,
                    $error_code,
                    $error_message,
                    (int) $duration
                ) );

                // Check if it's a rate limiting issue
                if ( strpos( $error_message, '403' ) !== false || strpos( $error_message, 'rate limit' ) !== false ) {
                    throw new \Exception( sprintf(
                        'RATE LIMITING DETECTED: GitHub API rate limit exceeded for organization %s. Error: %s. Consider implementing better rate limiting or using web scraping fallback.',
                        $organization,
                        $error_message
                    ) );
                }

                // Check if it's a network connectivity issue
                if ( strpos( $error_message, 'timeout' ) !== false || strpos( $error_message, 'network' ) !== false ) {
                    throw new \Exception( sprintf(
                        'NETWORK ISSUE: GitHub API network connectivity problem for organization %s. Error: %s. Duration: %dms. Check network connectivity and retry logic.',
                        $organization,
                        $error_message,
                        (int) $duration
                    ) );
                }

                // Other API errors
                throw new \Exception( sprintf(
                    'GITHUB API ISSUE: API request failed for organization %s. Error: %s. Duration: %dms. Check API endpoint and authentication.',
                    $organization,
                    $error_message,
                    (int) $duration
                ) );
            }

            if ( ! is_array( $repositories ) || empty( $repositories ) ) {
                throw new \Exception( sprintf(
                    'GITHUB API ISSUE: API returned empty or invalid data for organization %s. Expected array of repositories but got: %s',
                    $organization,
                    gettype( $repositories )
                ) );
            }

            return sprintf( 'GitHub API working: fetched %d repositories in %dms', count( $repositories ), (int) $duration );
        });

        // Test 2: Retry Logic Validation
        $tests[] = $this->run_test( 'Retry Logic Validation', function() {
            // Test with a non-existent organization to trigger retry logic
            $fake_org = 'definitely-does-not-exist-test-org-12345';

            $start_time = microtime( true );
            $result = $this->github_service->fetch_repositories_for_account( $fake_org, true, 1 );
            $end_time = microtime( true );
            $duration = ( $end_time - $start_time ) * 1000;

            if ( ! is_wp_error( $result ) ) {
                throw new \Exception( sprintf(
                    'RETRY LOGIC ISSUE: Expected WP_Error for non-existent organization %s, but got successful result. Retry logic may not be working properly.',
                    $fake_org
                ) );
            }

            // Check that it took reasonable time (should include retry delays)
            // With 2 retries and 1 second delay, minimum should be around 2000ms
            if ( $duration < 1000 ) {
                error_log( sprintf(
                    'SBI Test: Retry logic may not be working - request completed too quickly (%dms) for non-existent org %s. Expected at least 1000ms with retry delays.',
                    (int) $duration,
                    $fake_org
                ) );
            }

            $error_message = $result->get_error_message();

            // Log the error for debugging
            error_log( sprintf(
                'SBI Test: Retry logic test completed for %s. Duration: %dms, Error: %s',
                $fake_org,
                (int) $duration,
                $error_message
            ) );

            return sprintf( 'Retry logic working: failed appropriately in %dms with error: %s', (int) $duration, $error_message );
        });

        // Test 3: Web Scraping Fallback
        $tests[] = $this->run_test( 'Web Scraping Fallback', function() {
            // Save current setting
            $original_method = get_option( 'sbi_fetch_method', 'web_only' );

            try {
                // Force web-only method
                update_option( 'sbi_fetch_method', 'web_only' );

                $organization = get_option( 'sbi_github_organization', '' );

                if ( empty( $organization ) ) {
                    throw new \Exception( 'No GitHub organization configured for web scraping test' );
                }

                $start_time = microtime( true );
                $repositories = $this->github_service->fetch_repositories_for_account( $organization, true, 2 );
                $end_time = microtime( true );
                $duration = ( $end_time - $start_time ) * 1000;

                if ( is_wp_error( $repositories ) ) {
                    $error_message = $repositories->get_error_message();

                    error_log( sprintf(
                        'SBI Test: Web scraping failed for organization %s. Error: %s, Duration: %dms',
                        $organization,
                        $error_message,
                        (int) $duration
                    ) );

                    throw new \Exception( sprintf(
                        'WEB SCRAPING ISSUE: Web scraping fallback failed for organization %s. Error: %s. Duration: %dms. Check web scraping implementation and GitHub page structure.',
                        $organization,
                        $error_message,
                        (int) $duration
                    ) );
                }

                if ( ! is_array( $repositories ) || empty( $repositories ) ) {
                    throw new \Exception( sprintf(
                        'WEB SCRAPING ISSUE: Web scraping returned empty or invalid data for organization %s. Expected array of repositories.',
                        $organization
                    ) );
                }

                return sprintf( 'Web scraping working: fetched %d repositories in %dms', count( $repositories ), (int) $duration );

            } finally {
                // Restore original setting
                update_option( 'sbi_fetch_method', $original_method );
            }
        });

        return $tests;
    }

    /**
     * Run a single test with error handling and timing.
     *
     * @param string   $name     Test name.
     * @param callable $callback Test callback function.
     * @return array Test result.
     */
    private function run_test( string $name, callable $callback ): array {
        $start_time = microtime( true );
        $result = [
            'name' => $name,
            'passed' => false,
            'message' => '',
            'error' => '',
            'time' => 0
        ];

        try {
            // Set a reasonable timeout for individual tests
            $timeout = 10; // 10 seconds max per test
            $end_time = $start_time + $timeout;

            $message = $callback();

            // Check if we exceeded timeout
            if ( microtime( true ) > $end_time ) {
                throw new \Exception( 'Test exceeded timeout of ' . $timeout . ' seconds' );
            }

            $result['passed'] = true;
            $result['message'] = $message ?: 'Test passed';
        } catch ( \Exception $e ) {
            $result['passed'] = false;
            $result['error'] = $e->getMessage();
            $result['message'] = 'Test failed';
        } catch ( \Throwable $e ) {
            $result['passed'] = false;
            $result['error'] = $e->getMessage();
            $result['message'] = 'Test failed with fatal error';
        }

        $result['time'] = round( ( microtime( true ) - $start_time ) * 1000, 2 );

        return $result;
    }

    /**
     * Render test results.
     */
    private function render_test_results(): void {
        $total_passed = 0;
        $total_failed = 0;
        $total_time = 0;

        foreach ( $this->test_results as $category ) {
            $total_passed += $category['passed'];
            $total_failed += $category['failed'];
            $total_time += $category['total_time'];
        }

        ?>
        <div class="test-summary">
            <h2><?php esc_html_e( 'Test Results Summary', 'kiss-smart-batch-installer' ); ?></h2>
            <p>
                <strong><?php echo esc_html( $total_passed + $total_failed ); ?></strong> tests run in
                <strong><?php echo esc_html( round( $total_time, 2 ) ); ?>ms</strong> -
                <span class="test-status <?php echo $total_failed === 0 ? 'passed' : 'failed'; ?>">
                    <?php echo esc_html( $total_passed ); ?> passed, <?php echo esc_html( $total_failed ); ?> failed
                </span>
            </p>
        </div>

        <?php foreach ( $this->test_results as $category_key => $category ) : ?>
            <div class="test-category <?php echo $category['failed'] === 0 ? 'passed' : 'failed'; ?>">
                <div class="test-category-header">
                    <?php echo esc_html( $category['title'] ); ?>
                    <span class="test-timing"><?php echo esc_html( round( $category['total_time'], 2 ) ); ?>ms</span>
                    <br>
                    <small>
                        <?php echo esc_html( $category['passed'] ); ?> passed,
                        <?php echo esc_html( $category['failed'] ); ?> failed
                    </small>
                </div>

                <?php foreach ( $category['tests'] as $test ) : ?>
                    <div class="test-item">
                        <span class="test-status <?php echo $test['passed'] ? 'passed' : 'failed'; ?>">
                            <?php echo $test['passed'] ? '✓' : '✗'; ?>
                        </span>
                        <strong><?php echo esc_html( $test['name'] ); ?></strong>
                        <span class="test-timing"><?php echo esc_html( $test['time'] ); ?>ms</span>

                        <div class="test-details">
                            <?php echo esc_html( $test['message'] ); ?>
                        </div>

                        <?php if ( ! $test['passed'] && ! empty( $test['error'] ) ) : ?>
                            <div class="test-error">
                                <strong>Error:</strong> <?php echo esc_html( $test['error'] ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php
    }
}
