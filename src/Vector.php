<?php

namespace Eloquage\Vector;

/**
 * Primary entrypoint for eloquage/vector.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Vector
{
    public function name(): string
    {
        return 'vector';
    }

    /**
     * Measure two equal-dimension vectors.
     *
     * @param  list<float>  $left
     * @param  list<float>  $right
     * @param  'dot'|'l2'|'cosine'  $metric
     */
    public function measure(array $left, array $right, string $metric = 'cosine'): float
    {
        if (! in_array($metric, ['dot', 'l2', 'cosine'], true)) {
            throw new \InvalidArgumentException('Metric must be dot, l2, or cosine.');
        }

        $left = $this->validateVector($left, 'left');
        $right = $this->validateVector($right, 'right');
        $this->assertSameDimension($left, $right);
        $this->assertNonZeroVector($left, 'left');
        $this->assertNonZeroVector($right, 'right');

        return match ($metric) {
            'dot' => $this->dotProduct($left, $right),
            'l2' => $this->l2Distance($left, $right),
            'cosine' => $this->cosineSimilarity($left, $right),
        };
    }

    /**
     * Return a vector with unit L2 norm.
     *
     * @param  list<float>  $vector
     * @return list<float>
     */
    public function normalize(array $vector): array
    {
        $vector = $this->validateVector($vector, 'vector');

        return $this->unitVector($vector, 'vector');
    }

    /**
     * Score one query or a query batch against a corpus using cosine similarity.
     *
     * A vector query returns ranked records. A matrix query returns an m x n
     * score matrix in input order.
     *
     * @param  list<float>|list<list<float>>  $queries
     * @param  list<list<float>>  $corpus
     * @return list<array{index: int, score: float}>|list<list<float>>
     */
    public function similarity(array $queries, array $corpus, ?int $topK = null): array
    {
        if ($topK !== null && $topK < 1) {
            throw new \InvalidArgumentException('topK must be a positive integer.');
        }

        $corpus = $this->validateMatrix($corpus, 'corpus');

        if ($queries === []) {
            if ($topK !== null) {
                throw new \InvalidArgumentException('topK is not supported for a query batch.');
            }

            return [];
        }

        if (! array_is_list($queries)) {
            throw new \InvalidArgumentException('queries must be a zero-based contiguous list.');
        }

        if (is_array($queries[0])) {
            if ($topK !== null) {
                throw new \InvalidArgumentException('topK is not supported for a query batch.');
            }

            $queryRows = $this->validateMatrix($queries, 'queries');
            $this->assertQueryDimensions($queryRows);
            $this->assertCorpusDimensions($queryRows, $corpus);
            $normalizedCorpus = $this->normalizeRows($corpus, 'corpus');
            $normalizedQueries = $this->normalizeRows($queryRows, 'queries');
            $results = [];

            foreach ($normalizedQueries as $query) {
                $scores = [];

                foreach ($normalizedCorpus as $corpusRow) {
                    $scores[] = $this->boundedScore($this->dotProduct($query, $corpusRow));
                }

                $results[] = $scores;
            }

            return $results;
        }

        $query = $this->validateVector($queries, 'query');
        $this->assertCorpusDimensions([$query], $corpus);
        $normalizedQuery = $this->unitVector($query, 'query');
        $normalizedCorpus = $this->normalizeRows($corpus, 'corpus');
        $results = [];

        foreach ($normalizedCorpus as $index => $corpusRow) {
            $results[] = [
                'index' => $index,
                'score' => $this->boundedScore($this->dotProduct($normalizedQuery, $corpusRow)),
            ];
        }

        usort($results, static function (array $left, array $right): int {
            if ($left['score'] === $right['score']) {
                return $left['index'] <=> $right['index'];
            }

            return $left['score'] > $right['score'] ? -1 : 1;
        });

        if ($topK === null) {
            return $results;
        }

        return array_slice($results, 0, min($topK, count($results)));
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function dotProduct(array $left, array $right): float
    {
        $sum = 0.0;

        foreach ($left as $index => $value) {
            $product = $value * $right[$index];

            if (! is_finite($product)) {
                throw new \OverflowException('Vector multiplication overflowed.');
            }

            $sum += $product;

            if (! is_finite($sum)) {
                throw new \OverflowException('Vector accumulation overflowed.');
            }
        }

        return $sum;
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function l2Distance(array $left, array $right): float
    {
        $differences = [];

        foreach ($left as $index => $value) {
            $difference = $value - $right[$index];

            if (! is_finite($difference)) {
                throw new \OverflowException('Vector difference overflowed.');
            }

            $differences[] = $difference;
        }

        $distance = $this->stableNorm($differences);

        if (! is_finite($distance)) {
            throw new \OverflowException('L2 distance overflowed.');
        }

        return $distance;
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        return $this->boundedScore($this->dotProduct(
            $this->unitVector($left, 'left'),
            $this->unitVector($right, 'right'),
        ));
    }

    /**
     * Scale before squaring so normalization remains finite for large values.
     *
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function unitVector(array $vector, string $label): array
    {
        $scale = 0.0;

        foreach ($vector as $value) {
            $scale = max($scale, abs($value));
        }

        if ($scale === 0.0) {
            throw new \DomainException("{$label} has a zero L2 norm.");
        }

        $sum = 0.0;

        foreach ($vector as $value) {
            $ratio = $value / $scale;
            $sum += $ratio * $ratio;
        }

        $root = sqrt($sum);

        if (! is_finite($root) || $root <= 0.0) {
            throw new \OverflowException("{$label} normalization overflowed.");
        }

        $normalized = [];

        foreach ($vector as $value) {
            $component = ($value / $scale) / $root;

            if (! is_finite($component)) {
                throw new \OverflowException("{$label} normalization overflowed.");
            }

            $normalized[] = $component;
        }

        return $normalized;
    }

    /**
     * @param  list<float>  $vector
     */
    private function stableNorm(array $vector): float
    {
        $scale = 0.0;

        foreach ($vector as $value) {
            $scale = max($scale, abs($value));
        }

        if ($scale === 0.0) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($vector as $value) {
            $ratio = $value / $scale;
            $sum += $ratio * $ratio;
        }

        $norm = $scale * sqrt($sum);

        if (! is_finite($norm)) {
            throw new \OverflowException('L2 norm overflowed.');
        }

        return $norm;
    }

    /**
     * @param  list<float>  $vector
     */
    private function assertNonZeroVector(array $vector, string $label): void
    {
        foreach ($vector as $value) {
            if ($value != 0.0) {
                return;
            }
        }

        throw new \DomainException("{$label} has a zero L2 norm.");
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function validateVector(array $vector, string $label): array
    {
        if (! array_is_list($vector) || $vector === []) {
            throw new \InvalidArgumentException("{$label} must be a non-empty contiguous list.");
        }

        $validated = [];

        foreach ($vector as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new \InvalidArgumentException("{$label} must contain only finite numbers.");
            }

            $validated[] = (float) $value;
        }

        return $validated;
    }

    /**
     * @param  list<list<float>>  $matrix
     * @return list<list<float>>
     */
    private function validateMatrix(array $matrix, string $label): array
    {
        if (! array_is_list($matrix)) {
            throw new \InvalidArgumentException("{$label} must be a zero-based contiguous matrix.");
        }

        $validated = [];

        foreach ($matrix as $index => $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException("{$label} row {$index} must be a vector.");
            }

            $validated[] = $this->validateVector($row, "{$label} row {$index}");
        }

        return $validated;
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function assertSameDimension(array $left, array $right): void
    {
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('Vectors must have equal dimensions.');
        }
    }

    /**
     * @param  list<list<float>>  $queries
     * @param  list<list<float>>  $corpus
     */
    private function assertCorpusDimensions(array $queries, array $corpus): void
    {
        if ($corpus === []) {
            return;
        }

        $dimension = count($queries[0]);

        foreach ($corpus as $row) {
            if (count($row) !== $dimension) {
                throw new \InvalidArgumentException('Vectors must have equal dimensions.');
            }
        }
    }

    /**
     * @param  list<list<float>>  $queries
     */
    private function assertQueryDimensions(array $queries): void
    {
        $dimension = count($queries[0]);

        foreach ($queries as $row) {
            if (count($row) !== $dimension) {
                throw new \InvalidArgumentException('Vectors must have equal dimensions.');
            }
        }
    }

    /**
     * @param  list<list<float>>  $rows
     * @return list<list<float>>
     */
    private function normalizeRows(array $rows, string $label): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = $this->unitVector($row, "{$label} row");
        }

        return $normalized;
    }

    private function boundedScore(float $score): float
    {
        if (! is_finite($score)) {
            throw new \OverflowException('Similarity score overflowed.');
        }

        if ($score > 1.0 && $score <= 1.0 + 1.0e-12) {
            return 1.0;
        }

        if ($score < -1.0 && $score >= -1.0 - 1.0e-12) {
            return -1.0;
        }

        return $score;
    }
}
