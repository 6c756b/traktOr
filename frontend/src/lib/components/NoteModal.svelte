<script lang="ts">
  import { saveNote, type NoteItemType } from "../api/notes";
  import { apiErrorMessage } from "../api/errors";
  import { toasts } from "../stores/toast";
  import { t } from "../i18n";

  let {
    open,
    itemType,
    id,
    note = $bindable(),
    onClose,
  }: {
    open: boolean;
    itemType: NoteItemType;
    id: number;
    note: string | null;
    onClose: () => void;
  } = $props();

  let draft = $state(note ?? "");
  let saving = $state(false);

  // Re-seed the draft from the current note every time the modal opens, so a cancelled
  // edit from a previous open doesn't leak into the next one.
  $effect(() => {
    if (open) {
      draft = note ?? "";
    }
  });

  function handleWindowKeydown(e: KeyboardEvent) {
    if (open && e.key === "Escape") {
      onClose();
    }
  }

  async function handleSave() {
    saving = true;
    try {
      await saveNote(itemType, id, draft);
      note = draft.trim() || null;
      onClose();
    } catch (e) {
      toasts.push(apiErrorMessage(e, "notes.saveError", $t), "error");
    } finally {
      saving = false;
    }
  }
</script>

<svelte:window onkeydown={handleWindowKeydown} />

{#if open}
  <div class="dialog-backdrop">
    <button
      type="button"
      class="dialog-backdrop-close"
      onclick={onClose}
      aria-label={$t("common.cancel")}
    ></button>
    <div
      class="card stack gap-m dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="note-modal-title"
      tabindex="-1"
    >
      <h2 id="note-modal-title" class="m-0 card-subtitle">{$t("notes.label")}</h2>
      <textarea
        class="input note-textarea"
        placeholder={$t("notes.placeholder")}
        maxlength="1000"
        bind:value={draft}
      ></textarea>
      <div class="row gap-s dialog-actions">
        <button type="button" class="btn btn-secondary" onclick={onClose}>{$t("common.cancel")}</button>
        <button type="button" class="btn btn-primary" disabled={saving} onclick={handleSave}>{$t("notes.save")}</button>
      </div>
    </div>
  </div>
{/if}

<style>
  .dialog-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    padding: var(--space-l);
  }

  .dialog-backdrop-close {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: none;
    background: none;
    padding: 0;
    cursor: default;
  }

  .dialog {
    position: relative;
    max-width: 420px;
    width: 100%;
  }

  .dialog-actions {
    justify-content: flex-end;
  }

  .note-textarea {
    min-height: 120px;
    resize: vertical;
    font: inherit;
  }
</style>
