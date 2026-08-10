import { BookOpenText, Calculator, CircleHelp } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

type Provenance = 'youtube' | 'calculated' | 'inferred' | 'observed';
type AnalyticsTerm = {
    term: string;
    meaning: string;
    usefulFor: string;
    calculation: string;
    provenance: Provenance;
};

export type AnalyticsGlossaryPage =
    | 'dashboard'
    | 'research'
    | 'analyzer'
    | 'analyzer_compare'
    | 'explore'
    | 'discovery'
    | 'history'
    | 'history_compare'
    | 'watchlist';

const provenanceLabels: Record<Provenance, string> = {
    youtube: 'Date YouTube',
    calculated: 'Calculat',
    inferred: 'Inferat',
    observed: 'Observat',
};

function term(
    name: string,
    meaning: string,
    usefulFor: string,
    calculation: string,
    provenance: Provenance,
): AnalyticsTerm {
    return { term: name, meaning, usefulFor, calculation, provenance };
}

const shared = {
    opportunityScore: term(
        'Opportunity score',
        'Scor de cercetare între 0 și 100 pentru eșantionul de rezultate observat. Nu reprezintă volumul căutărilor YouTube și nici o estimare a veniturilor.',
        'Pentru prioritizarea rulărilor comparabile, întotdeauna împreună cu încrederea și momentul colectării.',
        '25% Demand momentum + 20% Competition opportunity + 20% Audience reachability + 15% Content freshness gap + 20% Creator viability.',
        'calculated',
    ),
    confidence: term(
        'Confidence',
        'Măsură separată, între 0 și 100, a completitudinii și comparabilității dovezilor. Nu mărește scorul de oportunitate.',
        'Pentru a decide câtă greutate poate fi acordată unui scor, profil inferat sau rang de dovezi.',
        'Folosește dimensiunea eșantionului utilizabil, numărul de canale, acoperirea îmbogățirii, disponibilitatea metricilor publice, mixul de formate și vârste, istoricul comparabil și erorile parțiale.',
        'calculated',
    ),
    lifetimeViewsPerDay: term(
        'Lifetime Average Views/Day',
        'Vizualizările totale normalizate după vârsta videoului. Este o medie istorică, nu viteza curentă.',
        'Pentru compararea mai corectă a videourilor cu vârste diferite decât prin vizualizări brute.',
        'Numărul total de vizualizări observat ÷ maximum dintre vârsta videoului în zile și o zi.',
        'calculated',
    ),
    median: term(
        'Median',
        'Valoarea din mijloc după sortarea eșantionului utilizabil; este mai rezistentă la valori extreme decât media aritmetică.',
        'Pentru descrierea unui video sau canal tipic într-un eșantion YouTube asimetric.',
        'Valoarea centrală pentru un număr impar de elemente; media celor două valori centrale pentru un număr par.',
        'calculated',
    ),
    observedAt: term(
        'Observed at',
        'Momentul UTC în care NisheTube a capturat snapshotul metricilor publice.',
        'Pentru diferențierea dovezilor istorice de valorile curente de pe YouTube.',
        'Timestamp stocat la colectare; valorile din cache păstrează momentul observației originale.',
        'observed',
    ),
};

const glossaries: Record<
    AnalyticsGlossaryPage,
    { title: string; description: string; terms: AnalyticsTerm[] }
