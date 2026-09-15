<?php

use Eloquage\Vector\Vector;

it('measures dot, euclidean l2, and cosine similarity', function () {
    $vector = new Vector;

    expect($vector->measure([3.0, 4.0], [0.0, 4.0], 'dot'))->toBe(16.0)
        ->and($vector->measure([3.0, 4.0], [0.0, 4.0], 'l2'))->toBe(3.0)
        ->and(abs($vector->measure([3.0, 4.0], [0.0, 4.0]) - 0.8))->toBeLessThan(1.0e-12);
});

it('normalizes vectors and ignores common positive magnitude for cosine', function () {
    $vector = new Vector;

    expect($vector->normalize([3, 4]))->toEqualCanonicalizing([0.6, 0.8])
        ->and(abs($vector->measure([3, 4], [30, 40]) - 1.0))->toBeLessThan(1.0e-12);
});

it('keeps large finite normalization stable', function () {
    $vector = new Vector;
    $large = [PHP_FLOAT_MAX, PHP_FLOAT_MAX];

    expect(abs($vector->normalize($large)[0] - sqrt(0.5)))->toBeLessThan(1.0e-12)
        ->and(abs($vector->measure($large, $large) - 1.0))->toBeLessThan(1.0e-12);
});

it('fails closed when direct arithmetic overflows', function () {
    $vector = new Vector;

    expect(fn () => $vector->measure([PHP_FLOAT_MAX], [PHP_FLOAT_MAX], 'dot'))
        ->toThrow(OverflowException::class)
        ->and(fn () => $vector->measure([PHP_FLOAT_MAX], [-PHP_FLOAT_MAX], 'l2'))
        ->toThrow(OverflowException::class);
});

it('rejects invalid metrics, malformed vectors, and dimension mismatches', function () {
    $vector = new Vector;

    expect(fn () => $vector->measure([1], [1], 'manhattan'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->measure([1, 2], [1]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->measure([], []))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->normalize(['1']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->normalize([true]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->normalize([NAN]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->normalize([INF]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->normalize([1 => 1.0]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->normalize([]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects zero vectors for scalar operations', function () {
    $vector = new Vector;

    expect(fn () => $vector->measure([0, 0], [1, 0], 'dot'))
        ->toThrow(DomainException::class)
        ->and(fn () => $vector->measure([1, 0], [0, 0], 'l2'))
        ->toThrow(DomainException::class)
        ->and(fn () => $vector->normalize([0, 0]))
        ->toThrow(DomainException::class);
});

it('returns deterministic exact top-k rankings and clamps k to the corpus', function () {
    $vector = new Vector;
    $corpus = [[1, 0], [0, 1], [-1, 0], [1, 0]];

    $all = $vector->similarity([1, 0], $corpus);
    $topTwo = $vector->similarity([1, 0], $corpus, 2);
    $clamped = $vector->similarity([1, 0], $corpus, 99);

    expect(array_column($all, 'index'))->toBe([0, 3, 1, 2])
        ->and(array_column($topTwo, 'index'))->toBe([0, 3])
        ->and($clamped)->toEqual($all)
        ->and(abs($all[0]['score'] - 1.0))->toBeLessThan(1.0e-12)
        ->and(abs($all[2]['score']))->toBeLessThan(1.0e-12);
});

it('rejects invalid top-k and corpus/query shapes', function () {
    $vector = new Vector;

    expect(fn () => $vector->similarity([1, 0], [[1, 0]], 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->similarity([[1, 0]], [[1, 0]], 1))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->similarity([[1, 0], [1]], [[1, 0]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->similarity([1, 0], [[1], [1, 0]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->similarity([1, 0], [['x', 0]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $vector->similarity([1, 0], [1, 0]))
        ->toThrow(InvalidArgumentException::class);
});

it('returns empty corpus and batch shapes without changing input order', function () {
    $vector = new Vector;

    expect($vector->similarity([1, 0], []))->toBe([])
        ->and($vector->similarity([[1, 0], [0, 1]], []))->toBe([[], []])
        ->and($vector->similarity([], []))->toBe([])
        ->and($vector->similarity([[1, 0], [0, 1]], [[1, 0], [0, 1]]))
        ->toEqual([
            [1.0, 0.0],
            [0.0, 1.0],
        ]);
});

it('makes batch scores pairwise-equivalent to scalar cosine', function () {
    $vector = new Vector;
    $queries = [[1, 2], [3, 4]];
    $corpus = [[5, 6], [7, 8]];
    $batch = $vector->similarity($queries, $corpus);

    expect(abs($batch[0][0] - $vector->measure($queries[0], $corpus[0])))->toBeLessThan(1.0e-12)
        ->and(abs($batch[0][1] - $vector->measure($queries[0], $corpus[1])))->toBeLessThan(1.0e-12)
        ->and(abs($batch[1][0] - $vector->measure($queries[1], $corpus[0])))->toBeLessThan(1.0e-12)
        ->and(abs($batch[1][1] - $vector->measure($queries[1], $corpus[1])))->toBeLessThan(1.0e-12);
});

it('rejects zero rows in similarity inputs', function () {
    $vector = new Vector;

    expect(fn () => $vector->similarity([0, 0], [[1, 0]]))
        ->toThrow(DomainException::class)
        ->and(fn () => $vector->similarity([1, 0], [[0, 0]]))
        ->toThrow(DomainException::class)
        ->and(fn () => $vector->similarity([[1, 0], [0, 0]], [[1, 0]]))
        ->toThrow(DomainException::class);
});
