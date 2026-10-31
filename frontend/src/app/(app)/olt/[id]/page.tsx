"use client";

import { useParams } from "next/navigation";
import { OltForm } from "@/components/olt-form";
import type { Olt } from "@/lib/types";
import { useApi } from "@/lib/use-api";

export default function ModifierOltPage() {
  const { id } = useParams<{ id: string }>();
  const result = useApi<{ data: Olt }>(`/olts/${id}`);

  return (
    <div className="max-w-xl space-y-4">
      <h1 className="text-xl font-semibold">Modifier l&apos;OLT</h1>

      {result.status === "loading" && <div aria-busy="true" className="h-40 animate-pulse rounded-xl bg-slate-200" />}

      {result.status === "error" && (
        <div role="alert" className="space-y-3 rounded-xl bg-red-50 p-4 text-sm text-red-800">
          <p>{result.message}</p>
          <button onClick={result.reload} className="h-11 rounded-lg border border-red-300 px-4 font-medium">
            Réessayer
          </button>
        </div>
      )}

      {result.status === "ok" && <OltForm olt={result.data.data} />}
    </div>
  );
}
