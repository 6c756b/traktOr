/**
 * Automatically loads every language file from ./locales/*.ts -- adding a new language
 * simply means dropping a new file into locales/ (following the example of locales/en.ts),
 * nothing else. 'en' is the guaranteed base (must be complete), every other language is
 * allowed to be incomplete -- missing keys automatically fall back to English, see translate().
 *
 * eager:true instead of true lazy loading, because the dictionaries are tiny (a few KB per
 * language) -- the bundle-size benefit of real lazy loading would be negligible here, and
 * eager keeps {$t(...)} usable synchronously like everywhere else in the codebase.
 */
const localeModules = import.meta.glob<{ default: Record<string, string> }>("./locales/*.ts", {
  eager: true,
});

export const translations: Record<string, Record<string, string>> = {};
for (const path in localeModules) {
  const code = path.match(/\/([a-z]{2}(?:-[A-Z]{2})?)\.ts$/)?.[1];
  if (code) {
    translations[code] = localeModules[path].default;
  }
}

export function translate(key: string, language: string, vars?: Record<string, string | number>): string {
  const template = translations[language]?.[key] ?? translations.en[key] ?? key;
  if (!vars) return template;
  return Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`{${k}}`, String(v)), template);
}
