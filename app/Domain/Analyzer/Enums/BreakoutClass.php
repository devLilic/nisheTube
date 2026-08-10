<?php

namespace App\Domain\Analyzer\Enums;

enum BreakoutClass: string
{
    case Underperformer = 'underperformer';
    case Normal = 'normal';
    case AboveAverage = 'above_average';
    case Strong = 'strong';
    case Breakout = 'breakout';

    public function label(): string
    {
        return match ($this) {
            self::Underperformer => 'Underperformer',
            self::Normal => 'Normal',
            self::AboveAverage => 'Above Average',
            self::Strong => 'Strong',
            self::Breakout => 'Breakout',
        };
    }
}
