"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/lib/auth/auth-context";
import { formatDateTime } from "@/lib/format";
import { PHASE_TONES, PROJECT_PHASES } from "@/lib/labels";
import type { Paged, Project } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

export default function ProjetsPage() {
  const { user } = useAuth();
  const canCreate = user?.role === "admin" || user?.role === "be_manager";
  const [search, setSearch] = useState("");
  const [phase, setPhase] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  if (phase) params.set("phase", phase);
  const { reload, ...state } = useApi<Paged<Project>>(`/projects?${params}`);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Projets</h1>
        {canCreate && (
          <Link href="/projets/nouveau" className="flex h-11 items-center rounded-lg bg-primary hover:bg-primary-hover px-4 text-sm font-medium text-white">
            Nouveau projet
          </Link>
        )}
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <div className="flex-1 space-y-1">
          <label htmlFor="search" className="text-sm font-medium">
            Rechercher
          </label>
          <input
            id="search"
            type="search"
            placeholder="Nom de la plaque"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            className={inputClass}
          />
        </div>
        <div className="space-y-1 sm:w-64">
          <label htmlFor="phase" className="text-sm font-medium">
            Phase
          </label>
          <select
            id="phase"
            value={phase}
            onChange={(e) => {
              setPhase(e.target.value);
              setPage(1);
            }}
            className={inputClass}
          >
            <option value="">Toutes les phases</option>
            {PROJECT_PHASES.map((p) => (
              <option key={p.value} value={p.value}>
                {p.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <AsyncList
        state={state}
        reload={reload}
        empty={q || phase ? "Aucun projet ne correspond à votre recherche." : canCreate ? "Aucun projet. Ouvrez le premier avec « Nouveau projet »." : "Aucun projet dans votre périmètre."}
      >
        {(projects) => (
          <>
            <ul className="space-y-2">
              {projects.map((project) => (
                <li key={project.id} className="space-y-1 rounded-xl border border-slate-200 bg-white p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-medium">
                      <Link href={`/projets/${project.id}`} className="underline-offset-2 hover:underline">
                        Plaque {project.plaque?.name ?? "—"}
                      </Link>
                    </p>
                    <span className="flex flex-wrap items-center gap-2">
                      {project.kind === "desaturation" && <Badge tone="warning">{project.kind_label}</Badge>}
                      <Badge tone={PHASE_TONES[project.phase]}>{project.phase_label}</Badge>
                    </span>
                  </div>
                  <p className="text-sm text-slate-700">Phase depuis le {formatDateTime(project.phase_changed_at)}</p>
                </li>
              ))}
            </ul>
            {state.status === "ok" && <Pager page={state.data.meta.current_page} lastPage={state.data.meta.last_page} onChange={setPage} />}
          </>
        )}
      </AsyncList>
    </div>
  );
}
