/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./../../web/verbrauch/**/*.{html,js,php}"],
  theme: {
	fontFamily: {
	  'sans': ['Raleway', 'Helvetica', 'Arial', 'sans-serif'],  
	},
    extend: {
      colors: {
        diffColor: {
          300: '#9ae6b4',
          700: '#2f855a',          
        },        
      },
      display: ["group-hover"],
    },
  },
  plugins: [],
}
