import { describe, expect, it } from "vitest";
import { formatDateTime } from "./format";

describe("formatDateTime", () => {
  it("affiche jour/mois/année et l'heure d'Abidjan", () => {
    expect(formatDateTime("2026-10-30T18:35:00Z")).toBe("30/10/2026 18:35");
  });

  it("reste lisible sans date ou avec une date invalide", () => {
    expect(formatDateTime(null)).toBe("—");
    expect(formatDateTime("n'importe quoi")).toBe("—");
  });
});
