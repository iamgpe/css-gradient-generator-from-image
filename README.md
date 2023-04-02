# Css gradient generator from image
Generate gradient (css) from image colors with theses steps

- Recovers the colors used on an image
- Groups them in clusters by color type
- Retrieves the most used colors
- Sorts the recovered colors by brightness order 
- Generate a gradient in css and cache it to avoid redoing the calculation (yeah, it's basically done in Laravel but the idea is there)

Then it's up to you
