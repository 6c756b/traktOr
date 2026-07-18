<script lang="ts">
  import {
    fetchSeasonShape, fetchProgress, watchEpisode, unwatchEpisode, watchSeason,
    type SeasonShape, type SeasonProgress, type EpisodeProgress,
  } from "../api/episodes";
  import StateMessage from "./StateMessage.svelte";
  import { toasts } from "../stores/toast";
  import { apiErrorMessage } from "../api/errors";
  import { t } from "../i18n";

  /** Called after a watch/unwatch/season mutation succeeds, so the parent (e.g. the show's
   *  progress summary card) can refresh itself -- this component only reloads its own state. */
  let { showId, onWatchChange }: { showId: number; onWatchChange?: () => void } = $props();

  // Shape (season/episode numbers, titles, year) and progress (watched status) load
  // independently and are never awaited against each other -- shape usually resolves near
  // instantly (cached), so the season rows can paint before watched-counts/checkmarks arrive.
  let shape = $state<SeasonShape[] | null>(null);
  let shapeError = $state("");
  let progress = $state<Map<number, SeasonProgress> | null>(null);
  let progressError = $state("");
  let expanded = $state<number | null>(null);
  let pending = $state<string | null>(null);

  type SeasonRow = SeasonShape & { progressState: "loading" | "unavailable" | SeasonProgress };

  const rows = $derived<SeasonRow[]>(
    (shape ?? []).map((s) => ({
      ...s,
      progressState: progress === null ? "loading" : (progress.get(s.number) ?? "unavailable"),
    }))
  );

  async function loadShape() {
    shapeError = "";
    try {
      shape = await fetchSeasonShape(showId);
    } catch {
      shapeError = $t("episodes.loadError");
    }
  }

  async function loadProgress() {
    progressError = "";
    try {
      const seasons = await fetchProgress(showId);
      progress = new Map(seasons.map((s) => [s.number, s]));
    } catch {
      progressError = $t("episodes.progressLoadError");
    }
  }

  $effect(() => {
    loadShape();
    loadProgress();
  });

  function toggleExpand(seasonNumber: number) {
    expanded = expanded === seasonNumber ? null : seasonNumber;
  }

  async function toggleEpisode(season: number, episode: EpisodeProgress) {
    const key = `ep-${season}-${episode.number}`;
    pending = key;
    try {
      if (episode.completed) {
        await unwatchEpisode(showId, season, episode.number);
      } else {
        await watchEpisode(showId, season, episode.number);
      }
      await loadProgress();
      onWatchChange?.();
    } catch (e) {
      toasts.push(apiErrorMessage(e, "common.actionError", $t), "error");
    } finally {
      pending = null;
    }
  }

  async function markSeasonWatched(season: number) {
    const key = `season-${season}`;
    pending = key;
    try {
      await watchSeason(showId, season);
      await loadProgress();
      onWatchChange?.();
    } catch (e) {
      toasts.push(apiErrorMessage(e, "common.actionError", $t), "error");
    } finally {
      pending = null;
    }
  }
</script>

<div class="stack gap-s">
  {#if shapeError}
    <StateMessage variant="error" text={shapeError} />
  {:else if !shape}
    <StateMessage variant="loading" text={$t("common.pageLoading")} />
  {:else}
    {#each rows as season (season.number)}
      {@const sp = season.progressState}
      <div class="card season">
        <button class="season-toggle row gap-s wrap" onclick={() => toggleExpand(season.number)}>
          <span>{expanded === season.number ? "▾" : "▸"}</span>
          <span class="nowrap">{$t("episodes.season", { n: season.number })}</span>
          {#if season.year}
            <span class="text-muted nowrap">· {season.year}</span>
          {/if}
          {#if sp === "loading"}
            <span class="text-muted">…</span>
          {:else if sp === "unavailable"}
            <span class="text-muted nowrap" title={progressError || undefined}>—</span>
          {:else}
            <span class="text-muted nowrap">({sp.completed} / {sp.aired})</span>
          {/if}
        </button>

        {#if expanded === season.number}
          <div class="stack gap-xs episode-rows">
            <button
              class="btn btn-secondary btn-sm season-mark-watched"
              disabled={pending === `season-${season.number}` || sp === "loading" || sp === "unavailable"}
              onclick={() => markSeasonWatched(season.number)}
            >
              {pending === `season-${season.number}` ? $t("common.markingWatched") : $t("episodes.markSeasonWatched")}
            </button>
            {#each season.episodes as episode (episode.number)}
              {@const ep = sp !== "loading" && sp !== "unavailable" ? sp.episodes.find((e) => e.number === episode.number) : undefined}
              <button
                class="row gap-s episode-row"
                disabled={pending === `ep-${season.number}-${episode.number}` || !ep}
                onclick={() => ep && toggleEpisode(season.number, ep)}
              >
                <span class="check {ep?.completed ? 'checked' : ''}">{ep?.completed ? "✓" : ""}</span>
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
    width: 100%;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    color: var(--text);
    padding: 0;
  }

  .nowrap {
    white-space: nowrap;
  }

  .season-mark-watched {
    align-self: flex-end;
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
