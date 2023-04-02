<?php

namespace App\Http\Services;

use App\Http\Enum\CacheKeysEnum;
use GdImage;
use Illuminate\Support\Facades\Cache;

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

    private function sortByLuminance(array $palette): array
    {
        uasort($palette, function ($a, $b) {
            $aLuminance = $this->calculateLuminance($a);
            $bLuminance = $this->calculateLuminance($b);

            return $bLuminance <=> $aLuminance;
        });

        return $palette;
    }

    private function generateGradient(array $palette): string
    {
        $gradient = 'linear-gradient(to left';

        foreach ($palette as $color) {
            $gradient .= sprintf(',rgba(%d,%d,%d,%.1f)', $color['red'], $color['green'], $color['blue'], 1);
        }

        $gradient .= ',rgb(var(--page-background)))';

        return $gradient;
    }

    public function generateCssGradientFromImage(string $imageURL): string
    {
        $cacheKey = CacheKeysEnum::mountKey(CacheKeysEnum::MOVIE_IMAGE_GRADIENT, compact('imageURL'));

        if (!Cache::has($cacheKey)) {
            $image = imagecreatefromjpeg($imageURL);
            $palette = $this->sortByLuminance($this->imageColorPalette($image, 2));
            $gradient = $this->generateGradient($palette);

            Cache::put($cacheKey, $gradient, now()->addMonth());
        }

        return Cache::get($cacheKey);
    }
}
