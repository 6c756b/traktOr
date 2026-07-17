<script lang="ts">
  import { onMount } from "svelte";
  import { fetchContinueWatching, markEpisodeWatched, type ContinueWatchingItem, type SortOrder } from "../lib/api/continueWatching";
  import { triggerFullSync } from "../lib/api/sync";
  import { apiErrorMessage } from "../lib/api/errors";
  import ShowCard from "../lib/components/ShowCard.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import { t } from "../lib/i18n";
  import { toasts } from "../lib/stores/toast";

  const emptyKeys = ["continueWatching.empty", "continueWatching.emptyAlt"];

  let items = $state<ContinueWatchingItem[] | null>(null);
  let error = $state("");
  let sort = $state<SortOrder>("new");
  let backgroundSyncing = $state(false);

  async function load() {
    error = "";
    try {
      const result = await fetchContinueWatching(sort);
      items = result.items;
      if (result.stale) {
        syncInBackground();
      }
    } catch (e) {
      error = apiErrorMessage(e, "common.loadError", $t);
    }
  }

  /** Runs after a stale response in the background, without blocking the page --
   *  see tasks/improvements.md item 4a. Silently reload on success, so fresh
   *  data appears without another click. */
  async function syncInBackground() {
    if (backgroundSyncing) return;
    backgroundSyncing = true;
    const toastId = toasts.push($t("continueWatching.backgroundSync"), "info", 0);
    try {
      await triggerFullSync();
      await load();
    } catch {
      // Sync is already running (lock) or failed -- existing (slightly
      // stale) data simply stays as is, the next page visit tries again.
    } finally {
      backgroundSyncing = false;
      toasts.dismiss(toastId);
    }
  }

  onMount(load);

  function setSort(next: SortOrder) {
    if (sort === next) return;
    sort = next;
    items = null;
    load();
  }

  async function handleMarkWatched(item: ContinueWatchingItem) {
    const { item: updated } = await markEpisodeWatched(item.id, item.nextEpisode.season, item.nextEpisode.number);
    if (!items) return;
    items = updated
      ? items.map((i) => (i.id === item.id ? updated : i))
      : items.filter((i) => i.id !== item.id);
    toasts.push($t("continueWatching.markWatchedSuccess"), "success");
  }
</script>

<div class="container stack gap-l page">
  <div class="row space-between">
    <div class="row gap-s">
      <h1 class="m-0">{$t("nav.continueWatching")}</h1>
    </div>
    <div class="row gap-s">
      <button
        class="btn {sort === 'new' ? 'btn-primary' : 'btn-secondary'}"
        onclick={() => setSort("new")}
      >
        {$t("continueWatching.sortNew")}
      </button>
      <button
        class="btn {sort === 'waiting' ? 'btn-primary' : 'btn-secondary'}"
        onclick={() => setSort("waiting")}
      >
        {$t("continueWatching.sortWaiting")}
      </button>
    </div>
  </div>

  {#if error}
    <StateMessage variant="error" text={error} />
  {:else if items === null}
    <StateMessage variant="loading" text={$t("common.pageLoading")} />
  {:else if items.length === 0}
    <StateMessage variant="empty" text={$t(emptyKeys[Math.floor(Math.random() * emptyKeys.length)])} />
  {:else}
    <div class="grid">
      {#each items as item (item.id)}
        <ShowCard {item} onMarkWatched={handleMarkWatched} />
      {/each}
    </div>
  {/if}
</div>
