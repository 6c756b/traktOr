import { translate } from "../i18n/translations";

function parseServerDate(mysqlDatetime: string): Date {
  return new Date(mysqlDatetime.replace(" ", "T"));
}

export function formatRelativeTime(mysqlDatetime: string | null, language: string): string {
  if (!mysqlDatetime) {
    return translate("time.neverWatched", language);
  }

  const diffMs = Date.now() - parseServerDate(mysqlDatetime).getTime();
  const diffMin = Math.round(diffMs / 60_000);
  if (Math.abs(diffMin) < 1) {
    return translate("time.justNow", language);
  }

  const rtf = new Intl.RelativeTimeFormat(language, { numeric: "auto" });
  if (Math.abs(diffMin) < 60) return rtf.format(-diffMin, "minute");

  const diffHours = Math.round(diffMin / 60);
  if (Math.abs(diffHours) < 24) return rtf.format(-diffHours, "hour");

  const diffDays = Math.round(diffHours / 24);
  if (Math.abs(diffDays) < 7) return rtf.format(-diffDays, "day");

  const diffWeeks = Math.round(diffDays / 7);
  if (Math.abs(diffWeeks) < 5) return rtf.format(-diffWeeks, "week");

  const diffMonths = Math.round(diffDays / 30);
  if (Math.abs(diffMonths) < 12) return rtf.format(-diffMonths, "month");

  const diffYears = Math.round(diffDays / 365);
  return rtf.format(-diffYears, "year");
}

export function formatAirDate(mysqlDatetime: string | null, language: string): string {
  if (!mysqlDatetime) {
    return "";
  }
  return parseServerDate(mysqlDatetime).toLocaleDateString(language, {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
}
