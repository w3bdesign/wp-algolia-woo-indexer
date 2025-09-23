# Project Debug Rules (Non-Obvious Only)

- WordPress debug logs are only location for error output - plugin doesn't write to separate log files
- AJAX debugging requires checking both browser network tab AND WordPress debug log (errors split between both)
- Plugin activation errors only visible in WordPress admin, not in server logs  
- Requirements check failures prevent plugin activation silently - check `Check_Requirements` class output
- Algolia API errors are caught and logged but don't prevent page loading (silent failures possible)
- Settings validation errors only show in WordPress admin notices, not AJAX responses
- Uninstall process leaves no traces if `WP_UNINSTALL_PLUGIN` constant isn't properly set
- Algolia connection test happens via `listApiKeys()` call - not a ping or simple connection check
- Product indexing failures return `NullResponse` class - check `get_class($result)` for this specific string