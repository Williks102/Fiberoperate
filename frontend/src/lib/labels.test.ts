import { describe, expect, it } from "vitest";
import { MISSION_LABELS, pcoStatusLabel, ROLE_LABELS, VENDOR_LABELS, ZONE_STATUS_LABELS } from "./labels";

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
    expect(Object.keys(ROLE_LABELS).sort()).toEqual(["admin", "coordinator", "dg", "dt", "supervisor", "technician"]);
    expect(Object.keys(MISSION_LABELS).sort()).toEqual(["customer_connection", "deployment", "maintenance", "study"]);
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
