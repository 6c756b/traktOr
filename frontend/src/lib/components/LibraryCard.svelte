<script lang="ts">
  import { link } from "../router";
  import { translateGenre } from "../utils/genres";
  import { translateStatus } from "../utils/status";
  import { language } from "../stores/settings";
  import { t } from "../i18n";

  let {
    href,
    title,
    year,
    posterUrl,
    genres,
    rating,
    status,
    progress,
    showProgress = true,
    collectionStatus,
    onRemove,
  }: {
    href: string;
    title: string;
    year: number | null;
    posterUrl: string | null;
    genres: string[];
    rating: number | null;
    status?: string | null;
    progress?: { aired: number; completed: number; hidden?: boolean } | null;
    showProgress?: boolean;
    collectionStatus?: "none" | "ok" | "behind";
    onRemove?: () => void;
  } = $props();

  const progressPct = $derived(
    progress && progress.aired > 0 ? Math.round((progress.completed / progress.aired) * 100) : null
  );

  // Deliberately quiet -- folded into the existing muted meta line, not a colored badge
  // (unlike the rating overlay), so it doesn't compete for attention on the grid.
  const statusHint = $derived(
    progress?.hidden
      ? $t("library.statusCanceled")
      : status === "canceled" || status === "ended"
        ? translateStatus(status, $language)
        : null
  );
</script>

<div class="library-card-wrap">
  {#if collectionStatus && collectionStatus !== "none"}
    <span
      class="fold"
      class:fold-behind={collectionStatus === "behind"}
      title={$t(collectionStatus === "behind" ? "detail.inCollectionBehind" : "detail.inCollection")}
      aria-label={$t(collectionStatus === "behind" ? "detail.inCollectionBehind" : "detail.inCollection")}
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M6 12l5 5L22 6" />
      </svg>
    </span>
  {/if}
  {#if rating}
    <span class="badge poster-badge" class:rating-badge-with-remove={!!onRemove}>★ {rating}</span>
  {/if}
  <a {href} use:link class="card library-card stack gap-s">
    <div class="poster">
      {#if posterUrl}
        <img src={posterUrl} alt={$t("detail.posterAlt", { title })} loading="lazy" />
      {:else}
        <div class="poster-fallback text-muted">{title}</div>
      {/if}
    </div>

    <div class="stack gap-xs library-card-info">
      <h3 class="m-0 card-title">{title}</h3>
      <p class="text-muted text-sm card-meta">
        {year ?? ""}{genres.length ? ` · ${genres.slice(0, 2).map((g) => translateGenre(g, $language)).join(", ")}` : ""}{statusHint ? ` · ${statusHint}` : ""}
      </p>
      {#if progressPct !== null && showProgress}
        <div class="progress-track">
          <div class="progress-fill" style="width: {progressPct}%;"></div>
        </div>
      {/if}
    </div>
  </a>

  {#if onRemove}
    <button
      type="button"
      class="badge poster-badge remove-badge"
      onclick={() => onRemove()}
      aria-label={$t("watchlist.remove")}
      title={$t("watchlist.remove")}
    >✕</button>
    <button
      type="button"
      class="btn btn-sm btn-danger library-card-remove-mobile"
      onclick={() => onRemove()}
    >{$t("watchlist.remove")}</button>
  {/if}
</div>

<style>
  .library-card-wrap {
    position: relative;
  }

  .library-card {
    height: 100%;
    padding: 0;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: transform var(--transition-fast) ease;
  }

  .library-card:hover {
    transform: translateY(-2px);
  }

  .library-card-info {
    padding: 0 var(--space-m) var(--space-m);
    flex: 1;
  }

  .card-title {
    font-size: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .card-meta {
    min-height: 2.4em;
    line-height: 1.2;
  }

  .poster {
    /* No position here: it must stay a plain in-flow (non-positioned) box, otherwise it
       paints above the earlier-in-DOM absolute-positioned .poster-badge/.remove-badge
       siblings instead of below them (CSS stacking order among position:auto siblings
       follows tree order, not source-order intuition). */
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
    background: rgba(0, 0, 0, 0.7);
    transition: transform var(--transition-fast) ease;
  }

  /* Dog-ear in the top-left corner -- inset by 1px/rounded to match .library-card's own
     1px border + border-radius (inherited from .card in app.css), otherwise the triangle's
     sharp corner pokes out past the card's rounded, bordered edge by a px or two. A property
     of the card rather than a sticker on top of it, and deliberately in --primary to read as
     the same "positive/complete" cue as the progress bar / primary buttons. pointer-events:
     none so it never creates a dead click zone over the poster link underneath. */
  .fold {
    position: absolute;
    top: 1px;
    left: 1px;
    width: 34px;
    height: 34px;
    overflow: hidden;
    border-top-left-radius: var(--radius);
    pointer-events: none;
    z-index: 1;
    transition: transform var(--transition-fast) ease;
  }

  /* .library-card's own hover-lift (below) only moves the <a> itself -- these badges are
     siblings (kept out of the anchor deliberately, see the click-through gotcha), so without
     this they'd stay put while the poster lifts underneath them and visibly detach. */
  .library-card-wrap:has(.library-card:hover) .fold,
  .library-card-wrap:has(.library-card:hover) .poster-badge {
    transform: translateY(-2px);
  }

  .fold::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 34px 34px 0 0;
    border-color: var(--primary) transparent transparent transparent;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
  }

  /* Caught up on watching but the collection hasn't kept pace (a newer season isn't owned
     yet) -- same shape, --warning instead of --primary as the "you might want to buy this"
     cue, distinct from the default "collection is fine" green. */
  .fold-behind::before {
    border-color: var(--warning) transparent transparent transparent;
  }

  .fold svg {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 12px;
    height: 12px;
    color: #fff;
  }

  .remove-badge {
    cursor: pointer;
    font: inherit;
    line-height: 1;
  }

  /* Pushes the rating star further left so it doesn't sit under the remove badge --
     both otherwise land in the same top-right corner (.poster-badge's default position). */
  .rating-badge-with-remove {
    right: calc(var(--space-m) + 2rem);
  }

  .remove-badge:hover {
    background: var(--danger);
  }

  .library-card-remove-mobile {
    display: none;
  }

  .btn-danger {
    background: #b91c1c;
    color: #fff;
  }

  .btn-danger:hover {
    opacity: 0.9;
  }

  @media (prefers-color-scheme: dark) {
    .btn-danger {
      background: var(--danger);
    }
  }

  :root[data-theme="dark"] .btn-danger {
    background: var(--danger);
  }

  .progress-track {
    margin-top: auto;
    height: 4px;
    border-radius: 999px;
    background: var(--border);
    overflow: hidden;
  }

  .progress-fill {
    height: 100%;
    background: var(--primary);
  }

  /* 455px, not the usual 640px breakpoint: the shared .grid (minmax(200px, 1fr), 24px gap,
     16px page padding) only drops to a single column below ~456px viewport width. Above that
     it stays 2-column with ~200-292px card width, too narrow for poster+text side by side. */
  @media (max-width: 455px) {
    .library-card {
      flex-direction: row;
      align-items: stretch;
    }

    .poster {
      width: 110px;
      flex-shrink: 0;
    }

    .library-card-info {
      padding: var(--space-m);
    }

    .remove-badge {
      display: none;
    }

    .library-card-remove-mobile {
      display: flex;
      position: absolute;
      left: calc(110px + var(--space-m));
      right: var(--space-m);
      bottom: var(--space-m);
    }
  }
</style>
