"use client";

import { useState } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";

interface Report {
  committed: boolean;
  rows: number;
  created: number;
  updated: number;
  unchanged: number;
  rejected: { line: number; message: string }[];
}

const SHOWN = 50;

/** Import CSV du catalogue de prix (administrateur) : vérification sans rien écrire, puis import. */
export function PriceCatalogImportPanel({ onImported }: { onImported: () => void }) {
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
      const res = await apiFetch<{ data: Report }>(mode === "preview" ? "/price-items/import/preview" : "/price-items/import", { method: "POST", body: form }, getToken());
      setReport(res.data);
      setConfirming(false);
      if (res.data.committed) onImported();
    } catch (e) {
      setReport(null);
      setError(e instanceof ApiError ? e.message : "Une erreur est survenue.");
    } finally {
      setBusy(null);
    }
  }

  const imported = report?.committed === true;
  const changes = report ? report.created + report.updated : 0;

  return (
    <section aria-labelledby="import-catalogue" className="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
      <div className="space-y-1">
        <h2 id="import-catalogue" className="text-lg font-semibold">
          Importer le catalogue (CSV)
        </h2>
        <p className="text-sm text-slate-700">
          Une ligne par article : référence, type (fourniture ou prestation), rubrique, désignation, unité, prix unitaire en FCFA. Un article déjà connu (même rubrique, référence et désignation) est mis à jour ; aucun article n&apos;est supprimé. Les devis déjà faits gardent leur prix.{" "}
          <a href="/modeles-import/catalogue-prix.csv" download className="underline">
            Télécharger le modèle
          </a>
        </p>
      </div>

      <div className="space-y-1">
        <label htmlFor="catalogue-file" className="text-sm font-medium">
          Fichier CSV
        </label>
        <input
          id="catalogue-file"
          type="file"
          accept=".csv,text/csv,text/plain"
          onChange={(e) => {
            setFile(e.target.files?.[0] ?? null);
            setReport(null);
            setError(null);
            setConfirming(false);
          }}
          className="block w-full rounded-lg border border-slate-300 bg-white p-2 text-base"
        />
      </div>

      <button onClick={() => send("preview")} disabled={!file || busy !== null} className="h-11 rounded-lg border border-slate-300 px-4 font-medium disabled:opacity-60">
        {busy === "preview" ? "Vérification…" : "Vérifier le fichier"}
      </button>

      {error && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {error}
        </p>
      )}

      {report && (
        <div className="space-y-3">
          <p role="status" className="rounded-xl border border-slate-200 p-4 text-sm">
            <span className="block font-semibold">{imported ? "Import terminé" : "Résultat de la vérification (rien n'est encore enregistré)"}</span>
            {report.rows} lignes lues · {imported ? "créés" : "à créer"} : {report.created} · {imported ? "mis à jour" : "à mettre à jour"} : {report.updated} · inchangés : {report.unchanged}
          </p>

          {report.rejected.length > 0 && (
            <section className="space-y-2 rounded-xl bg-red-50 p-4 text-sm text-red-900">
              <h3 className="font-semibold">Lignes refusées ({report.rejected.length}) : elles ne seront pas importées</h3>
              <ul className="space-y-1">
                {report.rejected.slice(0, SHOWN).map((issue, i) => (
                  <li key={i}>
                    <span className="font-medium">Ligne {issue.line} :</span> {issue.message}
                  </li>
                ))}
              </ul>
              {report.rejected.length > SHOWN && <p>… et {report.rejected.length - SHOWN} autres.</p>}
            </section>
          )}

          {!imported && changes > 0 && !confirming && (
            <button onClick={() => setConfirming(true)} className="h-11 rounded-lg bg-primary hover:bg-primary-hover px-6 font-medium text-white">
              Importer
            </button>
          )}
          {!imported && confirming && (
            <div className="space-y-3 rounded-xl border border-slate-300 p-4 text-sm">
              <p>
                Vous allez créer {report.created} article(s) et en mettre à jour {report.updated}. Les prix du catalogue changeront pour les prochains devis.
              </p>
              <div className="flex gap-3">
                <button onClick={() => send("import")} disabled={busy !== null} className="h-11 rounded-lg bg-primary hover:bg-primary-hover px-6 font-medium text-white disabled:opacity-60">
                  {busy === "import" ? "Import en cours…" : "Oui, importer"}
                </button>
                <button onClick={() => setConfirming(false)} disabled={busy !== null} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
                  Annuler
                </button>
              </div>
            </div>
          )}
          {!imported && changes === 0 && <p className="text-sm text-slate-700">Rien à créer ni à modifier avec ce fichier.</p>}
        </div>
      )}
    </section>
  );
}
