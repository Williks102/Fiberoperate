"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { NetworkImportPanel } from "@/components/network-import-panel";
import { useAuth } from "@/lib/auth/auth-context";
import { formatDateTime } from "@/lib/format";
import { DECISION_LABELS, phaseProgress, ZONE_STATUS_LABELS } from "@/lib/labels";
import type { Project } from "@/lib/types";
import { useApi } from "@/lib/use-api";

export default function ProjetPage() {
  const { id } = useParams<{ id: string }>();
  const result = useApi<{ data: Project }>(`/projects/${id}`);

  return (
    <div className="max-w-2xl space-y-5">
      <Link href="/projets" className="inline-flex h-11 items-center text-sm font-medium underline">
        ← Retour aux projets
      </Link>

      {result.status === "loading" && <div aria-busy="true" className="h-64 animate-pulse rounded-xl bg-zinc-200" />}

      {result.status === "error" && (
        <div role="alert" className="space-y-3 rounded-xl bg-red-50 p-4 text-sm text-red-800">
          <p>{result.message}</p>
          <button onClick={result.reload} className="h-11 rounded-lg border border-red-300 px-4 font-medium">
            Réessayer
          </button>
        </div>
      )}

      {result.status === "ok" && <ProjectDetail project={result.data.data} reload={result.reload} />}
    </div>
  );
}

function ProjectDetail({ project, reload }: { project: Project; reload: () => void }) {
  const { user } = useAuth();
  const canImport = user?.role === "admin" || user?.role === "be_manager";
  const steps = phaseProgress(project.phase);
  const history = [...(project.history ?? [])].reverse();

  return (
    <>
      <header className="space-y-1">
        <h1 className="text-xl font-semibold">Projet de la plaque {project.plaque?.name ?? "—"}</h1>
        <p className="text-sm text-zinc-700">
          Phase actuelle : <span className="font-medium text-zinc-900">{project.phase_label}</span> depuis le {formatDateTime(project.phase_changed_at)}
        </p>
      </header>

      <Characteristics project={project} />
      {canImport && <NetworkImportPanel projectId={project.id} onImported={reload} />}

      <section aria-labelledby="phases" className="space-y-2">
        <h2 id="phases" className="text-lg font-semibold">
          Parcours
        </h2>
        <ol className="space-y-1 rounded-xl border border-zinc-200 bg-white p-3">
          {steps.map((step, i) => (
            <li
              key={step.value}
              aria-current={step.state === "current" ? "step" : undefined}
              className={`flex min-h-11 items-center gap-3 rounded-lg px-2 text-sm ${step.state === "current" ? "bg-slate-900 font-semibold text-white" : step.state === "done" ? "text-zinc-900" : "text-zinc-700"}`}
            >
              <span
                aria-hidden="true"
                className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold ${step.state === "current" ? "border-white" : step.state === "done" ? "border-green-700 bg-green-700 text-white" : "border-zinc-400"}`}
              >
                {step.state === "done" ? "✓" : i + 1}
              </span>
              <span>{step.label}</span>
              <span className="sr-only">{step.state === "done" ? "(terminée)" : step.state === "current" ? "(en cours)" : "(à venir)"}</span>
            </li>
          ))}
        </ol>
      </section>

      <section aria-labelledby="historique" className="space-y-2">
        <h2 id="historique" className="text-lg font-semibold">
          Historique
        </h2>
        {history.length === 0 ? (
          <p className="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-700">Aucun changement enregistré.</p>
        ) : (
          <ul className="space-y-2">
            {history.map((line) => (
              <li key={line.id} className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
                <p className="font-medium">
                  {line.from_phase_label ? `${line.from_phase_label} → ${line.to_phase_label}` : `Projet ouvert en « ${line.to_phase_label} »`}
                </p>
                <p className="text-zinc-700">
                  {formatDateTime(line.created_at)} · {line.author?.name ?? "Auteur inconnu"}
                </p>
                {line.decision && (
                  <p>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${line.decision === "approved" ? "bg-green-100 text-green-900" : "bg-red-100 text-red-900"}`}>{DECISION_LABELS[line.decision]}</span>
                  </p>
                )}
                {line.reason && <p className="text-zinc-900">Motif : {line.reason}</p>}
              </li>
            ))}
          </ul>
        )}
      </section>
    </>
  );
}

/** Caractéristiques du réseau du projet : OLT, superviseur, totaux et une ligne par sous-plaque. */
function Characteristics({ project }: { project: Project }) {
  const network = project.network;
  if (!network) return null;

  const total = (key: "splitters" | "pcos") => network.zones.reduce((sum, z) => sum + z[key], 0);
  const facts: [string, string][] = [
    ["OLT (source du signal)", network.olt?.name ?? "Non renseigné"],
    ["Superviseur", network.supervisor?.name ?? "Non désigné"],
    ["Sous-plaques", String(network.zones.length)],
    ["Splitters", String(total("splitters"))],
    ["PCO", String(total("pcos"))],
  ];

  return (
    <section aria-labelledby="caracteristiques" className="space-y-3">
      <h2 id="caracteristiques" className="text-lg font-semibold">
        Caractéristiques
      </h2>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {facts.map(([label, value]) => (
          <div key={label} className="rounded-xl border border-zinc-200 bg-white p-3">
            <dt className="text-xs text-zinc-700">{label}</dt>
            <dd className="font-medium">{value}</dd>
          </div>
        ))}
      </dl>
      {network.zones.length === 0 ? (
        <p className="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-700">Aucune sous-plaque pour l&apos;instant. Ajoutez-les dans « Sous-plaques ».</p>
      ) : (
        <ul className="space-y-2">
          {network.zones.map((zone) => (
            <li key={zone.id} className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
              <p className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">{zone.code}</span>
                <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700">{ZONE_STATUS_LABELS[zone.status]}</span>
              </p>
              <p className="text-zinc-700">
                SRO {zone.sro ?? "à créer"} · {zone.splitters} splitters · {zone.pcos} PCO
              </p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
