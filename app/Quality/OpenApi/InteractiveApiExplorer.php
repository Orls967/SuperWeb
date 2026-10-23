<?php

declare(strict_types=1);

namespace App\Quality\OpenApi;

final class InteractiveApiExplorer
{
    /**
     * Generate a standalone interactive HTML API explorer.
     *
     * @param  array<string, mixed>  $spec
     */
    public static function renderHtml(array $spec): string
    {
        $specJson = json_encode($spec, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
        $title = htmlspecialchars((string) ($spec['info']['title'] ?? 'AutoServe Enterprise API Explorer'), ENT_QUOTES, 'UTF-8');
        $version = htmlspecialchars((string) ($spec['info']['version'] ?? '3.1.0'), ENT_QUOTES, 'UTF-8');
        $tagCount = count($spec['tags'] ?? []);
        $pathCount = count($spec['paths'] ?? []);

        return <<<HTML
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} (v{$version})</title>
    <style>
        :root {
            --bg-primary: #020617;
            --bg-secondary: #0f172a;
            --bg-card: #1e293b;
            --border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #38bdf8;
            --accent-green: #22c55e;
            --accent-amber: #f59e0b;
            --accent-rose: #f43f5e;
            --accent-purple: #a855f7;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg-primary); color: var(--text-main); line-height: 1.5; }
        header { background: var(--bg-secondary); border-bottom: 1px solid var(--border); padding: 1.25rem 2rem; position: sticky; top: 0; z-index: 50; }
        .badge { display: inline-flex; align-items: center; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; }
        .badge-get { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-post { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
        .badge-put, .badge-patch { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-delete { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }
        .role-tag { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); font-size: 0.7rem; padding: 0.15rem 0.45rem; border-radius: 0.375rem; }
        .layout { display: grid; grid-template-columns: 280px 1fr; height: calc(100vh - 80px); }
        aside { background: var(--bg-secondary); border-right: 1px solid var(--border); overflow-y: auto; padding: 1rem; }
        main { overflow-y: auto; padding: 2rem; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0.75rem; margin-bottom: 1rem; overflow: hidden; transition: border-color 0.15s; }
        .card:hover { border-color: #475569; }
        .card-header { padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.875rem; cursor: pointer; user-select: none; }
        .card-body { padding: 1.25rem; border-top: 1px solid var(--border); background: rgba(15, 23, 42, 0.6); display: none; }
        .card.open .card-body { display: block; }
        input[type="text"] { background: var(--bg-secondary); border: 1px solid var(--border); color: var(--text-main); padding: 0.5rem 0.75rem; border-radius: 0.5rem; width: 100%; outline: none; font-size: 0.875rem; }
        input[type="text"]:focus { border-color: var(--accent); }
        .sidebar-item { padding: 0.45rem 0.75rem; border-radius: 0.375rem; color: var(--text-muted); cursor: pointer; font-size: 0.85rem; display: flex; justify-content: space-between; margin-bottom: 0.2rem; }
        .sidebar-item:hover, .sidebar-item.active { background: rgba(56, 189, 248, 0.1); color: var(--accent); }
        pre { background: #020617; border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.75rem; font-family: ui-monospace, monospace; font-size: 0.8rem; overflow-x: auto; color: #e2e8f0; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; font-size: 0.85rem; }
        th, td { border: 1px solid var(--border); padding: 0.5rem 0.75rem; text-align: left; }
        th { background: rgba(15, 23, 42, 0.8); color: var(--text-muted); font-weight: 600; }
    </style>
</head>
<body>
    <header>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-size: 1.25rem; font-weight: 700; color: #f8fafc; display: flex; align-items: center; gap: 0.75rem;">
                    <span>⚡ {$title}</span>
                    <span class="badge" style="background: rgba(56, 189, 248, 0.2); color: #38bdf8;">OAS {$version}</span>
                </h1>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Integer Minor-Unit (int64) • Deterministic Idempotency • Two-Source Hash-Chain Verification • {$tagCount} Modules • {$pathCount} Endpoints
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <input type="text" id="searchInput" placeholder="Search paths, roles, operationId (e.g. /api, admin)..." style="width: 320px;">
                <a href="openapi-v3.json" download class="badge" style="background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); text-decoration: none; padding: 0.5rem 0.75rem;">JSON</a>
                <a href="openapi-v3.yaml" download class="badge" style="background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); text-decoration: none; padding: 0.5rem 0.75rem;">YAML</a>
            </div>
        </div>
    </header>

    <div class="layout">
        <aside>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em;">
                Modules & Tags ({$tagCount})
            </div>
            <div id="moduleList">
                <div class="sidebar-item active" data-filter="all">
                    <span>All Modules</span>
                    <span id="totalBadge">{$pathCount}</span>
                </div>
            </div>
        </aside>

        <main id="operationsContainer">
            <!-- Operations populated dynamically via JS from embedded spec -->
        </main>
    </div>

    <script>
        const spec = {$specJson};
        let activeFilter = 'all';
        let searchQuery = '';

        function init() {
            renderSidebar();
            renderOperations();

            document.getElementById('searchInput').addEventListener('input', (e) => {
                searchQuery = e.target.value.toLowerCase().trim();
                renderOperations();
            });
        }

        function renderSidebar() {
            const list = document.getElementById('moduleList');
            const counts = {};

            Object.entries(spec.paths || {}).forEach(([path, methods]) => {
                Object.entries(methods).forEach(([method, op]) => {
                    const tag = (op.tags && op.tags[0]) || 'Other';
                    counts[tag] = (counts[tag] || 0) + 1;
                });
            });

            (spec.tags || []).forEach(tagObj => {
                const name = tagObj.name;
                const count = counts[name] || 0;
                const item = document.createElement('div');
                item.className = 'sidebar-item';
                item.dataset.filter = name;
                item.innerHTML = `<span>\${name}</span><span style="font-size: 0.75rem; opacity: 0.7;">\${count}</span>`;
                item.onclick = () => {
                    document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    activeFilter = name;
                    renderOperations();
                };
                list.appendChild(item);
            });

            document.querySelector('[data-filter="all"]').onclick = function() {
                document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
                this.classList.add('active');
                activeFilter = 'all';
                renderOperations();
            };
        }

        function renderOperations() {
            const container = document.getElementById('operationsContainer');
            container.innerHTML = '';
            let matched = 0;

            Object.entries(spec.paths || {}).forEach(([path, methods]) => {
                Object.entries(methods).forEach(([method, op]) => {
                    const tag = (op.tags && op.tags[0]) || 'Other';
                    const roles = op['x-allowed-roles'] || [];
                    const operationId = op.operationId || '';
                    const summary = op.summary || '';

                    if (activeFilter !== 'all' && tag !== activeFilter) return;

                    if (searchQuery) {
                        const hay = (path + ' ' + method + ' ' + tag + ' ' + operationId + ' ' + summary + ' ' + roles.join(' ')).toLowerCase();
                        if (!hay.includes(searchQuery)) return;
                    }

                    matched++;
                    const card = document.createElement('div');
                    card.className = 'card';

                    const roleBadges = roles.map(r => `<span class="role-tag">\${r}</span>`).join(' ');
                    const paramRows = (op.parameters || []).map(p => `
                        <tr>
                            <td><code>\${p.name}</code></td>
                            <td>\${p.in}</td>
                            <td>\${p.required ? '<span style="color: #fb7185; font-weight: bold;">Yes</span>' : 'No'}</td>
                            <td>\${p.schema ? (p.schema.type || '') + (p.schema.format ? ' (' + p.schema.format + ')' : '') : ''}</td>
                            <td>\${p.description || '-'}</td>
                        </tr>
                    `).join('');

                    const respRows = Object.entries(op.responses || {}).map(([code, resp]) => `
                        <tr>
                            <td><strong style="color: \${code.startsWith('2') ? '#4ade80' : '#fb7185'};">\${code}</strong></td>
                            <td>\${resp.description || ''}</td>
                        </tr>
                    `).join('');

                    card.innerHTML = `
                        <div class="card-header" onclick="this.parentElement.classList.toggle('open')">
                            <span class="badge badge-\${method}">\${method.toUpperCase()}</span>
                            <span style="font-family: ui-monospace, monospace; font-size: 0.9rem; font-weight: 600;">\${path}</span>
                            <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 0.5rem;">\${summary}</span>
                            <div style="margin-left: auto; display: flex; gap: 0.35rem; align-items: center;">
                                \${roleBadges}
                                <span class="badge" style="background: rgba(255,255,255,0.05); color: #cbd5e1;">\${tag}</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="margin-bottom: 0.75rem; font-size: 0.85rem; color: #cbd5e1;">
                                <strong>Operation ID:</strong> <code>\${operationId}</code>
                            </div>
                            <div style="margin-bottom: 0.75rem; font-size: 0.85rem; color: #cbd5e1;">
                                <strong>Description:</strong> \${op.description || summary}
                            </div>
                            \${paramRows ? `
                                <div style="margin-top: 1rem;">
                                    <h4 style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Parameters</h4>
                                    <table>
                                        <thead><tr><th>Name</th><th>In</th><th>Required</th><th>Type</th><th>Description</th></tr></thead>
                                        <tbody>\${paramRows}</tbody>
                                    </table>
                                </div>
                            ` : ''}
                            \${op.requestBody ? `
                                <div style="margin-top: 1rem;">
                                    <h4 style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Request Body</h4>
                                    <pre><code>\${JSON.stringify(op.requestBody, null, 2)}</code></pre>
                                </div>
                            ` : ''}
                            <div style="margin-top: 1rem;">
                                <h4 style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Responses</h4>
                                <table>
                                    <thead><tr><th>Code</th><th>Description</th></tr></thead>
                                    <tbody>\${respRows}</tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    container.appendChild(card);
                });
            });

            if (matched === 0) {
                container.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 3rem;">No operations matched current filters.</div>`;
            }
        }

        window.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
HTML;
    }
}
