<script lang="ts">
  import { onMount } from "svelte";
  import { currentPath, link, matchPath, navigate } from "./lib/router";
  import { session } from "./lib/stores/session";
  import { fetchSession } from "./lib/api/auth";
  import { language, availableLanguages, theme, effectiveTheme } from "./lib/stores/settings";
  import { fetchSettings } from "./lib/api/settings";
  import { t } from "./lib/i18n";
  import ContinueWatching from "./routes/ContinueWatching.svelte";
  import Watchlist from "./routes/Watchlist.svelte";
  import Search from "./routes/Search.svelte";
  import Library from "./routes/Library.svelte";
  import ShowDetail from "./routes/ShowDetail.svelte";
  import MovieDetail from "./routes/MovieDetail.svelte";
  import Settings from "./routes/Settings.svelte";
  import Login from "./routes/Login.svelte";
  import Toast from "./lib/components/Toast.svelte";
  import ScrollToTop from "./lib/components/ScrollToTop.svelte";

  const navItems = [
    { href: "/", key: "nav.continueWatching", icon: "play" },
    { href: "/watchlist", key: "nav.watchlist", icon: "bookmark" },
    { href: "/library", key: "nav.library", icon: "grid" },
    { href: "/search", key: "nav.search", icon: "search" },
    { href: "/settings", key: "nav.settings", icon: "sliders" },
  ];

  let settingsLoaded = false;

  onMount(() => {
    fetchSession();
  });

  $effect(() => {
    if (!$session.checked) return;
    if (!$session.authenticated && $currentPath !== "/login") {
      navigate("/login", true);
    } else if ($session.authenticated && $currentPath === "/login") {
      navigate("/", true);
    }

    if ($session.authenticated && !settingsLoaded) {
      settingsLoaded = true;
      fetchSettings().then((s) => {
        language.set(s.language);
        availableLanguages.set(s.availableLanguages);
        theme.set(s.theme);
      });
    }
  });

  $effect(() => {
    document.documentElement.lang = $language;
  });

  $effect(() => {
    if ($theme) {
      document.documentElement.dataset.theme = $theme;
    } else {
      delete document.documentElement.dataset.theme;
    }
  });

  let showId = $derived(matchPath("/show/:id", $currentPath));
  let movieId = $derived(matchPath("/movie/:id", $currentPath));
</script>

{#if !$session.checked}
  <div class="container row center full-page">
    <p class="text-muted">{$t("common.pageLoading")}</p>
  </div>
{:else}
  {#if $currentPath !== "/login"}
    <nav class="row space-between container">
      <a href="/" use:link class="logo-link">
        <img
          class="logo"
          src="{import.meta.env.BASE_URL}{$effectiveTheme === 'dark' ? 'logo-dark.svg' : 'logo.svg'}"
          alt="TraktOr"
        />
      </a>
      <div class="row gap-m nav-links">
        {#each navItems as item}
          <a href={item.href} use:link class:active={$currentPath === item.href} aria-label={$t(item.key)}>
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              {#if item.icon === "play"}
                <circle cx="12" cy="12" r="9" />
                <path d="M10 8.5l5 3.5-5 3.5z" fill="currentColor" stroke="none" />
              {:else if item.icon === "bookmark"}
                <path d="M7 4.5A1.5 1.5 0 0 1 8.5 3h7A1.5 1.5 0 0 1 17 4.5V20l-5-3.5L7 20V4.5Z" />
              {:else if item.icon === "grid"}
                <rect x="3.5" y="3.5" width="7" height="7" rx="1" />
                <rect x="13.5" y="3.5" width="7" height="7" rx="1" />
                <rect x="3.5" y="13.5" width="7" height="7" rx="1" />
                <rect x="13.5" y="13.5" width="7" height="7" rx="1" />
              {:else if item.icon === "search"}
                <circle cx="11" cy="11" r="7" />
                <line x1="16.5" y1="16.5" x2="21" y2="21" />
              {:else}
                <line x1="4" y1="7" x2="20" y2="7" />
                <circle cx="9" cy="7" r="2" fill="var(--bg)" />
                <line x1="4" y1="17" x2="20" y2="17" />
                <circle cx="15" cy="17" r="2" fill="var(--bg)" />
              {/if}
            </svg>
            <span class="nav-label">{$t(item.key)}</span>
          </a>
        {/each}
      </div>
    </nav>
  {/if}

  <main>
    {#if $currentPath === "/"}
      <ContinueWatching />
    {:else if $currentPath === "/watchlist"}
      <Watchlist />
    {:else if $currentPath === "/library"}
      <Library />
    {:else if $currentPath === "/search"}
      <Search />
    {:else if showId}
      <ShowDetail id={showId.id} />
    {:else if movieId}
      <MovieDetail id={movieId.id} />
    {:else if $currentPath === "/settings"}
      <Settings />
    {:else if $currentPath === "/login"}
      <Login />
    {:else}
      <ContinueWatching />
    {/if}
  </main>
{/if}

<Toast />
<ScrollToTop />

<style>
  .full-page {
    min-height: 100svh;
  }

  nav {
    padding-block: var(--space-m);
    border-bottom: 1px solid var(--border);
  }

  .logo-link {
    display: flex;
    border-radius: var(--radius-s);
  }

  .logo {
    height: 64px;
    width: auto;
    display: block;
  }

  nav a {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    min-height: 44px;
    padding: var(--space-xs);
    border-radius: var(--radius-s);
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.95rem;
  }

  nav a.active {
    color: var(--text);
    font-weight: 500;
  }

  .nav-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
  }

  @media (max-width: 640px) {
    nav {
      padding-block: var(--space-s);
    }

    .logo {
      height: 48px;
    }

    .nav-links {
      gap: var(--space-s);
    }

    .nav-label {
      display: none;
    }

    nav a {
      padding: var(--space-s);
    }
  }
</style>
