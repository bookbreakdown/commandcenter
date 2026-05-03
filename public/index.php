<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Command Center</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            background: #0d1117;
            color: #c9d1d9;
            padding: 24px;
            font-size: 14px;
        }
        h1 {
            font-size: 20px;
            color: #58a6ff;
            margin-bottom: 24px;
            font-weight: 500;
        }
        .project {
            margin-bottom: 32px;
        }
        .project-name {
            font-size: 16px;
            color: #f0883e;
            margin-bottom: 12px;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th {
            text-align: left;
            padding: 8px 12px;
            background: #161b22;
            color: #8b949e;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #30363d;
        }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #21262d;
            vertical-align: top;
        }
        tr:hover td { background: #161b22; }
        .workspace-path { color: #58a6ff; }
        .branch { color: #a5d6ff; }
        .sync-clean { color: #3fb950; }
        .sync-ahead { color: #d29922; }
        .sync-behind { color: #f85149; }
        .sync-diverged { color: #f85149; font-weight: 600; }
        .session-guid {
            font-size: 12px;
            color: #6e7681;
            cursor: pointer;
        }
        .session-guid:hover { color: #c9d1d9; }
        .session-label { color: #c9d1d9; font-weight: 500; }
        .session-unlabeled { color: #6e7681; font-style: italic; }
        .status-active { color: #3fb950; }
        .status-paused { color: #d29922; }
        .status-done { color: #6e7681; }
        .action-btn {
            font-size: 11px;
            color: #8b949e;
            cursor: pointer;
            background: #21262d;
            padding: 2px 8px;
            border-radius: 3px;
            border: 1px solid #30363d;
        }
        .action-btn:hover { color: #58a6ff; border-color: #58a6ff; }
        .sessions-list {
            margin-top: 4px;
            padding-left: 0;
            list-style: none;
        }
        .sessions-list li {
            padding: 4px 0;
            border-top: 1px solid #21262d;
            display: flex;
            align-items: baseline;
            gap: 12px;
        }
        .sessions-list li:first-child { border-top: none; }
        .no-sessions { color: #484f58; font-style: italic; font-size: 12px; }
        .discovered { opacity: 0.6; }
        .discovered:hover { opacity: 1; }
        .age-stale { color: #484f58; }
        .age-recent { color: #d29922; }
        .age-fresh { color: #3fb950; }
        .empty-state {
            text-align: center;
            padding: 48px;
            color: #484f58;
        }
        .empty-state code {
            background: #161b22;
            padding: 2px 6px;
            border-radius: 3px;
            color: #8b949e;
        }
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1f6feb;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .toast.show { opacity: 1; }
        .time-ago { color: #6e7681; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Command Center <span class="action-btn" onclick="copyRegisterPrompt()" style="font-size:11px;vertical-align:middle;margin-left:8px">register session</span> <span id="pulse" style="font-size:12px;color:#484f58;font-weight:400"></span></h1>

    <div id="dashboard"></div>

    <!-- Rendered by JS from API -->

    <div class="toast" id="toast">Copied!</div>

    <script>
        function copyToClipboard(text, msg) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast(msg);
        }

        function copyResume(guid) {
            copyToClipboard('claude --resume ' + guid + ' --dangerously-skip-permissions', 'Resume command copied!');
        }

        function copyLabel(guid) {
            copyToClipboard('Do not read or investigate the command center project. Just run this command exactly as-is:\n\n/var/www/commandcenter/bin/cc-register --guid ' + guid + ' --label "PROJECT | brief task description"\n\nReplace PROJECT with your project name and give a 3-4 word task description based on what we have been working on.', 'Label prompt copied!');
        }

        function copyRegisterPrompt() {
            copyToClipboard('Do not read or investigate the command center project. Just run this command exactly as-is:\n\n/var/www/commandcenter/bin/cc-register --label "PROJECT | brief task description"\n\nReplace PROJECT with your project name and give a 3-4 word task description based on what we have been working on. Your GUID and workspace are auto-detected.', 'Register prompt copied!');
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 1500);
        }

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function timeAgo(dt) {
            const now = new Date();
            const then = new Date(dt + 'Z');
            const s = Math.floor((now - then) / 1000);
            if (s < 60) return 'just now';
            if (s < 3600) return Math.floor(s / 60) + 'm ago';
            if (s < 86400) return Math.floor(s / 3600) + 'h ago';
            return Math.floor(s / 86400) + 'd ago';
        }

        function syncHtml(git) {
            if (!git.exists) return '<span class="sync-behind">missing</span>';
            if (git.ahead === 0 && git.behind === 0) return '<span class="sync-clean">clean</span>';
            if (git.ahead > 0 && git.behind > 0) return '<span class="sync-diverged">' + git.ahead + ' ahead, ' + git.behind + ' behind</span>';
            if (git.ahead > 0) return '<span class="sync-ahead">' + git.ahead + ' ahead</span>';
            return '<span class="sync-behind">' + git.behind + ' behind</span>';
        }

        function render(projects) {
            const el = document.getElementById('dashboard');
            if (!projects.length) {
                el.innerHTML = '<div class="empty-state"><p>No projects registered yet.</p></div>';
                return;
            }
            let html = '';
            for (const p of projects) {
                html += '<div class="project"><div class="project-name">' + esc(p.name) + '</div>';
                html += '<table><thead><tr><th style="width:28%">Workspace</th><th style="width:10%">Branch</th><th style="width:7%">Sync</th><th style="width:9%">Last Active</th><th>Sessions</th></tr></thead><tbody>';
                for (const ws of p.workspaces) {
                    const latestSession = ws.sessions[0] || null;
                    const lastActiveAt = latestSession ? latestSession.last_active_at : null;
                    const lastAgo = lastActiveAt ? timeAgo(lastActiveAt) : '--';
                    const lastAgoClass = !lastActiveAt ? 'age-stale'
                        : lastAgo.includes('just') || lastAgo.includes('m ago') || lastAgo.includes('h ago') ? 'age-fresh'
                        : lastAgo.includes('d ago') && parseInt(lastAgo) < 7 ? 'age-recent'
                        : 'age-stale';

                    html += '<tr>';
                    html += '<td class="workspace-path">' + esc(ws.path) + '</td>';
                    html += '<td class="branch">' + esc(ws.git.branch || '--') + '</td>';
                    html += '<td>' + syncHtml(ws.git) + '</td>';
                    html += '<td class="' + lastAgoClass + '">' + lastAgo + '</td>';
                    html += '<td>';
                    const hasAny = ws.sessions.length || (ws.discovered && ws.discovered.length);
                    if (!hasAny) {
                        html += '<span class="no-sessions">no sessions</span>';
                    } else {
                        html += '<ul class="sessions-list">';
                        for (const s of ws.sessions) {
                            html += '<li>';
                            html += '<span class="status-' + s.status + '">' + s.status + '</span>';
                            if (s.label) {
                                html += '<span class="session-label">' + esc(s.label) + '</span>';
                            } else {
                                html += '<span class="session-unlabeled">' + s.guid.substring(0, 8) + '...</span>';
                            }
                            html += '<span class="action-btn" onclick="copyLabel(\'' + s.guid + '\')">label</span>';
                            html += '<span class="action-btn" onclick="copyResume(\'' + s.guid + '\')">resume</span>';
                            html += '<span class="time-ago">' + timeAgo(s.last_active_at) + '</span>';
                            html += '</li>';
                        }
                        if (ws.discovered && ws.discovered.sessions && ws.discovered.sessions.length) {
                            const disc = ws.discovered;
                            const wsKey = 'ws-' + ws.id;
                            const expanded = expandedSets.has(wsKey);
                            const toShow = expanded ? disc.sessions : disc.sessions.slice(0, 5);
                            for (const d of toShow) {
                                const ageClass = d.age_days < 1 ? 'age-fresh' : d.age_days < 7 ? 'age-recent' : 'age-stale';
                                html += '<li class="discovered">';
                                html += '<span class="' + ageClass + '">' + (d.age_days < 1 ? 'today' : d.age_days + 'd ago') + '</span>';
                                html += '<span class="session-unlabeled">' + d.guid.substring(0, 8) + '...</span>';
                                html += '<span style="color:#484f58;font-size:11px">' + d.size_kb + 'kb</span>';
                                html += '<span class="action-btn" onclick="copyLabel(\'' + d.guid + '\')">label</span>';
                                html += '<span class="action-btn" onclick="copyResume(\'' + d.guid + '\')">resume</span>';
                                html += '</li>';
                            }
                            if (disc.total > 5 && !expanded) {
                                html += '<li class="discovered"><span class="action-btn" onclick="expandDiscovered(\'' + wsKey + '\')">' + (disc.total - 5) + ' more...</span></li>';
                            }
                        }
                        html += '</ul>';
                    }
                    html += '</td></tr>';
                }
                html += '</tbody></table></div>';
            }
            el.innerHTML = html;
        }

        const expandedSets = new Set();

        function expandDiscovered(wsKey) {
            expandedSets.add(wsKey);
            poll();
        }

        async function poll() {
            try {
                const expand = [...expandedSets].join(',');
                const res = await fetch('/api.php?action=dashboard' + (expand ? '&expand=' + expand : ''));
                const data = await res.json();
                render(data);
                document.getElementById('pulse').textContent = 'updated ' + new Date().toLocaleTimeString();
            } catch (e) {
                document.getElementById('pulse').textContent = 'poll failed';
            }
        }

        poll();
        setInterval(poll, 5000);
    </script>
</body>
</html>