> = {
    dashboard: {
        title: 'Ajutor pentru datele Dashboard',
        description:
            'Termenii folosiți în sumarul contului și graficele de tendință.',
        terms: [
            shared.opportunityScore,
            shared.confidence,
            term(
                'Best recent opportunity',
                'Cel mai mare scor de oportunitate disponibil în fereastra recentă și limitată a Dashboardului.',
                'Pentru alegerea rapidă a unei cercetări recente care merită inspectată.',
                'Maximumul scorurilor generale eligibile din rulările recente finalizate ale proprietarului; egalitățile urmează ordinea deterministă după recență.',
                'calculated',
            ),
            term(
                'Score trend',
                'Succesiunea cronologică a scorurilor de oportunitate stocate, fiecare legat de rularea sa imuabilă și de Confidence.',
                'Pentru observarea schimbării evaluărilor între încercări de cercetare finalizate.',
                'Afișează scorurile persistate după momentul calculului; nu interpolează rulările lipsă.',
                'observed',
            ),
            term(
                'YouTube search quota estimate',
                'Estimarea registrului local NisheTube pentru unitățile de căutare folosite astăzi, nu soldul oficial al proiectului Google.',
                'Pentru planificarea acțiunilor explicite care consumă cotă.',
                'Limita zilnică configurată minus consumul search înregistrat local pentru ziua curentă de cotă Pacific.',
                'calculated',
            ),
            shared.observedAt,
        ],
    },
    research: {
        title: 'Ajutor pentru rezultatul Search',
        description:
            'Termenii scorului, eșantionului, videourilor și canalelor acestei rulări.',
        terms: [
            shared.opportunityScore,
            shared.confidence,
            term(
                'Demand momentum',
                'Puterea recentă a vizionărilor observate în eșantion. Nu reprezintă volumul căutării expresiei.',
                'Pentru identificarea eșantioanelor cu cerere observată puternică și recentă.',
                'Combinație robustă și versionată între mediana și quartila superioară a Views/Day, ponderea videourilor active, recență și istoric comparabil când există.',
                'calculated',
            ),
            term(
                'Competition opportunity',
                'Măsură inversată a concentrării: o valoare mai mare indică o dominare mai redusă a canalelor consacrate.',
                'Pentru separarea cererii vizibile de presiunea concurențială.',
                'Combină concentrarea vizualizărilor, ponderea canalelor mari, numărul canalelor active distincte și duplicarea rezultatelor.',
                'calculated',
            ),
            term(
                'Audience reachability',
                'Arată cât de des canalele mici sau noi obțin o acoperire disproporționată în eșantion.',
                'Pentru a vedea dacă succesul observat nu este limitat doar la cele mai mari canale.',
                'Folosește raporturile views/subscribers în grupuri comparabile, câștigători mici și medii, Breakout share și diversitatea canalelor.',
                'calculated',
            ),
            term(
                'Content freshness gap',
                'Dovadă că cererea observată coexistă cu puțin conținut recent puternic sau cu un gol de format/categorie.',
                'Pentru identificarea tiparelor insuficient acoperite ca recență sau format.',
                'Combină vârsta câștigătorilor, deficitul uploadurilor recente puternice, persistența videourilor vechi și golurile de format/categorie, condiționat de cerere minimă.',
                'calculated',
            ),
            term(
                'Creator viability',
                'Dovadă că performanța poate susține o serie repetabilă; nu măsoară monetizarea sau venitul.',
                'Pentru evitarea oportunităților susținute de un singur outlier.',
                'Folosește câștigători repetați, cadență, stabilitatea medianei robuste, unghiuri viabile și rotația observată a conținutului.',
                'calculated',
            ),
            shared.median,
            shared.lifetimeViewsPerDay,
            term(
                'Reach ratio',
                'Vizualizările videoului în raport cu numărul public de abonați ai canalului.',
                'Pentru detectarea videourilor care ajung dincolo de baza curentă de abonați.',
                'Vizualizări observate ÷ abonați publici observați; indisponibil dacă abonații sunt ascunși sau zero.',
                'calculated',
            ),
            term(
                'Public engagement rate',
                'Proxy al interacțiunilor publice, nu watch time, satisfacție sau engagement privat.',
                'Pentru compararea interacțiunilor vizibile în eșantionul stocat.',
                '(Likeuri publice + comentarii publice) ÷ vizualizări publice × 100.',
                'calculated',
            ),
            shared.observedAt,
        ],
    },
    analyzer: {
        title: 'Ajutor pentru datele Analyzer',
        description:
            'Termenii video, canal, cohortă, evoluție și analiză inferată din acest profil.',
        terms: [
            shared.lifetimeViewsPerDay,
            term(
                'Observed Recent Views/Day',
                'Schimbarea vizualizărilor dintre două observații NisheTube, normalizată după timpul scurs.',
                'Pentru măsurarea mișcării observate recent fără a o confunda cu media pe întreaga viață.',
                '(Vizualizări curente − vizualizări precedente) ÷ zilele scurse între snapshoturi.',
                'calculated',
            ),
            term(
                'Views / subscribers',
                'Vizualizările observate ale videoului raportate la abonații publici ai canalului.',
                'Pentru identificarea unei acoperiri disproporționate față de mărimea canalului.',
                'Vizualizări video ÷ abonați publici; indisponibil când numărul este ascuns sau zero.',
                'calculated',
            ),
            term(
                'Like rate / Comment rate / Public engagement proxy',
                'Rapoarte ale interacțiunilor publice; nu reprezintă engagement privat, sentiment sau calitatea vizionării.',
                'Pentru compararea interacțiunilor vizibile ale videoului analizat.',
                'Likeuri ÷ views × 100; comentarii ÷ views × 100; respectiv (likeuri + comentarii) ÷ views × 100.',
                'calculated',
            ),
            term(
                'Channel median ratio',
                'Vizualizările videoului în raport cu mediana altor videouri valide din cohorta recentă a aceluiași canal.',
                'Pentru clasificarea performanței relative în interiorul canalului.',
                'Vizualizările videoului ÷ mediana cohortei; videoul ancoră este exclus din propria bază de comparație.',
                'calculated',
            ),
            term(
                'Recent percentile',
                'Poziția empirical midrank a videoului în setul recent utilizabil al canalului.',
                'Pentru înțelegerea poziției relative fără departajarea arbitrară a egalităților.',
                '(Valori mai mici + 0,5 × valori egale) ÷ numărul comparațiilor × 100.',
                'calculated',
            ),
            term(
                'Strong / Breakout',
                'Clase versionate în interiorul canalului, bazate pe mediana cohortei și interpretate împreună cu vârsta videoului.',
                'Pentru localizarea uploadurilor recent neobișnuit de puternice ale aceluiași canal.',
                'Strong la ≥3× mediană; Breakout strict peste 5× în video-relative-performance-v1.',
                'calculated',
            ),
            shared.median,
            term(
                'Coverage',
                'Ponderea elementelor solicitate din cohorta recentă care au detalii stocate utilizabile.',
                'Pentru evaluarea reprezentativității sumarului canalului față de eșantionul cerut.',
                'Videouri recente valide ÷ videouri recente solicitate × 100.',
                'calculated',
            ),
            term(
                'Videos / week și Videos / month',
                'Estimarea cadenței publicării în cohorta recentă înghețată.',
                'Pentru înțelegerea frecvenței observate a uploadurilor canalului.',
                'Derivat din intervalele ordonate dintre publicări; cadența lunară folosește convenția versionată de zile medii/lună.',
                'calculated',
            ),
            term(
                'Momentum class',
                'Comparația medianei Lifetime Average Views/Day dintre blocul recent și blocul precedent al cohortei.',
                'Pentru observarea direcției cohortei fără o afirmație de cauzalitate.',
                'Mediana blocului recent ÷ mediana blocului precedent, clasificată prin pragurile înghețate declining/stable/growing.',
                'calculated',
            ),
            term(
                'Consistency score',
                'Măsură robustă 0–100 a dispersiei Lifetime Average Views/Day în cohortă.',
                'Pentru diferențierea performanței repetabile de o cohortă volatilă.',
                'clamp(100 × (1 − MAD ÷ mediană), 0, 100), cu minimum cinci valori utilizabile.',
                'calculated',
            ),
            term(
                'Duration / performance correlation',
                'Asocierea Spearman dintre durată și Lifetime Average Views/Day; asocierea nu înseamnă cauzalitate.',
                'Pentru a verifica dacă duratele mai lungi sau scurte co-variază cu performanța în această cohortă.',
                'Coeficient Spearman pe minimum cinci perechi utilizabile; medianele pe intervale de durată rămân alternativa exactă.',
                'calculated',
            ),
            term(
                'Topic profile și Niche concentration',
                'Nișa, subnișa, topicurile, pilonii și concentrarea inferate din titlurile cohortei; nu sunt categoria oficială YouTube.',
                'Pentru sumarizarea tiparelor tematice recurente.',
                'Clasificare deterministă și versionată; concentrarea și Confidence folosesc acoperirea dovezilor și acordul etichetelor.',
                'inferred',
            ),
            term(
                'Topic / title-pattern Breakout rate',
                'Asocierea observată dintre un grup clasificat și videourile Breakout din canal; nu este cauzală.',
                'Pentru compararea grupurilor cu eșantion suficient din aceeași cohortă imuabilă.',
                'Intrări Breakout ÷ intrări cu clasă relativă × 100, după pragul minim înghețat.',
                'inferred',
            ),
            term(
                'Audience Signal confidence',
                'Încrederea în întrebări, teme, entități, sugestii, plângeri sau confuzii recurente inferate.',
                'Pentru prioritizarea tiparelor repetate, păstrând comentariile-sursă exacte.',
                'Combină mărimea eșantionului utilizabil, acoperirea semnalelor și acordul limbii dominante; recurența cere minimum două comentarii.',
                'inferred',
            ),
            term(
                'Transcript structure confidence',
                'Încrederea în hook, secțiuni, topicuri, entități, CTA, întrebări și structură inferate pentru o revizie de transcript.',
                'Pentru evaluarea structurii detectate fără a înlocui transcriptul original.',
                'Analiză deterministă versionată a textului furnizat de utilizator, cu dovezi prin offseturi exacte.',
                'inferred',
            ),
            term(
                'Thumbnail pattern association',
                'Caracteristici vizuale inferate și asocierea lor observată cu performanța cohortei; nu este cauzală.',
                'Pentru compararea clusterelor vizuale cu numărul de exemple și imaginile indisponibile la vedere.',
                'Caracteristici deterministe din URL-uri aprobate, grupate în clustere versionate cu agregate peste eșantionul minim.',
                'inferred',
            ),
            shared.observedAt,
        ],
    },
    analyzer_compare: {
        title: 'Ajutor pentru comparația canalelor',
        description:
            'Termenii folosiți la compararea a două încercări Analyzer imuabile.',
        terms: [
            shared.median,
            shared.lifetimeViewsPerDay,
            term(
                'Breakout rate',
                'Ponderea videourilor clasificabile care depășesc pragul Breakout păstrat în încercare.',
                'Pentru compararea rezultatelor neobișnuit de puternice între încercări compatibile.',
                'Videouri Breakout ÷ videouri cu o clasă relativă de canal × 100.',
                'calculated',
            ),
            term(
                'Compatible evidence',
                'Două valori produse din aceeași familie de dovezi și versiuni compatibile ale modelelor.',
                'Pentru evitarea comparațiilor între piețe, surse, modele sau eșantioane incompatibile.',
                'Verificări stricte ale tipului de dovezi, versiunilor și contextului necesar; valorile incompatibile rămân separate.',
                'calculated',
            ),
            term(
                'Sample count',
                'Numărul elementelor utilizabile din spatele agregatului afișat.',
                'Pentru interpretarea diferențelor împreună cu profunzimea reală a dovezilor.',
                'Numărul elementelor eligibile din cohorta fixată pentru acea familie de dovezi.',
                'observed',
            ),
            shared.observedAt,
        ],
    },
    explore: {
        title: 'Ajutor pentru datele Explore',
        description:
            'Termenii proiecțiilor stocate afișați în rezultatele Explore.',
        terms: [
            shared.opportunityScore,
            shared.confidence,
            shared.lifetimeViewsPerDay,
            shared.median,
            term(
                'Relative class',
                'Clasa stocată a performanței în interiorul canalului, produsă de formula Analyzer canonică.',
                'Pentru filtrarea videourilor după performanță contextuală, nu doar după views brute.',
                'Vizualizările fixate raportate la mediana cohortei canalului sub versiunea de prag stocată.',
                'calculated',
            ),
            term(
                'Partial',
                'Proiecția stocată este utilizabilă, dar unul sau mai multe câmpuri publice așteptate lipsesc.',
                'Pentru a nu interpreta valorile lipsă drept zero.',
                'Setat din avertismente persistate sau din absența metricilor necesare în snapshot.',
                'observed',
            ),
            shared.observedAt,
        ],
    },
    discovery: {
        title: 'Ajutor pentru dovezile Discover',
        description:
            'Termenii rangului și dovezilor candidaților din această rulare.',
        terms: [
            term(
                'Discovery evidence score',
                'Rang determinist pentru gruparea dovezilor Breakout. Nu este Opportunity score și necesită Validation Search.',
                'Pentru ordonarea temelor candidate înainte de validare.',
                'Combinație versionată între puterea Breakout stocată, recurența între seeds, profunzimea dovezilor și coerența candidatului.',
                'calculated',
            ),
            shared.confidence,
            term(
                'Breakout videos',
                'Videouri stocate care au depășit pragul Breakout relativ la canalul lor.',
                'Pentru trasarea candidatului până la dovada exactă de performanță relativă.',
                'Numărul videourilor distincte clasificate Breakout în contextul Analyzer fixat.',
                'observed',
            ),
            term(
                'Seeds',
                'Intrări de cercetare stocate distincte care contribuie dovezi unui candidat.',
                'Pentru diferențierea recurenței între eșantioane de o expresie cu o singură sursă.',
                'Numărul rulărilor seed eligibile și unice legate de dovada candidatului.',
                'observed',
            ),
            term(
                'Inferred Analyzer topics',
                'Clasificări tematice versionate atașate dovezilor Analyzer.',
                'Pentru context semantic suplimentar fără a înlocui Validation Search.',
                'Etichete tematice inferate, deduplicate, din profilurile Analyzer owner-scoped legate.',
                'inferred',
            ),
        ],
    },
    history: {
        title: 'Ajutor pentru datele History',
        description:
            'Termenii cronologiei imuabile și ai selectorului de comparații.',
        terms: [
            shared.opportunityScore,
            shared.confidence,
            term(
                'Immutable snapshot',
                'Rulare finalizată ale cărei metrici-sursă și context de formulă nu sunt rescrise.',
                'Pentru comparații ulterioare reproductibile.',
                'Leagă permanent rularea, snapshoturile, parametrii, timestampul și versiunile formulelor.',
                'observed',
            ),
            term(
                'Comparable pair',
                'Două rulări ale proprietarului cu query, piață, filtre și context de scor compatibile.',
                'Pentru prevenirea calculelor de schimbare între eșantioane diferite.',
                'Egalitate validată pentru cheile de comparație înghețate cerute de modelul History.',
                'calculated',
            ),
            shared.observedAt,
        ],
    },
    history_compare: {
        title: 'Ajutor pentru comparația History',
        description: 'Termenii schimbărilor de scor, metrici și compoziție.',
        terms: [
            shared.opportunityScore,
            shared.confidence,
            term(
                'Absolute delta',
                'Valoarea stocată ulterior minus valoarea stocată anterior.',
                'Pentru citirea schimbării exacte în unitatea metricii.',
                'Valoare after − valoare before.',
                'calculated',
            ),
            term(
                'Percent delta',
                'Schimbarea absolută raportată la valoarea anterioară.',
                'Pentru compararea proporțională a metricilor cu scări diferite.',
                '(After − before) ÷ |before| × 100; indisponibil dacă before este zero sau lipsește o valoare.',
                'calculated',
            ),
            shared.median,
            shared.lifetimeViewsPerDay,
            term(
                'New / lost / leading videos',
                'Schimbările componenței rezultatelor și cele mai puternice dovezi video între rulări.',
                'Pentru explicarea schimbărilor agregate prin elementele care au intrat, ieșit sau au condus.',
                'Diferență de mulțimi și clasare deterministă peste membershipurile celor două rulări fixate.',
                'calculated',
            ),
            shared.observedAt,
        ],
    },
    watchlist: {
        title: 'Ajutor pentru datele Watchlist',
        description:
            'Termenii observațiilor, refreshurilor și schimbărilor subiectelor monitorizate.',
        terms: [
            term(
                'Watchlist observation',
                'Snapshot al metricilor publice capturat printr-un refresh explicit.',
                'Pentru monitorizarea videourilor și canalelor selectate fără colectare automată în fundal.',
                'O observație imuabilă bazată pe Analyzer pentru fiecare refresh finalizat.',
                'observed',
            ),
            term(
                'Change since previous observation',
                'Diferența brută dintre ultimul snapshot Watchlist și cel precedent.',
                'Pentru observarea mișcării exacte dintre două refreshuri explicite.',
                'Ultima valoare stocată − valoarea precedentă pentru același subiect și aceeași metrică.',
                'calculated',
            ),
            term(
                'Partial observation',
                'Refreshul a păstrat dovezi utilizabile, dar unele câmpuri publice au fost indisponibile.',
                'Pentru păstrarea istoricului fără inventarea unor valori zero.',
                'Persistat când providerul întoarce date incomplete sau avertismente după stocarea unor valori.',
                'observed',
            ),
            shared.observedAt,
        ],
    },
};

