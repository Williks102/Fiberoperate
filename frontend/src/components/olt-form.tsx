"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Olt } from "@/lib/types";

/** Création (olt absent) ou modification d'un OLT par l'administrateur. */
export function OltForm({ olt }: { olt?: Olt }) {
  const router = useRouter();
  const editing = Boolean(olt);

  const [name, setName] = useState(olt?.name ?? "");
  const [notes, setNotes] = useState(olt?.notes ?? "");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    try {
      await apiFetch(editing ? `/olts/${olt!.id}` : "/olts", { method: editing ? "PATCH" : "POST", body: JSON.stringify({ name, notes: notes || null }) }, getToken());
      if (editing) {
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/olt");
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

      <Field id="name" label="Nom de l'OLT" error={errors.name?.[0]}>
        <input id="name" value={name} onChange={(e) => setName(e.target.value)} className={inputClass} required />
      </Field>

      <Field id="notes" label="Notes (facultatif)" error={errors.notes?.[0]}>
        <textarea id="notes" value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} className={`${inputClass} h-auto py-2`} />
      </Field>

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-6px_16px_rgba(15,23,42,0.06)] backdrop-blur md:static md:border-0 md:bg-transparent md:p-0 md:shadow-none md:backdrop-blur-none">
        <button type="submit" disabled={submitting} className="h-11 flex-1 rounded-lg bg-primary hover:bg-primary-hover font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer l'OLT"}
        </button>
        <button type="button" onClick={() => router.push("/olt")} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
