<script lang="ts">
  import {
    searchTrakt,
    fetchRecommendedShows,
    fetchRecommendedMovies,
    fetchTrendingShows,
    fetchPopularMovies,
    type SearchResult,
  } from "../lib/api/search";
  import { apiErrorMessage } from "../lib/api/errors";
  import { debounce } from "../lib/utils/debounce";
  import SearchResultCard from "../lib/components/SearchResultCard.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import { t } from "../lib/i18n";

  type TabKey = "recShows" | "recMovies" | "trendingShows" | "popularMovies";

  const tabs: { key: TabKey; label: string; fetch: () => Promise<SearchResult[]> }[] = [
    { key: "recShows", label: "search.tabRecommendedShows", fetch: fetchRecommendedShows },
    { key: "recMovies", label: "search.tabRecommendedMovies", fetch: fetchRecommendedMovies },
    { key: "trendingShows", label: "search.tabTrendingShows", fetch: fetchTrendingShows },
    { key: "popularMovies", label: "search.tabPopularMovies", fetch: fetchPopularMovies },
  ];

  let query = $state("");
  let results = $state<SearchResult[] | null>(null);
  let loading = $state(false);
  let error = $state("");

  let activeTab = $state<TabKey>("recShows");
  let tabResults = $state<SearchResult[] | null>(null);
  let tabLoading = $state(false);
  let tabError = $state("");
  // Per-tab result cache so switching back and forth doesn't refetch -- these lists change
  // slowly (personalized recommendations, daily-ish trending/popular), not worth reloading
  // every click within one page visit.
  const tabCache = new Map<TabKey, SearchResult[]>();

  async function loadTab(key: TabKey) {
    const cached = tabCache.get(key);
    if (cached) {
      tabResults = cached;
      tabError = "";
      return;
    }
    tabLoading = true;
    tabError = "";
    tabResults = null;
    try {
      const data = await tabs.find((tab) => tab.key === key)!.fetch();
      tabCache.set(key, data);
      // Guard against a slower, earlier request for a since-abandoned tab resolving after a
      // faster later one -- only commit if this is still the tab the user is looking at. The
      // cache above still gets warmed either way, so switching back later is instant.
      if (activeTab === key) {
        tabResults = data;
      }
    } catch (e) {
      if (activeTab === key) {
        tabError = apiErrorMessage(e, "search.loadError", $t);
      }
    } finally {
      if (activeTab === key) {
        tabLoading = false;
      }
    }
  }

  $effect(() => {
    if (query.trim() === "") {
      loadTab(activeTab);
    }
  });

  const runSearch = debounce(async (q: string) => {
    if (q.trim() === "") {
      results = null;
      loading = false;
      return;
    }
    loading = true;
    error = "";
    try {
      results = await searchTrakt(q.trim());
    } catch (e) {
      error = apiErrorMessage(e, "search.loadError", $t);
    } finally {
      loading = false;
    }
  }, 400);

  function handleInput(e: Event) {
    query = (e.currentTarget as HTMLInputElement).value;
    runSearch(query);
  }
</script>

<div class="container stack gap-l page">
  <h1 class="m-0">{$t("search.title")}</h1>

  <input
    class="input"
    type="search"
    placeholder={$t("search.placeholder")}
    value={query}
    oninput={handleInput}
  />

  {#if query.trim() !== ""}
    {#if error}
      <StateMessage variant="error" text={error} />
    {:else if loading}
      <StateMessage variant="loading" text={$t("common.actionPending")} />
    {:else if results === null}
      <StateMessage variant="empty" text={$t("search.empty")} />
    {:else if results.length === 0}
      <StateMessage variant="empty" text={$t("search.noResults", { query })} />
    {:else}
      <div class="grid">
        {#each results as result (`${result.type}-${result.traktId}`)}
          <SearchResultCard {result} />
        {/each}
      </div>
    {/if}
  {:else}
    <div class="row gap-xs wrap">
      {#each tabs as tab (tab.key)}
        <button
          type="button"
          class="btn btn-sm {activeTab === tab.key ? 'btn-primary' : 'btn-secondary'}"
          onclick={() => (activeTab = tab.key)}
        >
          {$t(tab.label)}
        </button>
      {/each}
    </div>

    {#if tabError}
      <StateMessage variant="error" text={tabError} />
    {:else if tabLoading}
      <StateMessage variant="loading" text={$t("common.actionPending")} />
    {:else if tabResults && tabResults.length > 0}
      <div class="grid">
        {#each tabResults as result (`${result.type}-${result.traktId}`)}
          <SearchResultCard {result} />
        {/each}
      </div>
    {/if}
  {/if}
</div>
