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
            
            <div id="nhk-event-list-demo" x-data="eventList({
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

                <!-- Diagnostic Controls -->
                <div class="bg-white p-4 rounded-lg shadow-sm border">
                    <h3 class="font-semibold mb-3">Diagnostic Controls</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step1()">Step 1: Check /health</button>
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step2()">Step 2: Init API Client</button>
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step3()">Step 3: Fetch Events</button>
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step4()">Step 4: Run FSM load</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3" style="margin-top:8px">
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step4a()">4a: Inspect DOM</button>
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step4b()">4b: Capture Alpine</button>
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step4c()">4c: Call loadEvents()</button>
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step4d()">4d: Send LOAD_EVENTS</button>
                        <button type="button" class="btn-outline" onclick="window.nhkDiag?.step4e()">4e: Show snapshot</button>
                    </div>
                    <div id="nhk-diag-log" style="margin-top:10px;padding:8px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;max-height:200px;overflow:auto;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;font-size:12px;line-height:1.45;"></div>
                    <div id="nhk-diag-render" style="margin-top:10px;padding:8px;background:#ffffff;border:1px dashed #e5e7eb;border-radius:6px;max-height:220px;overflow:auto"></div>
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

            // Lightweight diagnostic harness to step through initialization
            (function(){
                const logEl = () => document.getElementById('nhk-diag-log');
                function log(msg, data){
                    try{ console.log('[NHK DIAG]', msg, data||''); }catch(e){}
                    const el = logEl(); if(!el) return;
                    const line = document.createElement('div');
                    const ts = new Date().toISOString().split('T')[1].replace('Z','');
                    line.textContent = '[' + ts + '] ' + msg;
                    el.appendChild(line);
                    el.scrollTop = el.scrollHeight;
                }
                function baseUrl(){
                    const admin = window.nhkEventManagerAdmin && window.nhkEventManagerAdmin.apiUrl;
                    const front = window.nhkEventManager && window.nhkEventManager.apiUrl;
                    return (admin || front || '/wp-json/nhk-events/v1').replace(/\/$/, '');
                }
                // Try to capture the Alpine component when it becomes available
                (function captureComponent(){
                    const root = document.getElementById('nhk-event-list-demo');
                    if (root && root.__x && root.__x.$data) {
                        window.nhkEventListCmp = root.__x.$data;
                        log('Alpine component captured');
                        if (window.nhkDiagPendingLoad && typeof window.nhkEventListCmp.loadEvents === 'function') {
                            log('Pending request detected — invoking loadEvents now');
                            try { window.nhkEventListCmp.loadEvents(); } catch(e) { log('Auto-load failed: ' + e); }
                            window.nhkDiagPendingLoad = false;
                        }
                        return;
                    }
                    setTimeout(captureComponent, 250);
                })();
                async function step1(){
                    const url = baseUrl() + '/health';
                    log('Step 1: GET ' + url);
                    try{
                        const res = await fetch(url, { credentials: 'same-origin' });
                        const json = await res.json();
                        log('Health OK: ' + JSON.stringify(json));
                    }catch(err){
                        log('Health FAILED: ' + (err && err.message ? err.message : err));
                    }
                }
                function ensureClient(){
                    if (window.nhkEventApi && typeof window.nhkEventApi.getEvents === 'function') return window.nhkEventApi;
                    const apiBase = baseUrl();
                    const nonce = (window.nhkEventManagerAdmin && window.nhkEventManagerAdmin.nonce) || (window.nhkEventManager && window.nhkEventManager.nonce);
                    log('Creating simple fetch client with base ' + apiBase);
                    window.nhkEventApi = {
                        async getEvents(params){
                            const u = new URL(apiBase + '/events', window.location.origin);
                            Object.entries(params||{}).forEach(([k,v]) => u.searchParams.set(k, v));
                            const headers = {};
                            if (nonce) headers['X-WP-Nonce'] = nonce;
                            const res = await fetch(u.toString(), { credentials: 'same-origin', headers });
                            if (!res.ok) throw new Error('HTTP ' + res.status + ' ' + res.statusText);
                            return res.json();
                        }
                    };
                    return window.nhkEventApi;
                }
                function step2(){
                    try{
                        const client = ensureClient();
                        log('Step 2: API client ready: ' + (client ? 'yes' : 'no'));
                    }catch(err){
                        log('Step 2 FAILED: ' + err);
                    }
                }
                async function step3(){
                    try{
                        const client = ensureClient();
                        log('Step 3: Fetching events via client…');
                        const data = await client.getEvents({ per_page: 5, page: 1 });
                        const count = (data && (data.events?.length || data.length || data.total || 0)) || 0;
                        log('Step 3 OK: received ' + count + ' items');
                    }catch(err){
                        log('Step 3 FAILED: ' + (err && err.message ? err.message : err));
                    }
                }
                function describeX(x){
                    if (!x) return 'n/a';
                    const keys = Object.keys(x).filter(k => typeof x[k] !== 'function');
                    const fns = Object.keys(x).filter(k => typeof x[k] === 'function');
                    return 'keys: ' + keys.join(', ') + ' | fns: ' + fns.join(', ');
                }
                function inspectDom(){
                    const root = document.getElementById('nhk-event-list-demo');
                    if (!root) { log('4a: root not found'); return; }
                    const hasX = !!root.__x;
                    const xDataAttr = root.getAttribute('x-data');
                    log('4a: root found. x-data attr: ' + xDataAttr + ' | __x: ' + hasX);
                    const list = Array.from(document.querySelectorAll('[x-data]')).map(el => el.getAttribute('x-data')||'');
                    log('4a: [x-data] count=' + list.length + ' first=' + (list[0]||''));
                }
                function captureAlpine(){
                    const root = document.getElementById('nhk-event-list-demo');
                    if (root && root.__x && root.__x.$data) {
                        window.nhkEventListCmp = root.__x.$data;
                        log('4b: captured component — ' + describeX(window.nhkEventListCmp));
                        // Attach debug hooks
                        try{
                            const svc = window.nhkEventListCmp.service;
                            if (svc && typeof svc.subscribe === 'function'){
                                svc.subscribe((snap)=>{ try{ log('FSM state=' + JSON.stringify(snap.value)); }catch(e){} });
                            }
                        }catch(e){ log('4b: subscribe attach failed: ' + e); }
                    } else {
                        log('4b: component not ready');
                    }
                }
                async function step4(){
                    try{
                        const root = document.getElementById('nhk-event-list-demo');
                        if (root && root.__x && root.__x.$data && typeof root.__x.$data.loadEvents === 'function'){
                            log('Step 4: Invoking Alpine loadEvents()');
                            root.__x.$data.loadEvents();
                        } else if (window.nhkEventListCmp && typeof window.nhkEventListCmp.loadEvents === 'function') {
                            log('Step 4: Using captured component handle');
                            window.nhkEventListCmp.loadEvents();
                        } else {
                            log('Step 4: Alpine not directly accessible; will auto-run when ready. Running 4a+4b…');
                            inspectDom();
                            captureAlpine();
                            window.nhkDiagPendingLoad = true;
                        }
                        log('Step 4 invoked. Watch UI and console for state transitions.');
                    }catch(err){
                        log('Step 4 ERROR: ' + err);
                    }
                }
                function renderList(container, items){
                    container.innerHTML = '';
                    if (!items || !items.length){ container.textContent = 'No items'; return; }
                    const ul = document.createElement('ul'); ul.style.margin='0'; ul.style.paddingLeft='18px';
                    for (const ev of items){
                        const li = document.createElement('li');
                        li.textContent = (ev.title || 'Untitled') + ' — ' + (ev.start_date || ev.date || '');
                        ul.appendChild(li);
                    }
                    container.appendChild(ul);
                }
                function step4c(){
                    if (window.nhkEventListCmp && typeof window.nhkEventListCmp.loadEvents === 'function') {
                        log('4c: calling loadEvents() via captured cmp');
                        try{ window.nhkEventListCmp.loadEvents(); }catch(e){ log('4c failed: ' + e); }
                    } else { log('4c: component not captured'); }
                }
                function step4d(){
                    const cmp = window.nhkEventListCmp;
                    if (cmp && cmp.service) {
                        try {
                            log('4d: sending LOAD_EVENTS to service');
                            cmp.service.send({ type: 'LOAD_EVENTS' });
                        } catch(e) { log('4d failed: ' + e); }
                    } else { log('4d: no service found'); }
                }
                async function step4f(){
                    const mount = document.getElementById('nhk-diag-render');
                    if (!mount){ log('4f: render mount not found'); return; }
                    try{
                        const client = ensureClient();
                        const data = await client.getEvents({ per_page: 5, page: 1 });
                        const events = data.events || data.data || data || [];
                        log('4f: rendering ' + events.length + ' items directly');
                        renderList(mount, events);
                    }catch(e){
                        log('4f failed: ' + (e && e.message ? e.message : e));
                    }
                }
                function step4e(){
                    const cmp = window.nhkEventListCmp;
                    if (cmp && cmp.state) {
                        try { log('4e: snapshot=' + JSON.stringify(cmp.state.value)); } catch(e) { log('4e: snapshot stringify failed'); }
                    } else { log('4e: no snapshot present'); }
                }
                window.nhkDiag = { step1, step2, step3, step4, step4a: inspectDom, step4b: captureAlpine, step4c, step4d, step4e, step4f };
                log('Diagnostic harness initialized');
            })();
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
            'ajax' => false,
        ], $atts, 'nhk_events');

        // Ensure assets are loaded
        $this->enqueue_demo_assets();

        ob_start();
        // Read filters from request (fallback-friendly)
        $request = wp_unslash($_GET);
        $page = isset($request['paged']) ? max(1, (int) $request['paged']) : (get_query_var('paged') ?: 1);
        $per_page = (int) $atts['limit'];
        $search = isset($request['s']) ? sanitize_text_field($request['s']) : '';
        $cat = isset($request['event_category']) ? sanitize_text_field($request['event_category']) : '';
        $venue = isset($request['event_venue']) ? sanitize_text_field($request['event_venue']) : '';
        $date_from = isset($request['date_from']) ? sanitize_text_field($request['date_from']) : '';
        $date_to = isset($request['date_to']) ? sanitize_text_field($request['date_to']) : '';

        $params = array_filter([
            'page' => $page,
            'per_page' => $per_page,
            's' => $search,
            'category' => $cat ?: null,
            'venue' => $venue ?: null,
            'date_from' => $date_from ?: null,
            'date_to' => $date_to ?: null,
        ], function($v){ return $v !== null && $v !== ''; });
        $args = \NHK\EventManager\Services\EventQueryBuilder::build_args($params);

        $query = new \WP_Query($args);
        ?>
        <div class="nhk-events-shortcode" data-nhk-enhance="event-list">
            <div x-data="eventList(<?php echo esc_attr(json_encode([
                'layout' => $atts['layout'],
                'perPage' => (int) $atts['limit'],
                'showFilters' => (bool) $atts['show_filters'],
                'showPagination' => (bool) $atts['show_pagination'],
                'ajax' => filter_var($atts['ajax'], FILTER_VALIDATE_BOOLEAN),
            ])); ?>)">
                <!-- Progressive enhancement spinners (hidden when SSR displays) -->
                <div x-show="isLoading" class="text-center py-4" style="display:none;">
                    <div class="loading-spinner mx-auto"></div>
                </div>

                <?php if ($query->have_posts()) : ?>
                    <ul class="space-y-4">
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <li class="border rounded-lg p-4">
                                <a href="<?php echo esc_url(get_permalink()); ?>" class="font-semibold text-lg"><?php echo esc_html(get_the_title()); ?></a>
                                <?php
                                $start = get_post_meta(get_the_ID(), '_nhk_event_start_date', true);
                                $venue_terms = get_the_terms(get_the_ID(), 'nhk_event_venue');
                                $venue_label = $venue_terms && !is_wp_error($venue_terms) ? $venue_terms[0]->name : '';
                                ?>
                                <div class="text-sm text-gray-600 mt-1">
                                    <?php if ($start) : ?><span><?php echo esc_html($start); ?></span><?php endif; ?>
                                    <?php if ($start && $venue_label) : ?> • <?php endif; ?>
                                    <?php if ($venue_label) : ?><span><?php echo esc_html($venue_label); ?></span><?php endif; ?>
                                </div>
                                <div class="text-gray-700 mt-2"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></div>
                            </li>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </ul>

                    <?php
                    $total_pages = $query->max_num_pages;
                    if ($total_pages > 1) {
                        $current = max(1, $page);
                        echo '<div class="mt-6">' . paginate_links([
                            'current' => $current,
                            'total' => $total_pages,
                            'type' => 'list',
                        ]) . '</div>';
                    }
                    ?>
                <?php else : ?>
                    <div class="text-center py-8 text-gray-500"><?php esc_html_e('No events found.', 'nhk-event-manager'); ?></div>
                <?php endif; ?>
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
                const base = (window.nhkEventManager && window.nhkEventManager.apiUrl) || '/wp-json/nhk-events/v1';
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
