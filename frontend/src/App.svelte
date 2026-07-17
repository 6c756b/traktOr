<script lang="ts">
  import { onMount } from "svelte";
  import { currentPath, link, matchPath, navigate } from "./lib/router";
  import { session } from "./lib/stores/session";
  import { fetchSession } from "./lib/api/auth";
  import { language, availableLanguages, theme, effectiveTheme } from "./lib/stores/settings";
  import { fetchSettings } from "./lib/api/settings";
  import { t } from "./lib/i18n";
  import ContinueWatching from "./routes/ContinueWatching.svelte";
  import Library from "./routes/Library.svelte";
  import ShowDetail from "./routes/ShowDetail.svelte";
  import MovieDetail from "./routes/MovieDetail.svelte";
  import Settings from "./routes/Settings.svelte";
  import Login from "./routes/Login.svelte";
  import Toast from "./lib/components/Toast.svelte";

  const navItems = [
    { href: "/", key: "nav.continueWatching" },
    { href: "/library", key: "nav.library" },
    { href: "/settings", key: "nav.settings" },
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
          <a href={item.href} use:link class:active={$currentPath === item.href}>{$t(item.key)}</a>
        {/each}
      </div>
    </nav>
  {/if}

  <main>
    {#if $currentPath === "/"}
      <ContinueWatching />
    {:else if $currentPath === "/library"}
      <Library />
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
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.95rem;
  }

  nav a.active {
    color: var(--text);
    font-weight: 500;
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

    nav a {
      font-size: 0.9rem;
    }
  }
</style>
