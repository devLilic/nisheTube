<?php

namespace App\Domain\Discovery\Enums;

enum CandidateEvidenceState: string
{
    case Candidate = 'candidate';
    case WeakPhraseSignal = 'weak_phrase_signal';
    case Legacy = 'legacy';
}
