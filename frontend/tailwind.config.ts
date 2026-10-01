import type { Config } from "tailwindcss";

export default {
  content: [
    "./pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        background: "var(--background)",
        foreground: "var(--foreground)",
        bet: {
          dark: "#0b0f17",
          card: "#121826",
          border: "#1e293b",
          primary: "#10b981", // Emerald/Green gaming aesthetic
          accent: "#6366f1",
          muted: "#94a3b8",
        },
      },
    },
  },
  plugins: [],
} satisfies Config;
