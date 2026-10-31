"use client";

import { LogOut } from "lucide-react";
import { useEffect, useRef, useState } from "react";

const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");

/** Pastille du compte : ouvre un petit menu avec le nom, le rôle et la déconnexion. */
export function AccountMenu({ name, email, roleLabel, onLogout }: { name: string; email: string; roleLabel: string; onLogout: () => void }) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => event.key === "Escape" && setOpen(false);
    const onClick = (event: MouseEvent) => rootRef.current && !rootRef.current.contains(event.target as Node) && setOpen(false);
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onClick);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("mousedown", onClick);
    };
  }, [open]);

  return (
    <div ref={rootRef} className="relative">
      <button
        onClick={() => setOpen((o) => !o)}
        aria-label={`Compte de ${name}`}
        aria-haspopup="true"
        aria-expanded={open}
        className="flex h-11 w-11 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary-ink ring-1 ring-primary/20"
      >
        {initials(name)}
      </button>
      {open && (
        <div role="group" aria-label="Compte" className="absolute right-0 top-13 z-40 w-64 space-y-1 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
          <div className="px-3 py-2">
            <p className="font-semibold text-ink">{name}</p>
            <p className="text-sm text-slate-700">{roleLabel}</p>
            <p className="truncate text-xs text-slate-600">{email}</p>
          </div>
          <button onClick={onLogout} className="flex h-11 w-full items-center gap-2 rounded-lg px-3 text-sm font-medium text-ink hover:bg-slate-100">
            <LogOut aria-hidden="true" className="h-4 w-4" />
            Se déconnecter
          </button>
        </div>
      )}
    </div>
  );
}
