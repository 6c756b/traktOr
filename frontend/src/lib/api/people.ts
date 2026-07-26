import { api } from "./client";
import type { SearchResult } from "./search";

export type CastMember = {
  personId: number;
  slug: string;
  name: string;
  character: string;
  photoUrl: string | null;
};

export type PersonDetail = {
  id: number;
  slug: string;
  name: string;
  biography: string | null;
  birthday: string | null;
  birthplace: string | null;
  photoUrl: string | null;
};

export function fetchMovieCast(movieId: number): Promise<CastMember[]> {
  return api.get<CastMember[]>(`/movies/${movieId}/cast`);
}

export function fetchShowCast(showId: number): Promise<CastMember[]> {
  return api.get<CastMember[]>(`/shows/${showId}/cast`);
}

export function fetchPerson(personId: number): Promise<PersonDetail> {
  return api.get<PersonDetail>(`/people/${personId}`);
}

export function fetchPersonCredits(personId: number): Promise<SearchResult[]> {
  return api.get<SearchResult[]>(`/people/${personId}/credits`);
}
