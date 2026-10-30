import { describe, expect, it } from "vitest";
import { MISSION_LABELS, pcoStatusLabel, phaseProgress, PROJECT_PHASES, ROLE_LABELS, VENDOR_LABELS, ZONE_STATUS_LABELS } from "./labels";

describe("pcoStatusLabel", () => {
  it("traduit un statut normalisé", () => {
    expect(pcoStatusLabel({ status: "spliced", status_raw: null })).toBe("Soudure faite");
  });

  it("garde le libellé d'origine tant que le statut n'est pas normalisé", () => {
    expect(pcoStatusLabel({ status: null, status_raw: "En attente BE" })).toBe("En attente BE");
  });

  it("indique l'absence de statut", () => {
    expect(pcoStatusLabel({ status: null, status_raw: null })).toBe("Non renseigné");
    expect(pcoStatusLabel({ status: null, status_raw: "  " })).toBe("Non renseigné");
  });
});

describe("libellés des rôles et missions", () => {
  it("couvrent tous les rôles et toutes les habilitations", () => {
    expect(Object.keys(ROLE_LABELS).sort()).toEqual(["admin", "be_manager", "coordinator", "dg", "dt", "supervisor", "technician"]);
    expect(Object.keys(MISSION_LABELS).sort()).toEqual(["customer_connection", "deployment", "drawing", "maintenance", "study", "survey"]);
  });
});

describe("libellés des sous-plaques", () => {
  it("nomment le fabricant avec son vocabulaire (hub chez Huawei, SRO chez Nokia)", () => {
    expect(VENDOR_LABELS.huawei).toContain("hub");
    expect(VENDOR_LABELS.nokia).toContain("SRO");
  });

  it("couvrent les quatre statuts d'une sous-plaque", () => {
    expect(Object.keys(ZONE_STATUS_LABELS).sort()).toEqual(["closed", "deploying", "in_operation", "planned"]);
  });
});

describe("phaseProgress", () => {
  it("marque les phases passées, la courante et celles à venir", () => {
    const steps = phaseProgress("client_review");
    expect(steps.map((s) => s.state)).toEqual(["done", "done", "done", "current", "todo", "todo", "todo", "todo"]);
  });

  it("garde l'ordre du parcours et les huit phases", () => {
    expect(PROJECT_PHASES).toHaveLength(8);
    expect(PROJECT_PHASES[0].value).toBe("pre_project");
    expect(PROJECT_PHASES[7].value).toBe("in_operation");
    expect(phaseProgress("pre_project").filter((s) => s.state === "current")).toHaveLength(1);
    expect(phaseProgress("in_operation").every((s) => s.state !== "todo")).toBe(true);
  });
});
