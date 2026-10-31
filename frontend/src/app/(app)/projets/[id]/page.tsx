"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { Badge } from "@/components/ui/badge";
import { ProjectActions } from "@/components/project-actions";
import { ProjectDocumentsPanel } from "@/components/project-documents-panel";
import { ProjectQuotePanel } from "@/components/project-quote-panel";
import { NetworkImportPanel } from "@/components/network-import-panel";
import { useAuth } from "@/lib/auth/auth-context";
import { formatDateTime } from "@/lib/format";
import { DECISION_LABELS, PHASE_TONES, phaseProgress, ZONE_STATUS_LABELS, ZONE_TONES } from "@/lib/labels";
import type { Project } from "@/lib/types";
import { useApi } from "@/lib/use-api";

export default function ProjetPage() {
  const { id } = useParams<{ id: string }>();
  const result = useApi<{ data: Project }>(`/projects/${id}`);

  return (
    <div className="space-y-5">
      <Link href="/projets" className="inline-flex h-11 items-center text-sm font-medium underline">
        ← Retour aux projets
      </Link>

      {result.status === "loading" && <div aria-busy="true" className="h-64 animate-pulse rounded-xl bg-slate-200" />}

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
  // Le devis est vu par l'administration, la direction et le BE (l'API refuse les autres).
  const canViewQuote = canImport || user?.role === "dg" || user?.role === "dt" || (user?.role === "technician" && user.skills?.includes("drawing") === true);
  const steps = phaseProgress(project.phase);
  const history = [...(project.history ?? [])].reverse();

  return (
    <>
      <header className="space-y-1">
        <h1 className="text-xl font-semibold">
          {project.kind === "desaturation" ? "Projet de désaturation" : "Projet"} de la plaque {project.plaque?.name ?? "—"}
        </h1>
        <p className="flex flex-wrap items-center gap-2 text-sm text-slate-700">
          <Badge tone={PHASE_TONES[project.phase]}>{project.phase_label}</Badge>
          depuis le {formatDateTime(project.phase_changed_at)}
        </p>
      </header>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
      <ProjectActions project={project} onDone={reload} />

      <Characteristics project={project} />
      <ProjectDocumentsPanel projectId={project.id} plaqueId={project.plaque_id} />
      {canViewQuote && <ProjectQuotePanel projectId={project.id} />}
      {canImport && <NetworkImportPanel projectId={project.id} onImported={reload} />}
        </div>
        <aside className="space-y-6">
      <section aria-labelledby="phases" className="space-y-2">
        <h2 id="phases" className="text-lg font-semibold">
          Parcours
        </h2>
        <ol className="space-y-1 rounded-xl border border-slate-200 bg-white p-3">
          {steps.map((step, i) => (
            <li
              key={step.value}
              aria-current={step.state === "current" ? "step" : undefined}
              className={`flex min-h-11 items-center gap-3 rounded-lg px-2 text-sm ${step.state === "current" ? "bg-primary hover:bg-primary-hover font-semibold text-white" : step.state === "done" ? "text-ink" : "text-slate-700"}`}
            >
              <span
                aria-hidden="true"
                className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold ${step.state === "current" ? "border-white" : step.state === "done" ? "border-green-700 bg-green-700 text-white" : "border-slate-400"}`}
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
          <p className="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-700">Aucun changement enregistré.</p>
        ) : (
          <ul className="space-y-2">
            {history.map((line) => (
              <li key={line.id} className="space-y-1 rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <p className="font-medium">
                  {line.from_phase_label ? `${line.from_phase_label} → ${line.to_phase_label}` : `Projet ouvert en « ${line.to_phase_label} »`}
                </p>
                <p className="text-slate-700">
                  {formatDateTime(line.created_at)} · {line.author?.name ?? "Auteur inconnu"}
                </p>
                {line.decision && (
                  <p>
                    <Badge tone={line.decision === "approved" ? "success" : "danger"}>{DECISION_LABELS[line.decision]}</Badge>
                  </p>
                )}
                {line.reason && <p className="text-ink">Motif : {line.reason}</p>}
              </li>
            ))}
          </ul>
        )}
      </section>
        </aside>
      </div>
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
    ["PCO prévus", String(total("pcos"))],
  ];

  return (
    <section aria-labelledby="caracteristiques" className="space-y-3">
      <h2 id="caracteristiques" className="text-lg font-semibold">
        Caractéristiques
      </h2>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {facts.map(([label, value]) => (
          <div key={label} className="rounded-xl border border-slate-200 bg-white p-3">
            <dt className="text-xs text-slate-700">{label}</dt>
            <dd className="font-medium">{value}</dd>
          </div>
        ))}
      </dl>
      {network.zones.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-700">Aucune sous-plaque pour l&apos;instant. Ajoutez-les dans « Sous-plaques ».</p>
      ) : (
        <ul className="space-y-2">
          {network.zones.map((zone) => (
            <li key={zone.id} className="space-y-1 rounded-xl border border-slate-200 bg-white p-4 text-sm">
              <p className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">{zone.code}</span>
                <Badge tone={ZONE_TONES[zone.status]}>{ZONE_STATUS_LABELS[zone.status]}</Badge>
              </p>
              <p className="text-slate-700">
                SRO {zone.sro ?? "à créer"} · {zone.splitters} splitters · {zone.pcos} PCO prévus
              </p>
              {zone.pcos > 0 && (
                <>
                  <ZoneProgress zone={zone} />
                  <p className="text-slate-700">
                    Soudés ou activés : {zone.progress.spliced + zone.progress.activated} sur {zone.pcos} · Positions relevées : {zone.progress.measured} sur {zone.pcos}
                  </p>
                </>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

const PROGRESS_STEPS: { key: "to_pull" | "pulled" | "spliced" | "activated"; label: string; color: string }[] = [
  { key: "to_pull", label: "À tirer", color: "bg-slate-300" },
  { key: "pulled", label: "Tirés", color: "bg-sky-400" },
  { key: "spliced", label: "Soudés", color: "bg-amber-400" },
  { key: "activated", label: "Activés", color: "bg-green-600" },
];

/** Barre d'avancement des PCO d'une sous-plaque, avec les chiffres écrits (la couleur ne porte jamais seule l'information). */
function ZoneProgress({ zone }: { zone: NonNullable<Project["network"]>["zones"][number] }) {
  return (
    <div className="space-y-1">
      <div className="flex h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
        {PROGRESS_STEPS.map((step) => (
          <span key={step.key} className={step.color} style={{ width: `${(zone.progress[step.key] / zone.pcos) * 100}%` }} />
        ))}
      </div>
      <p className="text-xs text-slate-700">{PROGRESS_STEPS.map((step) => `${step.label} ${zone.progress[step.key]}`).join(" · ")}</p>
    </div>
  );
}
