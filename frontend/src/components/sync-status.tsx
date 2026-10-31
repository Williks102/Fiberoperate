"use client";

import { Badge } from "@/components/ui/badge";
import { formatDateTime } from "@/lib/format";

/** État du réseau et de la copie locale : en ligne (à jour à telle heure) ou hors ligne (données de telle heure). */
export function SyncStatus({
  online,
  syncing,
  lastSyncAt,
  error,
  pending = 0,
  pendingPhotos = 0,
  onRefresh,
}: {
  online: boolean;
  syncing: boolean;
  lastSyncAt: string | null;
  error: string | null;
  /** Résultats saisis sur ce téléphone et pas encore confirmés par le serveur. */
  pending?: number;
  /** Photos prises sur ce téléphone et pas encore enregistrées par le serveur. */
  pendingPhotos?: number;
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
      {pending > 0 && (
        <p className="text-sm font-medium text-amber-900">
          {pending} résultat{pending > 1 ? "s" : ""} en attente d&apos;envoi : {pending > 1 ? "ils partiront" : "il partira"} automatiquement dès que le réseau est là.
        </p>
      )}
      {pendingPhotos > 0 && (
        <p className="text-sm font-medium text-amber-900">
          {pendingPhotos} photo{pendingPhotos > 1 ? "s" : ""} en attente d&apos;envoi : {pendingPhotos > 1 ? "elles partiront" : "elle partira"} dès que le réseau est assez bon.
        </p>
      )}
      {error && <p className="text-sm text-amber-900">{error}</p>}
    </div>
  );
}
