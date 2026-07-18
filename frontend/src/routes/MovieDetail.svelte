<script lang="ts">
  import { fetchMovieDetail, markMovieWatched, type MovieListItem } from "../lib/api/library";
  import { apiErrorMessage } from "../lib/api/errors";
  import { formatAirDate } from "../lib/utils/time";
  import { translateGenre } from "../lib/utils/genres";
  import { translateStatus } from "../lib/utils/status";
  import RatingWidget from "../lib/components/RatingWidget.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import { language } from "../lib/stores/settings";
  import { toasts } from "../lib/stores/toast";
  import { t } from "../lib/i18n";

  let { id }: { id: string } = $props();

  let movie = $state<MovieListItem | null>(null);
  let error = $state("");
  let markWatchedPending = $state(false);

  async function load(movieId: string) {
    movie = null;
    error = "";
    try {
      movie = await fetchMovieDetail(Number(movieId));
    } catch (e) {
      error = apiErrorMessage(e, "common.loadError", $t);
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

  $effect(() => {
    load(id);
  });
</script>

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
        <h1 class="m-0">{movie.title} {#if movie.year}<span class="text-muted">({movie.year})</span>{/if}</h1>
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
  {/if}
</div>

<style>
  @media (max-width: 640px) {
    .poster {
      width: 100%;
    }
  }
</style>
