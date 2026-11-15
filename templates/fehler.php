<?php
/** @var int $status @var string $text */
$this->layout('layout_einfach', ['titel' => t('Fehler {status}', ['status' => $status])]);
?>
<p class="obertitel"><?= te('Fehler {status}', ['status' => $status]) ?></p>
<h1><?= $status === 404 ? te('Nicht gefunden') : te('Das hat nicht geklappt') ?></h1>
<pre class="fehlertext"><?= e($text) ?></pre>
<p><a href="<?= e(url('/')) ?>"><?= te('Zur Startseite') ?></a></p>
