<script lang="ts">
  import { searchTrakt, type SearchResult } from "../lib/api/search";
  import { apiErrorMessage } from "../lib/api/errors";
  import { debounce } from "../lib/utils/debounce";
  import SearchResultCard from "../lib/components/SearchResultCard.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import { t } from "../lib/i18n";

  let query = $state("");
  let results = $state<SearchResult[] | null>(null);
  let loading = $state(false);
  let error = $state("");

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
</div>
