import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* config options here */
  cacheComponents: true,
  partialPrefetching: true,
  // L'écran « Réseau » s'appelle désormais « Plaques ».
  redirects: async () => [{ source: "/reseau", destination: "/plaques", permanent: false }],
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
