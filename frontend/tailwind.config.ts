import type { Config } from "tailwindcss";
import typography from "@tailwindcss/typography";

const config: Config = {
  content: [
    "./pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          black: "#000000",
          white: "#FFFFFF",
        },
        accent: {
          red: "#DC2626",
          orange: "#F97316",
        },
        gray: {
          light: "#F3F4F6",
          medium: "#6B7280",
          dark: "#1F2937",
        },
      },
      fontFamily: {
        sans: ["Inter", "sans-serif"],
        heading: ["DM Sans", "Poppins", "sans-serif"],
      },
      typography: {
        DEFAULT: {
          css: {
            maxWidth: 'none',
            h2: {
              marginTop: '1.5em',
              marginBottom: '0.75em',
            },
            h3: {
              marginTop: '1.25em',
              marginBottom: '0.5em',
            },
            p: {
              marginTop: '0.75em',
              marginBottom: '0.75em',
            },
            'ul, ol': {
              marginTop: '0.5em',
              marginBottom: '0.75em',
            },
            li: {
              marginTop: '0.25em',
              marginBottom: '0.25em',
            },
            blockquote: {
              marginTop: '1.25em',
              marginBottom: '1.25em',
            },
          },
        },
      },
    },
  },
  plugins: [typography],
};
export default config;
