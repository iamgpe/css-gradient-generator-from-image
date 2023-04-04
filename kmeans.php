<?php

if (!function_exists('kmeans')) {
    /**
     * Applies the k-means clustering algorithm to a set of points.
     *
     * The function divides the input points into $k clusters by iteratively assigning each point to the closest centroid, and
     * then updating the centroids to be the mean of the points in each cluster. The algorithm repeats until convergence or
     * the maximum number of iterations is reached.
     *
     * @param array $points An array of points to cluster. Each point should be an array of numerical values representing its
     *                      dimensions.
     * @param int $k The number of clusters to divide the points into.
     * @param int $maxIterations The maximum number of iterations to perform. Defaults to PHP_INT_MAX.
     * @return array An array of clusters, each containing the points assigned to it. Each cluster is itself an array of points,
     *               with each point represented as an array of numerical values representing its dimensions.
     */
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
    /**
     * Computes the Euclidean distance between two points.
     *
     * The Euclidean distance between two points in a two-dimensional space is the length of the straight line segment connecting 
     * the two points. In higher-dimensional spaces, the Euclidean distance between two points is the square root of the sum of the 
     * squared differences of their coordinates.
     *
     * In the context of the kmeans() function, the Euclidean distance is used to determine the distance between each point and the
     * centroids of the clusters. The points are assigned to the closest centroid based on their Euclidean distance to that centroid.
     *
     * @param array $point1 The first point, represented as an array of numerical values representing its dimensions.
     * @param array $point2 The second point, represented as an array of numerical values representing its dimensions.
     * @return float The Euclidean distance between the two points.
     */
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
