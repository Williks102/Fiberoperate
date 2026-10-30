import { describe, expect, it } from "vitest";
import type { Role } from "./types";
import { BOTTOM_BAR, BOTTOM_BAR_MAX, bottomBarFor, isActive, navFor, NAV_ITEMS } from "./navigation";

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

describe("barre du bas (téléphone)", () => {
  const roles = Object.keys(BOTTOM_BAR) as Role[];

  it("ne dépasse jamais 4 entrées et range le reste dans « Plus »", () => {
    for (const role of roles) {
      const { main, all, hasMore } = bottomBarFor(role);
      expect(main.length).toBeLessThanOrEqual(BOTTOM_BAR_MAX);
      expect(hasMore).toBe(all.length > BOTTOM_BAR_MAX);
    }
    expect(bottomBarFor("admin").hasMore).toBe(true);
  });

  it("ne propose « Plus » que si des entrées ne tiennent pas dans la barre", () => {
    expect(bottomBarFor("supervisor").hasMore).toBe(false);
    expect(bottomBarFor("technician").main.map((i) => i.href)).toEqual(["/", "/reseau"]);
  });

  it("n'affiche dans la barre que des écrans auxquels le rôle a droit", () => {
    for (const role of roles) {
      const allowed = navFor(role).map((i) => i.href);
      for (const item of bottomBarFor(role).main) expect(allowed).toContain(item.href);
    }
  });

  it("garde le menu complet dans le tiroir", () => {
    for (const role of roles) expect(bottomBarFor(role).all).toEqual(navFor(role));
  });
});
