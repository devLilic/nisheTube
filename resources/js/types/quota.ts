export type QuotaBucketSummary = {
    bucket: string;
    measure: 'requests' | 'units';
    allowance: number;
    used: number;
    remaining: number;
    exhausted: boolean;
    last_endpoint: string | null;
    last_cost: number | null;
    last_occurred_at: string | null;
    last_outcome: string | null;
};

export type QuotaSummary = {
    label: 'NisheTube estimate';
    authoritative: false;
    generated_at: string;
    reset_at: string;
    buckets: QuotaBucketSummary[];
};
