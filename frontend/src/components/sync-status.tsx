"use client";

import { Badge } from "@/components/ui/badge";
import { formatDateTime } from "@/lib/format";

/** État du réseau et de la copie locale : en ligne (à jour à telle heure) ou hors ligne (données de telle heure). */
export function SyncStatus({
  online,
  syncing,
  lastSyncAt,
  error,
  onRefresh,
}: {
  online: boolean;
  syncing: boolean;
  lastSyncAt: string | null;
  error: string | null;
  onRefresh: () => void;
}) {
  return (
    <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-3" role="status">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={online ? "success" : "warning"}>{online ? "En ligne" : "Hors ligne"}</Badge>
          <span className="text-sm text-slate-700">
            {syncing ? "Mise à jour…" : lastSyncAt ? `${online ? "Mis à jour" : "Données du"} ${formatDateTime(lastSyncAt)}` : "Pas encore de données sur ce téléphone"}
          </span>
        </div>
        <button type="button" onClick={onRefresh} disabled={!online || syncing} className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium disabled:opacity-60">
          Actualiser
        </button>
      </div>
      {error && <p className="text-sm text-amber-900">{error}</p>}
    </div>
  );
}
