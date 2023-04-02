<?php

if (!function_exists('kmeans')) {
    function kmeans(array $points, int $k, int $maxIterations = PHP_INT_MAX): array
    {
        // Initialize centroids randomly
        $centroids = array_rand($points, $k);
        $centroids = array_map(fn ($index) => $points[$index], $centroids);

        // Repeat until convergence or maximum iterations reached
        for ($i = 0; $i < $maxIterations; $i++) {
            $clusters = array_fill(0, $k, []);

            foreach ($points as $point) {
                // Assign each point to the closest centroid
                $distances = array_map(fn ($centroid) =>  kmeans_distance($point, $centroid), $centroids);
                $closestCentroidIndex = array_keys($distances, min($distances))[0];
                $clusters[$closestCentroidIndex][] = $point;
            }

            // Update centroids to be the mean of the points in each cluster
            $newCentroids = [];

            foreach ($clusters as $cluster) {
                if (count($cluster) > 0) {
                    $dimensions = count($cluster[0]);
                    $means = array_fill(0, $dimensions, 0);

                    foreach ($cluster as $point) {
                        for ($j = 0; $j < $dimensions; $j++) {
                            $means[$j] += $point[$j];
                        }
                    }

                    $means = array_map(fn ($mean) => $mean / count($cluster), $means);
                    $newCentroids[] = $means;
                } else {
                    // If a cluster has no points, keep the same centroid
                    $newCentroids[] = $centroids[$i];
                }
            }

            // Stop if centroids have not changed
            if ($centroids == $newCentroids) {
                break;
            }

            $centroids = $newCentroids;
        }

        return $clusters;
    }
}

if (!function_exists('kmeans_distance')) {
    function kmeans_distance(array $point1, array $point2): float
    {
        $sum = 0;
        $dimensions = count($point1);

        for ($i = 0; $i < $dimensions; $i++) {
            $sum += ($point1[$i] - $point2[$i]) ** 2;
        }

        return sqrt($sum);
    }
}
