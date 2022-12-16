/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./../../web/verbrauch/**/*.{html,js}"],
  theme: {
	fontFamily: {
	  'sans': ['Raleway', 'Helvetica', 'Arial', 'sans-serif'],  
	},
    extend: {
      colors: {
        differentColor: '#43302b',        
      }
    },
  },
  plugins: [],
}
