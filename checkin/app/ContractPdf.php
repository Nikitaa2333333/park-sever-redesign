<?php
declare(strict_types=1);

// Подключается только после pdf_lib() — наследуется от tFPDF.
class ContractPdf extends tFPDF
{
    public string $footerText = '';

    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Serif', '', 7.5);
        $this->SetTextColor(40, 38, 42);
        $w = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $this->Cell($w - 28, 4, $this->footerText, 0, 0, 'L');
        $this->Cell(28, 4, 'стр. ' . $this->PageNo() . ' из {nb}', 0, 0, 'R');
    }
}
