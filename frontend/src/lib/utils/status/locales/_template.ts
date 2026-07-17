/**
 * Template for a new language's status translations. Here's how to add one:
 *
 * 1. Copy this file and name it after the ISO 639-1 language code (e.g. "fr.ts" for
 *    French) -- the filename without ".ts" automatically becomes the language code,
 *    matching frontend/src/lib/i18n/locales/.
 * 2. Translate whichever values you want (the text to the right of the ":"). Do NOT
 *    change the keys (left side) -- they're Trakt's status values.
 * 3. Incomplete is fine, and so is skipping this file entirely: any status missing from
 *    the table automatically falls back to a title-cased version of the value itself
 *    (see translateStatus() in ../../index.ts).
 * 4. This file itself (_template.ts) is ignored -- the filename doesn't match the
 *    language-code pattern.
 */
export default {
  "returning series": "Returning Series",
  "in production": "In Production",
  planned: "Planned",
  upcoming: "Upcoming",
  canceled: "Canceled",
  ended: "Ended",
  released: "Released",
  "post production": "Post Production",
  rumored: "Rumored",
};
