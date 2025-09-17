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
    <title>TxPlays — Rewards</title>

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

  <body class="bg-[radial-gradient(2000px_800px_at_50%_120%,#070910,transparent)] text-white font-sans antialiased" data-page="rewards">
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
        <a class="nav-item is-active" aria-label="Rewards" style="--accent:#FBBF24">
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
  <!-- Hero layer lives inside MAIN but behind the content -->
  <section class="max-w-7xl mx-auto px-6 pt-12 hero-section">
    <!-- HERO: behind heading + cards -->

    <!-- Page header (sits ABOVE the sun) -->
    <div class="relative z-10 text-center">
      <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-blue-brand via-violet to-pink-brand">
        Rewards
      </h1>
      <p class="mt-3 text-white/70">Claim up to $4,444 in rank-up rewards directly from Tx.</p>
    </div>

    <!-- States -->
    <div id="loading" class="relative z-10 mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <div class="glass rcard skeleton h-48"></div>
      <div class="glass rcard skeleton h-48"></div>
      <div class="glass rcard skeleton h-48"></div>
    </div>

    <div id="error-message" class="relative z-10 hidden mt-6">
      <div class="glass rounded-xl p-4 border border-red-500/20">
        <div class="flex items-center gap-2 text-red-300 font-semibold">
          <i data-lucide="alert-triangle" class="w-4 h-4"></i>
          <span>Unable to load user data. Showing rewards with default progress.</span>
        </div>
      </div>
    </div>

    <!-- Rewards Grid -->
    <div id="rewards-grid" class="relative z-10 mt-8 rgrid"></div>

    <!-- Help CTA -->
    <div class="relative z-10 mt-10 text-center">
      <a href="#how-to-rank" class="magnetic btn-primary" aria-label="How to rank up">
        <i data-lucide="sparkles"></i><span>How do rank-ups work?</span>
      </a>
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
            <a class="block hover:text-white/90" href="">Events</a>
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
