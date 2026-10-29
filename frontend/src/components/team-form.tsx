"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Paged, Team, User } from "@/lib/types";
import { useApi } from "@/lib/use-api";

/** Création (team absent) ou modification d'une équipe par l'administrateur ou le DT. */
export function TeamForm({ team }: { team?: Team }) {
  const router = useRouter();
  const editing = Boolean(team);

  const [name, setName] = useState(team?.name ?? "");
  const [coordinatorId, setCoordinatorId] = useState(team?.coordinator_id ?? "");
  const [leaderId, setLeaderId] = useState(team?.leader_id ?? "");
  const [memberIds, setMemberIds] = useState<string[]>(team?.member_ids ?? []);

  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const coordinators = useApi<Paged<User>>("/users?role=coordinator&is_active=1&per_page=100");
  const technicians = useApi<Paged<User>>("/users?role=technician&is_active=1&per_page=100");
  const coordinatorOptions = coordinators.status === "ok" ? coordinators.data.data : [];
  const technicianOptions = technicians.status === "ok" ? technicians.data.data : [];

  const error = (field: string) => errors[field]?.[0] ?? errors[`${field}.0`]?.[0];

  function toggleMember(id: string) {
    setMemberIds((current) => (current.includes(id) ? current.filter((m) => m !== id) : [...current, id]));
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    const body = { name, coordinator_id: coordinatorId || null, leader_id: leaderId || null, member_ids: memberIds };

    try {
      await apiFetch(editing ? `/teams/${team!.id}` : "/teams", { method: editing ? "PATCH" : "POST", body: JSON.stringify(body) }, getToken());
      if (editing) {
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/equipes");
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

      <Field id="name" label="Nom de l'équipe" error={error("name")}>
        <input id="name" value={name} onChange={(e) => setName(e.target.value)} className={inputClass} required />
      </Field>

      <Field id="coordinator" label="Coordinateur" error={error("coordinator_id")}>
        <select id="coordinator" value={coordinatorId} onChange={(e) => setCoordinatorId(e.target.value)} className={inputClass}>
          <option value="">Aucun</option>
          {coordinatorOptions.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </select>
      </Field>

      <Field id="leader" label="Chef d'équipe" error={error("leader_id")}>
        <select id="leader" value={leaderId} onChange={(e) => setLeaderId(e.target.value)} className={inputClass}>
          <option value="">Aucun</option>
          {technicianOptions.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </select>
      </Field>

      <fieldset className="space-y-2">
        <legend className="text-sm font-medium">Membres</legend>
        {technicians.status === "loading" && <div aria-busy="true" className="h-11 animate-pulse rounded-lg bg-zinc-200" />}
        {technicians.status === "ok" && technicianOptions.length === 0 && (
          <p className="text-sm text-zinc-600">Aucun technicien actif. Créez d&apos;abord des techniciens dans « Utilisateurs ».</p>
        )}
        <div className="max-h-72 space-y-2 overflow-y-auto">
          {technicianOptions.map((u) => (
            <label key={u.id} className="flex min-h-11 items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3">
              <input type="checkbox" checked={memberIds.includes(u.id)} onChange={() => toggleMember(u.id)} className="h-5 w-5" />
              {u.name}
            </label>
          ))}
        </div>
        {error("member_ids") && (
          <p role="alert" className="text-sm text-red-700">
            {error("member_ids")}
          </p>
        )}
      </fieldset>

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-zinc-200 bg-white p-3 md:static md:border-0 md:p-0">
        <button type="submit" disabled={submitting} className="h-11 flex-1 rounded-lg bg-slate-900 font-medium text-white disabled:opacity-50 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer l'équipe"}
        </button>
        <button type="button" onClick={() => router.push("/equipes")} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
