"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { MISSION_LABELS, ROLE_LABELS } from "@/lib/labels";
import type { MissionType, Paged, Role, User } from "@/lib/types";
import { useApi } from "@/lib/use-api";

const MANAGER_ROLES: Role[] = ["supervisor", "coordinator", "dt"];

/** Création (user absent) ou modification d'un utilisateur par l'administrateur. */
export function UserForm({ user }: { user?: User }) {
  const router = useRouter();
  const editing = Boolean(user);

  const [name, setName] = useState(user?.name ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [phone, setPhone] = useState(user?.phone ?? "");
  const [role, setRole] = useState<Role>(user?.role ?? "technician");
  const [password, setPassword] = useState("");
  const [managerId, setManagerId] = useState(user?.manager_id ?? "");
  const [skills, setSkills] = useState<MissionType[]>(user?.skills ?? []);
  const [isActive, setIsActive] = useState(user?.is_active ?? true);

  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Candidats au poste de supérieur (technicien → superviseur ou coordinateur, coordinateur → DT).
  const managers = useApi<Paged<User>>("/users?per_page=100");
  const managerOptions = managers.status === "ok" ? managers.data.data.filter((u) => MANAGER_ROLES.includes(u.role) && u.id !== user?.id) : [];

  const error = (field: string) => errors[field]?.[0];

  function toggleSkill(skill: MissionType) {
    setSkills((current) => (current.includes(skill) ? current.filter((s) => s !== skill) : [...current, skill]));
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    const body: Record<string, unknown> = {
      name,
      email,
      phone: phone || null,
      role,
      manager_id: managerId || null,
      skills: role === "technician" ? skills : [],
    };
    if (password) body.password = password;
    if (editing) body.is_active = isActive;

    try {
      await apiFetch(editing ? `/users/${user!.id}` : "/users", { method: editing ? "PATCH" : "POST", body: JSON.stringify(body) }, getToken());
      if (editing) {
        setPassword("");
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/utilisateurs");
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

      <Field id="name" label="Nom complet" error={error("name")}>
        <input id="name" value={name} onChange={(e) => setName(e.target.value)} className={inputClass} required />
      </Field>

      <Field id="email" label="Adresse e-mail" error={error("email")}>
        <input id="email" type="email" inputMode="email" value={email} onChange={(e) => setEmail(e.target.value)} className={inputClass} required />
      </Field>

      <Field id="phone" label="Téléphone (facultatif)" error={error("phone")}>
        <input id="phone" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} className={inputClass} />
      </Field>

      <Field
        id="password"
        label={editing ? "Nouveau mot de passe" : "Mot de passe"}
        hint={editing ? "Laissez vide pour ne pas le changer. 8 caractères minimum, avec une lettre et un chiffre." : "8 caractères minimum, avec une lettre et un chiffre."}
        error={error("password")}
      >
        <input id="password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} className={inputClass} required={!editing} />
      </Field>

      <Field id="role" label="Rôle" error={error("role")}>
        <select id="role" value={role} onChange={(e) => setRole(e.target.value as Role)} className={inputClass}>
          {(Object.keys(ROLE_LABELS) as Role[]).map((r) => (
            <option key={r} value={r}>
              {ROLE_LABELS[r]}
            </option>
          ))}
        </select>
      </Field>

      {role === "technician" && (
        <fieldset className="space-y-2">
          <legend className="text-sm font-medium">Missions autorisées</legend>
          {(Object.keys(MISSION_LABELS) as MissionType[]).map((skill) => (
            <label key={skill} className="flex min-h-11 items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3">
              <input type="checkbox" checked={skills.includes(skill)} onChange={() => toggleSkill(skill)} className="h-5 w-5" />
              {MISSION_LABELS[skill]}
            </label>
          ))}
          {error("skills") && (
            <p role="alert" className="text-sm text-red-700">
              {error("skills")}
            </p>
          )}
        </fieldset>
      )}

      <Field id="manager" label="Supérieur hiérarchique (facultatif)" error={error("manager_id")}>
        <select id="manager" value={managerId} onChange={(e) => setManagerId(e.target.value)} className={inputClass}>
          <option value="">Aucun</option>
          {managerOptions.map((m) => (
            <option key={m.id} value={m.id}>
              {m.name} ({m.role_label})
            </option>
          ))}
        </select>
      </Field>

      {editing && (
        <div className="space-y-1">
          <label className="flex min-h-11 items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3">
            <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} className="h-5 w-5" />
            Compte actif
          </label>
          {error("is_active") && (
            <p role="alert" className="text-sm text-red-700">
              {error("is_active")}
            </p>
          )}
          {!isActive && <p className="text-xs text-zinc-600">Un compte désactivé est déconnecté immédiatement et ne peut plus se connecter.</p>}
        </div>
      )}

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-zinc-200 bg-white p-3 md:static md:border-0 md:p-0">
        <button type="submit" disabled={submitting} className="h-11 flex-1 rounded-lg bg-slate-900 font-medium text-white disabled:opacity-50 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer l'utilisateur"}
        </button>
        <button type="button" onClick={() => router.push("/utilisateurs")} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
