import { derived, readable, writable } from "svelte/store";
import type { LanguageOption, Theme } from "../api/settings";

// Best guess before login (browser language) -- the actual saved
// setting overwrites this after fetchSettings() in App.svelte.
const browserLanguage = typeof navigator !== "undefined" ? navigator.language.split("-")[0] : "en";

export const language = writable(browserLanguage || "en");
export const availableLanguages = writable<LanguageOption[]>([]);

// null before login / before fetchSettings() resolves, and stays null until the user
// actually flips the switch in Settings -- effectiveTheme below then falls back to the OS
// setting on its own.
export const theme = writable<Theme>(null);

const systemPrefersDark = readable(
  typeof matchMedia !== "undefined" && matchMedia("(prefers-color-scheme: dark)").matches,
  (set) => {
    if (typeof matchMedia === "undefined") return;
    const query = matchMedia("(prefers-color-scheme: dark)");
    const onChange = (e: MediaQueryListEvent) => set(e.matches);
    query.addEventListener("change", onChange);
    return () => query.removeEventListener("change", onChange);
  }
);

/** "light" | "dark", resolving the explicit user choice against the live OS setting --
 *  used anywhere the app needs to pick an asset/behavior per theme in JS (CSS itself
 *  reacts via the data-theme attribute App.svelte sets from `theme` directly). */
export const effectiveTheme = derived(
  [theme, systemPrefersDark],
  ([$theme, $systemPrefersDark]) => $theme ?? ($systemPrefersDark ? "dark" : "light")
);
