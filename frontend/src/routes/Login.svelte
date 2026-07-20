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
    <p class="login-intro">{$t("login.intro")}</p>
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
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M10.226 17.284c-2.965-.36-5.054-2.493-5.054-5.256 0-1.123.404-2.336 1.078-3.144-.292-.741-.247-2.314.09-2.965.898-.112 2.111.36 2.83 1.01.853-.269 1.752-.404 2.853-.404 1.1 0 1.999.135 2.807.382.696-.629 1.932-1.1 2.83-.988.315.606.36 2.179.067 2.942.72.854 1.101 2 1.101 3.167 0 2.763-2.089 4.852-5.098 5.234.763.494 1.28 1.572 1.28 2.807v2.336c0 .674.561 1.056 1.235.786 4.066-1.55 7.255-5.615 7.255-10.646C23.5 6.188 18.334 1 11.978 1 5.62 1 .5 6.188.5 12.545c0 4.986 3.167 9.12 7.435 10.669.606.225 1.19-.18 1.19-.786V20.63a2.9 2.9 0 0 1-1.078.224c-1.483 0-2.359-.808-2.987-2.313-.247-.607-.517-.966-1.034-1.033-.27-.023-.359-.135-.359-.27 0-.27.45-.471.898-.471.652 0 1.213.404 1.797 1.235.45.651.921.943 1.483.943.561 0 .92-.202 1.437-.719.382-.381.674-.718.944-.943" />
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

  .login-intro {
    text-align: center;
    font-size: 0.95rem;
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
