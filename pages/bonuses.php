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
    <title>TxPlays — Bonuses</title>

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

  <body class="bg-[radial-gradient(2000px_800px_at_50%_120%,#070910,transparent)] text-white font-sans antialiased" data-page="bonuses">
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
        <a class="nav-item is-active" aria-label="Bonuses" style="--accent:#EC4899">
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
          <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-blue-brand via-violet to-pink-brand">Exclusive Bonuses</h1>
          <p class="mt-3 text-white/70">Claim your special bonuses and boost your bankroll. TRUE rewards for TRUE players.</p>
          </div>

          <!-- Main bonus card -->
          <div class="mt-10 grid gap-6 items-stretch lg:grid-cols-[380px,1fr]">
          <div class="glass rounded-3xl p-4 flex items-center justify-center shadow-card">
            <img src="/assets/images/bonus.png" alt="Main Bonus" class="h-full w-full rounded-2xl object-contain" />
          </div>

          <div class="glass rounded-3xl p-6 sm:p-8 shadow-card">
            <div class="flex items-start justify-between gap-6">
              <div>
                <h2 class="text-2xl sm:text-3xl font-bold leading-tight">
                  Shuffle Casino Deposit Bonus
                </h2>
                <div class="mt-2 text-3xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-pink-brand to-blue-brand">
                  100% Bonus up to $1,000
                </div>
              </div>
            </div>

            <p class="mt-4 text-white/70">
              Get a massive 100% bonus on your first deposit at Shuffle Casino. Double your money instantly and enjoy our premium casino experience with this exclusive TXPLAY offer.
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
              <!-- Bonus code copy pill -->
              <button id="bonusCode" onclick="copyBonusCode()" class="relative group inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-2 hover:bg-white/10">
                <i data-lucide="clipboard" class="h-4 w-4"></i>
                <span class="text-sm">CODE:</span>
                <span class="text-sm font-semibold" id="bonusCodeText">TX</span>
                <span class="pointer-events-none absolute -bottom-9 left-1/2 -translate-x-1/2 text-[12px] bg-black/70 border border-white/10 rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition">Click to copy</span>
              </button>

              <!-- Claim button -->
              <button class="magnetic btn-primary" onclick="claimBonus()" aria-label="Claim Bonus Now">
  <i data-lucide="zap"></i>
  <span>Claim Bonus Now</span>
</button>

            </div>

            <!-- Terms -->
            <div class="mt-8">
              <div class="flex items-center gap-2 text-sm uppercase tracking-wide text-white/80">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                <span>Bonus Terms</span>
              </div>
              <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="flex items-start gap-2 text-sm text-white/70"><i data-lucide="check-circle" class="w-4 h-4 mt-0.5"></i><span>Bonus code: TX must be used</span></div>
                <div class="flex items-start gap-2 text-sm text-white/70"><i data-lucide="check-circle" class="w-4 h-4 mt-0.5"></i><span>Minimum deposit: $20</span></div>
                <div class="flex items-start gap-2 text-sm text-white/70"><i data-lucide="check-circle" class="w-4 h-4 mt-0.5"></i><span>35x wagering requirement (Depo+Bonus)</span></div>
                <div class="flex items-start gap-2 text-sm text-white/70"><i data-lucide="check-circle" class="w-4 h-4 mt-0.5"></i><span>Max Bet: $10</span></div>
                <div class="flex items-start gap-2 text-sm text-white/70"><i data-lucide="check-circle" class="w-4 h-4 mt-0.5"></i><span>Valid for new players only</span></div>
                <div class="flex items-start gap-2 text-sm text-white/70"><i data-lucide="check-circle" class="w-4 h-4 mt-0.5"></i><span>Restricted: Challenges, Accumulator Games</span></div>
              </div>
            </div>
          </div>
          </div>

          <!-- Small bonuses grid -->
          <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="glass rounded-2xl p-6 text-center shadow-card">
              <div class="mb-1 text-xl font-extrabold" style="color:#60A5FA">3K Leaderboard</div>
              <div class="mb-2 text-white/80">(Top 10 prizes)</div>
              <div class="text-white/60">Fight amongst your friends for competitive prizes and rewards 🏆</div>
              <button onclick="window.open('https://discord.gg/txplays','_blank')" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-pink-brand to-blue-brand px-4 py-2 hover:drop-shadow-neonPink">
                <span>Learn More</span>
              </button>
            </div>
            <div class="glass rounded-2xl p-6 text-center shadow-card">
              <div class="mb-1 text-xl font-extrabold" style="color:#A855F7">Affiliate Bonus Buys</div>
              <div class="mb-2 text-white/80">Win bonus buys daily.</div>
              <div class="text-white/60">Based on your play, you can receive random bonus buys from TX.</div>
              <button onclick="window.open('https://discord.gg/txplays','_blank')" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-pink-brand to-blue-brand px-4 py-2 hover:drop-shadow-neonPink">
                <span>Learn More</span>
              </button>
            </div>
            <div class="glass rounded-2xl p-6 text-center shadow-card">
              <div class="mb-1 text-xl font-extrabold" style="color:#F59E0B">Cash Events</div>
              <div class="mb-2 text-white/80">Access events worth $1,000s of dollars.</div>
              <div class="text-white/60">Participate periodically in community cash events.</div>
              <button onclick="window.open('https://discord.gg/txplays','_blank')" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-pink-brand to-blue-brand px-4 py-2 hover:drop-shadow-neonPink">
                <span>Learn More</span>
              </button>
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
