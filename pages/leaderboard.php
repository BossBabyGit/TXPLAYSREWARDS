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

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
    <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TxPlays — Leaderboard</title>

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

  <body class="bg-[radial-gradient(2000px_800px_at_50%_120%,#070910,transparent)] text-white font-sans antialiased" data-page="leaderboard">
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
        <a class="nav-item is-active" aria-label="Leaderboards" style="--accent:#F59E0B">
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
        <a href="/pages/content.php" class="nav-item" aria-label="Content" style="--accent:#22D3EE">
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
          <a href="/discord-login.php" class="magnetic group relative inline-flex items-center gap-2 rounded-xl px-8 py-3 text-sm font-medium bg-discord hover:bg-discordDark transition-colors">
            <span>Login</span>
            <span class="absolute -inset-px rounded-xl ring-1 ring-white/10"></span>
          </a>
        </div>
      </div>
    </header>

    <main class="pl-rail relative z-10">
      <section class="relative pt-10 pb-14 hero-section">
        <div class="max-w-7xl mx-auto px-6 relative z-10">
          <!-- Local moving background + header -->
          <div class="lb-hero text-center relative">
           

            <h1 class="relative z-10 text-4xl sm:text-6xl font-extrabold tracking-tight">
              <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-brand via-violet to-pink-brand">Leaderboard</span>
            </h1>
            <p class="relative z-10 mt-3 text-white/70">See where you stand among the top players. Compete for amazing prizes every week.</p>

            <!-- timeframe -->
            <div class="relative z-10 mt-6 inline-flex gap-2 glass rounded-2xl p-1">
              <button data-range="weekly" class="range-btn px-4 py-2 rounded-xl text-sm font-medium bg-white/10">Current</button>
              <button data-range="monthly" class="range-btn px-4 py-2 rounded-xl text-sm font-medium">Previous</button>
            </div>
          

          <!-- Podium (Top 3) — screenshot style -->
          <div class="mt-10 grid md:grid-cols-3 gap-6 items-end">
            <!-- 2nd -->
            <div class="podium podium-2">
              <span class="pill">Runner-up</span>
              <span class="pill right"><i data-lucide="medal" class="w-4 h-4"></i> 2nd</span>

              <div class="flex items-start justify-between gap-4">
                <div>
                  <br>
                  <br>  
                  <div class="rank">#2</div>
                  <div class="name" id="p2-name">—</div>
                </div>
                <div class="meta">
                  <br>
                  <br>
                  <div class="lab">Wagered</div>
                  <div class="val" id="p2-wager">$0</div>
                  <div class="lab mt-1">Prize</div>
                  <div class="prize-chip" id="p2-prize-chip">
                    <span class="gift" aria-hidden="true">
  <svg viewBox="0 0 24 24" class="gift-svg">
    <defs>
      <linearGradient id="gift-grad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0"  stop-color="var(--gift-a)"/>
        <stop offset="1"  stop-color="var(--gift-b)"/>
      </linearGradient>
    </defs>
    <!-- lid -->
    <rect class="fill" x="3.5" y="8" width="17" height="4" rx="1.2" stroke="var(--gift-stroke)"/>
    <!-- box -->
    <rect class="fill" x="4.5" y="12" width="15" height="8.5" rx="1.6" stroke="var(--gift-stroke)"/>
    <!-- ribbon vertical -->
    <rect x="11" y="8" width="2" height="12.5" rx="1" fill="white" opacity=".9" stroke="var(--gift-stroke)"/>
    <!-- bow loops -->
    <path d="M12 8
             c-1.6-3 -4.2-3.6 -5.2-2.1
             c-.9 1.4 .6 2.8 2.9 3.4
             M12 8
             c1.6-3 4.2-3.6 5.2-2.1
             c.9 1.4 -.6 2.8 -2.9 3.4"
          fill="none" stroke="var(--gift-stroke)"/>
    <!-- bow center -->
    <circle cx="12" cy="9" r="1.2" class="fill" stroke="var(--gift-stroke)"/>
  </svg>
</span>
                    <span id="p2-prize">$0</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- 1st -->
            <div class="podium podium-1">
              <span class="pill">Champion</span>
              <span class="pill right"><i data-lucide="crown" class="w-4 h-4"></i></span>

              <div class="flex items-start justify-between gap-4">
                <div>
                  <br>
                  <br> 
                  <div class="rank">#1</div>
                  <div class="name" id="p1-name">—</div>
                </div>
                <div class="meta">
                  <br>
                  <br> 
                  <div class="lab">Wagered</div>
                  <div class="val" id="p1-wager">$0</div>
                  <br>
                  <div class="lab mt-1">Prize</div>
                  <div class="prize-chip" id="p1-prize-chip">
                   <span class="gift" aria-hidden="true">
  <svg viewBox="0 0 24 24" class="gift-svg">
    <defs>
      <linearGradient id="gift-grad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0"  stop-color="var(--gift-a)"/>
        <stop offset="1"  stop-color="var(--gift-b)"/>
      </linearGradient>
    </defs>
    <!-- lid -->
    <rect class="fill" x="3.5" y="8" width="17" height="4" rx="1.2" stroke="var(--gift-stroke)"/>
    <!-- box -->
    <rect class="fill" x="4.5" y="12" width="15" height="8.5" rx="1.6" stroke="var(--gift-stroke)"/>
    <!-- ribbon vertical -->
    <rect x="11" y="8" width="2" height="12.5" rx="1" fill="white" opacity=".9" stroke="var(--gift-stroke)"/>
    <!-- bow loops -->
    <path d="M12 8
             c-1.6-3 -4.2-3.6 -5.2-2.1
             c-.9 1.4 .6 2.8 2.9 3.4
             M12 8
             c1.6-3 4.2-3.6 5.2-2.1
             c.9 1.4 -.6 2.8 -2.9 3.4"
          fill="none" stroke="var(--gift-stroke)"/>
    <!-- bow center -->
    <circle cx="12" cy="9" r="1.2" class="fill" stroke="var(--gift-stroke)"/>
  </svg>
