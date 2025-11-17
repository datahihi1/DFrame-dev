<?php
$host = "192.168.137.1";
$port = 19132;

use Datahihi1\RakNet\RakNetClient;

$client = new RakNetClient($host, $port);

// measure ping latency
$start = microtime(true);
$info = $client->ping();
$latency = $info ? round((microtime(true) - $start) * 1000, 2) : null;

$status = $info ? "Online" : "Offline";
$color = $info ? '#10b981' : '#ef4444';

$motd = $info['motd_text'] ?? ($info['motd'] ?? '');
$protocol = $info['protocol'] ?? null;
$version = $info['version'] ?? null;
$players = $info['players'] ?? null;
$maxPlayers = $info['maxPlayers'] ?? null;
$guid = $info['server_guid'] ?? null;
$timestamp = isset($info['timestamp']) ? date('Y-m-d H:i:s', (int)($info['timestamp'] / 1000)) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Minecraft Server Status — <?= htmlspecialchars($host . ':' . $port, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root{
            --bg:#0f172a;
            --card:#0b1220;
            --muted:#94a3b8;
            --glass: rgba(255,255,255,0.03);
            --radius:12px;
            --accent: <?= $color ?>;
            --max-width:980px;
        }
        html,body{height:100%;margin:0;font-family:Inter,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:linear-gradient(180deg,#071024 0%, #071f3a 100%);color:#e6eef8}
        .wrap{max-width:var(--max-width);margin:28px auto;padding:20px}
        .header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
        .title{display:flex;gap:12px;align-items:center}
        .logo{width:56px;height:56px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#4f46e5);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px;box-shadow:0 6px 18px rgba(2,6,23,0.6)}
        h1{font-size:20px;margin:0}
        .meta{font-size:13px;color:var(--muted)}
        .card{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));backdrop-filter: blur(6px);border-radius:var(--radius);padding:18px;box-shadow:0 6px 24px rgba(2,6,23,0.6)}
        .status{display:flex;align-items:center;gap:12px}
        .badge{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:var(--glass);border:1px solid rgba(255,255,255,0.03);font-weight:600}
        .dot{width:12px;height:12px;border-radius:50%;display:inline-block;box-shadow:0 0 8px rgba(0,0,0,0.4)}
        .grid{display:grid;grid-template-columns:1fr 320px;gap:16px;margin-top:14px}
        .col-main{min-height:160px}
        .stat-list{display:flex;flex-direction:column;gap:8px}
        .stat{display:flex;justify-content:space-between;background:rgba(255,255,255,0.02);padding:10px;border-radius:8px;font-size:14px}
        .muted{color:var(--muted);font-size:13px}
        details{margin-top:12px;color:var(--muted)}
        pre{white-space:pre-wrap;word-break:break-word;background:rgba(0,0,0,0.25);padding:12px;border-radius:8px;color:#e6eef8;overflow:auto}
        .actions{display:flex;gap:8px;align-items:center}
        .btn{background:transparent;border:1px solid rgba(255,255,255,0.06);color:inherit;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px}
        .btn.primary{background:var(--accent);border-color:transparent;color:#051023}
        @media (max-width:880px){
            .grid{grid-template-columns:1fr; }
            .logo{width:48px;height:48px}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div class="title">
            <div class="logo">MC</div>
            <div>
                <h1>Minecraft Server Status</h1>
                <div class="meta"><?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?> : <?= (int)$port ?> • <?= htmlspecialchars($motd ?: 'No MOTD', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="actions">
            <form method="get" action="" style="margin:0">
                <button class="btn" title="Refresh" type="submit">Refresh</button>
            </form>
            <button class="btn" onclick="navigator.clipboard?.writeText('<?= htmlspecialchars($host . ':' . $port, ENT_QUOTES, 'UTF-8') ?>')">Copy</button>
        </div>
    </div>

    <div class="card">
        <div class="status" style="justify-content:space-between">
            <div style="display:flex;gap:12px;align-items:center">
                <div class="badge">
                    <span class="dot" style="background:<?= $color ?>"></span>
                    <span><?= $status ?></span>
                </div>

                <div class="muted">Latency: <?= $latency !== null ? htmlspecialchars($latency . ' ms', ENT_QUOTES, 'UTF-8') : '—' ?></div>
                <div class="muted">Checked: <?= $timestamp ?? '—' ?></div>
            </div>

            <div class="muted">Protocol: <?= $protocol ?? '—' ?> <?= $version ? ('• ' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8')) : '' ?></div>
        </div>

        <div class="grid">
            <div class="col-main">
                <div class="stat-list">
                    <div class="stat"><div class="muted">MOTD</div><div><?= htmlspecialchars($motd ?: '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="stat"><div class="muted">Players</div><div><?= $players !== null ? htmlspecialchars($players . ($maxPlayers !== null ? " / $maxPlayers" : ''), ENT_QUOTES, 'UTF-8') : '—' ?></div></div>
                    <div class="stat"><div class="muted">Server GUID</div><div style="font-family:monospace;font-size:13px"><?= $guid !== null ? htmlspecialchars((string)$guid, ENT_QUOTES, 'UTF-8') : '—' ?></div></div>
                </div>

                <details>
                    <summary>Raw payload</summary>
                    <pre><?= htmlspecialchars(var_export($info, true), ENT_QUOTES, 'UTF-8') ?></pre>
                </details>
            </div>

            <div>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <div class="stat"><div class="muted">Status</div><div style="font-weight:700;color:<?= $color ?>"><?= $status ?></div></div>
                    <div class="stat"><div class="muted">Host</div><div><?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="stat"><div class="muted">Port</div><div><?= (int)$port ?></div></div>
                    <div class="stat"><div class="muted">Checked at</div><div><?= $timestamp ?? '—' ?></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- small progressive enhancement for auto-refresh -->
<script>
(function(){
    // optional: auto refresh every 3 minutes if user leaves page visible
    var AUTO = 180000;
    var timer = setInterval(function(){
        if (document.visibilityState === 'visible') location.reload();
    }, AUTO);
})();
</script>
</script>
</body>
</html>