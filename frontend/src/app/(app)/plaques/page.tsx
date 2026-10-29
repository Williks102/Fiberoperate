"use client";

import { useState } from "react";
import { useApi } from "@/lib/use-api";

interface Plaque {
  id: string;
  name: string;
  olt_id: string | null;
  supervisor_id: string | null;
}

interface Page {
  data: Plaque[];
  meta: { current_page: number; last_page: number; total: number };
}

export default function PlaquesPage() {
  const [page, setPage] = useState(1);
  const result = useApi<Page>(`/plaques?per_page=20&page=${page}`);

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Plaques</h1>

      {result.status === "loading" && (
        <ul aria-busy="true" className="space-y-2">
          {[0, 1, 2].map((i) => (
            <li key={i} className="h-14 animate-pulse rounded-xl bg-zinc-200" />
          ))}
        </ul>
      )}

      {result.status === "error" && (
        <div role="alert" className="space-y-3 rounded-xl bg-red-50 p-4 text-sm text-red-800">
          <p>{result.message}</p>
          <button onClick={result.reload} className="h-11 rounded-lg border border-red-300 px-4 font-medium">
            Réessayer
          </button>
        </div>
      )}

      {result.status === "ok" && result.data.data.length === 0 && (
        <p className="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">
          Aucune plaque dans votre périmètre.
        </p>
      )}

      {result.status === "ok" && result.data.data.length > 0 && (
        <>
          <ul className="space-y-2">
            {result.data.data.map((plaque) => (
              <li key={plaque.id} className="rounded-xl border border-zinc-200 bg-white p-4 font-medium">
                {plaque.name}
              </li>
            ))}
          </ul>

          <div className="flex items-center justify-between gap-3">
            <button
              onClick={() => setPage((p) => p - 1)}
              disabled={result.data.meta.current_page <= 1}
              className="h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium disabled:opacity-40"
            >
              Précédent
            </button>
            <span className="text-sm text-zinc-600">
              Page {result.data.meta.current_page} / {result.data.meta.last_page}
            </span>
            <button
              onClick={() => setPage((p) => p + 1)}
              disabled={result.data.meta.current_page >= result.data.meta.last_page}
              className="h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium disabled:opacity-40"
            >
              Suivant
            </button>
          </div>
        </>
      )}
    </div>
  );
}
