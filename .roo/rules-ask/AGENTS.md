# Project Documentation Rules (Non-Obvious Only)

- Main plugin file (`algolia-woo-indexer.php`) contains WordPress plugin headers but core logic is in `classes/` directory
- `DOCS/repository_context.txt` contains comprehensive project context not found in README
- Class files use WordPress naming convention (`class-name.php`) but implement PHP namespacing patterns
- Plugin functionality is split across 4 separate classes with specific responsibilities (not MVC pattern)
- Settings structure documented in code comments, not external docs
- Composer is used for development tools only (PHPCS), not for plugin dependencies
- Translation files in `languages/` directory use WordPress translation system, not standard gettext
- Plugin header mentions PHP 8.1+ but error messages reference PHP 7.2+ (outdated messaging)
- Constants are scattered: some in main class, others in `Send_Products` class
- Product categories are required - plugin silently skips products without categories (undocumented behavior)