import { describe, expect, it } from "vitest";
import { NAV_GROUPS } from "../lib/navigation";
import { NAV_ICONS } from "./nav-icons";

describe("icônes du menu", () => {
  it("existent pour l'accueil et pour chaque activité", () => {
    expect(NAV_ICONS.home).toBeDefined();
    for (const group of NAV_GROUPS) expect(NAV_ICONS[group.icon], group.label).toBeDefined();
  });

  it("sont distinctes d'une activité à l'autre", () => {
    const icons = NAV_GROUPS.map((g) => g.icon);
    expect(new Set(icons).size).toBe(icons.length);
  });
});
