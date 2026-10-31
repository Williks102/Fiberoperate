/** Repère de la marque : un brin de fibre qui rejoint un point lumineux. */
export function BrandMark({ className = "h-9 w-9" }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" aria-hidden="true" className={`shrink-0 ${className}`}>
      <rect width="32" height="32" rx="9" fill="var(--color-primary)" />
      <path d="M7 22c6 0 6-12 12-12h6" fill="none" stroke="#fff" strokeWidth="2.6" strokeLinecap="round" />
      <circle cx="25" cy="10" r="2.8" fill="var(--color-signal)" />
    </svg>
  );
}

/** Repère et nom de l'application, sur une seule ligne. */
export function BrandName({ className = "" }: { className?: string }) {
  return (
    <span className={`flex items-center gap-2.5 whitespace-nowrap ${className}`}>
      <BrandMark />
      <span className="text-base font-semibold tracking-tight text-ink">FTTH Gestion</span>
    </span>
  );
}
