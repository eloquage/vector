# eloquage/vector

Framework-agnostic, in-process vector math for PHP 8.3+: dot product,
square-rooted Euclidean distance, cosine similarity, L2 normalization, and
exact top-k or batch similarity. Pure PHP is the required implementation;
TypePHP extension acceleration is optional.

## Installation

```bash
composer require eloquage/vector
```

## How-to

Instantiate `Eloquage\Vector\Vector`, then pass zero-based contiguous numeric
lists to the public methods. Integers are accepted and canonicalized to floats.

```php
use Eloquage\Vector\Vector;

$vector = new Vector();

$cosine = $vector->measure([3, 4], [0, 4]); // 0.8
$distance = $vector->measure([3, 4], [0, 4], 'l2'); // 3.0
$unit = $vector->normalize([3, 4]); // [0.6, 0.8]
```

Find the nearest corpus vectors with an exact scan. Results retain the source
indices and are ordered by descending score, then ascending index for ties.

```php
$ranked = $vector->similarity(
    [1, 0],
    [[0.8, 0.2], [1, 0], [0, 1]],
    topK: 2,
);

// [
//     ['index' => 1, 'score' => 1.0],
//     ['index' => 0, 'score' => 0.9701425001...],
// ]
```

Pass a batch of queries to receive an `m × n` cosine-score matrix. Corpus row
order is preserved in the matrix.

```php
$scores = $vector->similarity(
    [[1, 0], [0, 1]],
    [[1, 0], [1, 1]],
);

// [
//     [1.0, 0.7071067811...],
//     [0.0, 0.7071067811...],
// ]
```

## Interface reference

```php
measure(array $left, array $right, string $metric = 'cosine'): float
normalize(array $vector): array
similarity(array $queries, array $corpus, ?int $topK = null): array
```

- `measure()` accepts `dot`, `l2`, or `cosine`. L2 is square-rooted Euclidean
  distance; cosine is higher-is-better and is computed from L2-normalized
  vectors.
- `normalize()` returns a unit-length vector using stable scaling, including
  for very large finite components.
- `similarity()` treats one vector query as a ranked result list and a matrix
  query as a score matrix. `topK` applies to ranked results, must be positive,
  and is clamped to the corpus size.
- All vectors and rows must be non-empty, contiguous `list<float>` values of
  equal dimension. Matrices must be contiguous lists of such rows. Empty
  corpora return an empty result list or one empty row per query; an empty
  query batch returns an empty matrix.
- Ranking is deterministic: score descending, original corpus index ascending
  on ties. Exact scanning costs `O(n·d)` for one query and `O(m·n·d)` for a
  batch; there is no ANN index, persistence, HTTP, or database layer.

## Errors

Malformed, associative, nested, non-finite, empty, or dimension-mismatched
inputs, invalid metrics, and invalid `topK` throw `InvalidArgumentException`.
Zero-norm vectors throw `DomainException`. Arithmetic that cannot be
represented as a finite result throws `OverflowException`.

## Testing

```bash
composer test
vendor/bin/pest --coverage --min=90
```

The optional extension is built by maintainers in the shared Docker workflow;
it is not a Composer dependency and does not replace `src/Vector.php`. See
[AGENTS.md](AGENTS.md) and [TYPEPHP.md](TYPEPHP.md) for repository workflows.
