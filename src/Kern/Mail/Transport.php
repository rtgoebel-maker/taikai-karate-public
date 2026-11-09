<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Mail;

/** Versand einer einzelnen Nachricht (reiner Text, UTF-8, optional mit Anhängen). Wirft bei Fehlern. */
interface Transport
{
    /** @param list<array{name: string, mime: string, daten: string}> $anhaenge Inhalt roh (nicht Base64) */
    public function senden(string $empfaenger, string $betreff, string $text, array $anhaenge = []): void;
}
