<script lang="ts">
  import { theme, effectiveTheme } from "../stores/settings";
  import { updateTheme } from "../api/settings";
  import { toasts } from "../stores/toast";
  import { t } from "../i18n";

  let saving = $state(false);

  async function toggle() {
    if (saving) return;
    const next = $effectiveTheme === "dark" ? "light" : "dark";
    const previous = $theme;
    theme.set(next);
    saving = true;
    try {
      await updateTheme(next);
    } catch {
      theme.set(previous);
      toasts.push($t("settings.themeError"), "error");
    } finally {
      saving = false;
    }
  }
</script>

<button
  type="button"
  role="switch"
  aria-checked={$effectiveTheme === "dark"}
  aria-label={$t("settings.appearance")}
  class="switch"
  disabled={saving}
  onclick={toggle}
>
  <span class="switch-track">
    <svg class="switch-icon switch-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" />
    </svg>
    <svg class="switch-icon switch-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z" />
    </svg>
    <span class="switch-knob"></span>
  </span>
</button>

<style>
  .switch {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    min-height: 44px;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    border-radius: var(--radius-s);
  }

  .switch:disabled {
    cursor: default;
    opacity: 0.7;
  }

  .switch-track {
    position: relative;
    display: flex;
    align-items: center;
    width: 56px;
    height: 30px;
    padding: 0 6px;
    border-radius: 999px;
    background: var(--border);
    transition: background-color var(--transition-base) ease;
  }

  .switch[aria-checked="true"] .switch-track {
    background: var(--primary);
  }

  .switch-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
  }

  .switch-icon-sun {
    color: #f59e0b;
    margin-right: auto;
  }

  .switch-icon-moon {
    color: #fff;
    margin-left: auto;
  }

  .switch-knob {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    transition: transform var(--transition-base) ease;
  }

  .switch[aria-checked="true"] .switch-knob {
    transform: translateX(26px);
  }
</style>
