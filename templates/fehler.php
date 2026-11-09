<?php
/** @var int $status @var string $text */
$this->layout('layout_einfach', ['titel' => 'Fehler ' . $status]);
?>
<p class="obertitel">Fehler <?= e($status) ?></p>
<h1><?= $status === 404 ? 'Nicht gefunden' : 'Das hat nicht geklappt' ?></h1>
<pre class="fehlertext"><?= e($text) ?></pre>
<p><a href="<?= e(url('/')) ?>">Zur Startseite</a></p>
