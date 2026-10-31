"use client";

import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { Pager } from "@/components/pager";
import { Badge, type Tone } from "@/components/ui/badge";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { useApi } from "@/lib/use-api";

interface Counts {
  ok: number;
  to_fix: number;
  rejected: number;
  create: number;
  update: number;
  rows: number;
  result?: { created: number; updated: number; excluded: number; rejected: number };
}

interface Batch {
  id: string;
  file_name: string;
  status: "analysed" | "validated";
  counts: Counts;
}

interface Row {
  id: string;
  line: number;
  status: "ok" | "to_fix" | "rejected";
  action: "create" | "update" | "none";
  excluded: boolean;
  customer_ref: string | null;
  name: string | null;
  anomalies: { level: "info" | "warning" | "error"; message: string }[];
}

type Page = { data: Batch & { rows: Row[] }; meta: { current_page: number; last_page: number; total: number } };

const STATUS: Record<Row["status"], { label: string; tone: Tone }> = {
  ok: { label: "OK", tone: "success" },
  to_fix: { label: "À corriger", tone: "warning" },
  rejected: { label: "Refusée", tone: "danger" },
};
const LEVEL: Record<Row["anomalies"][number]["level"], string> = { info: "Information", warning: "À corriger", error: "Erreur" };
const FILTERS: { value: string; label: string }[] = [
  { value: "", label: "Toutes" },
  { value: "to_fix", label: "À corriger" },
  { value: "rejected", label: "Refusées" },
  { value: "ok", label: "OK" },
];

/** Import des clients en deux temps : on analyse le fichier, on relit les lignes (on peut en exclure), puis on importe. */
export function CustomerImport() {
  const [batch, setBatch] = useState<Batch | null>(null);

  return (
    <div className="max-w-4xl space-y-5">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">Importer les clients</h1>
        <p className="text-sm text-slate-700">
          Un fichier CSV, une ligne par client. Rien n&apos;est créé avant votre validation ; un ID client déjà connu est mis à jour.{" "}
          <a href="/modeles-import/clients.csv" download className="underline">
            Télécharger le modèle
          </a>
        </p>
      </div>
      {batch === null ? <Upload onAnalysed={setBatch} /> : <Review initial={batch} onRestart={() => setBatch(null)} />}
    </div>
  );
}

