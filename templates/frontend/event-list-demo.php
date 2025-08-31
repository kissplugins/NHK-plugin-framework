<?php
/**
 * Event List Demo Template
 * 
 * Demonstrates the modern frontend integration with Alpine.js, XState, and Tailwind CSS.
 * This template shows how to use the event list component with all its features.
 * 
 * @package NHK\EventManager\Templates
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NHK Event Manager - Modern Frontend Demo</title>
    
    <!-- WordPress head -->
    <?php wp_head(); ?>
    
    <!-- Demo-specific styles -->
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
        }
        
        .demo-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .demo-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .demo-section {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .demo-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1a202c;
        }
        
        .demo-description {
            color: #4a5568;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .feature-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 1rem;
        }
        
        .feature-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .feature-description {
            font-size: 0.875rem;
            color: #4a5568;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-success { background-color: #48bb78; }
        .status-warning { background-color: #ed8936; }
        .status-error { background-color: #f56565; }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Demo Header -->
    <header class="demo-header">
        <div class="demo-container">
            <h1 class="text-3xl font-bold mb-2">NHK Event Manager</h1>
            <p class="text-xl opacity-90">Modern Frontend Integration Demo</p>
            <p class="mt-4 opacity-75">
                Showcasing Alpine.js, XState, Tailwind CSS, and modern JavaScript patterns
            </p>
        </div>
    </header>

    <main class="demo-container">
        <!-- System Status -->
        <section class="demo-section">
            <h2 class="demo-title">🚀 System Status</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-title">
                        <span class="status-indicator status-success"></span>
                        Frontend Assets
                    </div>
                    <div class="feature-description">
                        Tailwind CSS and JavaScript assets are built and loaded
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-title">
                        <span class="status-indicator status-success"></span>
                        Alpine.js Integration
                    </div>
                    <div class="feature-description">
                        Reactive components are initialized and ready
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-title">
                        <span class="status-indicator status-success"></span>
                        XState Machines
                    </div>
                    <div class="feature-description">
                        State machines for complex UI logic are active
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-title">
                        <span class="status-indicator status-success"></span>
                        REST API
                    </div>
                    <div class="feature-description">
                        Event management API endpoints are available
                    </div>
                </div>
            </div>
        </section>

        <!-- Interactive Event List Demo -->
        <section class="demo-section">
            <h2 class="demo-title">📅 Interactive Event List</h2>
            <p class="demo-description">
                This demonstrates the modern event list component with filtering, sorting, 
                pagination, and real-time updates using Alpine.js and XState.
            </p>
            
            <!-- Event List Component -->
            <div x-data="eventList({
                layout: 'list',
                perPage: 5,
                showFilters: true,
                showPagination: true,
                allowedLayouts: ['list', 'grid', 'table']
            })" class="space-y-6">
                
                <!-- Loading State -->
                <div x-show="isLoading" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-4"></div>
                    <p class="text-gray-600">Loading events...</p>
                </div>
                
                <!-- Error State -->
                <div x-show="hasError" class="notification-error">
                    <p x-text="error?.message || 'An error occurred while loading events'"></p>
                    <button @click="retryLoad()" class="btn-nhk-primary mt-2">
                        Retry
                    </button>
                </div>
                
                <!-- Filters Panel -->
                <div x-show="config.showFilters" class="event-filters">
                    <div class="event-filters-title">Filter Events</div>
                    <div class="event-filters-grid">
                        <div class="event-filter-group">
                            <label class="event-filter-label">Search</label>
                            <input 
                                type="text" 
                                x-model="filters.search"
                                @input.debounce.300ms="searchEvents($event.target.value)"
                                placeholder="Search events..."
                                class="event-filter-input"
                                x-ref="searchInput"
                            >
                        </div>
                        <div class="event-filter-group">
                            <label class="event-filter-label">Category</label>
                            <select x-model="filters.category" @change="applyFilters()" class="event-filter-input">
                                <option value="">All Categories</option>
                                <option value="workshop">Workshop</option>
                                <option value="conference">Conference</option>
                                <option value="meetup">Meetup</option>
                            </select>
                        </div>
                        <div class="event-filter-group">
                            <label class="event-filter-label">Date From</label>
                            <input 
                                type="date" 
                                x-model="filters.dateFrom"
                                @change="applyFilters()"
                                class="event-filter-input"
                            >
                        </div>
                        <div class="event-filter-group">
                            <button @click="clearFilters()" class="btn-nhk btn-outline w-full">
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Layout Controls -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-medium text-gray-700">Layout:</span>
                        <template x-for="layout in config.allowedLayouts" :key="layout">
                            <button 
                                @click="changeLayout(layout)"
                                :class="getCurrentLayout() === layout ? 'btn-nhk-primary' : 'btn-outline'"
                                class="btn-nhk text-sm"
                                x-text="layout.charAt(0).toUpperCase() + layout.slice(1)"
                            ></button>
                        </template>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <span x-text="events.length"></span> events found
                    </div>
                </div>
                
                <!-- Events Display -->
                <div x-show="!isLoading && !hasError">
                    <!-- List Layout -->
                    <div x-show="getCurrentLayout() === 'list'" class="event-list">
                        <template x-for="event in events" :key="event.id">
                            <div class="event-list-item">
                                <div class="event-list-content">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2" x-text="event.title"></h3>
                                    <p class="text-gray-600 mb-2" x-text="event.content?.substring(0, 150) + '...'"></p>
                                    <div class="flex items-center text-sm text-gray-500">
                                        <span x-text="formatDate(event.date)"></span>
                                        <span class="mx-2">•</span>
                                        <span x-text="event.venue || 'Online'"></span>
                                    </div>
                                </div>
                                <div class="event-list-meta">
                                    <span class="event-status event-status-published">Published</span>
                                    <div class="mt-2 space-x-2">
                                        <a :href="event.url" class="btn-nhk-primary text-sm">View</a>
                                        <a :href="getEditUrl(event)" class="btn-outline text-sm">Edit</a>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Grid Layout -->
                    <div x-show="getCurrentLayout() === 'grid'" class="event-grid">
                        <template x-for="event in events" :key="event.id">
                            <div class="event-card">
                                <div class="event-card-header">
                                    <h3 class="event-card-title" x-text="event.title"></h3>
                                    <span class="event-card-date" x-text="formatDate(event.date)"></span>
                                </div>
                                <p class="event-card-content" x-text="event.content?.substring(0, 100) + '...'"></p>
                                <div class="event-card-footer">
                                    <span class="event-card-venue" x-text="event.venue || 'Online'"></span>
                                    <div class="space-x-2">
                                        <a :href="event.url" class="btn-nhk-primary text-sm">View</a>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Table Layout -->
                    <div x-show="getCurrentLayout() === 'table'" class="event-table">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th @click="changeSort('title')" class="cursor-pointer">
                                        Title <span x-text="getSortIcon('title')"></span>
                                    </th>
                                    <th @click="changeSort('date')" class="cursor-pointer">
                                        Date <span x-text="getSortIcon('date')"></span>
                                    </th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="event in events" :key="event.id">
                                    <tr>
                                        <td class="font-medium" x-text="event.title"></td>
                                        <td x-text="formatDate(event.date)"></td>
                                        <td x-text="event.venue || 'Online'"></td>
                                        <td>
                                            <span class="event-status event-status-published">Published</span>
                                        </td>
                                        <td>
                                            <div class="space-x-2">
                                                <a :href="event.url" class="text-blue-600 hover:text-blue-800">View</a>
                                                <a :href="getEditUrl(event)" class="text-gray-600 hover:text-gray-800">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Empty State -->
                <div x-show="!isLoading && !hasError && events.length === 0" class="text-center py-12">
                    <div class="text-6xl mb-4">📅</div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No events found</h3>
                    <p class="text-gray-600 mb-4">Try adjusting your filters or create a new event.</p>
                    <button class="btn-nhk-primary">Create Event</button>
                </div>
                
                <!-- Pagination -->
                <div x-show="config.showPagination && getPagination().totalPages > 1" class="flex items-center justify-center space-x-2">
                    <button 
                        @click="changePage(getPagination().currentPage - 1)"
                        :disabled="getPagination().currentPage <= 1"
                        class="btn-outline"
                    >
                        Previous
                    </button>
                    
                    <span class="text-sm text-gray-600">
                        Page <span x-text="getPagination().currentPage"></span> of <span x-text="getPagination().totalPages"></span>
                    </span>
                    
                    <button 
                        @click="changePage(getPagination().currentPage + 1)"
                        :disabled="getPagination().currentPage >= getPagination().totalPages"
                        class="btn-outline"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>

        <!-- Technical Details -->
        <section class="demo-section">
            <h2 class="demo-title">🔧 Technical Implementation</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-title">XState Integration</div>
                    <div class="feature-description">
                        Complex state management for loading, filtering, and error handling
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-title">Alpine.js Components</div>
                    <div class="feature-description">
                        Reactive UI components with minimal JavaScript footprint
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-title">Tailwind CSS</div>
                    <div class="feature-description">
                        Utility-first CSS framework for rapid UI development
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-title">Modern Build Process</div>
                    <div class="feature-description">
                        Bun for fast builds, TypeScript support, and asset optimization
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- WordPress footer -->
    <?php wp_footer(); ?>
    
    <!-- Demo initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎉 NHK Event Manager Demo loaded successfully!');
            
            // Show notification about demo
            if (window.showNotification) {
                window.showNotification(
                    'Welcome to the NHK Event Manager modern frontend demo!', 
                    'info', 
                    8000
                );
            }
        });
    </script>
</body>
</html>