export function AnalyticsGlossary({ page }: { page: AnalyticsGlossaryPage }) {
    const glossary = glossaries[page];

    return (
        <Sheet>
            <div className="fixed top-1/2 right-0 z-40 -translate-y-1/2">
                <SheetTrigger asChild>
                    <Button
                        variant="outline"
                        className="h-auto rounded-r-none border-r-0 bg-background/95 px-2 py-3 shadow-md backdrop-blur sm:px-3"
                        aria-label={`Deschide ${glossary.title}`}
                    >
                        <BookOpenText aria-hidden="true" />
                        <span className="hidden [writing-mode:vertical-rl] sm:inline">
                            Ajutor date
                        </span>
                    </Button>
                </SheetTrigger>
            </div>
            <SheetContent
                side="right"
                overlayClassName="bg-black/10"
                closeLabel="Închide ajutorul"
                className="inset-y-3 right-3 h-[calc(100%_-_1.5rem)] w-[min(94vw,30rem)] gap-0 overflow-hidden rounded-2xl border shadow-2xl sm:max-w-md"
            >
                <SheetHeader className="border-b px-5 py-4 pr-12 text-center">
                    <SheetTitle className="flex items-center justify-center gap-2 text-lg font-medium">
                        <CircleHelp
                            className="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {glossary.title}
                    </SheetTitle>
                    <SheetDescription className="mx-auto max-w-sm">
                        {glossary.description}
                    </SheetDescription>
                </SheetHeader>
                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-6">
                    <div className="space-y-8">
                        {glossary.terms.map((item) => (
                            <article
                                key={item.term}
                                className="border-b pb-7 last:border-b-0 last:pb-2"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <strong className="text-xl leading-tight font-bold">
                                        {item.term}
                                    </strong>
                                    <Badge
                                        variant="outline"
                                        className="font-normal"
                                    >
                                        {provenanceLabels[item.provenance]}
                                    </Badge>
                                </div>
                                <dl className="mt-4 space-y-4 text-sm leading-6">
                                    <div>
                                        <dt className="text-base font-medium">
                                            Ce înseamnă
                                        </dt>
                                        <dd className="mt-1 text-muted-foreground">
                                            {item.meaning}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-base font-medium">
                                            Pentru ce este util
                                        </dt>
                                        <dd className="mt-1 text-muted-foreground">
                                            {item.usefulFor}
                                        </dd>
                                    </div>
                                    <div className="border-l-2 border-primary/40 pl-3">
                                        <dt className="flex items-center gap-1.5 text-base font-medium">
                                            <Calculator
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Cum se calculează
                                        </dt>
                                        <dd className="mt-1 text-muted-foreground">
                                            {item.calculation}
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        ))}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
