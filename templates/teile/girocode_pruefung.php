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
    <p class="hilfe">✓ <?= th('<strong>QR-Code für Banking-Apps</strong> (GiroCode) auf der Zahlungsaufforderung: an <strong>{name}</strong>,', ['name' => mb_substr($name, 0, 70)]) ?>
        IBAN <?= e(Girocode::ibanLesbar($iban)) ?><?= Girocode::bic($bank) !== null ? ', BIC ' . e((string) Girocode::bic($bank)) : '' ?> – <?= te('Betrag und Verwendungszweck füllt die App aus.') ?></p>
<?php elseif ($bank !== '' || $absender !== ''): ?>
    <p class="hilfe"><?= te('Kein QR-Code für Banking-Apps:') ?> <?= $iban === null ? te('keine gültige IBAN in der Bankverbindung erkannt (Prüfziffer)') : te('Empfänger fehlt (erste Zeile = Name)') ?>.</p>
<?php else: ?>
    <p class="hilfe"><?= te('Mit Empfänger und IBAN erhält die Zahlungsaufforderung einen QR-Code für Banking-Apps (GiroCode).') ?></p>
<?php endif; ?>
