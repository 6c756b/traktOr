/**
 * Template for a new language's genre translations. Here's how to add one:
 *
 * 1. Copy this file and name it after the ISO 639-1 language code (e.g. "fr.ts" for
 *    French) -- the filename without ".ts" automatically becomes the language code,
 *    matching frontend/src/lib/i18n/locales/.
 * 2. Translate whichever values you want (the text to the right of the ":"). Do NOT
 *    change the keys (left side) -- they're Trakt's genre slugs.
 * 3. Incomplete is fine, and so is skipping this file entirely: any slug missing from
 *    the table automatically falls back to a title-cased, dash-to-space version of the
 *    slug itself (see translateGenre() in ../../index.ts) -- often readable enough on
 *    its own for languages close to English.
 * 4. This file itself (_template.ts) is ignored -- the filename doesn't match the
 *    language-code pattern.
 */
export default {
  action: "Action",
  adventure: "Adventure",
  animation: "Animation",
  anime: "Anime",
  comedy: "Comedy",
  crime: "Crime",
  documentary: "Documentary",
  drama: "Drama",
  eastern: "Eastern",
  family: "Family",
  fantasy: "Fantasy",
  "film-noir": "Film-Noir",
  food: "Food",
  "game-show": "Game Show",
  history: "History",
  holiday: "Holiday",
  horror: "Horror",
  indie: "Indie",
  "mini-series": "Mini Series",
  music: "Music",
  musical: "Musical",
  mystery: "Mystery",
  news: "News",
  none: "No Genre",
  reality: "Reality TV",
  "road-movie": "Road Movie",
  romance: "Romance",
  "science-fiction": "Science Fiction",
  short: "Short",
  soap: "Soap",
  "special-interest": "Special Interest",
  sport: "Sport",
  "sporting-event": "Sporting Event",
  superhero: "Superhero",
  suspense: "Suspense",
  "talk-show": "Talk Show",
  thriller: "Thriller",
  travel: "Travel",
  war: "War",
  western: "Western",
};
