<!doctype html>
<html lang="en" data-theme="{{ $config->get('ui.theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->get('ui.theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    <script>
        const originalFetch = window.fetch;

        // intercept TryIt requests and add the XSRF-TOKEN header,
        // which is necessary for Sanctum cookie-based authentication to work correctly
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                const { headers = new Headers() } = options || {};
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
                return originalFetch(url, {
                    ...options,
                    headers,
                });
            }

            return originalFetch(url, options);
        };
    </script>

    <style>
        html, body { margin:0; height:100%; }
        body { background-color: var(--color-canvas); }
        /* issues about the dark theme of stoplight/mosaic-code-viewer using web component:
         * https://github.com/stoplightio/elements/issues/2188#issuecomment-1485461965
         */
        [data-theme="dark"] .token.property {
            color: rgb(128, 203, 196) !important;
        }
        [data-theme="dark"] .token.operator {
            color: rgb(255, 123, 114) !important;
        }
        [data-theme="dark"] .token.number {
            color: rgb(247, 140, 108) !important;
        }
        [data-theme="dark"] .token.string {
            color: rgb(165, 214, 255) !important;
        }
        [data-theme="dark"] .token.boolean {
            color: rgb(121, 192, 255) !important;
        }
        [data-theme="dark"] .token.punctuation {
            color: #dbdbdb !important;
        }

        /* Search overlay styles */
        .autodocs-search-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99999;
            justify-content: center;
            padding-top: 10vh;
        }
        .autodocs-search-overlay.active {
            display: flex;
        }
        .autodocs-search-container {
            width: 100%;
            max-width: 600px;
            max-height: 70vh;
            background: var(--color-canvas, #fff);
            border: 1px solid var(--color-border, #e2e8f0);
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        [data-theme="dark"] .autodocs-search-container {
            background: #1e1e2e;
            border-color: #444;
        }
        .autodocs-search-input-wrapper {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--color-border, #e2e8f0);
            gap: 12px;
        }
        [data-theme="dark"] .autodocs-search-input-wrapper {
            border-color: #444;
        }
        .autodocs-search-icon {
            color: #94a3b8;
            flex-shrink: 0;
        }
        .autodocs-search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            background: transparent;
            color: var(--color-text, #1e293b);
        }
        [data-theme="dark"] .autodocs-search-input {
            color: #e2e8f0;
        }
        .autodocs-search-input::placeholder {
            color: #94a3b8;
        }
        .autodocs-search-kbd {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-family: monospace;
        }
        [data-theme="dark"] .autodocs-search-kbd {
            background: #333;
            border-color: #555;
            color: #aaa;
        }
        .autodocs-search-results {
            overflow-y: auto;
            padding: 8px;
            flex: 1;
        }
        .autodocs-search-result {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 8px;
            cursor: pointer;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }
        .autodocs-search-result:hover,
        .autodocs-search-result.active {
            background: #f1f5f9;
        }
        [data-theme="dark"] .autodocs-search-result:hover,
        [data-theme="dark"] .autodocs-search-result.active {
            background: #2a2a3e;
        }
        .autodocs-search-method {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            min-width: 50px;
            text-align: center;
            flex-shrink: 0;
        }
        .autodocs-method-get { background: #dcfce7; color: #166534; }
        .autodocs-method-post { background: #dbeafe; color: #1e40af; }
        .autodocs-method-put { background: #fef3c7; color: #92400e; }
        .autodocs-method-patch { background: #fef3c7; color: #92400e; }
        .autodocs-method-delete { background: #fee2e2; color: #991b1b; }
        [data-theme="dark"] .autodocs-method-get { background: #064e3b; color: #6ee7b7; }
        [data-theme="dark"] .autodocs-method-post { background: #1e3a5f; color: #93c5fd; }
        [data-theme="dark"] .autodocs-method-put { background: #451a03; color: #fcd34d; }
        [data-theme="dark"] .autodocs-method-patch { background: #451a03; color: #fcd34d; }
        [data-theme="dark"] .autodocs-method-delete { background: #450a0a; color: #fca5a5; }
        .autodocs-search-info {
            flex: 1;
            min-width: 0;
        }
        .autodocs-search-group {
            font-size: 11px;
            font-weight: 600;
            color: #8b5cf6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        [data-theme="dark"] .autodocs-search-group {
            color: #a78bfa;
        }
        .autodocs-search-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--color-text, #334155);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        [data-theme="dark"] .autodocs-search-name {
            color: #e2e8f0;
        }
        .autodocs-search-path {
            font-size: 12px;
            font-family: monospace;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }
        [data-theme="dark"] .autodocs-search-path {
            color: #94a3b8;
        }
        .autodocs-search-summary {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .autodocs-search-empty {
            padding: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
        }
        .autodocs-search-hint {
            padding: 8px 16px;
            border-top: 1px solid var(--color-border, #e2e8f0);
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            gap: 16px;
        }
        [data-theme="dark"] .autodocs-search-hint {
            border-color: #444;
        }

        /* Fixed search trigger button */
        .autodocs-search-trigger {
            position: fixed;
            top: 12px;
            right: 16px;
            z-index: 99998;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--color-border, #e2e8f0);
            background: var(--color-canvas, #fff);
            color: #64748b;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.15s ease;
        }
        .autodocs-search-trigger:hover {
            border-color: #8b5cf6;
            color: #8b5cf6;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.15);
        }
        [data-theme="dark"] .autodocs-search-trigger {
            background: #1e1e2e;
            border-color: #444;
            color: #94a3b8;
        }
        [data-theme="dark"] .autodocs-search-trigger:hover {
            border-color: #a78bfa;
            color: #a78bfa;
        }
        .autodocs-search-trigger svg {
            flex-shrink: 0;
        }
        .autodocs-search-trigger-kbd {
            font-size: 11px;
            padding: 1px 5px;
            border-radius: 3px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #94a3b8;
            font-family: monospace;
        }
        [data-theme="dark"] .autodocs-search-trigger-kbd {
            background: #333;
            border-color: #555;
        }
    </style>
</head>
<body style="height: 100vh; overflow-y: hidden">
<elements-api
    id="docs"
    tryItCredentialsPolicy="{{ $config->get('ui.try_it_credentials_policy', 'include') }}"
    router="hash"
    @if($config->get('ui.hide_try_it')) hideTryIt="true" @endif
    @if($config->get('ui.hide_schemas')) hideSchemas="true" @endif
    @if($config->get('ui.logo')) logo="{{ $config->get('ui.logo') }}" @endif
    @if($config->get('ui.layout')) layout="{{ $config->get('ui.layout') }}" @endif
/>
<script>
    (async () => {
        const docs = document.getElementById('docs');
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>

@if($config->get('ui.theme', 'light') === 'system')
    <script>
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function updateTheme(e) {
            if (e.matches) {
                window.document.documentElement.setAttribute('data-theme', 'dark');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'dark');
            } else {
                window.document.documentElement.setAttribute('data-theme', 'light');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'light');
            }
        }

        mediaQuery.addEventListener('change', updateTheme);
        updateTheme(mediaQuery);
    </script>
@endif

<!-- Fixed Search Button -->
<button class="autodocs-search-trigger" id="searchTrigger" type="button" aria-label="Search endpoints">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <path d="m21 21-4.35-4.35"></path>
    </svg>
    <span>Search...</span>
    <kbd class="autodocs-search-trigger-kbd">⌘K</kbd>
</button>

<!-- Search Overlay -->
<div class="autodocs-search-overlay" id="searchOverlay">
    <div class="autodocs-search-container">
        <div class="autodocs-search-input-wrapper">
            <svg class="autodocs-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" class="autodocs-search-input" id="searchInput" placeholder="Search endpoints..." autocomplete="off">
            <kbd class="autodocs-search-kbd">ESC</kbd>
        </div>
        <div class="autodocs-search-results" id="searchResults"></div>
        <div class="autodocs-search-hint">
            <span>↑↓ Navigate</span>
            <span>↵ Open</span>
            <span>ESC Close</span>
        </div>
    </div>
</div>

<script>
(function() {
    const spec = @json($spec);
    let endpoints = [];
    let activeIndex = 0;

    // Parse OpenAPI spec to extract endpoints
    function parseEndpoints() {
        if (!spec || !spec.paths) return;
        for (const [path, methods] of Object.entries(spec.paths)) {
            for (const [method, operation] of Object.entries(methods)) {
                if (['get','post','put','patch','delete','head','options'].includes(method)) {
                    const operationId = operation.operationId || '';
                    // Stoplight Elements uses #/operations/{operationId} format
                    const hash = operationId
                        ? '#/operations/' + operationId
                        : '#/paths/' + ('/' + path).replace(/\//g, '~1') + '/' + method;
                    endpoints.push({
                        method: method.toUpperCase(),
                        path: path.startsWith('/') ? path : '/' + path,
                        summary: operation.summary || '',
                        description: operation.description || '',
                        operationId: operationId,
                        tags: (operation.tags || []).join(', '),
                        hash: hash,
                    });
                }
            }
        }
    }

    // Get base server URL from spec
    const serverUrl = (spec.servers && spec.servers[0] && spec.servers[0].url) || window.location.origin;

    parseEndpoints();

    const overlay = document.getElementById('searchOverlay');
    const input = document.getElementById('searchInput');
    const results = document.getElementById('searchResults');

    function openSearch() {
        overlay.classList.add('active');
        input.value = '';
        activeIndex = 0;
        renderResults([]);
        setTimeout(() => input.focus(), 50);
    }

    function closeSearch() {
        overlay.classList.remove('active');
        input.value = '';
    }

    function filterEndpoints(query) {
        if (!query.trim()) return endpoints;
        const q = query.toLowerCase();
        return endpoints.filter(ep =>
            ep.path.toLowerCase().includes(q) ||
            ep.method.toLowerCase().includes(q) ||
            ep.summary.toLowerCase().includes(q) ||
            ep.tags.toLowerCase().includes(q) ||
            ep.operationId.toLowerCase().includes(q) ||
            ep.description.toLowerCase().includes(q)
        );
    }

    function renderResults(items) {
        if (items.length === 0) {
            results.innerHTML = input.value.trim()
                ? '<div class="autodocs-search-empty">No endpoints found</div>'
                : '<div class="autodocs-search-empty">Type to search endpoints...</div>';
            return;
        }
        results.innerHTML = items.map((ep, i) => {
            const base = serverUrl.replace(/\/$/, '');
            const pathPart = ep.path.startsWith('/') ? ep.path : '/' + ep.path;
            const fullUrl = (base + pathPart).replace(/([^:])\/\//g, '$1/');
            return `
            <div class="autodocs-search-result ${i === activeIndex ? 'active' : ''}" data-index="${i}" data-hash="${ep.hash}">
                <span class="autodocs-search-method autodocs-method-${ep.method.toLowerCase()}">${ep.method}</span>
                <div class="autodocs-search-info">
                    ${ep.tags ? `<div class="autodocs-search-group">${ep.tags}</div>` : ''}
                    <div class="autodocs-search-name">${ep.summary || ep.operationId || ep.path}</div>
                    <div class="autodocs-search-path">${fullUrl}</div>
                    ${ep.description ? `<div class="autodocs-search-summary">${ep.description.substring(0, 100)}${ep.description.length > 100 ? '...' : ''}</div>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    function navigateTo(hash) {
        closeSearch();
        window.location.hash = hash;
    }

    // Event: keyboard shortcut to open (Ctrl+K / Cmd+K)
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            closeSearch();
        }
    });

    // Event: input filtering
    input.addEventListener('input', function() {
        const filtered = filterEndpoints(this.value);
        activeIndex = 0;
        renderResults(filtered);
    });

    // Event: keyboard navigation in results
    input.addEventListener('keydown', function(e) {
        const items = results.querySelectorAll('.autodocs-search-result');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, items.length - 1);
            renderResults(filterEndpoints(input.value));
            items[activeIndex]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            renderResults(filterEndpoints(input.value));
            items[activeIndex]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const active = results.querySelector('.autodocs-search-result.active');
            if (active) navigateTo(active.dataset.hash);
        }
    });

    // Event: click on result
    results.addEventListener('click', function(e) {
        const item = e.target.closest('.autodocs-search-result');
        if (item) navigateTo(item.dataset.hash);
    });

    // Event: click overlay background to close
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeSearch();
    });

    // Event: search trigger button click
    document.getElementById('searchTrigger').addEventListener('click', function() {
        openSearch();
    });
})();
</script>
</body>
</html>
