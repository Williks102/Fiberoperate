"use client";

import { useParams } from "next/navigation";
import { PcoForm } from "@/components/pco-form";
import { PcoMeasurePanel } from "@/components/pco-measure-panel";
import { useAuth } from "@/lib/auth/auth-context";
import type { Pco } from "@/lib/types";
import { useApi } from "@/lib/use-api";

export default function ModifierPcoPage() {
  const { id } = useParams<{ id: string }>();
  const result = useApi<{ data: Pco }>(`/pcos/${id}`);
  const { user } = useAuth();
  const skills = user?.skills ?? [];
  // Le BE et l'administrateur modifient le PCO ; les techniciens « relevés » n'enregistrent que la position mesurée.
  const canEdit = user?.role === "admin" || user?.role === "be_manager" || (user?.role === "technician" && skills.includes("drawing"));
  const canMeasure = canEdit || (user?.role === "technician" && skills.includes("survey"));

  return (
    <div className="max-w-xl space-y-5">
      <h1 className="text-xl font-semibold">Modifier le PCO</h1>

      {result.status === "loading" && <div aria-busy="true" className="h-40 animate-pulse rounded-xl bg-zinc-200" />}

      {result.status === "error" && (
        <div role="alert" className="space-y-3 rounded-xl bg-red-50 p-4 text-sm text-red-800">
          <p>{result.message}</p>
          <button onClick={result.reload} className="h-11 rounded-lg border border-red-300 px-4 font-medium">
            Réessayer
          </button>
        </div>
      )}

      {result.status === "ok" && (
        <>
          <PcoMeasurePanel pco={result.data.data} canMeasure={canMeasure} />
          {canEdit && <PcoForm pco={result.data.data} />}
        </>
      )}
    </div>
  );
}
