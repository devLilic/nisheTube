import { Globe2, Languages } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { MarketKey } from '@/types';

const markets: Record<MarketKey, { label: string; code: string }> = {
    global_en: { label: 'Global / English', code: 'EN' },
    ro_ro: { label: 'Romania / Romanian', code: 'RO' },
    ru_ru: { label: 'Russia / Russian', code: 'RU' },
};

export function MarketBadge({ market }: { market: MarketKey }) {
    const definition = markets[market];
    const Icon = market === 'global_en' ? Globe2 : Languages;

    return (
        <Badge variant="outline" className="gap-1.5 bg-card">
            <Icon />
            {definition.label}
            <span className="text-[10px] text-muted-foreground">
                {definition.code}
            </span>
        </Badge>
    );
}
