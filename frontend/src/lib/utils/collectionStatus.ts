import type { MovieListItem, ShowListItem } from "../api/library";

export type CollectionStatus = "none" | "ok" | "behind";

/** Movies are a single unit -- either collected or not, no partial state. */
export function movieCollectionStatus(item: MovieListItem): CollectionStatus {
  return item.inCollection ? "ok" : "none";
}

/**
 * Shows are collected per season, so partial collections are normal and not a problem on
 * their own -- someone still working through a show's back catalog is expected to be behind
 * on owning newer seasons. It only becomes "behind" once they've exhausted everything within
 * the season(s) they own and the next thing up is in a season they haven't collected --
 * whether or not they've actually started watching that next season yet. `nextEpisode` (the
 * first unwatched aired episode, in airing order) already encodes "have I finished my
 * collected seasons": if it were still inside a collected season, nextEpisode would point
 * there instead of jumping ahead.
 */
export function showCollectionStatus(item: ShowListItem): CollectionStatus {
  if (item.collectedSeasons.length === 0) {
    return "none";
  }

  const nextEpisode = item.progress?.nextEpisode;
  if (!nextEpisode) {
    return "ok";
  }

  const maxCollectedSeason = Math.max(...item.collectedSeasons);
  return nextEpisode.season > maxCollectedSeason ? "behind" : "ok";
}
