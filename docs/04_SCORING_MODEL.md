# Opportunity Scoring Model

## 1. Purpose

The opportunity score ranks observed YouTube niches for research. It is a comparative decision aid, not a prediction of guaranteed views, revenue, ranking, or true search volume.

Each completed run produces:

- overall score from 0 to 100;
- five component scores from 0 to 100;
- confidence score from 0 to 100;
- plain-English explanations;
- warnings for missing or biased data;
- formula version and input summary.

This score remains attached to a validated Research run. Analyzer relative-performance metrics are evidence, not a second niche-opportunity score, and do not silently modify historical opportunity scores.

## 2. Default components and weights

| Component               | Weight | High score means                                                          |
| ----------------------- | -----: | ------------------------------------------------------------------------- |
| Demand momentum         |    25% | Returned content shows strong and recent observed viewing activity.       |
| Competition opportunity |    20% | Demand is not concentrated only among many dominant established channels. |
| Audience reachability   |    20% | Smaller/newer channels can also achieve disproportionate reach.           |
| Content freshness gap   |    15% | Demand exists while top coverage leaves a useful recency or format gap.   |
| Creator viability       |    20% | Performance appears repeatable enough to support a content series.        |

```text
overall = 0.25*demand
        + 0.20*competition_opportunity
        + 0.20*audience_reachability
        + 0.15*freshness_gap
        + 0.20*creator_viability
```

Round only for display. Persist sufficient decimal precision for comparisons.

## 3. Normalization rules

- Use robust statistics: medians, percentiles, interquartile ranges, and winsorized values where outliers would dominate.
- Prefer within-market and within-collection-window percentile benchmarks.
- Do not compare raw views across very different video ages without a rate such as views per day.
- Separate Shorts from long-form when enough data exists; otherwise emit a mixed-format warning.
- Missing/hidden channel statistics reduce confidence rather than being treated as zero.
- Formula inputs must come from stored snapshots, making the calculation deterministic and testable.

## 4. Component definitions for formula `v1`

These definitions guide implementation. Exact percentile breakpoints and minimum sample thresholds must be implemented as configuration and frozen in versioned tests.

### 4.1 Demand momentum — 25%

Signals:

- median and upper-quartile views per day;
- share of sampled videos with meaningful recent velocity;
- recency-weighted performance of returned videos;
- positive change versus a comparable previous snapshot, when available.

Without historical data, calculate a cross-sectional baseline and reduce confidence. Do not infer platform-wide query volume.

### 4.2 Competition opportunity — 20%

Signals are inverted so a higher component is better:

- concentration of views among the top channels;
- share of results controlled by very large channels;
- number of distinct active channels in the sample;
- result duplication and dominance across the first result pages.

High raw competition pressure produces a low opportunity component. A niche with no visible competition but no demand must not receive a high overall score because demand remains independently weighted.

### 4.3 Audience reachability — 20%

Signals:

- views-to-subscriber ratio where subscribers are public;
- share of top-performing videos from small and mid-sized channels;
- breakout rate: videos materially outperforming their channel-size peer group;
- diversity of channels represented among top results.

Use peer-group percentiles rather than one universal subscriber threshold.

### 4.4 Content freshness gap — 15%

Signals:

- age distribution of top-performing results;
- scarcity of strong recent uploads relative to observed demand;
- persistence of older winners;
- underserved duration/format/category patterns when reliably identified.

The score must combine gap evidence with minimum demand evidence. Old results in a dead topic are not an opportunity by themselves.

### 4.5 Creator viability — 20%

Signals:

- multiple successful videos per relevant channel/topic, not only one outlier;
- repeatability of publishing cadence and performance;
- stability of median performance after excluding the largest outlier;
- evidence of multiple viable angles or subtopics;
- manageable content turnover inferred from the sample.

This component is about a sustainable series, not monetization. The API does not provide reliable revenue data.

## 5. Confidence score

Confidence is separate from opportunity. It should consider:

- sample size and number of unique channels;
- enrichment completion percentage;
- availability of subscriber/engagement fields;
- mix of formats and ages;
- presence of a comparable historical run;
- API partial failures.

Suggested labels:

- 80–100: High confidence;
- 60–79: Moderate confidence;
- 40–59: Limited confidence;
- below 40: Exploratory only.

The UI always shows confidence next to opportunity.

## 6. Versioning

- Start with `niche-opportunity-v1`.
- Never change the behavior of a released version silently.
- A new weight, input, normalization, or threshold creates a new version.
- Historical scores remain attached to their original version.
- Comparisons across different versions show a warning and compare components only when semantically compatible.

## 7. Required test fixtures

Create deterministic fixtures for:

- strong demand with dominant large-channel competition;
- strong reach by small channels;
- stale low-demand results;
- one viral outlier among weak results;
- missing subscriber counts;
- mixed Shorts and long-form results;
- insufficient sample;
- identical inputs producing identical scores;
- each component monotonicity where expected.

## 8. Analyzer metric versions

Analyzer calculations use separate version identifiers, initially planned as `video-relative-performance-v1` and `channel-behavior-v1`. They include the frozen recent-video limit, cohort membership, cache/source timestamps, channel-size bands, breakout thresholds, and exact missing-data warnings.

Required invariants:

