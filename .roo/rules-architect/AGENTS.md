# Project Architecture Rules (Non-Obvious Only)

The root `AGENTS.md` is the authority. This file only adds architecture
notes and must not contradict it.

- Plugin follows WordPress plugin architecture, not MVC - all classes are utility/service classes
- **There is no AJAX anywhere in this plugin.** Both the settings form and the "Send products" form are plain POSTs to `options-general.php?page=algolia-woo-indexer-settings`, handled on `admin_init`
- Singleton on the main class exists so the activation/deactivation hooks can be bound to one instance, and so `init()` registers its hooks only once - not because of AJAX
- Mostly static methods, but not "no dependency injection": `Algolia_Woo_Indexer` takes `AlgoliaConnectionStatus` as an optional injected constructor dependency. New collaborators should follow that pattern rather than adding more static coupling
- Static access is deliberate and is suppressed per-callback with `@SuppressWarnings(PHPMD.StaticAccess)`, because Codacy runs PHPMD and blocks merges. Do not refactor to DI just to silence it
- Plugin settings stored as individual prefixed options (not single serialized array for performance)
- Activation/deactivation hooks in main file, but actual logic delegated to class methods
- No autoloader - classes included manually via `require_once` in main file
- WordPress hooks used for integration points, not observer patterns or events
- The `save_post` auto-index hook is registered outside `is_admin()` on purpose so REST, WP-CLI and scheduled publishes are indexed. Do not move it inside
- All admin output is deferred to `admin_notices`, usually through `Algolia_Check_Requirements::queue_admin_notice()`. Never echo from `admin_init` or at file scope
- Algolia client instantiated fresh each time (no connection pooling or reuse)
- Product data transformation happens in loops without caching, and `saveObjects()` is called once with every record and no chunking (the main scaling bottleneck)
