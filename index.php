<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/session.php';

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
    <title>TxPlays — Casino Hub</title>

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

  <body class="bg-[radial-gradient(2000px_800px_at_50%_120%,#070910,transparent)] text-white font-sans antialiased" data-page="home">
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
        <a href="#home" class="nav-item is-active" aria-label="Home" style="--accent:#60A5FA">
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
        <a href="/events" class="nav-item" aria-label="Events" style="--accent:#8B5CF6">
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

    <main id="home" class="pl-rail relative z-10">
      <section class="hero-section relative min-h-[80vh] grid place-items-center">
        <div
          class="hero-sun"
          data-hero-img="/assets/images/background.png"
          style="--hero-img: url('/assets/images/background.png')"
        ></div>

        <!-- HERO CONTENT -->
        <div class="max-w-6xl mx-auto px-6 text-center relative z-20">
          <div class="inline-flex items-center justify-center w-48 h-48 rounded-[2rem] overflow-hidden">
            <img src="/assets/images/crown.png" alt="Your Logo" class="w-300 h-300 object-cover rounded-lg">
          </div>
          <h1 class="mt-8 text-5xl sm:text-7xl font-extrabold leading-tight tracking-tight">
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-brand via-violet to-pink-brand">TXPLAYS REWARDS</span>
          </h1>
          <p class="mt-5 text-white/70 max-w-2xl mx-auto">Welcome to TRUE Rewards designed by a TRUE Player. Leaderboards, Challenges and an Active Community for all things Casino.</p>

          <div class="mt-8 flex items-center justify-center gap-4">
            <a href="/pages/leaderboard.php" class="magnetic rounded-2xl px-6 py-3 font-semibold text-sm bg-gradient-to-br from-pink-brand to-blue-brand hover:drop-shadow-neonPink inline-flex items-center gap-2">
              <i data-lucide="trophy" class="w-5 h-5"></i> Leaderboards
            </a>
            <a href="#events" class="magnetic rounded-2xl px-6 py-3 font-semibold text-sm bg-gradient-to-br from-indigo to-violet hover:drop-shadow-neon inline-flex items-center gap-2">
              <i data-lucide="calendar-range" class="w-5 h-5"></i> Events
            </a>
          </div>

          <!-- Depth-parallax stats strip -->
          <div class="mt-16 grid place-items-center">
            <div class="tilt w-full max-w-4xl">
              <div class="relative grid w-full gap-4 sm:grid-cols-3">
                <div class="glass rounded-3xl p-6 text-center" style="transform: translateZ(40px)">
                  <div class="text-2xl font-bold text-white sm:text-3xl">5,000+</div>
                  <div class="mt-1 text-xs text-white/60">Community Users</div>
                </div>
                <div class="glass rounded-3xl p-6 text-center" style="transform: translateZ(60px)">
                  <div class="text-2xl font-bold text-white sm:text-3xl">$50,000+</div>
                  <div class="mt-1 text-xs text-white/60">Given Away</div>
                </div>
                <div class="glass rounded-3xl p-6 text-center" style="transform: translateZ(30px)">
                  <div class="text-2xl font-bold text-white sm:text-3xl">100+</div>
                  <div class="mt-1 text-xs text-white/60">Events Completed</div>
                </div>
              </div>
            </div>
          </div>
          <a href="#showcase" class="scroll-cue mt-10 inline-grid place-items-center" aria-label="Scroll down">
            <i data-lucide="chevron-down" class="w-5 h-5"></i>
          </a>
        </div>

        <div class="floaters">
          <div class="floater floater-sm floater-1">
            <img src="/assets/images/fp.png" alt="" class="sway">
          </div>
          <div class="floater floater-md floater-2">
            <img src="/assets/images/wanted.png" alt="" class="sway">
          </div>
          <div class="floater floater-lg floater-3">
            <img src="/assets/images/sweet.png" alt="" class="sway">
          </div>
          <div class="floater floater-sm floater-4">
            <img src="/assets/images/gates.png" alt="" class="sway">
          </div>
        </div>
      </section>

      <br><br><br>

      <!-- SHOWCASE GRID (unchanged content) -->
      <section id="showcase" class="relative z-0 pb-20">
        <div class="max-w-7xl mx-auto px-6">
          <div class="flex items-end justify-between">
            <h2 class="text-2xl sm:text-3xl font-semibold">Explore the TRUE Side</h2>
          </div>

          <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Leaderboards -->
            <a id="leaderboards" href="/pages/leaderboard.php" class="group relative rounded-3xl p-5 glass transition overflow-hidden tilt">
              <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl grid place-items-center"><i data-lucide="trophy" class="w-6 h-6"></i></div>
                <div>
                  <h3 class="text-lg font-semibold">Leaderboards</h3>
                  <p class="text-sm text-white/60">Monthly Wager Leaderboards for HUGE Prizes among the Community</p>
                </div>
              </div>
              <div class="mt-4 relative h-42 rounded-2xl overflow-hidden">
                <img src="/assets/images/1.png" alt="Leaderboards asset" class="w-full h-full object-cover" />
              </div>
            </a>

            <!-- Bonuses -->
            <a id="bonuses" href="/pages/bonuses.php" class="group relative rounded-3xl p-5 glass transition overflow-hidden tilt">
              <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl grid place-items-center"><i data-lucide="gift" class="w-6 h-6"></i></div>
                <div>
                  <h3 class="text-lg font-semibold">Bonuses</h3>
                  <p class="text-sm text-white/60">Exclusive Promo Codes, Deposit Bonuses & much more!</p>
                </div>
              </div>
              <div class="mt-4 relative h-42 rounded-2xl overflow-hidden">
                <img src="/assets/images/2.png" alt="Bonuses asset" class="w-full h-full object-cover" />
              </div>
            </a>

            <!-- Rewards -->
            <a id="rewards" href="/pages/rewards.php" class="group relative rounded-3xl p-5 glass transition overflow-hidden tilt">
              <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl grid place-items-center"><i data-lucide="coins" class="w-6 h-6"></i></div>
                <div>
                  <h3 class="text-lg font-semibold">Rewards</h3>
                  <p class="text-sm text-white/60">Get up to $4,444 in Exclusive Rewards & track your progress</p>
                </div>
              </div>
              <div class="mt-4 relative h-42 rounded-2xl overflow-hidden">
                <img src="/assets/images/3.png" alt="Rewards asset" class="w-full h-full object-cover" />
              </div>
            </a>

            <!-- Events -->
            <a id="events" href="#" class="group relative rounded-3xl p-5 glass transition overflow-hidden tilt">
              <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl grid place-items-center"><i data-lucide="calendar" class="w-6 h-6"></i></div>
                <div>
                  <h3 class="text-lg font-semibold">Events</h3>
                  <p class="text-sm text-white/60">Exclusive Events for our Community with Crypto, IRL & other cool Prizes</p>
                </div>
              </div>
              <div class="mt-4 relative h-42 rounded-2xl overflow-hidden">
                <img src="/assets/images/4.png" alt="Events asset" class="w-full h-full object-cover" />
              </div>
            </a>

            <!-- Content -->
            <a id="content" href="/pages/content.php" class="group relative rounded-3xl p-5 glass transition overflow-hidden tilt">
              <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl grid place-items-center"><i data-lucide="video" class="w-6 h-6"></i></div>
                <div>
                  <h3 class="text-lg font-semibold">Content</h3>
                  <p class="text-sm text-white/60">Find my latest Youtube Uploads to be up to Date</p>
                </div>
              </div>
              <div class="mt-4 relative h-42 rounded-2xl overflow-hidden">
                <img src="/assets/images/5.png" alt="Content asset" class="w-full h-full object-cover" />
              </div>
            </a>
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
