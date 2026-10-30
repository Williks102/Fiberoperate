"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Paged, Plaque, Project } from "@/lib/types";
import { useApi } from "@/lib/use-api";

export default function NouveauProjetPage() {
  const router = useRouter();
  const [plaqueId, setPlaqueId] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");
  const projects = useApi<Paged<Project>>("/projects?per_page=100");
  // Une plaque qui a déjà un projet n'est plus proposée (l'API le refuserait de toute façon).
  const taken = new Set(projects.status === "ok" ? projects.data.data.map((p) => p.plaque_id) : []);
  const options = plaques.status === "ok" ? plaques.data.data.filter((p) => !taken.has(p.id)) : [];

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);
    try {
      await apiFetch("/projects", { method: "POST", body: JSON.stringify({ plaque_id: plaqueId }) }, getToken());
      router.push("/projets");
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage(Object.keys(e.errors).length ? "Corrigez le champ indiqué." : e.message);
      } else {
        setMessage("Une erreur est survenue.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="max-w-xl space-y-4">
      <h1 className="text-xl font-semibold">Nouveau projet</h1>
      <p className="text-sm text-zinc-700">Un projet porte sur une plaque. Il démarre en phase « Avant-projet ».</p>

      <form onSubmit={onSubmit} className="space-y-4 pb-20" noValidate>
        {message && (
          <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
            {message}
          </p>
        )}

        <Field id="plaque" label="Plaque" error={errors.plaque_id?.[0]}>
          <select id="plaque" value={plaqueId} onChange={(e) => setPlaqueId(e.target.value)} className={inputClass} required>
            <option value="">Choisir une plaque</option>
            {options.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </Field>
        {plaques.status === "ok" && projects.status === "ok" && options.length === 0 && (
          <p className="text-sm text-zinc-700">Toutes les plaques ont déjà un projet. Créez d&apos;abord une plaque dans « Plaques », ou importez le réseau.</p>
        )}

        <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-zinc-200 bg-white p-3 md:static md:border-0 md:p-0">
          <button type="submit" disabled={submitting || !plaqueId} className="h-11 flex-1 rounded-lg bg-slate-900 font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
            {submitting ? "Création…" : "Ouvrir le projet"}
          </button>
          <button type="button" onClick={() => router.push("/projets")} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
            Retour
          </button>
        </div>
      </form>
    </div>
  );
}
