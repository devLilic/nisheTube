<?php

namespace App\Domain\Discovery\Contracts;

use App\Domain\Discovery\Data\BreakoutSignal;
use App\Domain\Discovery\Data\TopicPhraseSignal;

interface TopicExpansionProvider
{
    /**
     * @param  list<BreakoutSignal>  $signals
     * @return list<TopicPhraseSignal>
     */
    public function expand(array $signals): array;
}
