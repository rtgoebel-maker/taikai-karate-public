<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

use Closure;

/**
 * Prüft das JWT, das Cloudflare Access bei jeder Anfrage im Header
 * „Cf-Access-Jwt-Assertion“ mitschickt (RS256, Schlüssel unter
 * <team_domain>/cdn-cgi/access/certs).
 *
 * Zweck: Die Anwendung beantwortet nur Anfragen, die wirklich durch
 * Cloudflare Access gelaufen sind. Wer den Webspace direkt über IP oder
 * Hoster-Domain aufruft, bekommt 403.
 *
 * Doku: https://developers.cloudflare.com/cloudflare-one/access-controls/applications/http-apps/authorization-cookie/validating-json/
 */
final class CloudflareAccess
{
    /** Höchstens so oft (Sekunden) bei unbekannter Schlüssel-ID neu laden. */
    private const NEULADEN_FRUEHESTENS = 300;

    private ?Closure $laden;

    /**
     * @param string $teamDomain z. B. https://meinteam.cloudflareaccess.com (ohne Schrägstrich am Ende)
     * @param string $aud Application Audience (AUD) Tag der Access-Anwendung
     * @param string $cacheDatei Ablage der Zertifikate, außerhalb des Web-Roots
     * @param (Closure(string): ?string)|null $laden nur für Tests: lädt die URL
     */
    public function __construct(
        private readonly string $teamDomain,
        private readonly string $aud,
        private readonly string $cacheDatei,
        private readonly int $cacheSekunden = 3600,
        ?Closure $laden = null,
        private readonly int $toleranzSekunden = 60,
    ) {
        $this->laden = $laden;
    }

    /**
     * @return string|null E-Mail aus dem Token ('' bei Service-Token ohne E-Mail), null wenn ungültig
     */
    public function pruefen(?string $jwt, ?int $jetzt = null): ?string
    {
        if ($jwt === null || $jwt === '' || $this->aud === '' || $this->teamDomain === '') {
            return null;
        }
        $teile = explode('.', $jwt);
        if (count($teile) !== 3) {
            return null;
        }
        [$kopf64, $daten64, $signatur64] = $teile;
        $kopf = json_decode((string) self::base64url($kopf64), true);
        $daten = json_decode((string) self::base64url($daten64), true);
        $signatur = self::base64url($signatur64);
        if (!is_array($kopf) || !is_array($daten) || $signatur === null) {
            return null;
        }
        if (($kopf['alg'] ?? null) !== 'RS256' || !is_string($kopf['kid'] ?? null)) {
            return null;
        }

        $zertifikat = $this->zertifikat($kopf['kid']);
        if ($zertifikat === null) {
            return null;
        }
        if (openssl_verify($kopf64 . '.' . $daten64, $signatur, $zertifikat, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }

        $jetzt ??= time();
        $aud = $daten['aud'] ?? null;
        if (!in_array($this->aud, is_array($aud) ? $aud : [$aud], true)) {
            return null;
        }
        if (($daten['iss'] ?? null) !== $this->teamDomain) {
            return null;
        }
        if (!is_numeric($daten['exp'] ?? null) || (int) $daten['exp'] < $jetzt - $this->toleranzSekunden) {
            return null;
        }
        if (isset($daten['nbf']) && (int) $daten['nbf'] > $jetzt + $this->toleranzSekunden) {
            return null;
        }
        return is_string($daten['email'] ?? null) ? $daten['email'] : '';
    }

    private function zertifikat(string $kid): ?string
    {
        $liste = $this->zertifikate(false);
        if (!isset($liste[$kid]) && $this->cacheAlter() > self::NEULADEN_FRUEHESTENS) {
            $liste = $this->zertifikate(true); // Schlüsselwechsel bei Cloudflare
        }
        return $liste[$kid] ?? null;
    }

    /** @return array<string, string> kid => PEM-Zertifikat */
    private function zertifikate(bool $neu): array
    {
        if (!$neu && $this->cacheAlter() < $this->cacheSekunden) {
            $gespeichert = json_decode((string) @file_get_contents($this->cacheDatei), true);
            if (is_array($gespeichert) && $gespeichert !== []) {
                return $gespeichert;
            }
        }
        $roh = $this->holen($this->teamDomain . '/cdn-cgi/access/certs');
        $json = $roh === null ? null : json_decode($roh, true);
        $liste = [];
        foreach ((is_array($json) ? ($json['public_certs'] ?? []) : []) as $eintrag) {
            if (is_array($eintrag) && is_string($eintrag['kid'] ?? null) && is_string($eintrag['cert'] ?? null)) {
                $liste[$eintrag['kid']] = $eintrag['cert'];
            }
        }
        if ($liste !== []) {
            @file_put_contents($this->cacheDatei, json_encode($liste), LOCK_EX);
        } elseif (is_file($this->cacheDatei)) {
            // Cloudflare nicht erreichbar: alten Stand weiterverwenden
            $alt = json_decode((string) @file_get_contents($this->cacheDatei), true);
            return is_array($alt) ? $alt : [];
        }
        return $liste;
    }

    private function cacheAlter(): int
    {
        clearstatcache(true, $this->cacheDatei);
        $zeit = @filemtime($this->cacheDatei);
        return $zeit === false ? PHP_INT_MAX : time() - $zeit;
    }

    private function holen(string $url): ?string
    {
        if ($this->laden !== null) {
            return ($this->laden)($url);
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3]);
            $antwort = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            return is_string($antwort) && $status === 200 ? $antwort : null;
        }
        $antwort = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5]]));
        return is_string($antwort) ? $antwort : null;
    }

    private static function base64url(string $teil): ?string
    {
        $roh = base64_decode(strtr($teil, '-_', '+/') . str_repeat('=', (4 - strlen($teil) % 4) % 4), true);
        return $roh === false ? null : $roh;
    }
}
