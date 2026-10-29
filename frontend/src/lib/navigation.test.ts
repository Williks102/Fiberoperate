import { describe, expect, it } from "vitest";
import { isActive, navFor, NAV_ITEMS } from "./navigation";

describe("navigation", () => {
  it("montre l'accueil à tous les rôles", () => {
    for (const role of ["admin", "dg", "dt", "supervisor", "coordinator", "technician"] as const) {
      expect(navFor(role).some((item) => item.href === "/")).toBe(true);
    }
  });

  it("filtre les entrées réservées à certains rôles", () => {
    const reserved = { href: "/secret", label: "Secret", roles: ["admin" as const] };
    NAV_ITEMS.push(reserved);
    try {
      expect(navFor("admin")).toContain(reserved);
      expect(navFor("technician")).not.toContain(reserved);
    } finally {
      NAV_ITEMS.pop();
    }
  });

  it("repère l'entrée active", () => {
    expect(isActive("/", "/")).toBe(true);
    expect(isActive("/plaques", "/")).toBe(false);
    expect(isActive("/plaques/abc", "/plaques")).toBe(true);
    expect(isActive("/plaquesX", "/plaques")).toBe(false);
  });
});
