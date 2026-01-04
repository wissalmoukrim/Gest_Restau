/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.{js,ts,jsx,tsx}",
    "./templates/**/*.html.twig",
    "./src/**/*.php",
    "./vendor/**/*.html.twig"  // Si tu utilises des bundles
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}