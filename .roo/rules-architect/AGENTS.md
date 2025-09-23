# Project Architecture Rules (Non-Obvious Only)

- Plugin follows WordPress plugin architecture, not MVC - all classes are utility/service classes
- Singleton pattern required for main class to prevent multiple AJAX handler registrations
- No dependency injection - classes access WordPress globals and functions directly
- AJAX endpoints registered globally but handled by specific class methods (tight coupling by design)
- Plugin settings stored as individual prefixed options (not single serialized array for performance)
- Activation/deactivation hooks in main file, but actual logic delegated to class methods
- No autoloader - classes included manually via `require_once` in main file
- WordPress hooks used for integration points, not observer patterns or events
- Algolia client instantiated fresh each time (no connection pooling or reuse)
- Product data transformation happens in loops without caching (potential performance bottleneck)