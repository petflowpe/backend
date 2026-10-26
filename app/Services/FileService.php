<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * XML / CDR / PDF fiscales: disco privado, una carpeta por empresa.
 * Lectura dual: paths nuevos en local; legacy en public sin prefijo de empresa.
 */
class FileService
{
    private const DISK = 'local'; // storage/app/private

    private const LEGACY_DISK = 'public';

    public function saveXml($document, string $xmlContent, ?string $typeHint = null): string
    {
        $path = $this->generatePath($document, 'xml', 'A4', $typeHint);
        $this->put($path, $xmlContent);

        return $path;
    }

    public function saveCdr($document, string $cdrContent, ?string $typeHint = null): string
    {
        $path = $this->generatePath($document, 'zip', 'A4', $typeHint);
        $this->put($path, $cdrContent);

        return $path;
    }

    public function savePdf($document, string $pdfContent, string $format = 'A4', ?string $typeHint = null): string
    {
        $path = $this->generatePath($document, 'pdf', $format, $typeHint);
        $this->put($path, $pdfContent);

        return $path;
    }

    public function fileExists(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return $this->resolveDisk($path) !== null;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function downloadFile(string $path, string $downloadName, array $headers = []): ?StreamedResponse
    {
        $diskName = $this->resolveDisk($path);
        if ($diskName === null) {
            return null;
        }

        return Storage::disk($diskName)->download($path, $downloadName, $headers);
    }

    protected function put(string $path, string $content): void
    {
        $disk = Storage::disk(self::DISK);
        $directory = dirname($path);
        if ($directory !== '.' && $directory !== '') {
            $disk->makeDirectory($directory);
        }
        $disk->put($path, $content);
    }

    /**
     * Disco donde existe el path relativo (privado primero, luego público legacy).
     */
    protected function resolveDisk(string $path): ?string
    {
        if (Storage::disk(self::DISK)->exists($path)) {
            return self::DISK;
        }

        if (Storage::disk(self::LEGACY_DISK)->exists($path)) {
            return self::LEGACY_DISK;
        }

        return null;
    }

    protected function generatePath(
        $document,
        string $extension,
        string $format = 'A4',
        ?string $typeHint = null
    ): string {
        $companyId = (int) ($document->company_id ?? 0);
        if ($companyId < 1) {
            throw new \InvalidArgumentException(
                'No se puede guardar archivo fiscal sin company_id en el documento.'
            );
        }

        $date = $this->resolveDocumentDate($document);
        $dateFolder = $date->format('dmY');
        $fileName = $this->resolveFileName($document);
        $tipoComprobante = $this->getDocumentTypeName($document, $typeHint);
        $tipoArchivo = $extension === 'zip' ? 'cdr' : $extension;

        $prefix = $extension === 'zip' ? 'R-' : '';

        if ($extension === 'pdf' && $format !== 'A4') {
            $fileName .= "_{$format}";
        }

        // companies/{id}/{tipo}/{xml|cdr|pdf}/DDMMYYYY/{file}
        return "companies/{$companyId}/{$tipoComprobante}/{$tipoArchivo}/{$dateFolder}/{$prefix}{$fileName}.{$extension}";
    }

    protected function resolveDocumentDate($document): Carbon
    {
        foreach (['fecha_emision', 'fecha_resumen', 'fecha_generacion', 'fecha_referencia'] as $field) {
            if (! empty($document->{$field})) {
                return Carbon::parse($document->{$field});
            }
        }

        return Carbon::now();
    }

    protected function resolveFileName($document): string
    {
        $name = (string) ($document->numero_completo
            ?? $document->identificador
            ?? '');

        $name = trim($name);
        if ($name !== '') {
            return preg_replace('/[^\w.\-]+/', '_', $name) ?: $name;
        }

        return 'doc-' . (string) ($document->id ?? uniqid('tmp', true));
    }

    protected function getDocumentTypeName($document, ?string $typeHint = null): string
    {
        if ($typeHint === 'retention' || $typeHint === 'retencion') {
            return 'retenciones';
        }
        if ($typeHint === 'perception' || $typeHint === 'percepcion') {
            return 'percepciones';
        }

        if (isset($document->tipo_documento)) {
            return match ((string) $document->tipo_documento) {
                '01' => 'facturas',
                '03' => 'boletas',
                '07' => 'notas-credito',
                '08' => 'notas-debito',
                '09' => 'guias-remision',
                '20' => 'retenciones',
                '40' => 'percepciones',
                'RA' => 'comunicaciones-baja',
                'RC' => 'resumenes-diarios',
                default => 'otros-comprobantes',
            };
        }

        $className = class_basename($document);

        return match ($className) {
            'Invoice' => 'facturas',
            'Boleta' => 'boletas',
            'CreditNote' => 'notas-credito',
            'DebitNote' => 'notas-debito',
            'DispatchGuide' => 'guias-remision',
            'Percepcion' => 'percepciones',
            'Retencion' => 'retenciones',
            'DailySummary' => 'resumenes-diarios',
            'VoidedDocument' => 'comunicaciones-baja',
            default => 'otros-comprobantes',
        };
    }

    public function getXmlPath($document): ?string
    {
        return $this->absolutePath($document->xml_path ?? null);
    }

    public function getCdrPath($document): ?string
    {
        return $this->absolutePath($document->cdr_path ?? null);
    }

    public function getPdfPath($document): ?string
    {
        return $this->absolutePath($document->pdf_path ?? null);
    }

    protected function absolutePath(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        $diskName = $this->resolveDisk($relative);
        if ($diskName === null) {
            return null;
        }

        return Storage::disk($diskName)->path($relative);
    }

    public function downloadXml($document)
    {
        if (! $document->xml_path || ! $this->fileExists($document->xml_path)) {
            return null;
        }

        return $this->downloadFile(
            $document->xml_path,
            $this->resolveFileName($document) . '.xml',
            ['Content-Type' => 'application/xml']
        );
    }

    public function downloadCdr($document)
    {
        if (! $document->cdr_path || ! $this->fileExists($document->cdr_path)) {
            return null;
        }

        return $this->downloadFile(
            $document->cdr_path,
            'R-' . $this->resolveFileName($document) . '.zip',
            ['Content-Type' => 'application/zip']
        );
    }

    public function downloadPdf($document)
    {
        if (! $document->pdf_path || ! $this->fileExists($document->pdf_path)) {
            return null;
        }

        return $this->downloadFile(
            $document->pdf_path,
            $this->resolveFileName($document) . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function createDirectoryStructure(): void
    {
        // Estructura por empresa se crea al guardar; no hace falta seed global.
    }

    public function ensureDirectoryExists($document, string $extension): void
    {
        // Compatibilidad: generatePath + put crean el directorio al guardar.
    }
}
