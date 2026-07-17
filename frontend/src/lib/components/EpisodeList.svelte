<script lang="ts">
  import { fetchEpisodes, watchEpisode, unwatchEpisode, watchSeason, type Season } from "../api/episodes";
  import StateMessage from "./StateMessage.svelte";
  import { t } from "../i18n";

  let { showId }: { showId: number } = $props();

  let seasons = $state<Season[] | null>(null);
  let error = $state("");
  let expanded = $state<number | null>(null);
  let pending = $state<string | null>(null);

  async function load() {
    error = "";
    try {
      seasons = await fetchEpisodes(showId);
    } catch {
      error = $t("episodes.loadError");
    }
  }

  $effect(() => {
    load();
  });

  function toggleExpand(seasonNumber: number) {
    expanded = expanded === seasonNumber ? null : seasonNumber;
  }

  async function toggleEpisode(season: number, episode: { number: number; completed: boolean }) {
    const key = `ep-${season}-${episode.number}`;
    pending = key;
    try {
      if (episode.completed) {
        await unwatchEpisode(showId, season, episode.number);
      } else {
        await watchEpisode(showId, season, episode.number);
      }
      await load();
    } finally {
      pending = null;
    }
  }

  async function markSeasonWatched(season: number) {
    const key = `season-${season}`;
    pending = key;
    try {
      await watchSeason(showId, season);
      await load();
    } finally {
      pending = null;
    }
  }
</script>

<div class="stack gap-s">
  {#if error}
    <StateMessage variant="error" text={error} />
  {:else if !seasons}
    <StateMessage variant="loading" text={$t("common.pageLoading")} />
  {:else}
    {#each seasons as season (season.number)}
      <div class="card season">
        <div class="row space-between">
          <button class="season-toggle row gap-s" onclick={() => toggleExpand(season.number)}>
            <span>{expanded === season.number ? "▾" : "▸"}</span>
            <span>{$t("episodes.season", { n: season.number })}</span>
            {#if season.year}
              <span class="text-muted">· {season.year}</span>
            {/if}
            <span class="text-muted">({season.completed} / {season.aired})</span>
          </button>
          <button
            class="btn btn-secondary btn-sm"
            disabled={pending === `season-${season.number}`}
            onclick={() => markSeasonWatched(season.number)}
          >
            {pending === `season-${season.number}` ? $t("common.markingWatched") : $t("episodes.markSeasonWatched")}
          </button>
        </div>

        {#if expanded === season.number}
          <div class="stack gap-xs episode-rows">
            {#each season.episodes as episode (episode.number)}
              <button
                class="row gap-s episode-row"
                disabled={pending === `ep-${season.number}-${episode.number}`}
                onclick={() => toggleEpisode(season.number, episode)}
              >
                <span class="check {episode.completed ? 'checked' : ''}">{episode.completed ? "✓" : ""}</span>
                <span class="text-muted episode-number">{episode.number}.</span>
                <span>{episode.title ?? $t("episodes.episodeFallback", { n: episode.number })}</span>
              </button>
            {/each}
          </div>
        {/if}
      </div>
    {/each}
  {/if}
</div>

<style>
  .season {
    padding: var(--space-s) var(--space-m);
  }

  .episode-number {
    width: 2.5em;
  }

  .season-toggle {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    color: var(--text);
    padding: 0;
  }

  .episode-rows {
    margin-top: var(--space-s);
    padding-top: var(--space-s);
    border-top: 1px solid var(--border);
  }

  .episode-row {
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    padding: var(--space-xs) 0;
    color: var(--text);
    font-size: 0.9rem;
    align-items: center;
  }

  .episode-row:disabled {
    opacity: 0.6;
    cursor: default;
  }

  .check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.4em;
    height: 1.4em;
    border-radius: 50%;
    border: 1px solid var(--border);
    color: #fff;
    font-size: 0.75rem;
    flex-shrink: 0;
  }

  .check.checked {
    background: var(--primary);
    border-color: var(--primary);
  }
</style>
