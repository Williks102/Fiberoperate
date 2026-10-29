import { describe, expect, it } from "vitest";
import { pcoStatusLabel } from "./labels";

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
