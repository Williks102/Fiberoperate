"use client";

import Link from "next/link";
import { useAuth } from "@/lib/auth/auth-context";
import { groupsFor } from "@/lib/navigation";

/** Page d'accueil d'une activité : la liste de ses écrans, en grandes cartes (pratique au doigt). */
export function ActivityHub({ href }: { href: string }) {
  const { user } = useAuth();
  const group = user ? groupsFor(user.role).find((g) => g.href === href) : undefined;

  if (!group) {
    return <p className="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-700">Aucun écran de cette activité n&apos;est disponible pour votre rôle.</p>;
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">{group.label}</h1>
        <p className="text-sm text-zinc-700">{group.description}</p>
      </div>
      <ul className="grid gap-2 sm:grid-cols-2">
        {group.items.map((item) => (
          <li key={item.href}>
            <Link href={item.href} className="block min-h-11 space-y-1 rounded-xl border border-zinc-200 bg-white p-4 hover:bg-zinc-50">
              <span className="block font-medium">{item.label}</span>
              {item.description && <span className="block text-sm text-zinc-700">{item.description}</span>}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
