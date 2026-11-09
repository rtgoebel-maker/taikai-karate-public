<?php
/**
 * Zeigt, ob aus Empfänger und Bankverbindung ein Überweisungs-QR-Code (GiroCode) entsteht.
 * @var string $absender
 * @var string $bank
 */
use Wettkampf\Kern\Girocode;

$iban = Girocode::iban($bank);
$name = Girocode::ersteZeile($absender);
?>
<?php if ($iban !== null && $name !== ''): ?>
    <p class="hilfe">✓ <strong>QR-Code für Banking-Apps</strong> (GiroCode) auf der Zahlungsaufforderung: an <strong><?= e(mb_substr($name, 0, 70)) ?></strong>,
        IBAN <?= e(Girocode::ibanLesbar($iban)) ?><?= Girocode::bic($bank) !== null ? ', BIC ' . e((string) Girocode::bic($bank)) : '' ?> – Betrag und Verwendungszweck füllt die App aus.</p>
<?php elseif ($bank !== '' || $absender !== ''): ?>
    <p class="hilfe">Kein QR-Code für Banking-Apps: <?= $iban === null ? 'keine gültige IBAN in der Bankverbindung erkannt (Prüfziffer)' : 'Empfänger fehlt (erste Zeile = Name)' ?>.</p>
<?php else: ?>
    <p class="hilfe">Mit Empfänger und IBAN erhält die Zahlungsaufforderung einen QR-Code für Banking-Apps (GiroCode).</p>
<?php endif; ?>
