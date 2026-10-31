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

/** Nom d'un client : un client importé avec son seul ID client n'a pas encore de nom. */
export function clientName(name: string | null | undefined): string {
  return name?.trim() ? name : "Client sans nom";
}

/** Jour d'une visite par rapport à aujourd'hui (jour local) : « En retard », « Aujourd'hui », « Demain » ou la date. */
export function visitDay(day: string, today: Date = new Date()): { label: string; late: boolean } {
  const match = day.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return { label: "—", late: false };

  const planned = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  const now = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  const days = Math.round((planned - now) / 86_400_000);

  if (days < 0) return { label: `En retard (${formatDate(day)})`, late: true };
  if (days === 0) return { label: "Aujourd'hui", late: false };
  if (days === 1) return { label: "Demain", late: false };

  return { label: formatDate(day), late: false };
}
