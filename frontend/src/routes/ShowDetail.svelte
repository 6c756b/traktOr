<script lang="ts">
  import { fetchShowDetail, type ShowListItem } from "../lib/api/library";
  import { apiErrorMessage } from "../lib/api/errors";
  import { formatAirDate, formatRelativeTime } from "../lib/utils/time";
  import { translateGenre } from "../lib/utils/genres";
  import { translateStatus } from "../lib/utils/status";
  import RatingWidget from "../lib/components/RatingWidget.svelte";
  import EpisodeList from "../lib/components/EpisodeList.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import { language } from "../lib/stores/settings";
  import { t } from "../lib/i18n";

  let { id }: { id: string } = $props();

  let show = $state<ShowListItem | null>(null);
  let error = $state("");

  async function load(showId: string) {
    show = null;
    error = "";
    try {
      show = await fetchShowDetail(Number(showId));
    } catch (e) {
      error = apiErrorMessage(e, "common.loadError", $t);
    }
  }

  $effect(() => {
    load(id);
  });
</script>

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
        <h1 class="m-0">{show.title} {#if show.year}<span class="text-muted">({show.year})</span>{/if}</h1>
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
      <EpisodeList showId={show.id} />
    </div>
  {/if}
</div>
