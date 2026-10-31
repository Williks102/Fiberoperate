"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Olt, Paged, Plaque, Project } from "@/lib/types";
import { useApi } from "@/lib/use-api";

/** Nombres proposés pour le réseau d'une sous-plaque ; la saisie reste libre. */
const SUGGESTED_COUNTS = [32, 64];

interface ZoneRow {
  code: string;
  pcoCount: string;
  sroCode: string;
}

const emptyRow = (): ZoneRow => ({ code: "", pcoCount: "", sroCode: "" });

/** Création guidée : la plaque naît avec le projet (nom, OLT choisi ou créé, sous-plaques et réseau à générer). */
export default function NouveauProjetPage() {
  const router = useRouter();
  const [existing, setExisting] = useState(false);
  const [plaqueId, setPlaqueId] = useState("");
  const [plaqueName, setPlaqueName] = useState("");
  const [oltMode, setOltMode] = useState<"none" | "choose" | "create">("none");
  const [oltId, setOltId] = useState("");
  const [oltName, setOltName] = useState("");
  const [rows, setRows] = useState<ZoneRow[]>([emptyRow()]);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");
  const projects = useApi<Paged<Project>>("/projects?per_page=100");
  const olts = useApi<Paged<Olt>>("/olts?per_page=100");
  // Une plaque qui a déjà un projet n'est plus proposée (l'API le refuserait de toute façon).
  const allProjects = projects.status === "ok" ? projects.data.data : [];
  // Une plaque a un seul projet en cours ; une fois le précédent en exploitation, on peut en ouvrir un autre (désaturation).
  const busy = new Set(allProjects.filter((p) => p.phase !== "in_operation").map((p) => p.plaque_id));
  const deployed = new Set(allProjects.map((p) => p.plaque_id));
  const freePlaques = plaques.status === "ok" ? plaques.data.data.filter((p) => !busy.has(p.id)) : [];
  const setRow = (i: number, patch: Partial<ZoneRow>) => setRows((prev) => prev.map((r, j) => (j === i ? { ...r, ...patch } : r)));

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    const zones = rows
      .filter((r) => r.code.trim() !== "")
      .map((r) => ({
        code: r.code.trim(),
        pco_count: r.pcoCount ? Number(r.pcoCount) : null,
        sro_code: r.sroCode.trim() || null,
      }));
    const body = existing
      ? { plaque_id: plaqueId }
      : {
          plaque_name: plaqueName.trim(),
          olt_id: oltMode === "choose" ? oltId || null : null,
          olt_name: oltMode === "create" ? oltName.trim() || null : null,
          zones,
        };

    try {
      const res = await apiFetch<{ data: Project }>("/projects", { method: "POST", body: JSON.stringify(body) }, getToken());
      // Sur la fiche du projet, on peut importer tout de suite un fichier CSV du réseau.
      router.push(`/projets/${res.data.id}`);
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage(Object.keys(e.errors).length ? "Corrigez les champs indiqués." : e.message);
      } else {
        setMessage("Une erreur est survenue.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  const canSubmit = existing ? plaqueId !== "" : plaqueName.trim() !== "";

  return (
    <div className="max-w-2xl space-y-4">
      <h1 className="text-xl font-semibold">Nouveau projet</h1>
      <p className="text-sm text-slate-700">La plaque naît avec le projet. Il démarre en phase « Avant-projet ».</p>

      <form onSubmit={onSubmit} className="space-y-6 pb-24" noValidate>
        {message && (
          <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
            {message}
          </p>
        )}

        {existing ? (
          <section className="space-y-3">
            <h2 className="text-base font-semibold">Plaque existante</h2>
            <p className="text-sm text-slate-700">Une plaque déjà déployée reçoit un projet de désaturation, une fois son projet précédent en exploitation.</p>
            <Field id="plaque" label="Plaque" error={errors.plaque_id?.[0]}>
              <select id="plaque" value={plaqueId} onChange={(e) => setPlaqueId(e.target.value)} className={inputClass} required>
                <option value="">Choisir une plaque</option>
                {freePlaques.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                    {deployed.has(p.id) ? " (désaturation)" : ""}
                  </option>
                ))}
              </select>
            </Field>
            {plaques.status === "ok" && projects.status === "ok" && freePlaques.length === 0 && <p className="text-sm text-slate-700">Toutes les plaques ont un projet en cours.</p>}
            <button type="button" onClick={() => setExisting(false)} className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium">
              Créer une nouvelle plaque
            </button>
          </section>
        ) : (
          <>
            <section className="space-y-3">
              <h2 className="text-base font-semibold">1. La plaque</h2>
              <Field id="plaque-name" label="Nom de la plaque" hint="Par exemple ABOBO." error={errors.plaque_name?.[0]}>
                <input id="plaque-name" value={plaqueName} onChange={(e) => setPlaqueName(e.target.value)} className={inputClass} required />
              </Field>
            </section>

            <section className="space-y-3">
              <h2 className="text-base font-semibold">2. L&apos;OLT (source du signal)</h2>
              <Field id="olt-mode" label="OLT de la plaque" error={errors.olt_id?.[0]}>
                <select id="olt-mode" value={oltMode} onChange={(e) => setOltMode(e.target.value as typeof oltMode)} className={inputClass}>
                  <option value="none">À renseigner plus tard</option>
                  <option value="choose">Choisir un OLT existant</option>
                  <option value="create">Créer un nouvel OLT</option>
                </select>
              </Field>
              {oltMode === "choose" && (
                <Field id="olt" label="OLT existant">
                  <select id="olt" value={oltId} onChange={(e) => setOltId(e.target.value)} className={inputClass}>
                    <option value="">Choisir un OLT</option>
                    {olts.status === "ok" &&
                      olts.data.data.map((o) => (
                        <option key={o.id} value={o.id}>
                          {o.name}
                        </option>
                      ))}
                  </select>
                </Field>
              )}
              {oltMode === "create" && (
                <Field id="olt-name" label="Nom du nouvel OLT" error={errors.olt_name?.[0]}>
                  <input id="olt-name" value={oltName} onChange={(e) => setOltName(e.target.value)} className={inputClass} />
                </Field>
              )}
            </section>

            <section className="space-y-3">
              <h2 className="text-base font-semibold">3. Les sous-plaques</h2>
              <p className="text-sm text-slate-700">
                Indiquez un nombre de PCO pour générer le réseau de la sous-plaque (SRO, splitters de 8 PCO, PCO). Laissez-le vide pour la créer seule. Le technicien du BE ajuste ensuite.
              </p>
              <datalist id="pco-counts">
                {SUGGESTED_COUNTS.map((n) => (
                  <option key={n} value={n} />
                ))}
              </datalist>
              {rows.map((row, i) => (
                <div key={i} className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                  <div className="grid gap-3 sm:grid-cols-3">
                    <Field id={`zone-code-${i}`} label="Sous-plaque" hint="Par exemple ABB01." error={errors[`zones.${i}.code`]?.[0]}>
                      <input id={`zone-code-${i}`} value={row.code} onChange={(e) => setRow(i, { code: e.target.value })} className={inputClass} />
                    </Field>
                    <Field id={`zone-pco-${i}`} label="Nombre de PCO" hint="32, 64 ou autre." error={errors[`zones.${i}.pco_count`]?.[0]}>
                      <input id={`zone-pco-${i}`} list="pco-counts" inputMode="numeric" value={row.pcoCount} onChange={(e) => setRow(i, { pcoCount: e.target.value.replace(/\D/g, "") })} className={inputClass} />
                    </Field>
                    <Field id={`zone-sro-${i}`} label="Numéro de la SRO" hint="Donné par l'opérateur." error={errors[`zones.${i}.sro_code`]?.[0]}>
                      <input id={`zone-sro-${i}`} value={row.sroCode} onChange={(e) => setRow(i, { sroCode: e.target.value })} className={inputClass} disabled={!row.pcoCount} />
                    </Field>
                  </div>
                  {rows.length > 1 && (
                    <button type="button" onClick={() => setRows((prev) => prev.filter((_, j) => j !== i))} className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium">
                      Retirer cette sous-plaque
                    </button>
                  )}
                </div>
              ))}
              <button type="button" onClick={() => setRows((prev) => [...prev, emptyRow()])} className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium">
                Ajouter une sous-plaque
              </button>
            </section>

            <button type="button" onClick={() => setExisting(true)} className="block text-sm text-slate-700 underline underline-offset-2">
              Ouvrir le projet d&apos;une plaque existante
            </button>
          </>
        )}

        <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-slate-200 bg-white p-3 md:static md:border-0 md:p-0">
          <button type="submit" disabled={submitting || !canSubmit} className="h-11 flex-1 rounded-lg bg-primary hover:bg-primary-hover font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
            {submitting ? "Création…" : "Ouvrir le projet"}
          </button>
          <button type="button" onClick={() => router.push("/projets")} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
            Retour
          </button>
        </div>
      </form>
    </div>
  );
}
