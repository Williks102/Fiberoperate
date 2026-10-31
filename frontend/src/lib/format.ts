/** Date seule « AAAA-MM-JJ » (celle de l'API) au format français : 31/10/2026. */
export function formatDate(day: string | null | undefined): string {
  const match = day?.match(/^(\d{4})-(\d{2})-(\d{2})/);

  return match ? `${match[3]}/${match[2]}/${match[1]}` : "—";
}

/** Date et heure à Abidjan, au format français : 30/10/2026 18:35. */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return "—";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "—";

  return new Intl.DateTimeFormat("fr-FR", {
    timeZone: "Africa/Abidjan",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  })
    .format(date)
    .replace(",", "");
}

/** Montant en FCFA entiers, groupé par milliers : « 150 000 FCFA ». */
export function formatFcfa(amount: number | null | undefined): string {
  return amount == null ? "—" : `${new Intl.NumberFormat("fr-FR").format(amount).replace(/[\u202f\u00a0]/g, " ")} FCFA`;
}
