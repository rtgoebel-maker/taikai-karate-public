<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\UploadedFile;

/**
 * Übersetzt zwischen PSR-7 (Slim) und den schlanken Request/Response-Klassen,
 * mit denen die Controller arbeiten.
 */
final class PsrBruecke
{
    /** Pfad innerhalb der Anwendung, z. B. '/turnier/5' (ohne Basis-Pfad, ohne /index.php, ohne Schrägstrich am Ende). */
    public static function pfad(ServerRequestInterface $request): string
    {
        $vorhanden = $request->getAttribute('pfad');
        if (is_string($vorhanden)) {
            return $vorhanden;
        }
        $uri = rawurldecode($request->getUri()->getPath());
        $basis = Url::basis();
        if ($basis !== '' && str_starts_with($uri, $basis)) {
            $uri = substr($uri, strlen($basis));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }
        return '/' . trim($uri, '/');
    }

    public static function istApi(ServerRequestInterface $request): bool
    {
        return str_starts_with(self::pfad($request), '/api/');
    }

    public static function request(ServerRequestInterface $request): Request
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $werte) {
            $headers[strtolower((string) $name)] = implode(', ', $werte);
        }
        $post = $request->getParsedBody();
        return new Request(
            strtoupper($request->getMethod()),
            self::pfad($request),
            $request->getQueryParams(),
            is_array($post) ? $post : [],
            self::dateien($request->getUploadedFiles()),
            $headers,
            (string) $request->getBody(),
        );
    }

    public static function antwort(Response $antwort, ?ResponseInterface $psr = null): ResponseInterface
    {
        $psr = ($psr ?? (new ResponseFactory())->createResponse())->withStatus($antwort->status);
        foreach ($antwort->headers as $name => $wert) {
            $psr = $psr->withHeader($name, $wert);
        }
        $psr->getBody()->write($antwort->body);
        return $psr;
    }

    /**
     * Hochgeladene Dateien im Aufbau von $_FILES (name, type, tmp_name, error, size), wie ihn die Controller erwarten.
     *
     * @param array<mixed> $dateien
     * @return array<mixed>
     */
    private static function dateien(array $dateien): array
    {
        $liste = [];
        foreach ($dateien as $name => $datei) {
            if (is_array($datei)) {
                $liste[$name] = self::dateien($datei);
            } elseif ($datei instanceof UploadedFileInterface) {
                $liste[$name] = [
                    'name' => (string) $datei->getClientFilename(),
                    'type' => (string) $datei->getClientMediaType(),
                    'tmp_name' => $datei instanceof UploadedFile ? $datei->getFilePath() : '',
                    'error' => $datei->getError(),
                    'size' => (int) $datei->getSize(),
                ];
            }
        }
        return $liste;
    }
}
