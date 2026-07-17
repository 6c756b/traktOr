<script lang="ts">
  import { navigate } from "../lib/router";
  import { login } from "../lib/api/auth";
  import { apiErrorMessage } from "../lib/api/errors";
  import { effectiveTheme } from "../lib/stores/settings";
  import { t } from "../lib/i18n";

  let password = $state("");
  let error = $state("");
  let loading = $state(false);
  let passwordInput: HTMLInputElement | undefined;

  $effect(() => {
    passwordInput?.focus();
  });

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    error = "";
    loading = true;
    try {
      await login(password);
      navigate("/", true);
    } catch (e) {
      error = apiErrorMessage(e, "login.loginFailed", $t);
    } finally {
      loading = false;
    }
  }
</script>

<div class="container stack center login-page">
  <form class="card stack gap-m login-form" onsubmit={submit}>
    <img
      class="login-logo"
      src="{import.meta.env.BASE_URL}{$effectiveTheme === 'dark' ? 'logo-dark.svg' : 'logo.svg'}"
      alt="TraktOr"
    />
    <p class="text-muted text-sm login-tagline">{$t("login.tagline")}</p>
    <input
      class="input"
      type="password"
      placeholder={$t("login.passwordPlaceholder")}
      bind:value={password}
      bind:this={passwordInput}
    />
    {#if error}
      <p class="text-danger">{error}</p>
    {/if}
    <button class="btn btn-primary" type="submit" disabled={loading}>
      {loading ? $t("login.starting") : $t("login.submit")}
    </button>
  </form>

  <a class="row gap-xs login-footer" href="https://github.com/6c756b/traktOr" target="_blank" rel="noopener noreferrer">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="9 18 4 12 9 6" />
      <polyline points="15 6 20 12 15 18" />
    </svg>
    {$t("login.footerGithub")}
  </a>
</div>

<style>
  .login-page {
    min-height: 80svh;
    gap: var(--space-l);
  }

  .login-form {
    width: 100%;
    max-width: 320px;
  }

  .login-logo {
    height: 56px;
    width: auto;
    align-self: center;
  }

  .login-tagline {
    text-align: center;
  }

  .login-footer {
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.85rem;
  }

  .login-footer:hover {
    color: var(--text);
  }

  .login-footer svg {
    width: 16px;
    height: 16px;
  }
</style>
