/* Tailwind CDN configuration */
window.tailwind = window.tailwind || {};
window.tailwind.config = {
  theme: {
    extend: {
      fontFamily: { sans: ["Poppins", "ui-sans-serif", "system-ui"] },
      colors: {
        pink: { brand: "#ec4899" },
        blue: { brand: "#3b82f6" },
        violet: { brand: "#7c3aed" },
        indigo: { brand: "#4f46e5" },
        purple: { brand: "#a855f7" },
        discord: "#5865F2",
        discordDark: "#4752C4"
      },
      dropShadow: {
        neon: "0 0 10px rgba(59,130,246,0.75)",
        neonPink: "0 0 10px rgba(236,72,153,0.75)"
      },
      boxShadow: {
        card: "0 20px 60px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.06)",
        ring: "0 0 0 1px rgba(255,255,255,.08)"
      }
    }
  }
};

(function () {
  const RAIL_KEY = "railCollapsed";
  const OLD_RAIL_KEY = "sidebar";

  const SESSION_CACHE_KEY = "txplays.session.cache";
  const SESSION_BROADCAST_KEY = "txplays.session.signal";
  const SHUFFLE_KEY_PREFIX = "txplays.shuffle.";

  const storage = {
    get(key) {
      if (typeof window === "undefined" || !window.localStorage) return null;
      try {
        return window.localStorage.getItem(key);
      } catch (error) {
        return null;
      }
    },
    set(key, value) {
      if (typeof window === "undefined" || !window.localStorage) return;
      try {
        window.localStorage.setItem(key, value);
      } catch (error) {
        /* ignore persistence errors (private mode, etc.) */
      }
    },
    remove(key) {
      if (typeof window === "undefined" || !window.localStorage) return;
      try {
        window.localStorage.removeItem(key);
      } catch (error) {
        /* ignore */
      }
    }
  };

  const refreshIcons = (options) => {
    if (window.lucide && typeof window.lucide.createIcons === "function") {
      window.lucide.createIcons(options);
    }
  };

  const onReady = (fn) => {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  };

  onReady(() => {
    const page = document.body?.dataset?.page || "";
    refreshIcons();
    updateYear();
    normalizeHeroBackgrounds();

    const nav = initNavRail();
    initMagneticButtons();
    initSpotlight();
    initDiscordAuth();

    switch (page) {
      case "home":
        initHomePage(nav);
        break;
      case "leaderboard":
        initLeaderboardPage(nav);
        break;
      case "bonuses":
        initBonusesPage();
        break;
      case "content":
        initContentPage();
        break;
      case "rewards":
        initRewardsPage();
        break;
      default:
        break;
    }
  });

  function updateYear() {
    const yearEl = document.getElementById("year");
    if (yearEl) {
      yearEl.textContent = new Date().getFullYear();
    }
  }

  function normalizeHeroBackgrounds() {
    const heroes = document.querySelectorAll(".hero-sun[data-hero-img]");
    heroes.forEach((hero) => {
      const rawPath = hero.getAttribute("data-hero-img");
      if (!rawPath) {
        return;
      }
      try {
        const resolved = new URL(rawPath, window.location.href);
        const value = `url("${resolved.href}")`;
        hero.style.setProperty("--hero-img", value);
        hero.style.backgroundImage = value;
      } catch (error) {
        const fallback = `url("${rawPath}")`;
        hero.style.setProperty("--hero-img", fallback);
        hero.style.backgroundImage = fallback;
      }
    });
  }

  async function initDiscordAuth() {
    const roots = [...document.querySelectorAll("[data-auth-root]")];
    if (!roots.length) {
      return;
    }

    const modal = ensureProfileModal();
    const config = getAppConfig();
    let currentSession = sanitizeSession(window.__APP_SESSION__);
    let currentUser = currentSession.user || null;
    let lastSessionFetch = 0;

    const login = () => {
      if (!config?.loginUrl) {
        console.warn("Discord login URL is not configured. Update config.php with valid credentials.");
        return;
      }
      const target = buildLoginUrl(config.loginUrl, window.location.href);
      if (target) {
        window.location.href = target;
      }
    };

    const logout = async () => {
      if (!config?.logoutUrl) {
        console.warn("Logout endpoint is not configured.");
        return;
      }
      modal.close();
      try {
        await fetch(config.logoutUrl, {
          method: "POST",
          credentials: "include",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json"
          }
        });
      } catch (error) {
        console.warn("Failed to logout from Discord session.", error);
      } finally {
        currentSession = { authenticated: false, user: null };
        currentUser = null;
        applyState();
        persistSession(currentSession);
        broadcastSessionChange();
      }
    };

    const openProfile = () => {
      if (!currentUser) return;
      modal.open(currentUser);
    };

    const applyState = () => {
      if (currentSession.authenticated && currentUser) {
        renderLoggedInControls(roots, currentUser, { onProfile: openProfile, onLogout: logout });
      } else {
        renderLoggedOutControls(roots, config, login);
      }
    };

    const syncFromSession = (nextSession) => {
      const normalized = sanitizeSession(nextSession);
      const changed = !sessionsEqual(currentSession, normalized);
      if (changed) {
        currentSession = normalized;
        currentUser = normalized.user || null;
        applyState();
        persistSession(normalized);
      }
      return changed;
    };

    const refreshSession = async ({ force = false, broadcast = false } = {}) => {
      if (!config?.sessionUrl) {
        return null;
      }
      const now = Date.now();
      if (!force && now - lastSessionFetch < 5000) {
        return currentSession;
      }
      lastSessionFetch = now;
      const latest = await fetchSession(config.sessionUrl);
      if (latest) {
        const changed = syncFromSession(latest);
        if (changed && broadcast) {
          broadcastSessionChange();
        }
      }
      return latest;
    };

    const cached = loadCachedSession();
    if (cached && !currentSession.authenticated && cached.authenticated) {
      currentSession = cached;
      currentUser = cached.user || null;
    }

    applyState();
    persistSession(currentSession);
    broadcastSessionChange();

    refreshSession({ force: true });

    window.addEventListener("focus", () => {
      refreshSession({ force: true });
    });

    window.setInterval(() => {
      refreshSession({ force: false });
    }, 120000);

    window.addEventListener("storage", (event) => {
      if (event.key === SESSION_CACHE_KEY && event.newValue) {
        const parsed = parseSessionCache(event.newValue);
        if (parsed) {
          syncFromSession(parsed);
        }
      } else if (event.key === SESSION_BROADCAST_KEY) {
        refreshSession({ force: true });
      } else if (modal.isOpen() && currentUser && event.key === `${SHUFFLE_KEY_PREFIX}${currentUser.id}`) {
        modal.syncShuffle(currentUser.id);
      }
    });
  }

  function getAppConfig() {
    const raw = window.__APP_CONFIG__ || {};
    if (!raw || typeof raw !== "object") {
      return null;
    }
    const config = {};
    if (raw.loginUrl) {
      config.loginUrl = String(raw.loginUrl);
    }
    if (raw.logoutUrl) {
      config.logoutUrl = String(raw.logoutUrl);
    }
    if (raw.sessionUrl) {
      config.sessionUrl = String(raw.sessionUrl);
    }
    return Object.keys(config).length ? config : null;
  }

  function sanitizeSession(session) {
    if (!session || typeof session !== "object") {
      return { authenticated: false, user: null };
    }
    const user = sanitizeUser(session.user);
    return {
      authenticated: Boolean(session.authenticated) && !!user,
      user
    };
  }

  function sanitizeUser(user) {
    if (!user || typeof user !== "object") {
      return null;
    }
    const id = user.id != null ? String(user.id) : null;
    if (!id) {
      return null;
    }
    return {
      id,
      username: user.username != null ? String(user.username) : null,
      discriminator: user.discriminator != null ? String(user.discriminator) : null,
      global_name: user.global_name != null ? String(user.global_name) : null,
      avatar: user.avatar != null ? String(user.avatar) : null,
      email: user.email != null ? String(user.email) : null,
      locale: user.locale != null ? String(user.locale) : null,
      mfa_enabled: typeof user.mfa_enabled === "boolean" ? user.mfa_enabled : Boolean(user.mfa_enabled),
      updated_at: user.updated_at != null ? String(user.updated_at) : null
    };
  }

  function sessionsEqual(a, b) {
    if (!a && !b) return true;
    if (!a || !b) return false;
    if (Boolean(a.authenticated) !== Boolean(b.authenticated)) {
      return false;
    }
    const aId = a.user?.id || null;
    const bId = b.user?.id || null;
    if (aId !== bId) {
      return false;
    }
    const aUpdated = a.user?.updated_at || null;
    const bUpdated = b.user?.updated_at || null;
    return aUpdated === bUpdated;
  }

  function buildLoginUrl(loginUrl, returnTo) {
    if (!loginUrl) {
      return "";
    }
    try {
      const target = new URL(loginUrl, window.location.origin);
      if (returnTo) {
        target.searchParams.set("return", returnTo);
      }
      return target.toString();
    } catch (error) {
      return loginUrl;
    }
  }

  async function fetchSession(url) {
    try {
      const response = await fetch(url, {
        credentials: "include",
        headers: { Accept: "application/json" },
        cache: "no-store"
      });
      if (!response.ok) {
        throw new Error(`Session request failed with status ${response.status}`);
      }
      const data = await response.json();
      return sanitizeSession(data);
    } catch (error) {
      console.warn("Failed to refresh session", error);
      return null;
    }
  }

  function persistSession(session) {
    if (!session) {
      storage.remove(SESSION_CACHE_KEY);
      return;
    }
    try {
      storage.set(SESSION_CACHE_KEY, JSON.stringify(session));
    } catch (error) {
      /* ignore */
    }
  }

  function parseSessionCache(value) {
    if (!value) {
      return null;
    }
    try {
      return sanitizeSession(JSON.parse(value));
    } catch (error) {
      return null;
    }
  }

  function loadCachedSession() {
    const raw = storage.get(SESSION_CACHE_KEY);
    if (!raw) {
      return null;
    }
    const parsed = parseSessionCache(raw);
    if (!parsed) {
      storage.remove(SESSION_CACHE_KEY);
    }
    return parsed;
  }

  function broadcastSessionChange() {
    try {
      storage.set(SESSION_BROADCAST_KEY, String(Date.now()));
    } catch (error) {
      /* ignore */
    }
  }

  function renderLoggedOutControls(roots, config, onLogin) {
    roots.forEach((root) => {
      root.innerHTML = "";
      const button = document.createElement("button");
      button.type = "button";
      button.className =
        "magnetic group relative inline-flex items-center gap-2 rounded-xl px-6 py-3 text-lg font-semibold transition-colors";
      if (config?.loginUrl) {
        button.className += " bg-discord hover:bg-discordDark";
        button.title = "Login with Discord";
        button.addEventListener("click", (event) => {
          event.preventDefault();
          onLogin();
        });
      } else {
        button.className += " bg-white/10 cursor-not-allowed";
        button.disabled = true;
        button.title = "Discord login is not configured.";
      }
      const label = document.createElement("span");
      label.textContent = "Login";
      button.appendChild(label);
      const ring = document.createElement("span");
      ring.className = "absolute -inset-px rounded-xl ring-1 ring-white/10";
      ring.setAttribute("aria-hidden", "true");
      button.appendChild(ring);
      root.appendChild(button);
    });
    initMagneticButtons();
  }

  function renderLoggedInControls(roots, user, handlers) {
    roots.forEach((root) => {
      root.innerHTML = "";
      const wrapper = document.createElement("div");
      wrapper.className = "flex items-center gap-2";

      const profileBtn = document.createElement("button");
      profileBtn.type = "button";
      profileBtn.className =
        "magnetic group relative inline-flex items-center gap-3 rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-white/10 hover:bg-white/20";
      profileBtn.addEventListener("click", (event) => {
        event.preventDefault();
        handlers.onProfile();
      });

      const avatar = document.createElement("span");
      avatar.className =
        "h-9 w-9 rounded-full overflow-hidden border border-white/15 bg-black/40 flex items-center justify-center";
      const avatarImg = document.createElement("img");
      avatarImg.src = getDiscordAvatar(user, 64);
      avatarImg.alt = `${formatUserLabel(user)}'s avatar`;
      avatarImg.className = "h-full w-full object-cover";
      avatar.appendChild(avatarImg);
      profileBtn.appendChild(avatar);

      const textWrap = document.createElement("div");
      textWrap.className = "flex flex-col text-left leading-tight";
      const title = document.createElement("span");
      title.className = "text-sm font-semibold";
      title.textContent = "Profile";
      const subtitle = document.createElement("span");
      subtitle.className = "text-xs text-white/60";
      subtitle.textContent = formatUserLabel(user);
      textWrap.appendChild(title);
      textWrap.appendChild(subtitle);
      profileBtn.appendChild(textWrap);

      const profileRing = document.createElement("span");
      profileRing.className = "absolute -inset-px rounded-xl ring-1 ring-white/10";
      profileRing.setAttribute("aria-hidden", "true");
      profileBtn.appendChild(profileRing);

      wrapper.appendChild(profileBtn);

      const logoutBtn = document.createElement("button");
      logoutBtn.type = "button";
      logoutBtn.className =
        "magnetic relative inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-red-500/90 hover:bg-red-500";
      logoutBtn.addEventListener("click", (event) => {
        event.preventDefault();
        handlers.onLogout();
      });
      const logoutLabel = document.createElement("span");
      logoutLabel.textContent = "Logout";
      logoutBtn.appendChild(logoutLabel);
      const logoutRing = document.createElement("span");
      logoutRing.className = "absolute -inset-px rounded-xl ring-1 ring-white/10";
      logoutRing.setAttribute("aria-hidden", "true");
      logoutBtn.appendChild(logoutRing);

      wrapper.appendChild(logoutBtn);
      root.appendChild(wrapper);
    });
    initMagneticButtons();
  }

  function getDiscordAvatar(user, size = 64) {
    if (!user || !user.id) {
      return "";
    }
    if (user.avatar) {
      const isAnimated = typeof user.avatar === "string" && user.avatar.startsWith("a_");
      const extension = isAnimated ? "gif" : "png";
      return `https://cdn.discordapp.com/avatars/${user.id}/${user.avatar}.${extension}?size=${size}`;
    }
    let fallbackIndex = 0;
    if (user.discriminator && user.discriminator !== "0") {
      const disc = parseInt(user.discriminator, 10);
      fallbackIndex = Number.isNaN(disc) ? 0 : disc % 5;
    } else if (user.id) {
      const lastDigit = parseInt(String(user.id).slice(-1), 10);
      fallbackIndex = Number.isNaN(lastDigit) ? 0 : lastDigit % 5;
    }
    return `https://cdn.discordapp.com/embed/avatars/${fallbackIndex}.png`;
  }

  function formatUserLabel(user) {
    if (!user) {
      return "";
    }
    if (user.global_name) {
      return user.global_name;
    }
    if (user.username) {
      if (user.discriminator && user.discriminator !== "0") {
        return `${user.username}#${user.discriminator}`;
      }
      return user.username;
    }
    return "Discord User";
  }

  function formatUserHandle(user) {
    if (!user) return "";
    if (user.username && user.discriminator && user.discriminator !== "0") {
      return `${user.username}#${user.discriminator}`;
    }
    if (user.username) {
      return `@${user.username}`;
    }
    return String(user.id || "");
  }

  function ensureProfileModal() {
    let overlay = document.getElementById("profileModalBackdrop");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.id = "profileModalBackdrop";
      overlay.className = "profile-modal-backdrop";
      overlay.setAttribute("aria-hidden", "true");
      overlay.innerHTML = `
        <div class="profile-modal glass" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
          <button type="button" class="profile-modal-close" data-profile-close aria-label="Close profile dialog">
            <span aria-hidden="true">&times;</span>
          </button>
          <div class="profile-modal-header">
            <div class="profile-modal-avatar">
              <img data-profile-avatar-img alt="" />
            </div>
            <div class="profile-modal-meta">
              <p class="profile-modal-overline">Signed in with Discord</p>
              <h2 id="profileModalTitle" data-profile-name></h2>
              <p class="profile-modal-username" data-profile-username></p>
            </div>
          </div>
          <div class="profile-modal-body">
            <div class="profile-modal-row">
              <span class="profile-modal-label">Discord ID</span>
              <span class="profile-modal-value" data-profile-id></span>
            </div>
            <a class="profile-modal-link" data-profile-link target="_blank" rel="noopener">View on Discord</a>
            <form class="profile-modal-form" data-profile-form>
              <label class="profile-modal-label" for="profileShuffleInput">Shuffle username</label>
              <div class="profile-input-group">
                <input id="profileShuffleInput" type="text" autocomplete="off" placeholder="Enter your Shuffle username" data-shuffle-input />
                <button type="submit" data-shuffle-save>Save</button>
              </div>
              <p class="profile-status" data-shuffle-status hidden></p>
            </form>
          </div>
        </div>`;
      document.body.appendChild(overlay);
    }

    const closeBtn = overlay.querySelector("[data-profile-close]");
    const avatarImg = overlay.querySelector("[data-profile-avatar-img]");
    const nameEl = overlay.querySelector("[data-profile-name]");
    const usernameEl = overlay.querySelector("[data-profile-username]");
    const idEl = overlay.querySelector("[data-profile-id]");
    const profileLink = overlay.querySelector("[data-profile-link]");
    const form = overlay.querySelector("[data-profile-form]");
    const shuffleInput = overlay.querySelector("[data-shuffle-input]");
    const statusEl = overlay.querySelector("[data-shuffle-status]");

    let activeUserId = null;
    let statusTimer = null;

    const close = () => {
      overlay.classList.remove("is-open");
      overlay.setAttribute("aria-hidden", "true");
      document.body.classList.remove("modal-open");
      if (statusTimer) {
        window.clearTimeout(statusTimer);
        statusTimer = null;
      }
      if (statusEl) {
        statusEl.hidden = true;
      }
    };

    const updateShuffleField = (userId) => {
      if (!shuffleInput) return;
      const stored = storage.get(`${SHUFFLE_KEY_PREFIX}${userId}`);
      shuffleInput.value = stored || "";
    };

    const showStatus = (message) => {
      if (!statusEl) return;
      statusEl.textContent = message;
      statusEl.hidden = false;
      statusEl.classList.remove("is-error");
      if (statusTimer) {
        window.clearTimeout(statusTimer);
      }
      statusTimer = window.setTimeout(() => {
        statusEl.hidden = true;
        statusTimer = null;
      }, 2400);
    };

    const handleSave = () => {
      if (!activeUserId || !shuffleInput) return;
      const value = shuffleInput.value.trim();
      if (value) {
        storage.set(`${SHUFFLE_KEY_PREFIX}${activeUserId}`, value);
        showStatus("Shuffle username saved.");
      } else {
        storage.remove(`${SHUFFLE_KEY_PREFIX}${activeUserId}`);
        showStatus("Shuffle username cleared.");
      }
    };

    const open = (user) => {
      if (!user) return;
      activeUserId = user.id;
      if (avatarImg) {
        const avatarUrl = getDiscordAvatar(user, 128);
        avatarImg.src = avatarUrl;
        avatarImg.alt = `${formatUserLabel(user)}'s avatar`;
      }
      if (nameEl) {
        nameEl.textContent = formatUserLabel(user) || "Discord User";
      }
      if (usernameEl) {
        usernameEl.textContent = formatUserHandle(user);
      }
      if (idEl) {
        idEl.textContent = user.id || "";
      }
      if (profileLink && user.id) {
        profileLink.href = `https://discord.com/users/${user.id}`;
      }
      updateShuffleField(activeUserId);
      if (statusEl) {
        statusEl.hidden = true;
      }
      overlay.classList.add("is-open");
      overlay.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");
      window.setTimeout(() => {
        shuffleInput?.focus({ preventScroll: true });
      }, 50);
    };

    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) {
        close();
      }
    });
    closeBtn?.addEventListener("click", close);
    form?.addEventListener("submit", (event) => {
      event.preventDefault();
      handleSave();
    });
    overlay.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && overlay.classList.contains("is-open")) {
        close();
      }
    });
    shuffleInput?.addEventListener("input", () => {
      if (statusEl) {
        statusEl.hidden = true;
      }
    });

    return {
      open,
      close,
      isOpen: () => overlay.classList.contains("is-open"),
      syncShuffle(userId) {
        if (!activeUserId || activeUserId !== userId) return;
        updateShuffleField(userId);
      }
    };
  }

  function initNavRail() {
    const railWrap = document.querySelector(".nav-rail");
    if (!railWrap) return null;
    const rail = railWrap.querySelector(".rail") || railWrap;
    const indicator = rail.querySelector(".rail-active");
    const toggleBtn = document.getElementById("railToggle");
    const navItems = [...rail.querySelectorAll(".nav-item")];

    const stored = localStorage.getItem(RAIL_KEY);
    const oldValue = localStorage.getItem(OLD_RAIL_KEY);
    const hasStoredPreference = stored !== null || oldValue === "folded";
    let collapsed = stored === "1";
    if (!collapsed && oldValue === "folded") {
      collapsed = true;
    }

    const mediaQuery = typeof window.matchMedia === "function" ? window.matchMedia("(max-width: 1023px)") : null;
    const mobileRailQuery =
      typeof window.matchMedia === "function" ? window.matchMedia("(max-width: 767px)") : null;
    if (!hasStoredPreference && mediaQuery?.matches) {
      collapsed = true;
    }

    let userInteracted = hasStoredPreference;

    const applyRailPadding = (isCollapsed) => {
      if (mobileRailQuery?.matches) {
        document.documentElement.style.setProperty("--rail-pad", "1.5rem");
      } else {
        document.documentElement.style.setProperty("--rail-pad", isCollapsed ? "4rem" : "14rem");
      }
    };

    const setCollapsed = (isCollapsed, { persist = true } = {}) => {
      railWrap.classList.toggle("collapsed", isCollapsed);
      applyRailPadding(isCollapsed);
      if (toggleBtn) {
        toggleBtn.innerHTML = isCollapsed
          ? '<i data-lucide="chevrons-right" class="w-4 h-4 text-white"></i>'
          : '<i data-lucide="chevrons-left" class="w-4 h-4 text-white"></i>';
        refreshIcons({ nameAttr: "data-lucide" });
      }
      if (persist) {
        localStorage.setItem(RAIL_KEY, isCollapsed ? "1" : "0");
      }
    };

    setCollapsed(collapsed, { persist: hasStoredPreference });
    if (oldValue) {
      localStorage.removeItem(OLD_RAIL_KEY);
    }

    if (!hasStoredPreference && mediaQuery) {
      const handleMediaChange = (event) => {
        if (!userInteracted) {
          setCollapsed(event.matches, { persist: false });
        }
      };
      if (typeof mediaQuery.addEventListener === "function") {
        mediaQuery.addEventListener("change", handleMediaChange);
      } else if (typeof mediaQuery.addListener === "function") {
        mediaQuery.addListener(handleMediaChange);
      }
    }

    if (mobileRailQuery) {
      const handleMobileLayoutChange = () => {
        applyRailPadding(railWrap.classList.contains("collapsed"));
      };
      if (typeof mobileRailQuery.addEventListener === "function") {
        mobileRailQuery.addEventListener("change", handleMobileLayoutChange);
      } else if (typeof mobileRailQuery.addListener === "function") {
        mobileRailQuery.addListener(handleMobileLayoutChange);
      }
    }

    if (toggleBtn) {
      toggleBtn.addEventListener("click", () => {
        const nextState = !railWrap.classList.contains("collapsed");
        userInteracted = true;
        setCollapsed(nextState);
      });
    }

    const moveIndicator = (link) => {
      if (!indicator || !link) return;
      const railRect = rail.getBoundingClientRect();
      const linkRect = link.getBoundingClientRect();
      const top = linkRect.top - railRect.top + (linkRect.height - indicator.offsetHeight) / 2;
      indicator.style.top = `${Math.max(8, top)}px`;
      indicator.style.height = `${Math.max(28, Math.min(44, linkRect.height - 8))}px`;
      const accent = getComputedStyle(link).getPropertyValue("--accent").trim();
      indicator.style.background = `linear-gradient(180deg, ${accent || "#3b82f6"}, #ec4899)`;
      indicator.style.boxShadow = `0 0 18px ${accent || "rgba(59,130,246,.6)"}`;
    };

    const setActive = (link) => {
      navItems.forEach((item) => item.classList.remove("is-active"));
      if (link) {
        link.classList.add("is-active");
        moveIndicator(link);
      }
    };

    const initialActive = rail.querySelector(".nav-item.is-active") || navItems.find((item) => item.hasAttribute("href"));
    if (initialActive) {
      moveIndicator(initialActive);
    }

    navItems.forEach((item) => item.addEventListener("click", () => setActive(item)));

    return { railWrap, rail, indicator, navItems, setCollapsed, setActive, moveIndicator };
  }

  function initMagneticButtons() {
    document.querySelectorAll(".magnetic").forEach((btn) => {
      if (btn.dataset.magneticInit === "1") {
        return;
      }
      btn.dataset.magneticInit = "1";
      let rect;
      btn.addEventListener("pointermove", (e) => {
        rect = rect || btn.getBoundingClientRect();
        const x = (e.clientX - rect.left - rect.width / 2) / (rect.width / 2);
        const y = (e.clientY - rect.top - rect.height / 2) / (rect.height / 2);
        btn.style.transform = `translate(${x * 6}px, ${y * 6}px)`;
      });
      btn.addEventListener("pointerleave", () => {
        rect = undefined;
        btn.style.transform = "translate(0,0)";
      });
    });
  }

  function initSpotlight() {
    const cvs = document.getElementById("spotlight");
    if (!cvs) return;
    const ctx = cvs.getContext("2d");
    if (!ctx) return;
    const DPR = Math.max(1, window.devicePixelRatio || 1);

    const resize = () => {
      cvs.width = window.innerWidth * DPR;
      cvs.height = window.innerHeight * DPR;
      cvs.style.width = `${window.innerWidth}px`;
      cvs.style.height = `${window.innerHeight}px`;
    };

    resize();
    window.addEventListener("resize", resize);

    let mx = window.innerWidth / 2;
    let my = window.innerHeight / 2;
    window.addEventListener("pointermove", (e) => {
      mx = e.clientX;
      my = e.clientY;
    });

    const frame = () => {
      ctx.clearRect(0, 0, cvs.width, cvs.height);
      const r = Math.min(window.innerWidth, window.innerHeight) * 0.25;
      const g = ctx.createRadialGradient(mx * DPR, my * DPR, 0, mx * DPR, my * DPR, r * DPR);
      g.addColorStop(0, "rgba(59,130,246,0.08)");
      g.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, cvs.width, cvs.height);
      requestAnimationFrame(frame);
    };

    requestAnimationFrame(frame);
  }

  function initHomePage(nav) {
    if (nav?.rail) {
      const links = [...nav.rail.querySelectorAll('.nav-item[href^="#"]')];
      const linkTargets = links.map((link) => {
        const href = link.getAttribute("href");
        const id = href && href.startsWith("#") ? href.slice(1) : null;
        const el = id ? document.getElementById(id) : null;
        return { link, el };
      });

      if (linkTargets.length) {
        const observer = new IntersectionObserver((entries) => {
          let best = null;
          let bestRatio = 0;
          entries.forEach((entry) => {
            const target = linkTargets.find((lt) => lt.el === entry.target);
            if (target && entry.intersectionRatio > bestRatio) {
              best = target;
              bestRatio = entry.intersectionRatio;
            }
          });
          if (best) {
            nav.setActive(best.link);
          }
        }, { threshold: [0.25, 0.5, 0.75] });

        linkTargets.forEach(({ el }) => el && observer.observe(el));
        links.forEach((link) => link.addEventListener("click", () => nav.setActive(link)));
      }

      const homeLink = nav.rail.querySelector('.nav-item[href="#home"]');
      if (homeLink) {
        nav.setActive(homeLink);
      }
    }

    document.querySelectorAll(".tilt:not(.vcard)").forEach((group) => {
      group.addEventListener("pointermove", (e) => {
        const rect = group.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width - 0.5;
        const y = (e.clientY - rect.top) / rect.height - 0.5;
        const rx = y * -10;
        const ry = x * 10;
        group.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`;
        [...group.children].forEach((child, index) => {
          child.style.transform = `translateZ(${30 + index * 8}px) translate(${x * 6}px, ${y * -6}px)`;
        });
      });

      group.addEventListener("pointerleave", () => {
        group.style.transform = "rotateX(0) rotateY(0)";
        [...group.children].forEach((child) => {
          child.style.transform = "translateZ(0)";
        });
      });
    });
  }

  function initLeaderboardPage() {
    const CURRENCY = "USD";
    const TIMEZONE = "America/New_York";
    const formatter = new Intl.NumberFormat(undefined, { style: "currency", currency: CURRENCY, maximumFractionDigits: 0 });
    const nf = new Intl.NumberFormat(undefined);
    const q = (sel) => document.querySelector(sel);
    const formatMoney = (value) => formatter.format(value);

    const getZonedNow = (tz = TIMEZONE) => {
      const now = new Date();
      const tzNow = new Date(now.toLocaleString("en-US", { timeZone: tz }));
      const diff = now.getTime() - tzNow.getTime();
      return new Date(now.getTime() + diff);
    };

    // NEW: nextMonthlyReset -> first day of next month 00:00 in TIMEZONE
const nextMonthlyReset = () => {
  const d = getZonedNow(TIMEZONE);      // "wall clock" in ET
  const target = new Date(d);
  // jump to first of next month, 00:00:00.000
  target.setMonth(d.getMonth() + 1, 1);
  target.setHours(0, 0, 0, 0);
  return target;
};


const startCountdown = () => {
  const target = nextMonthlyReset();
  const ids = { d: q("#d"), h: q("#h"), m: q("#m"), s: q("#s") };

  // derive "EST"/"EDT" dynamically
  const tzShort = new Intl.DateTimeFormat("en-US", {
    timeZone: TIMEZONE,
    timeZoneName: "short"
  }).formatToParts(new Date()).find(p => p.type === "timeZoneName")?.value || "ET";

  const tick = () => {
    const now = getZonedNow(TIMEZONE);
    let diff = Math.max(0, target - now);
    const sec = Math.floor(diff / 1000) % 60;
    const min = Math.floor(diff / 60000) % 60;
    const hr  = Math.floor(diff / 3600000) % 24;
    const day = Math.floor(diff / 86400000);
    if (ids.d) ids.d.textContent = day;
    if (ids.h) ids.h.textContent = String(hr).padStart(2, "0");
    if (ids.m) ids.m.textContent = String(min).padStart(2, "0");
    if (ids.s) ids.s.textContent = String(sec).padStart(2, "0");
  };

  tick();
  setInterval(tick, 1000);

  const sub = q("#countdown-sub");
  if (sub) sub.textContent = `Resets Monthly ( ${tzShort} )`;
};


    const DATA_SOURCE = "/api/leaderboard.php";
    const MOCK = {
      prizes: { 1: 1000, 2: 500, 3: 250 },
      entries: Array.from({ length: 20 }).map((_, i) => ({
        rank: i + 1,
        username: [
          "AceHunter",
          "LuckyLuna",
          "SpinWizard",
          "CryptoCobra",
          "NeonNova",
          "RNGod",
          "FeverSpin",
          "StackShark",
          "HighRollHer",
          "TiltProof",
          "Jackspot",
          "MegaMidas",
          "StreakSeek",
          "VioletVibes",
          "DiceDyno",
          "BetBender",
          "Slotsy",
          "VaultViper",
          "GridGale",
          "PinkPhantom"
        ][i],
        total_wagered: Math.round((20 - i) * 2500 + Math.random() * 5000),
        prize: i < 3 ? [1000, 500, 250][i] : Math.max(0, Math.round(100 - i * 2))
      })),
      stats: { total_players: 5123, total_wagered: 3456789, highest_wager: 98765 }
    };

    const dataCache = new Map();
    const loadData = async (range) => {
      if (window.LEADERBOARD_DATA) {
        if (range && window.LEADERBOARD_DATA[range]) {
          return window.LEADERBOARD_DATA[range];
        }
        return window.LEADERBOARD_DATA;
      }

      const cacheKey = range ?? "default";
      if (dataCache.has(cacheKey)) {
        return dataCache.get(cacheKey);
      }

      const url = range ? `${DATA_SOURCE}?type=${encodeURIComponent(range)}` : DATA_SOURCE;

      try {
        const res = await fetch(url, { headers: { Accept: "application/json" } });
        if (res.ok) {
          const json = await res.json();
          dataCache.set(cacheKey, json);
          return json;
        }
      } catch (err) {
        // ignore network errors and fall back to mock data
      }

      return MOCK;
    };

    const setPodium = (top3) => {
      const [a, b, c] = top3;
      const setText = (selector, value) => {
        const el = q(selector);
        if (el) el.textContent = value;
      };

      setText("#p1-name", a?.username ?? "—");
      setText("#p1-wager", formatMoney(a?.total_wagered || 0));
      setText("#p1-prize", formatMoney(a?.prize || 0));

      setText("#p2-name", b?.username ?? "—");
      setText("#p2-wager", formatMoney(b?.total_wagered || 0));
      setText("#p2-prize", formatMoney(b?.prize || 0));

      setText("#p3-name", c?.username ?? "—");
      setText("#p3-wager", formatMoney(c?.total_wagered || 0));
      setText("#p3-prize", formatMoney(c?.prize || 0));

      q("#p1-prize-chip")?.style.setProperty("--tone", "#EFBF04");
      q("#p2-prize-chip")?.style.setProperty("--tone", "#C0C0C0");
      q("#p3-prize-chip")?.style.setProperty("--tone", "#CD7F32");
    };

    const renderRows = (entries) => {
      const wrap = q("#rows");
      if (!wrap) return;
      wrap.innerHTML = "";

      const header = document.createElement("div");
      header.className =
        "grid grid-cols-[auto,1fr] sm:grid-cols-[40px,1fr,1fr,120px] px-2 sm:px-4 text-xs uppercase tracking-wide text-white/50";
      header.innerHTML = `
        <span class="hidden sm:block">Rank</span>
        <span class="hidden sm:block">Player</span>
        <span class="col-span-2 text-right sm:col-span-1 sm:text-left">Wager</span>
        <span class="col-span-2 text-right sm:col-span-1">Prize</span>
      `;
      wrap.appendChild(header);

      entries.slice(3, 20).forEach((entry) => {
        const row = document.createElement("div");
        row.className =
          "glass rounded-2xl p-4 grid grid-cols-[auto,1fr] sm:grid-cols-[40px,1fr,1fr,120px] gap-x-3 gap-y-2 items-start sm:items-center";
        row.innerHTML = `
          <div class="text-white/70 font-semibold">#${entry.rank}</div>
          <div class="flex items-center gap-3">
            <div class="grid h-8 w-8 place-items-center rounded-xl bg-white/10"><i data-lucide="user" class="h-4 w-4"></i></div>
            <div class="truncate">${entry.username}</div>
          </div>
          <div class="col-span-2 text-right text-white/80 sm:col-span-1 sm:text-left">${formatMoney(entry.total_wagered)}</div>
          <div class="col-span-2 flex items-center justify-end gap-2 text-right sm:col-span-1 sm:justify-end"><span class="gift" aria-hidden="true">
  <svg viewBox="0 0 24 24" class="gift-svg">
    <defs>
      <linearGradient id="gift-grad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0"  stop-color="var(--gift-a)"/>
        <stop offset="1"  stop-color="var(--gift-b)"/>
      </linearGradient>
    </defs>
    <rect class="fill" x="3.5" y="8" width="17" height="4" rx="1.2" stroke="var(--gift-stroke)"/>
    <rect class="fill" x="4.5" y="12" width="15" height="8.5" rx="1.6" stroke="var(--gift-stroke)"/>
    <rect x="11" y="8" width="2" height="12.5" rx="1" fill="white" opacity=".9" stroke="var(--gift-stroke)"/>
    <path d="M12 8 c-1.6-3 -4.2-3.6 -5.2-2.1 c-.9 1.4 .6 2.8 2.9 3.4 M12 8 c1.6-3 4.2-3.6 5.2-2.1 c.9 1.4 -.6 2.8 -2.9 3.4" fill="none" stroke="var(--gift-stroke)"/>
    <circle cx="12" cy="9" r="1.2" class="fill" stroke="var(--gift-stroke)"/>
  </svg>
</span><span>${formatMoney(entry.prize || 0)}</span></div>
        `;
        wrap.appendChild(row);
      });
      refreshIcons({ attrs: { "stroke-width": 1.8 } });
    };

    const renderStats = (stats) => {
      const setText = (selector, value) => {
        const el = q(selector);
        if (el) el.textContent = value;
      };
      setText("#stat-players", nf.format(stats.total_players || 0));
      setText("#stat-wagered", formatMoney(stats.total_wagered || 0));
      setText("#stat-highest", formatMoney(stats.highest_wager || 0));
    };

    const setRangeActive = (range) => {
      document.querySelectorAll(".range-btn").forEach((btn) => {
        if (btn.dataset.range === range) {
          btn.classList.add("bg-white/10");
        } else {
          btn.classList.remove("bg-white/10");
        }
      });
    };

    const hydrate = async (range = "weekly") => {
      setRangeActive(range);
      const data = await loadData(range);
      const dataset = data && Array.isArray(data.entries) ? data : data?.[range];
      const source = dataset && Array.isArray(dataset.entries) && dataset.entries.length ? dataset : MOCK;
      const entries = [...source.entries].sort((a, b) => (b.total_wagered || 0) - (a.total_wagered || 0)).slice(0, 20);
      const top3 = [entries[0], entries[1], entries[2]];
      setPodium(top3);
      renderRows(entries);
      const stats = dataset?.stats || source.stats || {};
      renderStats(stats);
    };

    startCountdown();
    hydrate("weekly");
    document.querySelectorAll(".range-btn").forEach((btn) => {
      btn.addEventListener("click", () => hydrate(btn.dataset.range));
    });
  }

  function initBonusesPage() {
    const copyBonusCode = () => {
      const text = document.getElementById("bonusCodeText")?.textContent?.trim();
      if (!text) return;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          const pill = document.getElementById("bonusCode");
          if (!pill) return;
          pill.classList.add("ring-2", "ring-blue-brand");
          setTimeout(() => pill.classList.remove("ring-2", "ring-blue-brand"), 600);
        }).catch(() => {});
      }
    };

    const claimBonus = () => {
      window.open("https://shuffle.com/?r=TX", "_blank");
    };

    window.copyBonusCode = copyBonusCode;
    window.claimBonus = claimBonus;
  }

  function initContentPage() {
    const markLoaded = (img) => {
      img.classList.add("loaded");
      const shimmer = img.parentElement?.querySelector(".vshimmer");
      if (shimmer) shimmer.remove();
    };

    document.querySelectorAll(".vthumb").forEach((img) => {
      if (img.complete) {
        markLoaded(img);
      } else {
        img.addEventListener("load", () => markLoaded(img));
        img.addEventListener("error", () => markLoaded(img));
      }
    });

    document.querySelectorAll(".tilt.vcard").forEach((card) => {
      let rect;
      card.addEventListener("pointerenter", () => {
        rect = card.getBoundingClientRect();
        card.style.setProperty("--tz", "18px");
      });
      card.addEventListener("pointerleave", () => {
        card.style.setProperty("--rx", "0deg");
        card.style.setProperty("--ry", "0deg");
        card.style.setProperty("--tz", "0px");
        rect = undefined;
      });
      card.addEventListener("pointermove", (e) => {
        if (!rect) rect = card.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width;
        const y = (e.clientY - rect.top) / rect.height;
        const rx = ((0.5 - y) * 10).toFixed(2);
        const ry = ((x - 0.5) * 12).toFixed(2);
        card.style.setProperty("--rx", `${rx}deg`);
        card.style.setProperty("--ry", `${ry}deg`);
      });
    });

    let ang = 0;
    setInterval(() => {
      ang = (ang + 2) % 360;
      document.querySelectorAll(".vcard").forEach((card) => card.style.setProperty("--ang", `${ang}deg`));
    }, 60);
  }

  function initRewardsPage() {
    const REWARDS = [
      { level: 1, requirement: 5000, image: "../../assets/images/bronze.svg", name: "Bronze Rank", amount: "$15" },
      { level: 2, requirement: 50000, image: "../../assets/images/silver.svg", name: "Silver Rank", amount: "$50" },
      { level: 3, requirement: 300000, image: "../../assets/images/gold.svg", name: "Gold Rank", amount: "$150" },
      { level: 4, requirement: 1050000, image: "../../assets/images/platinum.svg", name: "Platinum Rank", amount: "$830" },
      { level: 5, requirement: 1800000, image: "../../assets/images/jade.svg", name: "Jade Rank", amount: "$900" },
      { level: 6, requirement: 4300000, image: "../../assets/images/sapphire.svg", name: "Sapphire Rank", amount: "$2500" }
    ];

    let userWagerAmount = 0;
    let isLoggedIn = false;
    let username = "Guest";

    const loadRewardsData = async () => {
      try {
        const res = await fetch("get_rewards_data.php");
        const data = await res.json();
        userWagerAmount = parseFloat(data.wager_amount) || 0;
        isLoggedIn = Boolean(data.is_logged_in);
        username = data.username || "Guest";
        renderRewards();
        document.getElementById("loading")?.classList.add("hidden");
        if (data.error && !isLoggedIn) {
          document.getElementById("error-message")?.classList.remove("hidden");
        }
      } catch (err) {
        console.error("Error loading rewards data:", err);
        userWagerAmount = 0;
        isLoggedIn = false;
        username = "Guest";
        renderRewards();
        document.getElementById("loading")?.classList.add("hidden");
        document.getElementById("error-message")?.classList.remove("hidden");
      }
    };

    const getRewardStatus = (requirement) => (userWagerAmount >= requirement ? "claimed" : "progress");
    const fmt = (num) => new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(num);

    const renderRewards = () => {
      const grid = document.getElementById("rewards-grid");
      if (!grid) return;
      grid.innerHTML = "";
      REWARDS.forEach((reward) => {
        const status = getRewardStatus(reward.requirement);
        const progress = Math.min(100, (userWagerAmount / reward.requirement) * 100);
        const remaining = Math.max(0, reward.requirement - userWagerAmount);
        const card = document.createElement("article");
        card.className = "glass rcard";
        card.innerHTML = `
          <div class="rcard-content p-5">
            <div class="flex items-start justify-between gap-4">
              <span class="rank-badge">
                <i data-lucide="shield" class="w-4 h-4"></i>
                <strong>Rank ${reward.level}</strong>
              </span>
              <span class="status-chip ${status === "claimed" ? "status-claimed" : "status-progress"}">
                <i data-lucide="${status === "claimed" ? "check-circle-2" : "chevrons-up"}" class="w-4 h-4"></i>
                ${status === "claimed" ? "Achieved" : "In Progress"}
              </span>
            </div>
            <div class="mt-4 flex items-center gap-4">
              <div class="w-20 h-20 rounded-xl overflow-hidden bg-black/30 border border-white/10 flex items-center justify-center">
                <img src="${reward.image}" alt="${reward.name}" class="w-full h-full object-contain">
              </div>
              <div class="min-w-0">
                <h3 class="text-xl font-extrabold leading-tight">${reward.name}</h3>
                <div class="mt-1 muted small flex items-center gap-2">
                  <i data-lucide="target" class="w-4 h-4"></i>
                  Requirement: <strong class="text-white/90">${fmt(reward.requirement)}</strong>
                </div>
              </div>
            </div>
            <div class="mt-5">
              <div class="pbar" aria-label="Progress to ${reward.name}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress.toFixed(1)}">
                <div class="pbar-fill" style="width:${progress}%;"></div>
                <div class="pbar-tip">${progress.toFixed(1)}%</div>
              </div>
              <div class="mt-2 muted small flex items-center gap-2">
                <i data-lucide="wallet" class="w-4 h-4"></i>
                ${status === "claimed" ? "Requirement met." : `${fmt(remaining)} remaining`}
              </div>
            </div>
            <div class="mt-5 flex items-center justify-between">
              <div class="amount">${reward.amount}</div>
              ${status === "claimed"
                ? `<button class="btn-primary" disabled aria-disabled="true" title="Already achieved"><i data-lucide="badge-check"></i><span>Achieved</span></button>`
                : `<button class="btn-primary" data-level="${reward.level}"><i data-lucide="info"></i><span>More Info</span></button>`
              }
            </div>
          </div>
        `;
        grid.appendChild(card);
      });
      refreshIcons({ nameAttr: "data-lucide" });
      grid.querySelectorAll("button[data-level]").forEach((btn) => {
        btn.addEventListener("click", () => openMoreInfo(parseInt(btn.dataset.level, 10)));
      });
    };

    const openMoreInfo = (level) => {
      window.open("https://discord.gg/txplays", "_blank");
    };

    window.openMoreInfo = openMoreInfo;

    loadRewardsData();

    let ang = 0;
    setInterval(() => {
      ang = (ang + 2) % 360;
      document.querySelectorAll(".rcard").forEach((card) => card.style.setProperty("--ang", `${ang}deg`));
    }, 60);
  }
})();
