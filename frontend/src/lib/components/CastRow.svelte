<script lang="ts">
  import { link } from "../router";
  import { t } from "../i18n";
  import type { CastMember } from "../api/people";

  let { cast }: { cast: CastMember[] } = $props();
</script>

{#if cast.length > 0}
  <div class="cast-row">
    {#each cast as member (member.personId)}
      <a href={`/person/${member.personId}`} use:link class="cast-member stack gap-xs">
        <div class="cast-photo">
          {#if member.photoUrl}
            <img src={member.photoUrl} alt={$t("detail.castPhotoAlt", { name: member.name })} loading="lazy" />
          {:else}
            <div class="cast-photo-fallback text-muted">{member.name}</div>
          {/if}
        </div>
        <p class="m-0 cast-name">{member.name}</p>
        {#if member.character}<p class="m-0 text-muted text-sm cast-character">{member.character}</p>{/if}
      </a>
    {/each}
  </div>
{/if}

<style>
  .cast-row {
    display: flex;
    gap: var(--space-m);
    overflow-x: auto;
    padding-bottom: var(--space-xs);
  }

  .cast-member {
    flex: 0 0 auto;
    width: 120px;
    text-decoration: none;
    color: inherit;
  }

  .cast-photo {
    aspect-ratio: 2 / 3;
    border-radius: var(--radius-s);
    background: var(--border);
    overflow: hidden;
  }

  .cast-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .cast-photo-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: var(--space-s);
    font-size: 0.85rem;
  }

  .cast-name {
    font-size: 0.9rem;
    font-weight: 500;
  }

  .cast-character {
    font-size: 0.8rem;
  }
</style>
