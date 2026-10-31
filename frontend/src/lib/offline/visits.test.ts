import "fake-indexeddb/auto";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { User, Visit } from "@/lib/types";
import { resetDb } from "./db";
import { clearLocalData, loadUser, readLastSync, readReasons, readStudyDefaults, readVisit, readVisits, refreshPcos, refreshVisits, saveUser, searchPcos } from "./visits";

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

const pco = (code: string, zone = "FAY03", splitter = "SPL001") => ({ id: `id-${code}`, code, zone, splitter });

/** Catalogue des PCO servi en deux pages ; `calls` compte les téléchargements. */
function catalogue(all: ReturnType<typeof pco>[], pageSize = 2) {
  const calls = { n: 0 };
  vi.stubGlobal(
    "fetch",
    vi.fn((url: string) => {
      if (!url.includes("pco-catalog")) return json({ data: [], meta: {} });
      calls.n++;
      const page = Number(new URL(url, "http://x").searchParams.get("page") ?? 1);
      const last = Math.max(1, Math.ceil(all.length / pageSize));

      return json({ data: all.slice((page - 1) * pageSize, page * pageSize), meta: { current_page: page, last_page: last, total: all.length } });
    }),
  );

  return calls;
}

describe("PCO du poste (étude sans réseau)", () => {
  it("télécharge toutes les pages et retrouve un PCO malgré les séparateurs et la casse", async () => {
    catalogue([pco("FAY03_PCO001"), pco("FAY03_PCO002"), pco("ABB01_PCO001", "ABB01")]);

    expect(await refreshPcos()).toBe(3);

    expect((await searchPcos("fay03 pco002")).map((p) => p.code)).toEqual(["FAY03_PCO002"]);
    expect((await searchPcos("ABB01")).map((p) => p.zone)).toEqual(["ABB01"]);
    expect((await searchPcos("")).map((p) => p.code)).toEqual(["ABB01_PCO001", "FAY03_PCO001", "FAY03_PCO002"]);
    expect(await searchPcos("introuvable")).toEqual([]);
  });

  it("ne retélécharge pas la liste plus d'une fois par jour, sauf si on le demande", async () => {
    const calls = catalogue([pco("FAY03_PCO001")]);

    await refreshPcos();
    await refreshPcos();
    expect(calls.n).toBe(1);

    await refreshPcos(true);
    expect(calls.n).toBe(2);
  });

  it("garde l'ancienne liste si le téléchargement échoue en cours de route", async () => {
    catalogue([pco("FAY03_PCO001")]);
    await refreshPcos();

    vi.stubGlobal("fetch", vi.fn(() => Promise.reject(new TypeError("réseau"))));
    await expect(refreshPcos(true)).rejects.toThrow();

    expect((await searchPcos("PCO001")).map((p) => p.code)).toEqual(["FAY03_PCO001"]);
  });

  it("retrouve une visite par son identifiant et efface la liste des PCO à la déconnexion", async () => {
    api([visit("a", "2026-11-01")]);
    await refreshVisits();

    expect((await readVisit("a"))?.order.customer_ref).toBe("RABa");
    expect(await readVisit("inconnue")).toBeUndefined();

    catalogue([pco("FAY03_PCO001")]);
    await refreshPcos(true);
    await clearLocalData();
    expect(await searchPcos("")).toEqual([]);
  });
});
