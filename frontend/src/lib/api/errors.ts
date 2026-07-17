import { ApiError } from "./client";

type TranslateFn = (key: string, vars?: Record<string, string | number>) => string;

/** Stable backend error codes (see Response::error()) -> i18n key. */
const ERROR_CODE_KEYS: Record<string, string> = {
  wrong_password: "errors.wrongPassword",
  rate_limited: "errors.rateLimited",
  not_authenticated: "errors.notAuthenticated",
  missing_fields: "errors.missingFields",
  invalid_item_type: "errors.invalidItemType",
  show_not_found: "errors.showNotFound",
  movie_not_found: "errors.movieNotFound",
  unsupported_language: "errors.unsupportedLanguage",
  invalid_oauth_state: "errors.invalidOauthState",
  server_error: "errors.generic",
};

/**
 * Translates an ApiError based on its stable code via the i18n dictionary.
 * Unknown codes (or non-ApiError errors) fall back to fallbackKey.
 */
export function apiErrorMessage(e: unknown, fallbackKey: string, t: TranslateFn): string {
  if (e instanceof ApiError && e.code) {
    const key = ERROR_CODE_KEYS[e.code];
    if (key) return t(key);
  }
  return t(fallbackKey);
}
