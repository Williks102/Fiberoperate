"use client";

import type { ApiState } from "@/lib/use-api";

/** États communs des listes : squelettes, message vide, erreur avec « Réessayer ». */
export function AsyncList<T>({
  state,
  reload,
  empty,
  children,
}: {
  state: ApiState<{ data: T[] }>;
  reload: () => void;
  empty: string;
  children: (items: T[]) => React.ReactNode;
}) {
  if (state.status === "loading") {
    return (
      <ul aria-busy="true" className="space-y-2">
        {[0, 1].map((i) => (
          <li key={i} className="h-12 animate-pulse rounded-lg bg-slate-200" />
        ))}
      </ul>
    );
  }

  if (state.status === "error") {
    return (
      <div role="alert" className="space-y-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">
        <p>{state.message}</p>
        <button onClick={reload} className="h-11 rounded-lg border border-red-300 px-4 font-medium">
          Réessayer
        </button>
      </div>
    );
  }

  if (state.data.data.length === 0) {
    return <p className="rounded-lg border border-dashed border-slate-300 p-3 text-sm text-slate-700">{empty}</p>;
  }

  return <>{children(state.data.data)}</>;
}
