"use client";

import Link from "next/link";
import { ItemIcon, NavIcon } from "@/components/nav-icons";
import { useAuth } from "@/lib/auth/auth-context";
import { groupsFor } from "@/lib/navigation";

/** Accueil : un mot de bienvenue et une carte par activité du rôle, avec ses écrans à portée de pouce. */
export default function Home() {
  const { user } = useAuth();
  if (!user) return null;

  const groups = groupsFor(user.role, user.skills);
  const firstName = user.name.split(/\s+/)[0];

  return (
    <div className="space-y-6">
      <section className="rounded-2xl bg-linear-to-br from-primary to-primary-ink p-5 text-white shadow-sm sm:p-6">
        <p className="text-sm text-white/80">{user.role_label}</p>
        <h1 className="text-2xl font-semibold tracking-tight">Bonjour {firstName}</h1>
        <p className="mt-1 text-sm text-white/80">Choisissez une activité pour commencer.</p>
      </section>

      {groups.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-300 p-6 text-center text-slate-700">Aucun écran n&apos;est disponible pour votre rôle pour l&apos;instant.</p>
      ) : (
        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {groups.map((group) => (
            <li key={group.href} className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <Link href={group.href} className="flex items-start gap-3">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-ink">
                  <NavIcon name={group.icon} className="h-5 w-5" />
                </span>
                <span className="space-y-0.5">
                  <span className="block font-semibold text-ink">{group.label}</span>
                  <span className="block text-sm text-slate-700">{group.description}</span>
                </span>
              </Link>
              <ul className="flex flex-wrap gap-2">
                {group.items.map((item) => (
                  <li key={item.href}>
                    <Link href={item.href} className="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-800 hover:border-primary/40 hover:bg-primary-soft">
                      <ItemIcon href={item.href} className="h-4 w-4 text-primary" />
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
