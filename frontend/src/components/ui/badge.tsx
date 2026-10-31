export type Tone = "neutral" | "info" | "success" | "warning" | "danger";

const TONES: Record<Tone, { box: string; dot: string }> = {
  neutral: { box: "bg-slate-100 text-slate-700", dot: "bg-slate-400" },
  info: { box: "bg-primary-soft text-primary-ink", dot: "bg-primary" },
  success: { box: "bg-emerald-50 text-emerald-800", dot: "bg-emerald-600" },
  warning: { box: "bg-amber-50 text-amber-900", dot: "bg-amber-500" },
  danger: { box: "bg-red-50 text-red-800", dot: "bg-red-600" },
};

/** Pastille d'état : du texte toujours, la couleur et le point ne font que renforcer (jamais l'information seule). */
export function Badge({ children, tone = "neutral" }: { children: React.ReactNode; tone?: Tone }) {
  const { box, dot } = TONES[tone];

  return (
    <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${box}`}>
      <span aria-hidden="true" className={`h-1.5 w-1.5 rounded-full ${dot}`} />
      {children}
    </span>
  );
}
