"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Olt, Paged, Plaque, User } from "@/lib/types";
import { useApi } from "@/lib/use-api";

/** Création (plaque absente) ou modification d'une plaque par l'administrateur. */
export function PlaqueForm({ plaque }: { plaque?: Plaque }) {
  const router = useRouter();
  const editing = Boolean(plaque);

  const [name, setName] = useState(plaque?.name ?? "");
  const [oltId, setOltId] = useState(plaque?.olt_id ?? "");
  const [supervisorId, setSupervisorId] = useState(plaque?.supervisor_id ?? "");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const olts = useApi<Paged<Olt>>("/olts?per_page=100");
  const supervisors = useApi<Paged<User>>("/users?role=supervisor&is_active=1&per_page=100");
  const oltOptions = olts.status === "ok" ? olts.data.data : [];
  const supervisorOptions = supervisors.status === "ok" ? supervisors.data.data : [];

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    const body = { name, olt_id: oltId || null, supervisor_id: supervisorId || null };

    try {
      await apiFetch(editing ? `/plaques/${plaque!.id}` : "/plaques", { method: editing ? "PATCH" : "POST", body: JSON.stringify(body) }, getToken());
      if (editing) {
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/plaques");
      }
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage({ type: "error", text: Object.keys(e.errors).length ? "Corrigez les champs indiqués." : e.message });
      } else {
        setMessage({ type: "error", text: "Une erreur est survenue." });
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4 pb-20" noValidate>
      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={`rounded-lg p-3 text-sm ${message.type === "error" ? "bg-red-50 text-red-800" : "bg-green-50 text-green-800"}`}
        >
          {message.text}
        </p>
      )}

      <Field id="name" label="Nom de la plaque" error={errors.name?.[0]}>
        <input id="name" value={name} onChange={(e) => setName(e.target.value)} className={inputClass} required />
      </Field>

      <Field id="olt" label="OLT (facultatif)" hint="Peut être renseigné plus tard." error={errors.olt_id?.[0]}>
        <select id="olt" value={oltId} onChange={(e) => setOltId(e.target.value)} className={inputClass}>
          <option value="">Aucun</option>
          {oltOptions.map((o) => (
            <option key={o.id} value={o.id}>
              {o.name}
            </option>
          ))}
        </select>
      </Field>

      <Field id="supervisor" label="Superviseur (facultatif)" hint="Un superviseur couvre la plaque entière." error={errors.supervisor_id?.[0]}>
        <select id="supervisor" value={supervisorId} onChange={(e) => setSupervisorId(e.target.value)} className={inputClass}>
          <option value="">Aucun</option>
          {supervisorOptions.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </select>
      </Field>

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-zinc-200 bg-white p-3 md:static md:border-0 md:p-0">
        <button type="submit" disabled={submitting} className="h-11 flex-1 rounded-lg bg-slate-900 font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer la plaque"}
        </button>
        <button type="button" onClick={() => router.push("/plaques")} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
