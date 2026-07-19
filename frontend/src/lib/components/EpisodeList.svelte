<script lang="ts">
  import {
    fetchSeasonShape, fetchProgress, watchEpisode, unwatchEpisode, watchSeason, watchEpisodes,
    type SeasonShape, type SeasonProgress, type EpisodeProgress, type EpisodeRef,
  } from "../api/episodes";
  import { collectSeason, uncollectSeason } from "../api/collection";
  import StateMessage from "./StateMessage.svelte";
  import ConfirmDialog from "./ConfirmDialog.svelte";
  import { toasts } from "../stores/toast";
  import { apiErrorMessage } from "../api/errors";
  import { t } from "../i18n";

  /** Called after a watch/unwatch/season/collection mutation succeeds, so the parent (e.g.
   *  the show's progress summary card and collectedSeasons prop) can refresh itself -- this
   *  component only reloads its own shape/progress state. */
  let {
    showId, collectedSeasons, onWatchChange,
  }: { showId: number; collectedSeasons: number[]; onWatchChange?: () => void } = $props();

  // Shape (season/episode numbers, titles, year) and progress (watched status) load
  // independently and are never awaited against each other -- shape usually resolves near
  // instantly (cached), so the season rows can paint before watched-counts/checkmarks arrive.
  let shape = $state<SeasonShape[] | null>(null);
  let shapeError = $state("");
  let progress = $state<Map<number, SeasonProgress> | null>(null);
  let progressError = $state("");
  let expanded = $state<number | null>(null);
  let pending = $state<string | null>(null);

  type PendingMark =
    | { kind: "episode"; key: string; season: number; number: number; prior: EpisodeRef[] }
    | { kind: "season"; key: string; season: number; prior: EpisodeRef[] };
  let pendingMark = $state<PendingMark | null>(null);

  type SeasonRow = SeasonShape & { progressState: "loading" | "unavailable" | SeasonProgress; collected: boolean };

  const rows = $derived<SeasonRow[]>(
    (shape ?? []).map((s) => ({
      ...s,
      progressState: progress === null ? "loading" : (progress.get(s.number) ?? "unavailable"),
      collected: collectedSeasons.includes(s.number),
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

  /** Episodes strictly before (season, number) -- across all seasons, not just the same one
   *  -- that are still unwatched. Pass Infinity for `number` to check "before this season"
   *  as a whole (used by markSeasonWatched, which doesn't need anything from its own season). */
  function unwatchedBefore(season: number, number: number): EpisodeRef[] {
    if (!progress) return [];
    const result: EpisodeRef[] = [];
    for (const [seasonNumber, seasonProgress] of progress) {
      if (seasonNumber > season) continue;
      for (const ep of seasonProgress.episodes) {
        if (seasonNumber === season && ep.number >= number) continue;
        if (!ep.completed) result.push({ season: seasonNumber, number: ep.number });
      }
    }
    return result;
  }

  async function toggleEpisode(season: number, episode: EpisodeProgress) {
    const key = `ep-${season}-${episode.number}`;
    if (episode.completed) {
      pending = key;
      try {
        await unwatchEpisode(showId, season, episode.number);
        await loadProgress();
        onWatchChange?.();
      } catch (e) {
        toasts.push(apiErrorMessage(e, "common.actionError", $t), "error");
      } finally {
        pending = null;
      }
      return;
    }

    const prior = unwatchedBefore(season, episode.number);
    if (prior.length > 0) {
      pendingMark = { kind: "episode", key, season, number: episode.number, prior };
      return;
    }
    await runMarkEpisode(key, season, episode.number, []);
  }

  async function runMarkEpisode(key: string, season: number, number: number, prior: EpisodeRef[]) {
    pending = key;
    try {
      if (prior.length > 0) {
        await watchEpisodes(showId, prior);
      }
      await watchEpisode(showId, season, number);
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
    const prior = unwatchedBefore(season, Infinity);
    if (prior.length > 0) {
      pendingMark = { kind: "season", key, season, prior };
      return;
    }
    await runMarkSeason(key, season, []);
  }

  async function runMarkSeason(key: string, season: number, prior: EpisodeRef[]) {
    pending = key;
    try {
      if (prior.length > 0) {
        await watchEpisodes(showId, prior);
      }
      await watchSeason(showId, season);
      await loadProgress();
      onWatchChange?.();
    } catch (e) {
      toasts.push(apiErrorMessage(e, "common.actionError", $t), "error");
    } finally {
      pending = null;
    }
  }

  /** Direct boolean toggle per season -- unlike watch history, Collection has no "prior
   *  episodes" cascade concept, so no ConfirmDialog involvement here. */
  async function toggleSeasonCollected(season: number, alreadyCollected: boolean) {
    const key = `collect-season-${season}`;
    pending = key;
    try {
      if (alreadyCollected) {
        await uncollectSeason(showId, season);
      } else {
        await collectSeason(showId, season);
      }
      onWatchChange?.();
    } catch (e) {
      toasts.push(apiErrorMessage(e, "common.actionError", $t), "error");
    } finally {
      pending = null;
    }
  }

  /** Confirm dialog result -- `true` also marks the earlier unwatched episodes gathered in
   *  pendingMark.prior, `false` marks only the episode/season the user actually clicked. */
  async function handleConfirmMark(includePrior: boolean) {
    const mark = pendingMark;
    pendingMark = null;
    if (!mark) return;
    if (mark.kind === "episode") {
      await runMarkEpisode(mark.key, mark.season, mark.number, includePrior ? mark.prior : []);
    } else {
      await runMarkSeason(mark.key, mark.season, includePrior ? mark.prior : []);
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
          {@const seasonFullyWatched = sp !== "loading" && sp !== "unavailable" && sp.aired > 0 && sp.completed === sp.aired}
          <div class="stack gap-xs episode-rows">
            <div class="row gap-s wrap season-actions">
              {#if !seasonFullyWatched}
                <button
                  class="btn btn-primary btn-sm"
                  disabled={pending === `season-${season.number}` || sp === "loading" || sp === "unavailable"}
                  onclick={() => markSeasonWatched(season.number)}
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 12l5 5L20 6" />
                  </svg>
                  {pending === `season-${season.number}` ? $t("common.markingWatched") : $t("episodes.markSeasonWatched")}
                </button>
              {/if}
              <button
                class="btn btn-sm {season.collected ? 'btn-secondary' : 'btn-primary'}"
                disabled={pending === `collect-season-${season.number}`}
                onclick={() => toggleSeasonCollected(season.number, season.collected)}
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="4" y="7" width="16" height="12" rx="1.5" />
                  <path d="M4 7l1.6-3h12.8L20 7" />
                  <line x1="9.5" y1="12" x2="14.5" y2="12" />
                </svg>
                {season.collected ? $t("episodes.uncollectSeason") : $t("episodes.collectSeason")}
              </button>
            </div>
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

<ConfirmDialog
  open={pendingMark !== null}
  title={$t("episodes.confirmPriorTitle")}
  message={$t("episodes.confirmPriorBody", { count: pendingMark?.prior.length ?? 0 })}
  confirmLabel={$t("episodes.markAll")}
  cancelLabel={pendingMark?.kind === "season" ? $t("episodes.markOnlySeason") : $t("episodes.markOnlyEpisode")}
  onConfirm={() => handleConfirmMark(true)}
  onCancel={() => handleConfirmMark(false)}
/>

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

  .season-actions svg {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
  }

  .season-actions {
    justify-content: flex-end;
    margin-bottom: var(--space-xs);
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
