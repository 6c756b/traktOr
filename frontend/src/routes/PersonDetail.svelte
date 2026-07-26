<script lang="ts">
  import { fetchPerson, fetchPersonCredits, type PersonDetail } from "../lib/api/people";
  import type { SearchResult } from "../lib/api/search";
  import { apiErrorMessage } from "../lib/api/errors";
  import SearchResultCard from "../lib/components/SearchResultCard.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import { t } from "../lib/i18n";

  let { id }: { id: string } = $props();

  let person = $state<PersonDetail | null>(null);
  let error = $state("");
  // Filmography -- purely supplementary, a failed/empty fetch just leaves it hidden
  // instead of surfacing its own error state (same pattern as detail-page "related" rows).
  let credits = $state<SearchResult[] | null>(null);

  async function load(personId: string) {
    person = null;
    error = "";
    credits = null;
    try {
      person = await fetchPerson(Number(personId));
    } catch (e) {
      error = apiErrorMessage(e, "common.loadError", $t);
    }
    try {
      credits = await fetchPersonCredits(Number(personId));
    } catch {
      credits = null;
    }
  }

  $effect(() => {
    load(id);
  });
</script>

<div class="container stack gap-l page">
  {#if error}
    <StateMessage variant="error" text={error} />
  {:else if !person}
    <StateMessage variant="loading" text={$t("common.pageLoading")} />
  {:else}
    <div class="row gap-l wrap detail-head">
      <div class="poster">
        {#if person.photoUrl}
          <img src={person.photoUrl} alt={$t("detail.castPhotoAlt", { name: person.name })} />
        {:else}
          <div class="poster-fallback text-muted">{person.name}</div>
        {/if}
      </div>

      <div class="stack gap-s grow detail-info">
        <h1 class="m-0">{person.name}</h1>
        {#if person.birthday || person.birthplace}
          <p class="text-muted m-0">
            {person.birthday ?? ""}{person.birthday && person.birthplace ? " · " : ""}{person.birthplace ?? ""}
          </p>
        {/if}
        {#if person.biography}
          <p class="biography">{person.biography}</p>
        {:else}
          <p class="text-muted m-0">{$t("person.bioMissing")}</p>
        {/if}
      </div>
    </div>

    {#if credits && credits.length > 0}
      <div class="stack gap-s">
        <h2 class="m-0 card-subtitle">{$t("person.filmographyHeading")}</h2>
        <div class="grid">
          {#each credits as result (`${result.type}-${result.traktId}`)}
            <SearchResultCard {result} />
          {/each}
        </div>
      </div>
    {/if}
  {/if}
</div>

<style>
  .poster-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: var(--space-m);
  }

  .biography {
    white-space: pre-wrap;
  }

  @media (max-width: 640px) {
    .poster {
      width: 100%;
    }
  }
</style>
