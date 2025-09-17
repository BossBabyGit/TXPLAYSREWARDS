<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';

$currentUser = getAuthenticatedUser();
$appSession = sessionPayload($currentUser);

$appConfig = [
    'loginUrl' => '/discord-login.php',
    'logoutUrl' => '/logout.php',
    'sessionUrl' => '/api/session.php'
];

$encodedConfig = json_encode($appConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$encodedSession = json_encode($appSession, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($encodedConfig === false) {
    $encodedConfig = '{}';
}
if ($encodedSession === false) {
    $encodedSession = '{"authenticated":false,"user":null}';
}

// --- Videos data helpers ---
function human_time_ago(DateTime $date, ?DateTime $now = null): string {
    $now = $now ?: new DateTime('now', new DateTimeZone('UTC'));
    $diff = $now->getTimestamp() - $date->getTimestamp();
    if ($diff < 60) return 'just now';
    $units = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
    ];
    foreach ($units as $secs => $name) {
        if ($diff >= $secs) {
            $v = floor($diff / $secs);
            return $v.' '.$name.($v>1?'s':'').' ago';
        }
    }
    return 'just now';
}

function format_int_commas($n): string {
    return number_format((int)$n, 0, '.', ',');
}

function first_existing_path(array $candidates): ?string {
    foreach ($candidates as $p) {
        if (is_readable($p)) return $p;
    }
    return null;
}

// Try a few sensible locations for videos.json
$videosJsonPath = first_existing_path([
    __DIR__ . '/../videos.json',
    __DIR__ . '/videos.json',
    __DIR__ . '/../assets/data/videos.json',
    __DIR__ . '/assets/data/videos.json',
    __DIR__ . '/../public/videos.json',
    __DIR__ . '/../../videos.json',
    '/mnt/data/videos.json', // dev/uploads location
]);

$videos = [];
if ($videosJsonPath) {
    $raw = file_get_contents($videosJsonPath);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            // Normalize + sort by publishedAt DESC
            foreach ($decoded as $item) {
                if (!isset($item['videoId'])) continue;
                $publishedAt = new DateTime($item['publishedAt'] ?? 'now', new DateTimeZone('UTC'));
                $videos[] = [
                    'title'       => $item['title'] ?? 'Untitled',
                    'videoId'     => $item['videoId'],
                    'publishedAt' => $publishedAt,
                    'thumbnail'   => $item['thumbnail'] ?? ("https://i.ytimg.com/vi/{$item['videoId']}/maxresdefault.jpg"),
                    'duration'    => $item['duration'] ?? null,
                    'views'       => $item['views'] ?? 0,
                ];
            }
            usort($videos, fn($a,$b) => $b['publishedAt'] <=> $a['publishedAt']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
    <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TxPlays — Content</title>

    <!-- Poppins font -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="/assets/styles/main.css" />

    <!-- Tailwind (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      window.__APP_CONFIG__ = <?= $encodedConfig ?>;
      window.__APP_SESSION__ = <?= $encodedSession ?>;
    </script>
    <script src="/assets/scripts/main.js"></script>

    <!-- Lucide icons (UMD) -->
    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  </head>

  <body class="bg-[radial-gradient(2000px_800px_at_50%_120%,#070910,transparent)] text-white font-sans antialiased" data-page="content">
        <!-- Layered dark background stack -->
    <div class="bg-stack">
      <div class="bg-aurora"></div>
      <div class="bg-grid"></div>
      <div class="bg-noise"></div>
    </div>
    <canvas id="spotlight" class="spotlight"></canvas>

        <!-- Sidebar (colorful + toggleable) -->
    <aside class="nav-rail collapsed fixed left-0 top-0 h-full z-50">
      <div class="rail glass h-full flex flex-col items-center pt-6 gap-2 overflow-hidden relative">
        <span class="rail-active pointer-events-none"></span>
        <a href="/index.php#home" class="nav-item" aria-label="Home" style="--accent:#60A5FA">
          <span class="nav-bubble"><i data-lucide="home"></i></span>
          <span class="nav-label">Home</span>
        </a>
        <a href="/pages/leaderboard.php" class="nav-item" aria-label="Leaderboards" style="--accent:#F59E0B">
          <span class="nav-bubble"><i data-lucide="trophy"></i></span>
          <span class="nav-label">Leaderboards</span>
        </a>
        <a href="/pages/bonuses.php" class="nav-item" aria-label="Bonuses" style="--accent:#EC4899">
          <span class="nav-bubble"><i data-lucide="gift"></i></span>
          <span class="nav-label">Bonuses</span>
        </a>
        <a href="/pages/rewards.php" class="nav-item" aria-label="Rewards" style="--accent:#FBBF24">
          <span class="nav-bubble"><i data-lucide="coins"></i></span>
          <span class="nav-label">Rewards</span>
        </a>
        <a href="#" class="nav-item" aria-label="Events" style="--accent:#8B5CF6">
          <span class="nav-bubble"><i data-lucide="calendar"></i></span>
          <span class="nav-label">Events</span>
        </a>
        <a class="nav-item is-active" aria-label="Content" style="--accent:#22D3EE">
          <span class="nav-bubble"><i data-lucide="video"></i></span>
          <span class="nav-label">Content</span>
        </a>
        <!-- collapse/expand toggle -->
        <button id="railToggle" class="rail-toggle" title="Collapse/Expand">
          <i data-lucide="chevrons-left" class="w-4 h-4 text-white"></i>
        </button>
      </div>
    </aside>

        <!-- Top bar -->
    <header class="pl-rail relative z-30">
      <div class="max-w-7xl mx-auto flex items-center justify-between p-4">
        <div class="flex items-center gap-3">
          <div class="w-28 h-28 rounded-2xl grid place-items-center overflow-hidden">
            <img src="/assets/images/word.png" alt="TxPlays Logo" class="w-30 h-30 object-contain" />
          </div>
        </div>

        <!-- Discord-style Login button -->
        <div data-auth-root class="auth-controls relative flex items-center gap-2">
          <a href="/discord-login.php" class="magnetic group relative inline-flex items-center gap-2 rounded-xl px-6 py-3 text-lg font-semibold bg-discord hover:bg-discordDark transition-colors">
            <span>Login</span>
            <span class="absolute -inset-px rounded-xl ring-1 ring-white/10"></span>
          </a>
        </div>
      </div>
    </header>

    <main class="pl-rail relative z-10 pb-20">
      <section class="max-w-7xl mx-auto px-6 pt-12 hero-section">
        <div class="relative z-10">
          <!-- Page header -->
          <div class="text-center">
          <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-blue-brand via-violet to-pink-brand">Latest Videos</h1>
          <p class="mt-3 text-white/70">Fresh highlights, guides, and viewer challenges.</p>
          </div>

        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <?php if (!empty($videos)): ?>
            <?php
              // show up to 6
              $slice = array_slice($videos, 0, 6);
              foreach ($slice as $v):
                $url = "https://www.youtube.com/watch?v=" . urlencode($v['videoId']);
                $thumb = $v['thumbnail'];
                // If the JSON's thumbnail is a relative path like "images/xyz.jpg", let it pass through as-is.
                if (!preg_match('~^https?://~i', $thumb)) {
                    // Keep relative; your server will serve it from project path (e.g., /pages/content/images/.. or adjust as needed)
                }
                $ago = human_time_ago($v['publishedAt']);
                $duration = $v['duration'] ?: '';
                $views = format_int_commas($v['views']);
            ?>
              <article class="glass vcard tilt">
                <div class="vframe">
                  <img class="vthumb" src="../../assets/<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($v['title']) ?>">
                  <div class="vshimmer"></div>
                  <?php if ($duration): ?><div class="vduration"><?= htmlspecialchars($duration) ?></div><?php endif; ?>
                  <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="hoverfx" aria-label="Play video: <?= htmlspecialchars($v['title']) ?>">
                    <div class="playbtn">
                      <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" style="margin-left:2px"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                    <div class="eq"><span style="--d:1"></span><span style="--d:2"></span><span style="--d:3"></span><span style="--d:4"></span></div>
                  </a>
                </div>
                <div class="vmeta">
                  <div class="creator">
                    <img src="../../assets/images/yt.jpg" alt="Creator avatar">
                    <span>TxPlays</span>
                  </div>
                  <h3 class="vtitle"><?= htmlspecialchars($v['title']) ?></h3>
                  <div class="chips">
                    <span class="chip">Highlights</span>
                    <span class="chip">Community</span>
                  </div>
                  <div class="stats">
                    <span class="stat-pill"><i data-lucide="clock" class="w-3.5 h-3.5"></i><?= htmlspecialchars($ago) ?></span>
                    <span class="stat-pill"><i data-lucide="eye" class="w-3.5 h-3.5"></i><?= htmlspecialchars($views) ?></span>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
              <!-- Fallback when no videos.json or empty -->
              <div class="col-span-full text-center text-white/70">
                No videos found. Add a <code>videos.json</code> file or check its path.
              </div>
          <?php endif; ?>
        </div>


          <!-- Load more -->
          <div class="mt-10 text-center">
          <button class="magnetic btn-primary" onclick="window.open('https://www.youtube.com/@TXPLAYSYT','_blank')" aria-label="Load more videos">
            <i data-lucide="youtube"></i>
            <span>Load More Videos</span>
          </button>
          </div>
        </div>
      </section>
    </main>

        <!-- FOOTER -->
    <footer class="pl-rail border-t border-white/10 relative z-10">
      <div class="max-w-7xl mx-auto px-6 py-10 grid gap-8 sm:grid-cols-3">
        <div>
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-pink-brand to-blue-brand grid place-items-center">
              <i data-lucide="zap" class="w-4 h-4"></i>
            </div>
            <span class="font-semibold">TxPlays</span>
          </div>
          <p class="mt-3 text-sm text-white/60">Promos, Leaderboards, Rewards and Events — all in one place.</p>
        </div>
        <div class="grid grid-cols-2 gap-6 text-sm">
          <div class="space-y-2">
            <div class="text-white/60 uppercase tracking-wide text-xs">Pages</div>
            <a class="block hover:text-white/90" href="/pages/leaderboard">Leaderboards</a>
            <a class="block hover:text-white/90" href="/pages/bonuses">Bonuses</a>
            <a class="block hover:text-white/90" href="/pages/rewards">Rewards</a>
            <a class="block hover:text-white/90" href="#">Events</a>
            <a class="block hover:text-white/90" href="/pages/content">Content</a>
          </div>
          <div class="space-y-2">
            <div class="text-white/60 uppercase tracking-wide text-xs">Company</div>
            <a class="block hover:text-white/90" href="#">About</a>
            <a class="block hover:text-white/90" href="#">Contact</a>
            <a class="block hover:text-white/90" href="#">Terms</a>
            <a class="block hover:text-white/90" href="#">Privacy</a>
          </div>
        </div>
        <div class="text-sm">
          <a href="/discord-login.php" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 mt-2 bg-discord hover:bg-discordDark transition-colors">Login</a>
          <p class="mt-4 text-xs text-white/50">© <span id="year"></span> TXPLAYS. All rights reserved.</p>
        </div>
      </div>
    </footer>

  </body>
</html>
