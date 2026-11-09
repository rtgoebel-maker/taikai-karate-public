<?php

declare(strict_types=1);

namespace Wettkampf\Domain;

/**
 * Fachlicher Fehler mit verständlicher Meldung für die Oberfläche
 * (z. B. „Auslosung nicht möglich: Klasse hat keine Starter“).
 */
class FachFehler extends \RuntimeException {}