</span>
                    <span id="p1-prize">$0</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- 3rd -->
            <div class="podium podium-3">
              <span class="pill">Third</span>
              <span class="pill right"><i data-lucide="medal" class="w-4 h-4"></i> 3rd</span>

              <div class="flex items-start justify-between gap-4">
                <div>
                  <br>
                  <br> 
                  <div class="rank">#3</div>
                  <div class="name" id="p3-name">—</div>
                </div>
                <div class="meta">
                  <br>
                  <br> 
                  <div class="lab">Wagered</div>
                  <div class="val" id="p3-wager">$0</div>
                  <div class="lab mt-1">Prize</div>
                  <div class="prize-chip" id="p3-prize-chip">
                    <span class="gift" aria-hidden="true">
  <svg viewBox="0 0 24 24" class="gift-svg">
    <defs>
      <linearGradient id="gift-grad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0"  stop-color="var(--gift-a)"/>
        <stop offset="1"  stop-color="var(--gift-b)"/>
      </linearGradient>
    </defs>
    <!-- lid -->
    <rect class="fill" x="3.5" y="8" width="17" height="4" rx="1.2" stroke="var(--gift-stroke)"/>
    <!-- box -->
    <rect class="fill" x="4.5" y="12" width="15" height="8.5" rx="1.6" stroke="var(--gift-stroke)"/>
    <!-- ribbon vertical -->
    <rect x="11" y="8" width="2" height="12.5" rx="1" fill="white" opacity=".9" stroke="var(--gift-stroke)"/>
    <!-- bow loops -->
    <path d="M12 8
             c-1.6-3 -4.2-3.6 -5.2-2.1
             c-.9 1.4 .6 2.8 2.9 3.4
             M12 8
             c1.6-3 4.2-3.6 5.2-2.1
             c.9 1.4 -.6 2.8 -2.9 3.4"
          fill="none" stroke="var(--gift-stroke)"/>
    <!-- bow center -->
    <circle cx="12" cy="9" r="1.2" class="fill" stroke="var(--gift-stroke)"/>
  </svg>
</span>
                    <span id="p3-prize">$0</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Countdown -->
          <div class="mt-10 grid">
            <div class="glass rounded-3xl p-6 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-3 text-center sm:items-center sm:text-left">
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-indigo to-violet"><i data-lucide="hourglass" class="h-5 w-5"></i></div>
                <div class="text-sm">
                  <div class="font-semibold">Time left to Wager</div>
                  <div class="text-white/60" id="countdown-sub">Resets Monthly 00:00 (CST)</div>
                </div>
              </div>
              <div class="flex w-full flex-wrap items-center justify-center gap-x-3 gap-y-2 text-center sm:w-auto sm:gap-4">
                <div class="min-w-[56px]"><div class="text-2xl font-extrabold flip sm:text-3xl" id="d">0</div><div class="text-xs text-white/60">D</div></div>
                <div class="hidden text-white/30 sm:block">:</div>
                <div class="min-w-[56px]"><div class="text-2xl font-extrabold flip sm:text-3xl" id="h">00</div><div class="text-xs text-white/60">H</div></div>
                <div class="hidden text-white/30 sm:block">:</div>
                <div class="min-w-[56px]"><div class="text-2xl font-extrabold flip sm:text-3xl" id="m">00</div><div class="text-xs text-white/60">M</div></div>
                <div class="hidden text-white/30 sm:block">:</div>
                <div class="min-w-[56px]"><div class="text-2xl font-extrabold flip sm:text-3xl" id="s">00</div><div class="text-xs text-white/60">S</div></div>
              </div>
            </div>
          </div>

          <!-- Places 4–20 -->
          <div class="mt-10">
            <div class="flex items-center justify-between">
              <h2 class="text-2xl font-semibold">#4 – #20</h2>
              <div class="text-xs text-white/50">Total Wagered Ascending</div>
            </div>
            <div class="mt-4 grid gap-3">
              <div id="rows" class="grid gap-3"></div>
            </div>
          </div>

          <!-- Stats -->
          <div class="mt-12 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="glass rounded-3xl p-6 text-center">
              <div class="text-3xl font-bold" id="stat-players">0</div>
              <div class="text-xs text-white/60 mt-1">Total Players</div>
            </div>
            <div class="glass rounded-3xl p-6 text-center">
              <div class="text-3xl font-bold" id="stat-wagered">$0</div>
              <div class="text-xs text-white/60 mt-1">Total Wagered</div>
            </div>
            <div class="glass rounded-3xl p-6 text-center">
              <div class="text-3xl font-bold" id="stat-highest">$0</div>
              <div class="text-xs text-white/60 mt-1">Highest Wager</div>
            </div>
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