function Upload({ onAnalysed }: { onAnalysed: (batch: Batch) => void }) {
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function analyse() {
    if (!file) return;
    setError(null);
    setBusy(true);
    try {
      const form = new FormData();
      form.append("file", file);
      const res = await apiFetch<{ data: Batch }>("/customer-imports", { method: "POST", body: form }, getToken());
      onAnalysed(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Une erreur est survenue.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="space-y-1.5">
        <label htmlFor="clients-file" className="text-sm font-medium">
          Fichier CSV des clients
        </label>
        <input id="clients-file" type="file" accept=".csv,text/csv,text/plain" onChange={(e) => (setFile(e.target.files?.[0] ?? null), setError(null))} className="block w-full rounded-xl border border-slate-300 bg-white p-2 text-base" />
        <p className="text-xs text-slate-700">Excel : « Enregistrer sous », puis « CSV ». 4 Mo au plus, 5 000 lignes.</p>
      </div>
      {error && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {error}
        </p>
      )}
      <button onClick={analyse} disabled={!file || busy} className="h-11 rounded-xl bg-primary px-6 font-semibold text-white hover:bg-primary-hover disabled:opacity-60">
        {busy ? "Analyse…" : "Analyser le fichier"}
      </button>
    </section>
  );
}

function Review({ initial, onRestart }: { initial: Batch; onRestart: () => void }) {
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { reload, ...state } = useApi<Page>(`/customer-imports/${initial.id}?per_page=20&page=${page}${status ? `&status=${status}` : ""}`);

  const batch = state.status === "ok" ? state.data.data : initial;
  // AsyncList attend { data: lignes[], meta } : on présente les lignes du lot sous cette forme.
  const listState = state.status === "ok" ? { status: "ok" as const, data: { data: state.data.data.rows, meta: state.data.meta } } : state;
  const validated = batch.status === "validated";
  const counts = batch.counts;

  async function toggle(row: Row) {
    setError(null);
    try {
      await apiFetch(`/customer-imports/${initial.id}/rows/${row.id}`, { method: "PATCH", body: JSON.stringify({ excluded: !row.excluded }) }, getToken());
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Une erreur est survenue.");
    }
  }

  async function validate() {
    setError(null);
    setBusy(true);
    try {
      await apiFetch(`/customer-imports/${initial.id}/validate`, { method: "POST" }, getToken());
      setConfirming(false);
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Une erreur est survenue.");
    } finally {
      setBusy(false);
    }
  }

  const tiles: [string, number, string][] = [
    ["Lignes lues", counts.rows, ""],
    ["OK", counts.ok, "text-emerald-800"],
    ["À corriger", counts.to_fix, "text-amber-900"],
    ["Refusées", counts.rejected, "text-red-800"],
  ];

  return (
    <div className="space-y-4">
      <section aria-label="Résumé de l'analyse" className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p className="text-sm text-slate-700">
          Fichier <span className="font-medium text-ink">{batch.file_name}</span> · {counts.create} création(s), {counts.update} mise(s) à jour
        </p>
        <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          {tiles.map(([label, value, color]) => (
            <div key={label} className="rounded-xl border border-slate-200 p-3">
              <dt className="text-xs text-slate-700">{label}</dt>
              <dd className={`text-xl font-semibold tabular-nums ${color}`}>{value}</dd>
            </div>
          ))}
        </dl>
      </section>

      {validated ? (
        <section role="status" className="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
          <p className="font-semibold">Import terminé</p>
          <p>
            {counts.result?.created ?? 0} commande(s) créée(s), {counts.result?.updated ?? 0} mise(s) à jour, {counts.result?.excluded ?? 0} exclue(s), {counts.result?.rejected ?? 0} refusée(s).
          </p>
          <button onClick={onRestart} className="h-11 rounded-xl border border-emerald-300 bg-white px-4 font-medium">
            Importer un autre fichier
          </button>
        </section>
      ) : (
        <>
          <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrer les lignes">
            {FILTERS.map((f) => (
              <button
                key={f.value}
                onClick={() => (setStatus(f.value), setPage(1))}
                aria-pressed={status === f.value}
                className={`h-11 rounded-xl border px-4 text-sm font-medium ${status === f.value ? "border-primary bg-primary-soft text-primary-ink" : "border-slate-300 bg-white text-slate-700"}`}
              >
                {f.label}
              </button>
            ))}
          </div>

          {error && (
            <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
              {error}
            </p>
          )}

          <AsyncList state={listState} reload={reload} empty="Aucune ligne dans ce filtre.">
            {(rows) => (
              <>
                <ul className="space-y-2">
                  {rows.map((row) => (
                    <RowCard key={row.id} row={row} onToggle={() => toggle(row)} />
                  ))}
                </ul>
                {listState.status === "ok" && <Pager page={listState.data.meta.current_page} lastPage={listState.data.meta.last_page} onChange={setPage} />}
              </>
            )}
          </AsyncList>

          <div className="sticky bottom-16 z-10 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur md:bottom-4">
            {confirming ? (
              <>
                <p className="flex-1 text-sm">Les lignes OK et à corriger non exclues seront créées ou mises à jour. Cette opération ne peut pas être annulée depuis l&apos;application.</p>
                <button onClick={validate} disabled={busy} className="h-11 rounded-xl bg-primary px-6 font-semibold text-white hover:bg-primary-hover disabled:opacity-60">
                  {busy ? "Import en cours…" : "Oui, importer"}
                </button>
                <button onClick={() => setConfirming(false)} disabled={busy} className="h-11 rounded-xl border border-slate-300 px-4 font-medium">
                  Annuler
                </button>
              </>
            ) : (
              <>
                <button onClick={() => setConfirming(true)} disabled={counts.ok + counts.to_fix === 0} className="h-11 flex-1 rounded-xl bg-primary px-4 font-semibold text-white hover:bg-primary-hover disabled:opacity-60 sm:flex-none sm:px-6">
                  Importer les clients
                </button>
                <button onClick={onRestart} className="h-11 rounded-xl border border-slate-300 px-4 font-medium">
                  Autre fichier
                </button>
              </>
            )}
          </div>
        </>
      )}
    </div>
  );
}

function RowCard({ row, onToggle }: { row: Row; onToggle: () => void }) {
  const { label, tone } = STATUS[row.status];

  return (
    <li className={`space-y-2 rounded-xl border bg-white p-4 text-sm ${row.excluded ? "border-slate-200 opacity-70" : "border-slate-200"}`}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="font-medium">
          <span className="mr-2 text-slate-700">Ligne {row.line}</span>
          {row.customer_ref ?? "—"} · {row.name ?? "—"}
        </p>
        <span className="flex flex-wrap items-center gap-2">
          <Badge tone={tone}>{label}</Badge>
          {row.action !== "none" && <Badge tone="info">{row.action === "create" ? "Création" : "Mise à jour"}</Badge>}
          {row.excluded && <Badge tone="neutral">Exclue</Badge>}
        </span>
      </div>
      {row.anomalies.length > 0 && (
        <ul className="space-y-1">
          {row.anomalies.map((a, i) => (
            <li key={i} className={a.level === "error" ? "text-red-800" : a.level === "warning" ? "text-amber-900" : "text-slate-700"}>
              <span className="font-medium">{LEVEL[a.level]} :</span> {a.message}
            </li>
          ))}
        </ul>
      )}
      {row.status !== "rejected" && (
        <button onClick={onToggle} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
          {row.excluded ? "Reprendre cette ligne" : "Exclure de l'import"}
        </button>
      )}
    </li>
  );
}
