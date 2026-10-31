"use client";

import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiDownload, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { formatDateTime } from "@/lib/format";
import type { Paged, Zone } from "@/lib/types";
import { useApi } from "@/lib/use-api";

interface DocVersion {
  id: string;
  type: string;
  type_label: string;
  zone: { id: string; code: string } | null;
  version: number;
  file_name: string;
  size: number;
  comment: string | null;
  author: { id: string; name: string } | null;
  created_at: string | null;
}

interface Documents {
  versions: DocVersion[];
  uploadable: { type: string; label: string; needs_zone: boolean }[];
}

const formatSize = (bytes: number) => (bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} Ko` : `${(bytes / 1024 / 1024).toFixed(1).replace(".", ",")} Mo`);

/** Documents du projet : plans, KMZ, AVP, justificatif du client. Chaque envoi ajoute une version, rien n'est écrasé. */
export function ProjectDocumentsPanel({ projectId, plaqueId }: { projectId: string; plaqueId: string }) {
  const { reload, ...state } = useApi<{ data: Documents }>(`/projects/${projectId}/documents`);
  const [error, setError] = useState<string | null>(null);

  async function download(v: DocVersion) {
    setError(null);
    try {
      await apiDownload(`/projects/${projectId}/documents/${v.id}/download`, v.file_name, getToken());
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Le téléchargement a échoué.");
    }
  }

  if (state.status === "loading") return <div aria-busy="true" className="h-24 animate-pulse rounded-xl bg-zinc-200" />;
  if (state.status === "error") return null;

  // Un document = un type (et une sous-plaque pour un plan de sous-plaque) ; ses versions du plus récent au plus ancien.
  const groups = new Map<string, DocVersion[]>();
  for (const v of state.data.data.versions) groups.set(`${v.type}|${v.zone?.id ?? ""}`, [...(groups.get(`${v.type}|${v.zone?.id ?? ""}`) ?? []), v]);
  const { uploadable } = state.data.data;

  return (
    <section aria-labelledby="documents" className="space-y-3">
      <h2 id="documents" className="text-lg font-semibold">
        Documents
      </h2>
      {error && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {error}
        </p>
      )}

      {groups.size === 0 ? (
        <p className="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-700">Aucun document pour l&apos;instant.</p>
      ) : (
        <ul className="space-y-2">
          {[...groups.values()].map((versions) => (
            <li key={`${versions[0].type}|${versions[0].zone?.id ?? ""}`} className="space-y-2 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
              <p className="font-medium">
                {versions[0].type_label}
                {versions[0].zone && ` · ${versions[0].zone.code}`}
              </p>
              <ul className="space-y-2">
                {versions.map((v, i) => (
                  <li key={v.id} className="flex flex-wrap items-center justify-between gap-2">
                    <span className={i === 0 ? "" : "text-zinc-700"}>
                      Version {v.version}
                      {i === 0 && " (la plus récente)"} · {v.file_name} · {formatSize(v.size)}
                      <span className="block text-xs text-zinc-700">
                        {formatDateTime(v.created_at)} · {v.author?.name ?? "auteur inconnu"}
                        {v.comment && ` · ${v.comment}`}
                      </span>
                    </span>
                    <button onClick={() => download(v)} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
                      Télécharger
                    </button>
                  </li>
                ))}
              </ul>
            </li>
          ))}
        </ul>
      )}

      {uploadable.length > 0 && <UploadForm projectId={projectId} plaqueId={plaqueId} uploadable={uploadable} onDone={reload} />}
    </section>
  );
}

function UploadForm({ projectId, plaqueId, uploadable, onDone }: { projectId: string; plaqueId: string; uploadable: Documents["uploadable"]; onDone: () => void }) {
  const [type, setType] = useState(uploadable[0].type);
  const [zoneId, setZoneId] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [comment, setComment] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [fileKey, setFileKey] = useState(0);

  const needsZone = uploadable.find((u) => u.type === type)?.needs_zone ?? false;
  const zones = useApi<Paged<Zone>>(`/zones?plaque_id=${plaqueId}&per_page=100`);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!file) return;
    setErrors({});
    setMessage(null);
    setBusy(true);
    try {
      const form = new FormData();
      form.append("type", type);
      if (needsZone) form.append("zone_id", zoneId);
      form.append("file", file);
      if (comment.trim()) form.append("comment", comment.trim());
      await apiFetch(`/projects/${projectId}/documents`, { method: "POST", body: form }, getToken());
      setFile(null);
      setComment("");
      setFileKey((k) => k + 1);
      setMessage("Version ajoutée.");
      onDone();
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage(Object.keys(e.errors).length ? "Corrigez les champs indiqués." : e.message);
      } else {
        setMessage("Une erreur est survenue.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="space-y-3 rounded-xl border border-zinc-200 bg-white p-4" noValidate>
      <h3 className="font-semibold">Ajouter un document ou une nouvelle version</h3>
      {message && (
        <p role="status" className="rounded-lg bg-zinc-100 p-3 text-sm">
          {message}
        </p>
      )}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field id="doc-type" label="Type de document" error={errors.type?.[0]}>
          <select id="doc-type" value={type} onChange={(e) => setType(e.target.value)} className={inputClass}>
            {uploadable.map((u) => (
              <option key={u.type} value={u.type}>
                {u.label}
              </option>
            ))}
          </select>
        </Field>
        {needsZone && (
          <Field id="doc-zone" label="Sous-plaque" error={errors.zone_id?.[0]}>
            <select id="doc-zone" value={zoneId} onChange={(e) => setZoneId(e.target.value)} className={inputClass}>
              <option value="">Choisir une sous-plaque</option>
              {zones.status === "ok" &&
                zones.data.data.map((z) => (
                  <option key={z.id} value={z.id}>
                    {z.code}
                  </option>
                ))}
            </select>
          </Field>
        )}
      </div>
      <Field id="doc-file" label="Fichier" hint="2 Mo au plus : DWG, KMZ, PDF, Excel…" error={errors.file?.[0]}>
        <input key={fileKey} id="doc-file" type="file" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="block w-full rounded-lg border border-zinc-300 bg-white p-2 text-base" />
      </Field>
      <Field id="doc-comment" label="Commentaire (facultatif)" error={errors.comment?.[0]}>
        <input id="doc-comment" value={comment} onChange={(e) => setComment(e.target.value)} className={inputClass} />
      </Field>
      <button type="submit" disabled={busy || !file || (needsZone && !zoneId)} className="h-11 rounded-lg bg-slate-900 px-6 font-medium text-white disabled:opacity-60">
        {busy ? "Envoi…" : "Envoyer"}
      </button>
    </form>
  );
}
