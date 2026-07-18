<script lang="ts">
  import { fetchShowDetail, hideShow, unhideShow, type ShowListItem } from "../lib/api/library";
  import { removeFromWatchlist, addToWatchlist } from "../lib/api/watchlist";
  import { apiErrorMessage } from "../lib/api/errors";
  import { formatAirDate, formatRelativeTime } from "../lib/utils/time";
  import { translateGenre } from "../lib/utils/genres";
  import { translateStatus } from "../lib/utils/status";
  import RatingWidget from "../lib/components/RatingWidget.svelte";
  import EpisodeList from "../lib/components/EpisodeList.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import ConfirmDialog from "../lib/components/ConfirmDialog.svelte";
  import { language } from "../lib/stores/settings";
  import { toasts } from "../lib/stores/toast";
  import { t } from "../lib/i18n";

  let { id }: { id: string } = $props();

  let show = $state<ShowListItem | null>(null);
  let error = $state("");
  let hiddenPending = $state(false);
  let menuOpen = $state(false);
  let menuRef: HTMLDivElement | undefined = $state();
  let removeConfirmOpen = $state(false);

  async function load(showId: string) {
    show = null;
    error = "";
    try {
      show = await fetchShowDetail(Number(showId));
    } catch (e) {
      error = apiErrorMessage(e, "common.loadError", $t);
    }
  }

  /** Re-fetches the show in place (no `show = null` reset) after an episode/season watch
   *  mutation, so the progress summary card reflects it without flashing the whole page back
   *  to a loading state and collapsing the episode list's expanded/pending state. */
  async function refreshProgress() {
    try {
      show = await fetchShowDetail(Number(id));
    } catch {
      // The episode list itself already reported success/failure for the mutation --
      // a failed background refresh just leaves the progress card slightly stale until
      // the next navigation, not worth surfacing a second, unrelated error for.
    }
  }

  /** Toggles whether the show is hidden from Trakt's watch-progress calculation (i.e.
   *  Continue Watching / up-next) -- watch history and the library stay untouched either
   *  way. Updates show.progress.hidden in place instead of a full reload, matching
   *  refreshProgress()'s lightweight-update approach. */
  async function handleToggleHidden() {
    if (!show?.progress) return;
    const wasHidden = show.progress.hidden;
    hiddenPending = true;
    try {
      if (wasHidden) {
        await unhideShow(show.id);
      } else {
        await hideShow(show.id);
      }
      show.progress.hidden = !wasHidden;
      toasts.push($t(wasHidden ? "detail.resumeSuccess" : "detail.cancelSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, wasHidden ? "detail.unhideError" : "detail.hideError", $t), "error");
    } finally {
      hiddenPending = false;
    }
  }

  function handleRemoveFromWatchlist() {
    removeConfirmOpen = true;
  }

  async function confirmRemoveFromWatchlist() {
    removeConfirmOpen = false;
    if (!show) return;
    try {
      await removeFromWatchlist("show", show.id);
      show.onWatchlist = false;
      toasts.push($t("watchlist.removeSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, "watchlist.removeError", $t), "error");
    }
  }

  async function handleAddToWatchlist() {
    if (!show) return;
    try {
      await addToWatchlist("show", show.id);
      show.onWatchlist = true;
      toasts.push($t("watchlist.addSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, "watchlist.addError", $t), "error");
    }
  }

  function handleWindowClick(e: MouseEvent) {
    if (menuOpen && menuRef && !menuRef.contains(e.target as Node)) {
      menuOpen = false;
    }
  }

  function handleWindowKeydown(e: KeyboardEvent) {
    if (e.key === "Escape") {
      menuOpen = false;
    }
  }

  $effect(() => {
    load(id);
  });
</script>

<svelte:window onclick={handleWindowClick} onkeydown={handleWindowKeydown} />

<div class="container stack gap-l page">
  {#if error}
    <StateMessage variant="error" text={error} />
  {:else if !show}
    <StateMessage variant="loading" text={$t("common.pageLoading")} />
  {:else}
    <div class="row gap-l wrap detail-head">
      <div class="poster">
        {#if show.posterUrl}
          <img src={show.posterUrl} alt={$t("detail.posterAlt", { title: show.title })} />
        {/if}
      </div>

      <div class="stack gap-s grow detail-info">
        <div class="row space-between">
          <h1 class="m-0">{show.title} {#if show.year}<span class="text-muted">({show.year})</span>{/if}</h1>
          <div class="menu-container" bind:this={menuRef}>
            <button
              class="btn-icon"
              onclick={() => (menuOpen = !menuOpen)}
              aria-expanded={menuOpen}
              aria-label={$t("detail.moreActions")}
            >⋮</button>
            {#if menuOpen}
              <div class="menu-popover stack gap-xs" role="menu">
                <a
                  class="menu-item"
                  role="menuitem"
                  href={`https://trakt.tv/shows/${show.slug}`}
                  target="_blank"
                  rel="noopener"
                  onclick={() => (menuOpen = false)}
                >
                  {$t("detail.openInTrakt")}
                </a>
                {#if show.tmdbId}
                  <a
                    class="menu-item"
                    role="menuitem"
                    href={`https://www.themoviedb.org/tv/${show.tmdbId}`}
                    target="_blank"
                    rel="noopener"
                    onclick={() => (menuOpen = false)}
                  >
                    {$t("detail.openInTmdb")}
                  </a>
                {/if}
                {#if show.progress}
                  <button
                    class="menu-item"
                    role="menuitem"
                    disabled={hiddenPending}
                    onclick={() => { menuOpen = false; handleToggleHidden(); }}
                  >
                    {show.progress.hidden ? $t("detail.resume") : $t("detail.cancel")}
                  </button>
                {/if}
                {#if show.onWatchlist}
                  <button
                    class="menu-item"
                    role="menuitem"
                    onclick={() => { menuOpen = false; handleRemoveFromWatchlist(); }}
                  >
                    {$t("watchlist.remove")}
                  </button>
                {:else}
                  <button
                    class="menu-item"
                    role="menuitem"
                    onclick={() => { menuOpen = false; handleAddToWatchlist(); }}
                  >
                    {$t("watchlist.add")}
                  </button>
                {/if}
              </div>
            {/if}
          </div>
        </div>
        <p class="row gap-xs wrap">
          {#if show.status}<span class="badge">{translateStatus(show.status, $language)}</span>{/if}
          {#if show.network}<span class="badge">{show.network}</span>{/if}
          {#if show.runtime}<span class="badge">{$t("detail.minutes", { n: show.runtime })}</span>{/if}
          {#if show.certification}<span class="badge">{show.certification}</span>{/if}
        </p>
        <p class="row gap-xs wrap">
          {#each show.genres as genre}<span class="badge">{translateGenre(genre, $language)}</span>{/each}
        </p>
        <p>{show.overview}</p>

        <RatingWidget itemType="show" id={show.id} bind:rating={show.rating} />

        {#if show.progress}
          <div class="card stack gap-xs">
            <h2 class="m-0 card-subtitle">{$t("detail.progressTitle")}</h2>
            <p class="text-muted">
              {$t("detail.episodesWatched", { completed: show.progress.completed, aired: show.progress.aired })}
              {#if show.progress.aired > 0}
                ({Math.round((show.progress.completed / show.progress.aired) * 100)}%)
              {/if}
            </p>
            <p class="text-muted">{$t("detail.lastWatched", { time: formatRelativeTime(show.progress.lastWatchedAt, $language) })}</p>
            {#if show.progress.nextEpisode}
              <p class="text-muted">
                {$t("detail.nextEpisode", {
                  code: `S${String(show.progress.nextEpisode.season).padStart(2, "0")}E${String(show.progress.nextEpisode.number).padStart(2, "0")}`,
                })}
                {#if show.progress.nextEpisode.title} · {show.progress.nextEpisode.title}{/if}
                {#if show.progress.nextEpisode.firstAired} · {formatAirDate(show.progress.nextEpisode.firstAired, $language)}{/if}
              </p>
            {/if}
          </div>
        {/if}
      </div>
    </div>

    <div class="stack gap-s">
      <h2 class="m-0 card-subtitle">{$t("detail.episodesHeading")}</h2>
      <EpisodeList showId={show.id} onWatchChange={refreshProgress} />
    </div>

    <ConfirmDialog
      open={removeConfirmOpen}
      title={$t("watchlist.removeConfirmTitle")}
      message={$t("watchlist.removeConfirmBody", { title: show.title })}
      confirmLabel={$t("common.confirm")}
      cancelLabel={$t("common.cancel")}
      variant="danger"
      onConfirm={confirmRemoveFromWatchlist}
      onCancel={() => (removeConfirmOpen = false)}
    />
  {/if}
</div>

<style>
  .menu-container {
    position: relative;
    flex-shrink: 0;
  }

  @media (max-width: 640px) {
    .poster {
      width: 100%;
    }
  }

  .btn-icon {
    background: none;
    border: 1px solid var(--border);
    border-radius: var(--radius-s);
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
    padding: var(--space-xs) var(--space-s);
  }

  .btn-icon:hover {
    color: var(--text);
    border-color: var(--text-muted);
  }

  .menu-popover {
    position: absolute;
    top: calc(100% + var(--space-xs));
    right: 0;
    z-index: 10;
    min-width: 180px;
    width: max-content;
    max-width: 320px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: var(--space-xs);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  }

  .menu-item {
    display: block;
    width: 100%;
    background: none;
    border: none;
    border-radius: var(--radius-s);
    color: var(--text);
    cursor: pointer;
    font: inherit;
    text-align: left;
    text-decoration: none;
    padding: var(--space-s);
  }

  .menu-item:hover {
    background: var(--border);
  }

  .menu-item:disabled {
    opacity: 0.6;
    cursor: default;
  }
</style>
