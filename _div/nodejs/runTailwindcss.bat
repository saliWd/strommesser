copy "tailwind.config.js" "latest/tailwind.config.js"
cd latest/
npx tailwindcss -i ./../input.css -o ./../../../web/verbrauch/strommesser.css --watch --minify
cd ..
