import { derived } from "svelte/store";
import { language } from "../stores/settings";
import { translate } from "./translations";

/** Reactive translator: {$t('key')} or {$t('key', {var: value})} in templates. */
export const t = derived(language, ($language) => {
  return (key: string, vars?: Record<string, string | number>) => translate(key, $language, vars);
});
