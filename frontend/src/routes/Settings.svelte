<script lang="ts">
  import { navigate } from "../lib/router";
  import { session } from "../lib/stores/session";
  import { logout } from "../lib/api/auth";
  import { triggerFullSync, type SyncResult } from "../lib/api/sync";
  import { apiErrorMessage } from "../lib/api/errors";
  import { language, availableLanguages, effectiveTheme } from "../lib/stores/settings";
  import { updateLanguage } from "../lib/api/settings";
  import { t } from "../lib/i18n";
  import ThemeSwitch from "../lib/components/ThemeSwitch.svelte";

  const justConnected = new URLSearchParams(window.location.search).has("connected");

  let syncing = $state(false);
  let syncResult = $state<SyncResult | null>(null);
  let syncError = $state("");

  let languageSaving = $state(false);
  let languageError = $state("");

  async function handleLogout() {
    await logout();
    navigate("/login", true);
  }

  async function handleLanguageChange(newLanguage: string) {
    const previous = $language;
    language.set(newLanguage);
    languageSaving = true;
    languageError = "";
    try {
      await updateLanguage(newLanguage);
      // Language is set, but translations for it may still be missing from the cache --
      // sync now runs decoupled in the background (no longer inline in the settings request,
      // see tasks/improvements.md item 4), visible via the sync section below.
      if ($session.traktConnected) {
        runSync();
      }
    } catch (e) {
      language.set(previous);
      languageError = apiErrorMessage(e, "settings.languageError", $t);
    } finally {
      languageSaving = false;
    }
  }

  async function runSync() {
    syncing = true;
    syncError = "";
    syncResult = null;
    try {
      syncResult = await triggerFullSync();
    } catch (e) {
      syncError = apiErrorMessage(e, "settings.syncError", $t);
    } finally {
      syncing = false;
    }
  }
</script>

<div class="container stack gap-l page">
  <h1>{$t("nav.settings")}</h1>

  {#if justConnected}
    <div class="card card-highlight">
      {$t("settings.justConnected")}
    </div>
  {/if}

  <div class="settings-grid">
    <section class="card stack gap-s">
      <h2>{$t("settings.traktConnection")}</h2>
      <p class="text-muted text-sm">{$t("settings.traktConnectionHint")}</p>
      {#if $session.traktConnected}
        <p class="row gap-s"><span class="badge">{$t("settings.connected")}</span></p>
      {:else}
        <p class="text-muted">{$t("settings.notConnected")}</p>
        <a class="btn btn-primary align-start" href="{import.meta.env.BASE_URL}api/auth/trakt/start">
          {$t("settings.connectButton")}
        </a>
      {/if}
    </section>

    <section class="card stack gap-s">
      <h2>{$t("settings.language")}</h2>
      <p class="text-muted text-sm">
        {$t("settings.languageHint")}
      </p>
      <select
        class="input align-start"
        value={$language}
        disabled={languageSaving}
        onchange={(e) => handleLanguageChange(e.currentTarget.value)}
      >
        {#each $availableLanguages as option}
          <option value={option.code}>{option.label}</option>
        {/each}
      </select>
      {#if languageSaving}
        <p class="text-muted">{$t("settings.languageSaving")}</p>
      {/if}
      {#if languageError}
        <p class="text-danger">{languageError}</p>
      {/if}
    </section>

    <section class="card stack gap-s">
      <h2>{$t("settings.appearance")}</h2>
      <p class="text-muted text-sm">{$t("settings.appearanceHint")}</p>
      <div class="row gap-s">
        <ThemeSwitch />
        <span class="text-muted text-sm">
          {$effectiveTheme === "dark" ? $t("settings.themeDark") : $t("settings.themeLight")}
        </span>
      </div>
    </section>

    {#if $session.traktConnected}
      <section class="card stack gap-s">
        <h2>{$t("settings.sync")}</h2>
        <p class="text-muted text-sm">{$t("settings.syncHint")}</p>
        <button class="btn btn-primary align-start" onclick={runSync} disabled={syncing}>
          {syncing ? $t("common.actionPending") : $t("settings.syncNow")}
        </button>
        {#if syncResult}
          <p class="text-muted">
            {$t("settings.syncResult", {
              shows: syncResult.shows,
              movies: syncResult.movies,
              ratings: syncResult.ratings,
              lists: syncResult.lists,
            })}
          </p>
          {#if syncResult.showsSkipped > 0}
            <p class="text-danger">
              {$t("settings.syncSkippedWarning", { n: syncResult.showsSkipped })}
            </p>
          {/if}
        {/if}
        {#if syncError}
          <p class="text-danger">{syncError}</p>
        {/if}
      </section>
    {/if}

    <section class="card stack gap-s">
      <h2>{$t("settings.session")}</h2>
      <p class="text-muted text-sm">{$t("settings.sessionHint")}</p>
      <button class="btn btn-secondary align-start" onclick={handleLogout}>
        {$t("settings.logout")}
      </button>
    </section>
  </div>

  <p class="text-muted text-sm settings-version">TraktOr v{__APP_VERSION__}</p>
</div>

<style>
  .settings-version {
    text-align: center;
  }

  .card-highlight {
    border-color: var(--primary);
  }

  .settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-l);
  }

  @media (max-width: 640px) {
    .settings-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
