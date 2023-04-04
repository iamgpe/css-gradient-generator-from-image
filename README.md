# Image Color Palette Generator
Generate gradient (css) from image colors with theses steps

This PHP script generates a color palette for a given image and generates a CSS gradient string using the colors in the palette. The color palette is generated using the k-means clustering algorithm, and the colors in the gradient are sorted by luminance, from dark to light.

## Requirements
- PHP 7.4 or higher
- The GD library for PHP

## Usage
To use the script, create an instance of the `ImageService` class and call the `generateCssGradientFromImage()` method, passing in the URL of the image you want to generate the gradient from:

```php
$imageService = new ImageService();
$gradient = $imageService->generateCssGradientFromImage('https://example.com/image.jpg');
```

The function returns a string containing the CSS gradient, which you can use in your web page's styles.

Since the script no longer uses caching, it will regenerate the gradient every time the method is called with a new image path.
