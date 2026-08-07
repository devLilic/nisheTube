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

## 2. Default components and weights

| Component | Weight | High score means |
|---|---:|---|
| Demand momentum | 25% | Returned content shows strong and recent observed viewing activity. |
| Competition opportunity | 20% | Demand is not concentrated only among many dominant established channels. |
| Audience reachability | 20% | Smaller/newer channels can also achieve disproportionate reach. |
| Content freshness gap | 15% | Demand exists while top coverage leaves a useful recency or format gap. |
| Creator viability | 20% | Performance appears repeatable enough to support a content series. |

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

