"use client";

/** Pagination : mêmes grands boutons sur téléphone et PC. */
export function Pager({
  page,
  lastPage,
  onChange,
}: {
  page: number;
  lastPage: number;
  onChange: (page: number) => void;
}) {
  return (
    <div className="flex items-center justify-between gap-3">
      <button
        onClick={() => onChange(page - 1)}
        disabled={page <= 1}
        className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium disabled:opacity-60"
      >
        Précédent
      </button>
      <span className="text-sm text-slate-700">
        Page {page} / {lastPage}
      </span>
      <button
        onClick={() => onChange(page + 1)}
        disabled={page >= lastPage}
        className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium disabled:opacity-60"
      >
        Suivant
      </button>
    </div>
  );
}
