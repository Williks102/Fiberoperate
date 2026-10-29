"use client";

import { useAuth } from "@/lib/auth/auth-context";

export default function Home() {
  const { user } = useAuth();

  return (
    <section className="space-y-1 rounded-xl border border-zinc-200 bg-white p-5">
      <h1 className="text-xl font-semibold">Bonjour {user?.name}</h1>
      <p className="text-sm text-zinc-600">
        {user?.role_label} · {user?.email}
      </p>
    </section>
  );
}
