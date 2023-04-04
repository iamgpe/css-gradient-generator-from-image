<?php

namespace App\Http\Services;

use GdImage;

/**
 * A service for generating CSS gradients from images.
 */
class ImageService
{   
    /**
     * Calculates the luminance of a given color, based on its RGB values.
     *
     * The luminance value is computed using the following formula: 0.2126 * R + 0.7152 * G + 0.0722 * B.
     * This formula is based on the CIE 1931 color space, which defines the relationship between the spectral characteristics
     * of light and the way humans perceive color.
     *
     * @param array $color The RGB values of the color to calculate the luminance for. The array must have the following keys:
     *                     'red', 'green' and 'blue', each representing an integer value between 0 and 255.
     * @return float The calculated luminance value as a float.
     */
    private function calculateLuminance(array $color): float
    {
        return (0.2126 * $color['red']) + (0.7152 * $color['green']) + (0.0722 * $color['blue']);
    }

    /**
     * Subsamples an image so that it has a maximum dimension of $maxDimension pixels.
     *
     * If the image's width or height is larger than $maxDimension, the function scales the image down to fit within the
     * maximum dimension while preserving its aspect ratio. The scaled-down image is returned along with its new width and height.
     *
     * @param resource|GdImage $image The image to subsample. This must be a valid image resource created using one of the imagecreate* functions.
     * @param int $maxDimension The maximum dimension (width or height) that the subsampled image should have. Defaults to 300.
     * @return array An array containing the subsampled image resource, as well as its new width and height. The array has the following keys:
     *               - 0: The subsampled image resource.
     *               - 1: The new width of the subsampled image.
     *               - 2: The new height of the subsampled image.
     */
    private function subSampleImage($image, int $maxDimension = 300): array
    {
        $width = $newWidth = imagesx($image);
        $height = $newHeight = imagesy($image);

        if ($width > $maxDimension || $height > $maxDimension) {
            $aspectRatio = $width / $height;

            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = $newWidth / $aspectRatio;
            } else {
                $newHeight = $maxDimension;
                $newWidth = $newHeight * $aspectRatio;
            }
        }

        $subsampledImage = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled($subsampledImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return [
            $subsampledImage,
            $newWidth,
            $newHeight
        ];
    }

    /**
     * Generates a color palette for an image using the k-means algorithm.
     *
     * This function generates a color palette by first subsampling the input image (using subSampleImage()) and then applying
     * the k-means clustering algorithm to the resulting set of colors. The number of colors in the resulting palette is specified
     * by $paletteSize.
     *
     * @param resource|GdImage $image The image to generate a color palette for. This must be a valid image resource created using one of the imagecreate* functions.
     * @param int $paletteSize The number of colors to include in the generated palette.
     * @return array An array of color values representing the generated color palette. Each element of the array is itself an array
     *               with the following keys:
     *               - 'count': The number of pixels in the subsampled image that have this color.
     *               - 'red': The red component of the color, as an integer between 0 and 255.
     *               - 'green': The green component of the color, as an integer between 0 and 255.
     *               - 'blue': The blue component of the color, as an integer between 0 and 255.
     */
    private function imageColorPalette($image, int $paletteSize): array
    {
        [$subsampledImage, $width, $height] = $this->subSampleImage($image);
        $points = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $colorIndex = imagecolorat($subsampledImage, $x, $y);
                $color = imagecolorsforindex($subsampledImage, $colorIndex);

                $points[] = [$color['red'], $color['green'], $color['blue']];
            }
        }

        $clusters = kmeans($points, $paletteSize);
        $palette = array_map(function ($cluster) {
            $red = $green = $blue = 0;

            foreach ($cluster as $color) {
                $red += $color[0];
                $green += $color[1];
                $blue += $color[2];
            }

            return [
                'count' => count($cluster),
                'red' => intval(round($red / count($cluster))),
                'green' => intval(round($green / count($cluster))),
                'blue' => intval(round($blue / count($cluster))),
            ];
        }, $clusters);

        return $palette;
    }

    /**
     * Sorts a color palette by luminance, from dark to light.
     *
     * @param array $palette The color palette to sort. The palette should be an array of color values, where each value is an
     *                       associative array with 'red', 'green' and 'blue' keys, each representing an integer between 0 and 255.
     * @return array The sorted color palette.
     */
    private function sortByLuminance(array $palette): array
    {
        uasort($palette, function ($a, $b) {
            $aLuminance = $this->calculateLuminance($a);
            $bLuminance = $this->calculateLuminance($b);

            return $bLuminance <=> $aLuminance;
        });

        return $palette;
    }

    /**
     * Generates a CSS gradient string from a color palette.
     *
     * @param array $palette The color palette to generate the gradient from. The palette should be an array of color values, where each value is an
     *                       associative array with 'red', 'green' and 'blue' keys, each representing an integer between 0 and 255.
     * @return string The CSS gradient string.
     */
    private function generateGradient(array $palette, string $direction = 'left', string $gradientAddition = ''): string
    {
        $gradient = 'linear-gradient(to ' . $direction;

        foreach ($palette as $color) {
            $gradient .= sprintf(',rgba(%d,%d,%d,%.1f)', $color['red'], $color['green'], $color['blue'], 1);
        }

        if ($gradientAddition !== '') {
            $gradient .= ',' . $gradientAddition);
        }
        
        $gradient .= ')';

        return $gradient;
    }

    /**
     * Generates a CSS gradient from an image, based on the colors in the image.
     *
     * The function first generates a color palette for the image using the k-means algorithm, and then sorts the palette by luminance,
     * from dark to light. It then generates a CSS gradient string from the sorted palette, with the colors in the gradient going from
     * dark to light.
     *
     * The function also caches the generated gradient string using Laravel's Cache facade, using the URL of the input image as the cache key.
     * The cached value is stored for one month.
     *
     * @param string $imageURL The URL of the image to generate the gradient from.
     * @return string The generated CSS gradient string.
     */
    public function generateCssGradientFromImage(string $imageURL): string
    {
        $image = imagecreatefromjpeg($imageURL);
        $palette = $this->sortByLuminance($this->imageColorPalette($image, 2));
        $gradient = $this->generateGradient($palette);

        return $gradient;
    }
}
