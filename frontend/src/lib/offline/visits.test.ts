import "fake-indexeddb/auto";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { User, Visit } from "@/lib/types";
import { resetDb } from "./db";
import { clearLocalData, loadUser, readLastSync, readReasons, readStudyDefaults, readVisits, refreshVisits, saveUser } from "./visits";

const visit = (id: string, planned_date: string): Visit => ({
  id,
  type: "study",
  planned_date,
  observation: null,
  order: { id: `o-${id}`, customer_ref: `RAB${id}`, name: "Client", phone_1: "+2250707123456", phone_2: null, address_raw: "Cité Verte", declared_latitude: null, declared_longitude: null, status: "in_study", zone: null, pco: null, ont_serial: null, fixed_line: null },
});

const json = (body: unknown) => Promise.resolve(new Response(JSON.stringify(body), { status: 200, headers: { "Content-Type": "application/json" } }));

/** Réponses de l'API : visites, motifs, réglages d'étude ; `fail` fait échouer une de ces trois routes. */
function api(visits: Visit[], fail?: "reasons" | "visits") {
  vi.stubGlobal(
    "fetch",
    vi.fn((url: string) => {
      if (url.includes("/my-visits")) return fail === "visits" ? Promise.reject(new TypeError("réseau")) : json({ data: visits, meta: {} });
      if (url.includes("outcome-reasons")) return fail === "reasons" ? Promise.reject(new TypeError("réseau")) : json({ data: [{ id: "r1", code: "customer_absent", label: "Client absent" }] });
      return json({ data: { pole_spacing_m: 50, counted_pole_types: ["concrete", "metal"] } });
    }),
  );
}

beforeEach(async () => {
  await resetDb();
});
afterEach(() => vi.unstubAllGlobals());

describe("copie locale des visites", () => {
  it("garde les visites du technicien, de la plus ancienne à la plus lointaine, avec la date de mise à jour et les listes de référence", async () => {
    api([visit("b", "2026-11-05"), visit("a", "2026-11-01")]);

    const { count } = await refreshVisits();

    expect(count).toBe(2);
    expect((await readVisits()).map((v) => v.id)).toEqual(["a", "b"]);
    expect(await readLastSync()).toMatch(/^\d{4}-\d{2}-\d{2}T/);
    expect((await readReasons()).map((r) => r.label)).toEqual(["Client absent"]);
    expect(await readStudyDefaults()).toEqual({ pole_spacing_m: 50, counted_pole_types: ["concrete", "metal"] });
  });

  it("remplace la copie : une visite réalisée ou reprogrammée ailleurs disparaît du poste", async () => {
    api([visit("a", "2026-11-01"), visit("b", "2026-11-02")]);
    await refreshVisits();

    api([visit("b", "2026-11-02")]);
    await refreshVisits();

    expect((await readVisits()).map((v) => v.id)).toEqual(["b"]);
  });

  it("ne touche à rien quand le réseau est coupé", async () => {
    api([visit("a", "2026-11-01")]);
    await refreshVisits();
    const before = await readLastSync();

    api([], "visits");
    await expect(refreshVisits()).rejects.toThrow();

    expect((await readVisits()).map((v) => v.id)).toEqual(["a"]);
    expect(await readLastSync()).toBe(before);
  });

  it("garde les motifs précédents si leur téléchargement échoue, sans perdre les visites", async () => {
    api([visit("a", "2026-11-01")]);
    await refreshVisits();

    api([visit("a", "2026-11-01"), visit("c", "2026-11-03")], "reasons");
    await refreshVisits();

    expect(await readVisits()).toHaveLength(2);
    expect((await readReasons()).map((r) => r.label)).toEqual(["Client absent"]);
  });

  it("efface tout à la déconnexion : visites, contacts des clients et utilisateur", async () => {
    api([visit("a", "2026-11-01")]);
    await refreshVisits();
    await saveUser({ id: "u1", name: "Koffi", role: "technician" } as User);
    expect((await loadUser())?.name).toBe("Koffi");

    await clearLocalData();

    expect(await readVisits()).toEqual([]);
    expect(await loadUser()).toBeNull();
    expect(await readLastSync()).toBeNull();
    expect(await readReasons()).toEqual([]);
  });
});
