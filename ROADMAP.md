Add an "Override - this is a WP plugin" button that also whitelists into a "cache" for the future

Optional override/whitelist
Admin button: “Override – this is a WP plugin”
Stores to option (e.g., sbi_plugin_overrides[owner/repo] = true or {plugin_file: ...})
StateManager::is_wordpress_plugin() checks overrides first, ensuring the FSM and UI reflect admin intent immediately
Include “Remove override” for reversibility