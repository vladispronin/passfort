import type { Config } from 'tailwindcss'

export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50:  '#eef2ff',
          100: '#e0e7ff',
          500: '#4361ee',
          600: '#3a56e8',
          700: '#2d44c7',
          900: '#1e2d6b',
        },
      },
    },
  },
  plugins: [],
} satisfies Config