- the anchor is excluded from its median/average baseline when it appears in the recent cohort, but included in rank and percentile;
- raw-view breakout class is shown with age and `Lifetime Average Views/Day` context;
- missing/zero subscribers, views, likes, comments, durations, or baseline denominators produce null plus warnings, not zero-valued claims or errors;
- observed growth requires at least two comparable immutable snapshots and never backfills the pre-first-seen period;
- `Observed Recent Views/Day` and `Lifetime Average Views/Day` remain separate fields and labels;
- consistency returns insufficient data until its named robust formula has the configured minimum sample.

For `video-relative-performance-v1`, the anchor baseline requires at least three other cohort videos with public views. Rank is competition rank (`1 + videos with strictly greater views`), equal values share rank, and percentile uses empirical midrank (`below + 0.5 × equal`) divided by the comparison count. Channel Strong share counts ratios `>=3x`; Breakout share counts ratios `>5x`. Missing views, an insufficient baseline, or a zero median returns null with a warning rather than a zero-valued classification.

For `channel-behavior-v1`:

- the effective version, block sizes, minimum samples, and thresholds are frozen in the collection configuration context when the Analyzer attempt is created, so a queued retry remains reproducible if local configuration later changes;
- momentum compares the median Lifetime Average Views/Day of the five newest fixed playlist positions with the next five; both blocks require five public values. A ratio `<0.8x` is `declining`, `0.8x–1.2x` is `stable`, and `>1.2x` is `growing`;
- consistency requires at least five public Lifetime Average Views/Day values. Its score is `clamp(100 × (1 - MAD / median), 0, 100)`, where MAD is median absolute deviation. A zero median is insufficient. Scores `>=75` are `consistent`, `50–<75` are `mixed`, and `<50` are `volatile`;
- duration/performance uses Spearman rank correlation over at least five videos with public Lifetime Average Views/Day and duration. Absolute coefficients `<0.3` are weak, `<0.7` are moderate, and otherwise strong, with positive/negative direction. Duration bucket medians remain visible and the UI labels this as observed correlation, not causation;
- observed growth compares the current pinned snapshot with the latest strictly earlier owner-scoped snapshot for the same entity. Deltas may be negative when YouTube corrects a count. Growth percentage requires a positive earlier count. `Observed Recent Views/Day` is the observed view delta divided by elapsed days and is never substituted for `Lifetime Average Views/Day`;
- cached attempts that reuse a snapshot do not create a new history point. History begins at the user's stored first-seen time, exposes at most 24 retained unique observations, and never infers the publication-to-first-seen period.

Discovery may rank an Analyzer breakout as candidate evidence, but validation still creates a normal market/query Research run before an opportunity score is presented.

## 9. Semantic performance versions

`semantic-performance-v1` groups only the pinned `channel_recent_upload` cohort. Topic membership reuses the exact evidence IDs from `semantic-title-terms-v1`; `editorial-title-patterns-v1` deterministically detects multilingual how-to, question, numbered-list, comparison, guide/tutorial, review, and challenge structures. Videos without a detected group remain in explicit Unclassified rows.

The frozen minimum sample is two videos. A group always retains its count and evidence IDs, but median/average views, median/average Lifetime Average Views/Day, and Breakout rate remain null until that specific metric has at least two public/classified inputs. Breakout rate is `Breakout inputs / inputs with a channel-relative class`, using the Analyzer's pinned threshold version. Groups may overlap because one title can contain multiple detected topics or editorial patterns. These values describe association inside one stored channel cohort; they do not claim that wording or topic caused performance and do not alter opportunity scoring.

## 9. Semantic performance versions

`semantic-performance-v1` groups only the pinned `channel_recent_upload` cohort. Topic membership reuses the exact evidence IDs from `semantic-title-terms-v1`; `editorial-title-patterns-v1` deterministically detects multilingual how-to, question, numbered-list, comparison, guide/tutorial, review, and challenge structures. Videos without a detected group remain in explicit Unclassified rows.

The frozen minimum sample is two videos. A group always retains its count and evidence IDs, but median/average views, median/average Lifetime Average Views/Day, and Breakout rate remain null until that specific metric has at least two public/classified inputs. Breakout rate is `Breakout inputs / inputs with a channel-relative class`, using the Analyzer's pinned threshold version. Groups may overlap because one title can contain multiple detected topics or editorial patterns. These values describe association inside one stored channel cohort; they do not claim that wording or topic caused performance and do not alter opportunity scoring.

See `12_UNIFIED_ANALYZER_MODEL.md` for the formulas, provenance, and cross-workflow boundaries.

## 10. Audience Signal versions

`audience-comment-terms-v1` deterministically derives repeated questions, topics, entities, suggestions, complaints, and confusion points only from a pinned stored top-level-comment collection. A signal requires recurrence in at least two comments and records exact comment evidence, comment/occurrence counts, language, and confidence. Overall confidence combines usable sample size, signal coverage, and dominant-language agreement.

Fewer than three stored or safe meaningful comments returns `insufficient`; mixed-language or partially excluded input returns `partial`; a sample with no safe meaningful input returns `unsafe`. URLs, email addresses, phone-like identifiers, and explicitly unsafe phrases are excluded from labels. These values are inferred sample patterns, not authoritative sentiment, full-audience measurement, causal evidence, or opportunity-score inputs.
