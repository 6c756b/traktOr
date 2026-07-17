/**
 * Automatically loads every language file from ./locales/*.ts, same mechanism as
 * frontend/src/lib/i18n/translations.ts -- adding a new language means dropping a new
 * file into locales/ (following the example of locales/_template.ts), nothing else.
 */
const localeModules = import.meta.glob<{ default: Record<string, string> }>("./locales/*.ts", {
  eager: true,
});

const GENRE_TRANSLATIONS: Record<string, Record<string, string>> = {};
for (const path in localeModules) {
  const code = path.match(/\/([a-z]{2}(?:-[A-Z]{2})?)\.ts$/)?.[1];
  if (code) {
    GENRE_TRANSLATIONS[code] = localeModules[path].default;
  }
}

/** Displays Trakt genre slugs localized. The underlying filter value stays English. */
export function translateGenre(slug: string, language: string): string {
  const table = GENRE_TRANSLATIONS[language];
  return table?.[slug] ?? slug.charAt(0).toUpperCase() + slug.slice(1).replace(/-/g, " ");
}
