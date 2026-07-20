<script lang="ts">
  import type { ContinueWatchingItem } from "../api/continueWatching";
  import { formatRelativeTime } from "../utils/time";
  import { link } from "../router";
  import { t } from "../i18n";
  import { language } from "../stores/settings";

  let { item, marking = false, onMarkWatched }: {
    item: ContinueWatchingItem;
    marking?: boolean;
    onMarkWatched: (item: ContinueWatchingItem) => void;
  } = $props();

  const episodeLabel = $derived(
    `S${String(item.nextEpisode.season).padStart(2, "0")}E${String(item.nextEpisode.number).padStart(2, "0")}`
  );
</script>

<article class="card stack gap-s show-card">
  <button
    type="button"
    class="poster-badge mark-watched-chip"
    onclick={() => onMarkWatched(item)}
    disabled={marking}
    aria-label={$t("continueWatching.markWatched")}
    title={$t("continueWatching.markWatched")}
  >
    {#if item.newEpisodesCount > 1}
      <span class="chip-count">+{item.newEpisodesCount}</span>
    {/if}
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M6 12l5 5L22 6" />
    </svg>
  </button>
  <a href="/show/{item.id}" use:link class="show-card-link stack gap-s">
    <div class="poster">
      {#if item.posterUrl}
        <img src={item.posterUrl} alt={$t("detail.posterAlt", { title: item.title })} loading="lazy" />
      {:else}
        <div class="poster-fallback text-muted">{item.title}</div>
      {/if}
    </div>

    <div class="stack gap-xs show-card-info">
      <h3 class="m-0">{item.title}</h3>
      <p class="text-muted text-sm episode-line">
        {episodeLabel}{item.nextEpisode.title ? ` · ${item.nextEpisode.title}` : ""}
      </p>
      <p class="text-muted text-sm last-watched">
        {$t("continueWatching.lastWatched", { time: formatRelativeTime(item.lastWatchedAt, $language) })}
      </p>
    </div>
  </a>
</article>

<style>
  .show-card {
    position: relative;
    padding: 0;
    overflow: hidden;
  }

  .show-card-link {
    text-decoration: none;
    color: inherit;
    flex: 1;
    transition: transform var(--transition-fast) ease;
  }

  .show-card-link:hover {
    transform: translateY(-2px);
  }

  .show-card-info {
    padding-inline: var(--space-m);
    flex: 1;
  }

  .episode-line {
    min-height: 2.4em;
    line-height: 1.2;
  }

  .last-watched {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .poster {
    /* No position here: it must stay a plain in-flow (non-positioned) box, otherwise it
       paints above the earlier-in-DOM absolute-positioned .poster-badge sibling instead of
       below it (CSS stacking order among position:auto siblings follows tree order, not
       source-order intuition). */
    width: auto;
    aspect-ratio: 2 / 3;
    background: var(--border);
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

  .poster-badge {
    background: var(--primary);
  }

  /* Two rows in one badge: the new-episodes count on top, the mark-watched action
     below -- replaces the separate +N badge and the full-width button underneath. */
  .mark-watched-chip {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: var(--space-xs) var(--space-s);
    border: none;
    border-radius: var(--radius-s);
    cursor: pointer;
    font: inherit;
    line-height: 1;
    transition: opacity var(--transition-fast) ease;
  }

  .mark-watched-chip:disabled {
    opacity: 0.6;
    cursor: default;
  }

  .chip-count {
    font-size: 0.7rem;
    font-weight: 600;
  }

  .mark-watched-chip svg {
    width: 14px;
    height: 14px;
  }

  /* 455px, not the usual 640px breakpoint: the shared .grid (minmax(200px, 1fr), 24px gap,
     16px page padding) only drops to a single column below ~456px viewport width. Above that
     it stays 2-column with ~200-292px card width, too narrow for poster+text side by side. */
  @media (max-width: 455px) {
    .show-card-link {
      flex-direction: row;
      align-items: stretch;
    }

    .poster {
      width: 110px;
      flex-shrink: 0;
    }

    .show-card-info {
      padding: var(--space-m);
    }
  }
</style>
