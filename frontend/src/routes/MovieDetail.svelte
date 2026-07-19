<script lang="ts">
  import { fetchMovieDetail, markMovieWatched, type MovieListItem } from "../lib/api/library";
  import { addToWatchlist, removeFromWatchlist } from "../lib/api/watchlist";
  import { addToCollection, removeFromCollection } from "../lib/api/collection";
  import { fetchRelatedMovies, type SearchResult } from "../lib/api/search";
  import { apiErrorMessage } from "../lib/api/errors";
  import { formatAirDate } from "../lib/utils/time";
  import { translateGenre } from "../lib/utils/genres";
  import { translateStatus } from "../lib/utils/status";
  import RatingWidget from "../lib/components/RatingWidget.svelte";
  import NoteModal from "../lib/components/NoteModal.svelte";
  import SearchResultCard from "../lib/components/SearchResultCard.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import { language } from "../lib/stores/settings";
  import { toasts } from "../lib/stores/toast";
  import { t } from "../lib/i18n";

  let { id }: { id: string } = $props();

  let movie = $state<MovieListItem | null>(null);
  let error = $state("");
  let markWatchedPending = $state(false);
  let watchlistPending = $state(false);
  let collectionPending = $state(false);
  let menuOpen = $state(false);
  let menuRef: HTMLDivElement | undefined = $state();
  let noteModalOpen = $state(false);
  // "More like this" -- purely supplementary, a failed/empty fetch just leaves it hidden
  // instead of surfacing its own error state.
  let related = $state<SearchResult[] | null>(null);

  async function load(movieId: string) {
    movie = null;
    error = "";
    related = null;
    try {
      movie = await fetchMovieDetail(Number(movieId));
    } catch (e) {
      error = apiErrorMessage(e, "common.loadError", $t);
    }
    try {
      related = await fetchRelatedMovies(Number(movieId));
    } catch {
      related = null;
    }
  }

  async function handleMarkWatched() {
    if (!movie) return;
    markWatchedPending = true;
    try {
      await markMovieWatched(movie.id);
      movie.watchedAt = new Date().toISOString();
      toasts.push($t("continueWatching.markWatchedSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, "detail.markWatchedError", $t), "error");
    } finally {
      markWatchedPending = false;
    }
  }

  /** No confirm dialog (unlike the Watchlist grid view) -- a deliberate single action on a
   *  detail page, low accidental-click risk. */
  async function handleToggleWatchlist() {
    if (!movie) return;
    const wasOnWatchlist = movie.onWatchlist;
    watchlistPending = true;
    try {
      if (wasOnWatchlist) {
        await removeFromWatchlist("movie", movie.id);
      } else {
        await addToWatchlist("movie", movie.id);
      }
      movie.onWatchlist = !wasOnWatchlist;
      toasts.push($t(wasOnWatchlist ? "watchlist.removeSuccess" : "watchlist.addSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, wasOnWatchlist ? "watchlist.removeError" : "watchlist.addError", $t), "error");
    } finally {
      watchlistPending = false;
    }
  }

  /** No confirm dialog, same reasoning as handleToggleWatchlist(). */
  async function handleToggleCollection() {
    if (!movie) return;
    const wasInCollection = movie.inCollection;
    collectionPending = true;
    try {
      if (wasInCollection) {
        await removeFromCollection(movie.id);
      } else {
        await addToCollection(movie.id);
      }
      movie.inCollection = !wasInCollection;
      toasts.push($t(wasInCollection ? "collection.removeSuccess" : "collection.addSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, wasInCollection ? "collection.removeError" : "collection.addError", $t), "error");
    } finally {
      collectionPending = false;
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
  {:else if !movie}
    <StateMessage variant="loading" text={$t("common.pageLoading")} />
  {:else}
    <div class="row gap-l wrap detail-head">
      <div class="poster">
        {#if movie.posterUrl}
          <img src={movie.posterUrl} alt={$t("detail.posterAlt", { title: movie.title })} />
        {/if}
      </div>

      <div class="stack gap-s grow detail-info">
        <div class="row space-between">
          <h1 class="m-0">{movie.title} {#if movie.year}<span class="text-muted">({movie.year})</span>{/if}</h1>
          <div class="menu-container" bind:this={menuRef}>
            <button
              class="btn-icon"
              onclick={() => (menuOpen = !menuOpen)}
              aria-expanded={menuOpen}
              aria-label={$t("detail.moreActions")}
            >⋮</button>
            {#if menuOpen}
              <div class="menu-popover stack gap-xs" role="menu">
                <button
                  class="menu-item"
                  role="menuitem"
                  onclick={() => { menuOpen = false; noteModalOpen = true; }}
                >
                  {$t("notes.menuLabel")}
                </button>
              </div>
            {/if}
          </div>
        </div>
        {#if movie.note}<p class="text-muted note-preview">{movie.note}</p>{/if}
        <p class="row gap-xs wrap">
          {#if movie.status}<span class="badge">{translateStatus(movie.status, $language)}</span>{/if}
          {#if movie.runtime}<span class="badge">{$t("detail.minutes", { n: movie.runtime })}</span>{/if}
          {#if movie.certification}<span class="badge">{movie.certification}</span>{/if}
          {#if movie.released}<span class="badge">{formatAirDate(movie.released, $language)}</span>{/if}
        </p>
        <p class="row gap-xs wrap">
          {#each movie.genres as genre}<span class="badge">{translateGenre(genre, $language)}</span>{/each}
        </p>
        <p>{movie.overview}</p>

        <div class="row gap-s wrap">
          <RatingWidget itemType="movie" id={movie.id} bind:rating={movie.rating} />
          <button type="button" class="btn btn-secondary btn-sm" disabled={watchlistPending} onclick={handleToggleWatchlist}>
            {movie.onWatchlist ? $t("watchlist.remove") : $t("watchlist.add")}
          </button>
          <button type="button" class="btn btn-secondary btn-sm" disabled={collectionPending} onclick={handleToggleCollection}>
            {movie.inCollection ? $t("collection.remove") : $t("collection.add")}
          </button>
          {#if movie.watchedAt}
            <span class="badge">{$t("detail.watched")}</span>
          {:else}
            <button type="button" class="btn btn-secondary btn-sm" disabled={markWatchedPending} onclick={handleMarkWatched}>
              {$t("detail.markWatched")}
            </button>
          {/if}
        </div>
      </div>
    </div>

    {#if related && related.length > 0}
      <div class="stack gap-s related-section">
        <h2 class="m-0 card-subtitle">{$t("detail.relatedMoviesHeading")}</h2>
        <div class="grid">
          {#each related as result (`${result.type}-${result.traktId}`)}
            <SearchResultCard {result} />
          {/each}
        </div>
      </div>
    {/if}

    <NoteModal
      open={noteModalOpen}
      itemType="movie"
      id={movie.id}
      bind:note={movie.note}
      onClose={() => (noteModalOpen = false)}
    />
  {/if}
</div>

<style>
  .related-section {
    margin-top: var(--space-l);
    padding-top: var(--space-xl);
    border-top: 1px solid var(--border);
  }

  .menu-container {
    position: relative;
    flex-shrink: 0;
  }

  .note-preview {
    font-size: 0.9rem;
    font-style: italic;
    white-space: pre-wrap;
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

  @media (max-width: 640px) {
    .poster {
      width: 100%;
    }
  }
</style>
