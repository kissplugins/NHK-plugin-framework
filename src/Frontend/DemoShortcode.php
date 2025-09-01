<?php
/**
 * Demo Shortcode Class
 * 
 * Provides a shortcode to display the modern frontend demo.
 * 
 * @package NHK\EventManager\Frontend
 * @since 1.0.0
 */

namespace NHK\EventManager\Frontend;

use NHK\Framework\Container\Container;

/**
 * Demo Shortcode Class
 * 
 * Demonstrates modern shortcode implementation with proper asset loading.
 */
class DemoShortcode {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Initialize the shortcode
     * 
     * @return void
     */
    public function init(): void {
        add_shortcode('nhk_event_demo', [$this, 'render_demo']);
        add_shortcode('nhk_events', [$this, 'render_event_list']);
        // Diagnostic: super-simple loader that bypasses FSM to help isolate issues
        add_shortcode('nhk_events_simple', [$this, 'render_simple_event_list']);
    }
    
    /**
     * Render the demo shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string Rendered output
     */
    public function render_demo($atts = [], $content = '') {
        $atts = shortcode_atts([
            'layout' => 'list',
            'per_page' => 5,
            'show_filters' => true,
            'show_pagination' => true,
        ], $atts, 'nhk_event_demo');
        
        // Ensure assets are loaded
        $this->enqueue_demo_assets();
        
        ob_start();
        ?>
        <div class="nhk-event-manager-demo">
            <div class="demo-header bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-lg mb-6">
                <h2 class="text-2xl font-bold mb-2">🚀 NHK Event Manager Demo</h2>
                <p class="opacity-90">Modern WordPress plugin with Alpine.js, XState, and Tailwind CSS</p>
            </div>
            
            <div x-data="eventList({
                layout: '<?php echo esc_attr($atts['layout']); ?>',
                perPage: <?php echo intval($atts['per_page']); ?>,
                showFilters: <?php echo $atts['show_filters'] ? 'true' : 'false'; ?>,
                showPagination: <?php echo $atts['show_pagination'] ? 'true' : 'false'; ?>,
                allowedLayouts: ['list', 'grid', 'table']
            })" class="space-y-6">
                
                <!-- Status Indicator -->
                <div class="bg-white p-4 rounded-lg shadow-sm border">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-sm font-medium">Alpine.js Active</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-sm font-medium">XState Ready</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-sm font-medium">Tailwind CSS Loaded</span>
                        </div>
                    </div>
                </div>
                
                <!-- Demo Controls -->
                <div class="bg-white p-4 rounded-lg shadow-sm border">
                    <h3 class="font-semibold mb-3">Demo Controls</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button @click="loadEvents()" class="btn-nhk-primary">
                            🔄 Load Events
                        </button>
                        <button @click="toggleFiltersPanel()" class="btn-outline">
                            🔍 Toggle Filters
                        </button>
                        <button @click="clearFilters()" class="btn-outline">
                            🧹 Clear Filters
                        </button>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div x-show="isLoading" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-4"></div>
                    <p class="text-gray-600">Loading events...</p>
                </div>
                
                <!-- Error State -->
                <div x-show="hasError" class="notification-error">
                    <?php
                    // Check if there are real events in the database
                    $real_events_count = wp_count_posts('nhk_event');
                    $has_real_events = ($real_events_count->publish ?? 0) > 0;

                    if (!$has_real_events): ?>
                        <p>⚠️ Demo Mode: This is a demonstration of the event list component.</p>
                        <p class="mt-2">In a real implementation, this would load events from the WordPress database.</p>
                        <button @click="loadDemoData()" class="btn-nhk-primary mt-3">
                            Load Demo Data
                        </button>
                    <?php else: ?>
                        <p>⚠️ Unable to load events. Please try again.</p>
                        <button @click="retryLoad()" class="btn-nhk-primary mt-3">
                            Retry Loading
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Filters Panel -->
                <div x-show="showFiltersPanel" x-transition class="event-filters">
                    <div class="event-filters-title">Filter Events</div>
                    <div class="event-filters-grid">
                        <div class="event-filter-group">
                            <label class="event-filter-label">Search</label>
                            <input 
                                type="text" 
                                x-model="filters.search"
                                placeholder="Search events..."
                                class="event-filter-input"
                            >
                        </div>
                        <div class="event-filter-group">
                            <label class="event-filter-label">Category</label>
                            <select x-model="filters.category" class="event-filter-input">
                                <option value="">All Categories</option>
                                <option value="workshop">Workshop</option>
                                <option value="conference">Conference</option>
                                <option value="meetup">Meetup</option>
                            </select>
                        </div>
                        <div class="event-filter-group">
                            <label class="event-filter-label">Layout</label>
                            <select x-model="config.layout" @change="changeLayout($event.target.value)" class="event-filter-input">
                                <option value="list">List</option>
                                <option value="grid">Grid</option>
                                <option value="table">Table</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Demo Event Display -->
                <div x-show="!isLoading" class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="font-semibold mb-4">Event List Component Demo</h3>
                    
                    <!-- Sample Events -->
                    <div x-data="{ 
                        demoEvents: [
                            {
                                id: 1,
                                title: 'WordPress Meetup',
                                content: 'Monthly WordPress developer meetup featuring talks on modern development practices.',
                                date: '2024-02-15',
                                venue: 'Tech Hub Downtown',
                                status: 'published'
                            },
                            {
                                id: 2,
                                title: 'Alpine.js Workshop',
                                content: 'Learn how to build reactive interfaces with Alpine.js in this hands-on workshop.',
                                date: '2024-02-20',
                                venue: 'Online',
                                status: 'published'
                            },
                            {
                                id: 3,
                                title: 'State Management Conference',
                                content: 'Deep dive into modern state management patterns with XState and other tools.',
                                date: '2024-02-25',
                                venue: 'Convention Center',
                                status: 'published'
                            }
                        ]
                    }">
                        <!-- List Layout -->
                        <div x-show="config.layout === 'list'" class="space-y-4">
                            <template x-for="event in demoEvents" :key="event.id">
                                <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <h4 class="font-semibold text-lg mb-2" x-text="event.title"></h4>
                                    <p class="text-gray-600 mb-3" x-text="event.content"></p>
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-gray-500">
                                            <span x-text="event.date"></span> • <span x-text="event.venue"></span>
                                        </div>
                                        <span class="event-status event-status-published">Published</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Grid Layout -->
                        <div x-show="config.layout === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <template x-for="event in demoEvents" :key="event.id">
                                <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <h4 class="font-semibold mb-2" x-text="event.title"></h4>
                                    <p class="text-gray-600 text-sm mb-3" x-text="event.content.substring(0, 80) + '...'"></p>
                                    <div class="text-xs text-gray-500">
                                        <div x-text="event.date"></div>
                                        <div x-text="event.venue"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Table Layout -->
                        <div x-show="config.layout === 'table'" class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left p-2">Title</th>
                                        <th class="text-left p-2">Date</th>
                                        <th class="text-left p-2">Venue</th>
                                        <th class="text-left p-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="event in demoEvents" :key="event.id">
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="p-2 font-medium" x-text="event.title"></td>
                                            <td class="p-2 text-sm text-gray-600" x-text="event.date"></td>
                                            <td class="p-2 text-sm text-gray-600" x-text="event.venue"></td>
                                            <td class="p-2">
                                                <span class="event-status event-status-published">Published</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Demo Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-900 mb-2">💡 Demo Information</h4>
                    <ul class="text-sm text-blue-800 space-y-1">
                        <li>• This demonstrates the modern frontend architecture</li>
                        <li>• Alpine.js provides reactive data binding</li>
                        <li>• XState manages complex UI state transitions</li>
                        <li>• Tailwind CSS enables rapid styling</li>
                        <li>• All assets are built with Bun for optimal performance</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <script>
            // Initialize demo data loading
            document.addEventListener('alpine:init', () => {
                Alpine.data('demoEventList', () => ({
                    loadDemoData() {
                        // Simulate loading demo data
                        this.isLoading = true;
                        setTimeout(() => {
                            this.isLoading = false;
                            this.hasError = false;
                            window.showNotification('Demo data loaded successfully!', 'success');
                        }, 1000);
                    }
                }));
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render the event list shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string Rendered output
     */
    public function render_event_list($atts = [], $content = '') {
        $atts = shortcode_atts([
            'limit' => 10,
            'category' => '',
            'venue' => '',
            'layout' => 'list',
            'show_filters' => false,
            'show_pagination' => true,
        ], $atts, 'nhk_events');
        
        // Ensure assets are loaded
        $this->enqueue_demo_assets();
        
        ob_start();
        ?>
        <div class="nhk-events-shortcode">
            <div x-data="eventList(<?php echo esc_attr(json_encode($atts)); ?>)">
                <!-- Event list component will be rendered here -->
                <div x-show="isLoading" class="text-center py-4">
                    <div class="loading-spinner mx-auto"></div>
                </div>
                
                <div x-show="!isLoading && events.length === 0" class="text-center py-8 text-gray-500">
                    No events found.
                </div>
                
                <div x-show="!isLoading && events.length > 0">
                    <!-- Events will be displayed here based on layout -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a super-simple event list that bypasses the FSM
     * Useful for diagnostics to confirm REST responses and asset loading
     */
    public function render_simple_event_list($atts = [], $content = '') {
        $atts = \shortcode_atts([
            'limit' => 5,
            'show_debug' => false,
        ], $atts, 'nhk_events_simple');

        // Ensure assets are loaded so window.nhkEventApi exists
        $this->enqueue_demo_assets();

        $container_id = 'nhk-simple-' . \wp_generate_password(6, false, false);
        ob_start();
        ?>
        <div id="<?php echo \esc_attr($container_id); ?>" class="nhk-events-simple">
            <button type="button" class="button button-primary">Load Events (Simple)</button>
            <div class="status" style="margin:8px 0;color:#555;">Idle</div>
            <ul class="events" style="list-style:disc;padding-left:20px"></ul>
        </div>
        <script>
        (function(){
            const root = document.getElementById('<?php echo \esc_js($container_id); ?>');
            if(!root) return;
            const btn = root.querySelector('button');
            const status = root.querySelector('.status');
            const list = root.querySelector('.events');
            const limit = <?php echo (int) $atts['limit']; ?>;

            async function fetchViaClient(){
                if (!window.nhkEventApi) throw new Error('ApiClient not initialized');
                return await window.nhkEventApi.getEvents({ per_page: limit, page: 1 });
            }
            async function fetchDirect(){
                const base = (window.nhkEventManager && window.nhkEventManager.apiUrl) || '/?rest_route=/nhk-events/v1';
                const url = base + '/events?per_page=' + encodeURIComponent(limit);
                const headers = {};
                if (window.nhkEventManager && window.nhkEventManager.isUserLoggedIn && window.nhkEventManager.nonce) {
                    headers['X-WP-Nonce'] = window.nhkEventManager.nonce;
                }
                const res = await fetch(url, { credentials: 'same-origin', headers });
                if(!res.ok){
                    const data = await res.text();
                    throw new Error('HTTP ' + res.status + ' ' + res.statusText + ' — ' + data);
                }
                return await res.json();
            }
            function render(events){
                list.innerHTML = '';
                if(!events || events.length === 0){
                    list.innerHTML = '<li>No events found.</li>';
                    return;
                }
                for(const ev of events){
                    const li = document.createElement('li');
                    const date = (ev.start_date || ev.date || '').toString();
                    li.innerHTML = `<strong>${(ev.title||'Untitled')}</strong> <em style="color:#777">${date}</em>`;
                    list.appendChild(li);
                }
            }
            async function load(){
                status.textContent = 'Loading…';
                list.innerHTML = '';
                try{
                    let data;
                    try { data = await fetchViaClient(); }
                    catch (e1) {
                        console.warn('ApiClient path failed, retrying direct REST route', e1);
                        data = await fetchDirect();
                    }
                    const events = data.events || data.data || data || [];
                    render(events);
                    status.textContent = 'Loaded ' + (events.length || 0) + ' event(s)';
                } catch(err){
                    console.error('Simple loader error:', err);
                    status.textContent = 'Error: ' + (err && err.message ? err.message : err);
                }
            }
            btn.addEventListener('click', load);
            // Auto-load once for convenience
            load();
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue demo assets
     *
     * @return void
     */
    protected function enqueue_demo_assets(): void {
        // Force asset loading for demo
        add_filter('nhk_event_manager_force_assets', '__return_true');

        // Enqueue additional demo styles
        wp_add_inline_style('nhk-event-manager-frontend', '
            .nhk-event-manager-demo {
                max-width: 1200px;
                margin: 0 auto;
            }

            .demo-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .loading-spinner {
                width: 24px;
                height: 24px;
                border: 2px solid #e5e7eb;
                border-top: 2px solid #3b82f6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        ');
    }
}
