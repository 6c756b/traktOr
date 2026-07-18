<script lang="ts">
  import { addToWatchlist } from "../api/watchlist";
  import { watchEpisode } from "../api/episodes";
  import { markMovieWatched } from "../api/library";
  import { apiErrorMessage } from "../api/errors";
  import { translateGenre } from "../utils/genres";
  import { language } from "../stores/settings";
  import { toasts } from "../stores/toast";
  import { navigate } from "../router";
  import { t } from "../i18n";
  import type { SearchResult } from "../api/search";

  let { result }: { result: SearchResult } = $props();

  let addPending = $state(false);
  let onWatchlist = $state(false);
  let watchPending = $state(false);

  async function handleAddToWatchlist() {
    addPending = true;
    try {
      await addToWatchlist(result.type, result.traktId);
      onWatchlist = true;
      toasts.push($t("watchlist.addSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, "watchlist.addError", $t), "error");
    } finally {
      addPending = false;
    }
  }

  async function handleWatch() {
    watchPending = true;
    try {
      if (result.type === "show") {
        await watchEpisode(result.traktId, 1, 1);
        navigate(`/show/${result.traktId}`);
      } else {
        await markMovieWatched(result.traktId);
        navigate(`/movie/${result.traktId}`);
      }
    } catch (e) {
      toasts.push(apiErrorMessage(e, "detail.markWatchedError", $t), "error");
      watchPending = false;
    }
  }
</script>

<div class="card search-card stack gap-s">
  <div class="poster">
    {#if result.posterUrl}
      <img src={result.posterUrl} alt={$t("detail.posterAlt", { title: result.title })} loading="lazy" />
    {:else}
      <div class="poster-fallback text-muted">{result.title}</div>
    {/if}
  </div>

  <div class="stack gap-xs search-card-body">
    <h3 class="m-0 card-title">
      {result.title}
      {#if result.year}<span class="text-muted">({result.year})</span>{/if}
    </h3>
    {#if result.genres.length}
      <p class="text-muted text-sm m-0">
        {result.genres.slice(0, 3).map((g) => translateGenre(g, $language)).join(", ")}
      </p>
    {/if}
    {#if result.overview}
      <p class="text-muted text-sm search-card-overview">{result.overview}</p>
    {/if}
  </div>

  <div class="stack gap-xs search-card-actions">
    {#if result.watched}
      <span class="badge">{$t("search.alreadyWatched")}</span>
    {:else}
      {#if onWatchlist}
        <span class="badge">{$t("search.onWatchlist")}</span>
      {:else}
        <button type="button" class="btn btn-secondary btn-sm" disabled={addPending} onclick={handleAddToWatchlist}>
          {$t("watchlist.add")}
        </button>
      {/if}
      <button type="button" class="btn btn-primary btn-sm" disabled={watchPending} onclick={handleWatch}>
        {result.type === "show" ? $t("search.watchEpisodeOne") : $t("detail.markWatched")}
      </button>
    {/if}
  </div>
</div>

<style>
  .search-card {
    padding: 0;
    overflow: hidden;
  }

  .poster {
    position: relative;
    aspect-ratio: 2 / 3;
    background: var(--border);
    width: auto;
    border-radius: 0;
  }

  .poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .poster-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: var(--space-m);
  }

  .search-card-body {
    flex: 1;
    padding: 0 var(--space-m);
  }

  .card-title {
    font-size: 1rem;
  }

  .search-card-overview {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .search-card-actions {
    padding: var(--space-m);
  }

  .search-card-actions :global(.btn) {
    width: 100%;
  }

  .search-card-actions :global(.badge) {
    justify-content: center;
    width: 100%;
    padding-block: var(--space-s);
  }
</style>
