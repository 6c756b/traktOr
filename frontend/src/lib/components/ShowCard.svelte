<script lang="ts">
  import type { ContinueWatchingItem } from "../api/continueWatching";
  import { formatRelativeTime } from "../utils/time";
  import { link } from "../router";
  import { t } from "../i18n";
  import { language } from "../stores/settings";

  let { item, onMarkWatched }: {
    item: ContinueWatchingItem;
    onMarkWatched: (item: ContinueWatchingItem) => Promise<void>;
  } = $props();

  let marking = $state(false);

  async function handleMarkWatched() {
    marking = true;
    try {
      await onMarkWatched(item);
    } finally {
      marking = false;
    }
  }

  const episodeLabel = $derived(
    `S${String(item.nextEpisode.season).padStart(2, "0")}E${String(item.nextEpisode.number).padStart(2, "0")}`
  );
</script>

<article class="card stack gap-s show-card">
  {#if item.newEpisodesCount > 1}
    <span class="badge poster-badge">+{item.newEpisodesCount}</span>
  {/if}
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

  <button class="btn btn-primary show-card-action" onclick={handleMarkWatched} disabled={marking}>
    {marking ? $t("common.markingWatched") : $t("continueWatching.markWatched")}
  </button>
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

  .show-card-action {
    margin: var(--space-m);
    margin-top: 0;
    padding-inline: var(--space-s);
    font-size: 0.85rem;
    white-space: nowrap;
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

    .show-card-action {
      position: absolute;
      left: calc(110px + var(--space-m));
      right: var(--space-m);
      bottom: var(--space-m);
      margin: 0;
      min-height: 36px;
      padding-block: var(--space-xs);
      white-space: normal;
    }
  }
</style>
