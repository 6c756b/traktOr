import { api } from "./client";

export type LanguageOption = { code: string; label: string };
/** null means "follow the OS setting" -- the user hasn't flipped the switch yet. */
export type Theme = "light" | "dark" | null;

export type Settings = {
  language: string;
  availableLanguages: LanguageOption[];
  theme: Theme;
};

export function fetchSettings(): Promise<Settings> {
  return api.get<Settings>("/settings");
}

export function updateLanguage(language: string): Promise<{ language: string }> {
  return api.post<{ language: string }>("/settings", { language });
}

export function updateTheme(theme: Theme): Promise<{ theme: Theme }> {
  return api.post<{ theme: Theme }>("/settings", { theme });
}
