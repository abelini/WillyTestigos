<?php

declare(strict_types=1);

namespace Willy\Service;

use FPDF;
use Willy\Domain\Job;
use Willy\Domain\PdfLayout;
use Willy\Domain\PdfResult;
use Willy\Domain\ProcessedImage;
use Willy\Exception\ProcessingException;
use Willy\Support\Clock;
use Willy\Support\FileSystem;

use function count;
use function dirname;
use function mb_convert_encoding;

final class PdfBuilder
{
    private const COVER_LINE_Y = 74.0;
    private const COVER_LINE_WIDTH = 60.0;
    private const COVER_TITLE_Y = 88.0;
    private const COVER_TITLE_HEIGHT = 16.0;
    private const COVER_TITLE_SIZE = 30.0;
    private const COVER_PERIOD_Y = 108.0;
    private const COVER_PERIOD_HEIGHT = 26.0;
    private const COVER_PERIOD_SIZE = 40.0;
    private const COVER_SUBTITLE_Y = 146.0;
    private const COVER_SUBTITLE_HEIGHT = 12.0;
    private const COVER_SUBTITLE_SIZE = 16.0;

    public function __construct(
        private readonly PdfLayout $layout,
        private readonly FileSystem $files,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param list<ProcessedImage> $images
     */
    public function build(Job $job, array $images, string $outputPath): PdfResult
    {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins($this->layout->margin(), $this->layout->margin(), $this->layout->margin());
        $pdf->SetTitle($this->latin1($job->month->title()));
        $pdf->SetAuthor($this->latin1('Proyecto Z'));
        $pdf->SetCreator($this->latin1('Willy Testigos'));

        $this->drawCover($pdf, $job);
        $this->drawPhotos($pdf, $images);

        $this->files->ensureDirectory(dirname($outputPath));

        $pdf->Output('F', $outputPath);

        if (!$this->files->isFile($outputPath)) {
            throw new ProcessingException('No se pudo escribir el PDF.');
        }

        $pages = $this->layout->totalPages(count($images));

        return new PdfResult(
            path: $outputPath,
            pages: $pages,
            size: $this->files->size($outputPath),
            sha256: $this->files->sha256($outputPath),
        );
    }

    private function drawCover(FPDF $pdf, Job $job): void
    {
        $pdf->AddPage();
        $center = $this->layout->pageWidth() / 2;

        $pdf->SetDrawColor(30, 30, 30);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(
            $center - (self::COVER_LINE_WIDTH / 2),
            self::COVER_LINE_Y,
            $center + (self::COVER_LINE_WIDTH / 2),
            self::COVER_LINE_Y,
        );

        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont('Helvetica', 'B', self::COVER_TITLE_SIZE);
        $pdf->SetXY(0, self::COVER_TITLE_Y);
        $pdf->Cell(
            $this->layout->pageWidth(),
            self::COVER_TITLE_HEIGHT,
            $this->latin1('TESTIGOS DEL MES'),
            0,
            0,
            'C',
        );

        $pdf->SetFont('Helvetica', 'B', self::COVER_PERIOD_SIZE);
        $pdf->SetXY(0, self::COVER_PERIOD_Y);
        $pdf->Cell(
            $this->layout->pageWidth(),
            self::COVER_PERIOD_HEIGHT,
            $this->latin1($job->coverPeriod($this->clock)),
            0,
            0,
            'C',
        );

        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetFont('Helvetica', '', self::COVER_SUBTITLE_SIZE);
        $pdf->SetXY(0, self::COVER_SUBTITLE_Y);
        $pdf->Cell(
            $this->layout->pageWidth(),
            self::COVER_SUBTITLE_HEIGHT,
            $this->latin1($job->coverSubtitle),
            0,
            0,
            'C',
        );
    }

    /**
     * @param list<ProcessedImage> $images
     */
    private function drawPhotos(FPDF $pdf, array $images): void
    {
        foreach ($images as $index => $image) {
            if ($this->layout->startsPage($index)) {
                $pdf->AddPage();
            }

            $placement = $this->layout->fit(
                (float) $image->width,
                (float) $image->height,
                $this->layout->columnFor($index),
                $this->layout->rowFor($index),
            );

            $pdf->Image(
                $image->path,
                $placement->x,
                $placement->y,
                $placement->width,
                $placement->height,
                'JPEG',
            );
        }
    }

    private function latin1(string $value): string
    {
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }
}
