<?php
/**
 * Farbschema aus den Systemeinstellungen (überschreibt --primaer/--primaer-hell; Hexwerte sind geprüft).
 * @var string|null $farbenCss  geteilt von App
 */
if (($farbenCss ?? '') !== ''): ?>
    <style><?= $farbenCss ?></style>
<?php endif;
