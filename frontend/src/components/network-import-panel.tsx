"use client";

import Link from "next/link";
import { useState } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";

interface Issue {
  line: number;
  message: string;
}

interface Report {
  committed: boolean;
  rows: number;
  created: { olts: number; zones: number; sros: number; jdvs: number; pcos: number };
  already_present: number;
  rejected: Issue[];
  warnings: Issue[];
}

const SHOWN = 50;

function IssueList({ title, tone, issues }: { title: string; tone: "red" | "amber"; issues: Issue[] }) {
  if (issues.length === 0) return null;
  const colors = tone === "red" ? "bg-red-50 text-red-900" : "bg-amber-50 text-amber-900";

  return (
    <section className={`space-y-2 rounded-xl p-4 text-sm ${colors}`}>
      <h3 className="font-semibold">
        {title} ({issues.length})
      </h3>
      <ul className="space-y-1">
        {issues.slice(0, SHOWN).map((issue, i) => (
          <li key={i}>
            <span className="font-medium">Ligne {issue.line} :</span> {issue.message}
          </li>
        ))}
      </ul>
      {issues.length > SHOWN && <p>… et {issues.length - SHOWN} autres. Corrigez le fichier et vérifiez-le de nouveau.</p>}
    </section>
  );
}

/** Import CSV du réseau d'un projet : un fichier, une seule plaque (celle du projet). */
export function NetworkImportPanel({ projectId, onImported }: { projectId: string; onImported: () => void }) {
  const [file, setFile] = useState<File | null>(null);
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<"preview" | "import" | null>(null);
  const [confirming, setConfirming] = useState(false);

  async function send(mode: "preview" | "import") {
    if (!file) return;
    setError(null);
    setBusy(mode);
    try {
      const form = new FormData();
      form.append("file", file);
      const res = await apiFetch<{ data: Report }>(mode === "preview" ? `/projects/${projectId}/imports/network/preview` : `/projects/${projectId}/imports/network`, { method: "POST", body: form }, getToken());
      setReport(res.data);
      if (res.data.committed) onImported();
      setConfirming(false);
    } catch (e) {
      setReport(null);
      setError(e instanceof ApiError ? e.message : "Une erreur est survenue.");
    } finally {
      setBusy(null);
    }
  }

  const toCreate = report ? Object.values(report.created).reduce((a, b) => a + b, 0) : 0;
  const imported = report?.committed === true;

  return (
    <section aria-labelledby="import" className="space-y-4 rounded-xl border border-zinc-200 bg-white p-4">
      <div className="space-y-1">
        <h2 id="import" className="text-lg font-semibold">
          Importer le réseau (CSV)
        </h2>
        <p className="text-sm text-zinc-700">
          Un fichier CSV, une ligne par PCO : sous-plaque, SRO, splitter, PCO… Une seule plaque par fichier : celle du projet (la colonne « plaque » peut rester vide). Les éléments déjà connus ne sont jamais modifiés.{" "}
          <a href="/modeles-import/reseau.csv" download className="underline">
            Télécharger le modèle
          </a>
        </p>
      </div>

      <div className="space-y-1">
        <label htmlFor="file" className="text-sm font-medium">
          Fichier CSV
        </label>
        <input
          id="file"
          type="file"
          accept=".csv,text/csv,text/plain"
          onChange={(e) => {
            setFile(e.target.files?.[0] ?? null);
            setReport(null);
            setError(null);
            setConfirming(false);
          }}
          className="block w-full rounded-lg border border-zinc-300 bg-white p-2 text-base"
        />
      </div>

      <button
        onClick={() => send("preview")}
        disabled={!file || busy !== null}
        className="h-11 rounded-lg border border-zinc-300 px-4 font-medium disabled:opacity-60"
      >
        {busy === "preview" ? "Vérification…" : "Vérifier le fichier"}
      </button>

      {error && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {error}
        </p>
      )}

      {report && (
        <div className="space-y-3">
          <section role="status" className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
            <h2 className="font-semibold">{imported ? "Import terminé" : "Résultat de la vérification (rien n'est encore enregistré)"}</h2>
            <p>{report.rows} lignes lues.</p>
            <p>
              {imported ? "Créés" : "À créer"} : {report.created.zones} sous-plaque(s), {report.created.sros} SRO, {report.created.jdvs} splitter(s), {report.created.pcos} PCO
              {report.created.olts > 0 && `, ${report.created.olts} OLT`}.
            </p>
            {report.already_present > 0 && <p>{report.already_present} PCO déjà présent(s) : laissés tels quels.</p>}
            {report.rejected.length > 0 && <p className="font-medium text-red-800">{report.rejected.length} ligne(s) refusée(s) : elles ne seront pas importées.</p>}
          </section>

          <IssueList title="Lignes refusées" tone="red" issues={report.rejected} />
          <IssueList title="Avertissements" tone="amber" issues={report.warnings} />

          {!imported && toCreate > 0 && !confirming && (
            <button onClick={() => setConfirming(true)} className="h-11 rounded-lg bg-slate-900 px-6 font-medium text-white">
              Importer
            </button>
          )}

          {!imported && confirming && (
            <div className="space-y-3 rounded-xl border border-zinc-300 bg-white p-4 text-sm">
              <p>
                Vous allez créer {report.created.pcos} PCO et leur structure. Cette opération ne peut pas être annulée depuis l&apos;application.
              </p>
              <div className="flex gap-3">
                <button onClick={() => send("import")} disabled={busy !== null} className="h-11 rounded-lg bg-slate-900 px-6 font-medium text-white disabled:opacity-60">
                  {busy === "import" ? "Import en cours…" : "Oui, importer"}
                </button>
                <button onClick={() => setConfirming(false)} disabled={busy !== null} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
                  Annuler
                </button>
              </div>
            </div>
          )}

          {!imported && toCreate === 0 && <p className="text-sm text-zinc-700">Rien à créer avec ce fichier.</p>}

          {imported && (
            <Link href="/reseau" className="inline-flex h-11 items-center rounded-lg border border-zinc-300 px-6 font-medium">
              Voir le réseau
            </Link>
          )}
        </div>
      )}
    </section>
  );
}
