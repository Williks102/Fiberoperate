/** Champ de formulaire : libellé toujours visible, message d'erreur sous le champ. */
export function Field({
  id,
  label,
  hint,
  error,
  children,
}: {
  id: string;
  label: string;
  hint?: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      {children}
      {hint && !error && <p className="text-xs text-slate-700">{hint}</p>}
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}

/**
 * Champ de saisie : 44 px de haut, 16 px de texte (iOS ne zoome pas), anneau de focus visible. Les listes déroulantes
 * gardent leur comportement natif (très bon au doigt) ; leur flèche est dans `globals.css`.
 */
export const inputClass =
  "h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-base text-ink shadow-xs transition-colors placeholder:text-slate-600 hover:border-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:bg-slate-100 disabled:text-slate-600";
