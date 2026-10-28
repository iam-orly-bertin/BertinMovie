<?php
/**
 * includes/export_lib.php : export des rapports en vrai fichier Excel (.xlsx) et en vrai PDF.
 *
 * Aucune bibliothèque externe (pas de Composer) : fonctionne sur XAMPP/WAMP et sur InfinityFree.
 * Prérequis : extension PHP « zip » (ZipArchive) pour l'Excel. Le PDF n'a besoin de rien de spécial.
 *
 * Format commun d'un rapport :
 *   $rapport = [
 *     'title'    => 'Rapports périodiques',
 *     'subtitle' => 'Annuel : 2005 à 2024',
 *     'sections' => [
 *        [ 'name' => 'Périodique',          // nom de l'onglet Excel
 *          'heading' => 'Films par année',  // titre de la section
 *          'head' => ['Période', 'Films'],
 *          'rows' => [['2024', 12], ...],
 *          'total' => ['TOTAL', 120],       // (facultatif) ligne de total
 *          'note' => 'Texte explicatif' ],  // (facultatif)
 *     ],
 *   ];
 *   bm_xlsx_output('rapport.xlsx', $rapport);   // télécharge puis exit
 *   bm_pdf_output('rapport.pdf', $rapport);     // télécharge puis exit
 */

/* ------------------------------------------------------------------ */
/*  Outils texte                                                       */
/* ------------------------------------------------------------------ */
function bm_ex_utf8($s): string
{
    $s = (string) $s;
    if (function_exists('mb_convert_encoding')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $s = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $s);
    }
    return $s;
}

function bm_ex_is_num($v): bool
{
    if (is_int($v) || is_float($v)) {
        return true;
    }
    return is_string($v) && preg_match('/^-?(0|[1-9][0-9]{0,14})(\.[0-9]+)?$/', $v) === 1;
}

/* ================================================================== */
/*  EXCEL (.xlsx)                                                      */
/* ================================================================== */
function bm_ex_xml($s): string
{
    $s = bm_ex_utf8($s);
    $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s);
    return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function bm_ex_col(int $i): string          // 0 -> A, 25 -> Z, 26 -> AA
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
        $s = chr(65 + ($i - 1) % 26) . $s;
    }
    return $s;
}

function bm_ex_sheet_name(string $n, array &$used): string
{
    $n = trim(preg_replace('/[\[\]\:\*\?\/\\\\]/u', ' ', bm_ex_utf8($n)));
    $n = $n === '' ? 'Rapport' : $n;
    $n = function_exists('mb_substr') ? mb_substr($n, 0, 28, 'UTF-8') : substr($n, 0, 28);
    $base = $n;
    $k = 2;
    while (isset($used[strtolower($n)])) {
        $n = $base . ' ' . $k++;
    }
    $used[strtolower($n)] = true;
    return $n;
}

function bm_ex_len($s): int
{
    $s = (string) $s;
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function bm_ex_cell(int $c, int $r, $v, int $styleText, int $styleNum): string
{
    $ref = bm_ex_col($c) . $r;
    if ($v === null || $v === '') {
        return '<c r="' . $ref . '" s="' . $styleText . '"/>';
    }
    if (bm_ex_is_num($v)) {
        $n = is_float($v) ? rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') : (string) $v;   // jamais de virgule décimale dans le XML
        return '<c r="' . $ref . '" s="' . $styleNum . '"><v>' . $n . '</v></c>';
    }
    return '<c r="' . $ref . '" s="' . $styleText . '" t="inlineStr"><is><t xml:space="preserve">' . bm_ex_xml($v) . '</t></is></c>';
}

function bm_ex_sheet_xml(array $rapport, array $sec): string
{
    $head = array_values($sec['head'] ?? []);
    $rows = $sec['rows'] ?? [];
    $nc = max(1, count($head));
    foreach ($rows as $r) {
        $nc = max($nc, count($r));
    }

    // largeur des colonnes
    $wd = array_fill(0, $nc, 8);
    foreach ($head as $i => $h) {
        $wd[$i] = max($wd[$i], bm_ex_len($h) + 4);
    }
    $all = $rows;
    if (!empty($sec['total'])) {
        $all[] = $sec['total'];
    }
    foreach ($all as $r) {
        $i = 0;
        foreach ($r as $v) {
            $wd[$i] = max($wd[$i], min(60, bm_ex_len($v) + 3));
            $i++;
        }
    }
    $cols = '';
    foreach ($wd as $i => $w) {
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . min(60, $w) . '" customWidth="1"/>';
    }

    $last = bm_ex_col($nc - 1);
    $xml = '';
    $r = 1;
    // titre + sous-titre
    $xml .= '<row r="1" ht="26" customHeight="1"><c r="A1" s="6" t="inlineStr"><is><t>' . bm_ex_xml('BERTIN MOVIE — ' . ($rapport['title'] ?? 'Rapport')) . '</t></is></c></row>';
    $sub = trim((string) ($rapport['subtitle'] ?? ''));
    $gen = 'Généré le ' . date('d/m/Y à H:i');
    $xml .= '<row r="2"><c r="A2" s="7" t="inlineStr"><is><t>' . bm_ex_xml($sub !== '' ? $sub . '   |   ' . $gen : $gen) . '</t></is></c></row>';
    $r = 3;
    if (!empty($sec['heading'])) {
        $xml .= '<row r="3"><c r="A3" s="8" t="inlineStr"><is><t>' . bm_ex_xml($sec['heading']) . '</t></is></c></row>';
    }
    $hr = 4;                                         // ligne d'en-tête du tableau
    $xml .= '<row r="' . $hr . '" ht="22" customHeight="1">';
    for ($c = 0; $c < $nc; $c++) {
        $xml .= '<c r="' . bm_ex_col($c) . $hr . '" s="1" t="inlineStr"><is><t>' . bm_ex_xml($head[$c] ?? '') . '</t></is></c>';
    }
    $xml .= '</row>';

    $r = $hr;
    foreach ($rows as $k => $row) {
        $r++;
        $zebra = ($k % 2 === 1);
        $xml .= '<row r="' . $r . '">';
        $row = array_values($row);
        for ($c = 0; $c < $nc; $c++) {
            $xml .= bm_ex_cell($c, $r, $row[$c] ?? '', $zebra ? 4 : 2, $zebra ? 5 : 3);
        }
        $xml .= '</row>';
    }
    $lastData = $r;
    if (!empty($sec['total'])) {
        $r++;
        $xml .= '<row r="' . $r . '">';
        $row = array_values($sec['total']);
        for ($c = 0; $c < $nc; $c++) {
            $xml .= bm_ex_cell($c, $r, $row[$c] ?? '', 9, 10);
        }
        $xml .= '</row>';
    }
    if (!empty($sec['note'])) {
        $r += 2;
        $xml .= '<row r="' . $r . '"><c r="A' . $r . '" s="7" t="inlineStr"><is><t>' . bm_ex_xml($sec['note']) . '</t></is></c></row>';
    }

    $filter = $rows ? '<autoFilter ref="A' . $hr . ':' . $last . $lastData . '"/>' : '';
    $merge = '<mergeCells count="1"><mergeCell ref="A1:' . $last . '1"/></mergeCells>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
        . '<pane ySplit="' . $hr . '" topLeftCell="A' . ($hr + 1) . '" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A' . ($hr + 1) . '" sqref="A' . ($hr + 1) . '"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="16"/>'
        . '<cols>' . $cols . '</cols>'
        . '<sheetData>' . $xml . '</sheetData>'
        . $filter . $merge
        . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        . '</worksheet>';
}

function bm_ex_styles_xml(): string
{
    $b = '<border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right>'
        . '<top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border>';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="6">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'                                                         // 0 normal
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'                              // 1 en-tête
        . '<font><b/><sz val="16"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>'                              // 2 titre
        . '<font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font>'                              // 3 sous-titre
        . '<font><b/><sz val="12"/><color rgb="FF3B82F6"/><name val="Calibri"/></font>'                              // 4 section
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'                                                     // 5 gras
        . '</fonts>'
        . '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF3B82F6"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $b . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="11">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                                      // 0
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 1 en-tête
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'                  // 2 texte
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 3 nombre
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'     // 4 texte zébré
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 5 nombre zébré
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'                     // 6 titre
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                          // 7 sous-titre
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                          // 8 section
        . '<xf numFmtId="0" fontId="5" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'                                           // 9 total texte
        . '<xf numFmtId="0" fontId="5" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 10 total nombre
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function bm_xlsx_build(array $rapport): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException("L'extension PHP « zip » est requise pour l'export Excel.");
    }
    $sections = $rapport['sections'] ?? [];
    if (!$sections) {
        $sections = [['name' => 'Rapport', 'head' => [], 'rows' => []]];
    }
    $used = [];
    $names = [];
    foreach ($sections as $s) {
        $names[] = bm_ex_sheet_name($s['name'] ?? 'Rapport', $used);
    }

    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach ($sections as $i => $s) {
        $n = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wb .= '<sheet name="' . bm_ex_xml($names[$i]) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $rel .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
    }
    $sn = count($sections) + 1;
    $rel .= '<Relationship Id="rId' . $sn . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $ct .= '</Types>';
    $wb .= '</sheets></workbook>';
    $root = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'bmx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Impossible de créer le fichier Excel temporaire.");
    }
    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', $root);
    $zip->addFromString('xl/workbook.xml', $wb);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $rel);
    $zip->addFromString('xl/styles.xml', bm_ex_styles_xml());
    foreach ($sections as $i => $s) {
        $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', bm_ex_sheet_xml($rapport, $s));
    }
    $zip->close();
    $data = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

function bm_xlsx_output(string $filename, array $rapport): void
{
    $data = bm_xlsx_build($rapport);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $data;
    exit();
}

/* ================================================================== */
/*  PDF (écrit à la main : Helvetica, A4, tableaux sur plusieurs pages) */
/* ================================================================== */
class BM_SimplePdf
{
    private array $pages = [];
    private string $cur = '';
    private float $W;
    private float $H;
    private float $mx = 36;
    private float $top = 36;
    private float $bot = 46;
    private float $y = 0;
    private array $cw = [];
    public string $footerLeft = '';
    private bool $first = true;

    public function __construct(bool $landscape = false)
    {
        $this->W = $landscape ? 841.89 : 595.28;
        $this->H = $landscape ? 595.28 : 841.89;
        $this->initWidths();
        $this->newPage();
    }

    /* ---- largeurs Helvetica (1/1000 em), approximation correcte pour la mise en page ---- */
    private function initWidths(): void
    {
        $ascii = [
            278,
            278,
            355,
            556,
            556,
            889,
            667,
            191,
            333,
            333,
            389,
            584,
            278,
            333,
            278,
            278,
            556,
            556,
            556,
            556,
            556,
            556,
            556,
            556,
            556,
            556,
            278,
            278,
            584,
            584,
            584,
            556,
            1015,
            667,
            667,
            722,
            722,
            667,
            611,
            778,
            722,
            278,
            500,
            667,
            556,
            833,
            722,
            778,
            667,
            778,
            722,
            667,
            611,
            722,
            667,
            944,
            667,
            667,
            611,
            278,
            278,
            278,
            469,
            556,
            333,
            556,
            556,
            500,
            556,
            556,
            278,
            556,
            556,
            222,
            222,
            500,
            222,
            833,
            556,
            556,
            556,
            556,
            333,
            500,
            278,
            556,
            500,
            722,
            500,
            500,
            500,
            334,
            260,
            334,
            584
        ];
        for ($i = 0; $i < 256; $i++) {
            $this->cw[$i] = 556;
        }
        foreach ($ascii as $k => $w) {
            $this->cw[32 + $k] = $w;
        }
        $base = [
            'A' => [192, 193, 194, 195, 196, 197],
            'C' => [199],
            'E' => [200, 201, 202, 203],
            'I' => [204, 205, 206, 207],
            'D' => [208],
            'N' => [209],
            'O' => [210, 211, 212, 213, 214, 216],
            'U' => [217, 218, 219, 220],
            'Y' => [221, 159],
            'P' => [222],
            'a' => [224, 225, 226, 227, 228, 229],
            'c' => [231],
            'e' => [232, 233, 234, 235],
            'i' => [236, 237, 238, 239],
            'o' => [242, 243, 244, 245, 246, 248, 240],
            'n' => [241],
            'u' => [249, 250, 251, 252],
            'y' => [253, 255],
            'p' => [254],
        ];
        foreach ($base as $ch => $codes) {
            foreach ($codes as $c) {
                $this->cw[$c] = $this->cw[ord($ch)];
            }
        }
        $this->cw[160] = 278;
        $this->cw[198] = 1000;
        $this->cw[230] = 889;
        $this->cw[151] = 1000;
        $this->cw[133] = 1000;
        $this->cw[146] = 222;
        $this->cw[145] = 222;
        $this->cw[147] = 333;
        $this->cw[148] = 333;
        $this->cw[176] = 400;
        $this->cw[215] = 584;
        $this->cw[247] = 584;
        $this->cw[236] = 278;
        $this->cw[237] = 278;
        $this->cw[238] = 278;
        $this->cw[239] = 278;
    }

    /* ---- UTF-8 -> Windows-1252 ---- */
    public function enc($s): string
    {
        $s = (string) $s;
        // seuls les caractères absents de Windows-1252 sont remplacés ici ; les autres (– — ’ « » é à œ …) sont convertis tels quels
        $s = strtr($s, ["\u{2192}" => '->', "\u{2264}" => '<=', "\u{2265}" => '>=']);
        if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        } elseif (function_exists('iconv')) {
            $s = (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        }
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', str_replace(["\r", "\n", "\t"], ' ', $s));
    }

    public function tw(string $enc, float $size, bool $bold = false): float
    {
        $w = 0;
        $n = strlen($enc);
        for ($i = 0; $i < $n; $i++) {
            $w += $this->cw[ord($enc[$i])];
        }
        return $w / 1000 * $size * ($bold ? 1.07 : 1);
    }

    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function f(float $n): string
    {
        return sprintf('%.2F', $n);
    }

    private function rgb(array $c): string
    {
        return $this->f($c[0] / 255) . ' ' . $this->f($c[1] / 255) . ' ' . $this->f($c[2] / 255);
    }

    public function text(float $x, float $yTop, string $enc, float $size, bool $bold = false, array $color = [15, 23, 42]): void
    {
        $this->cur .= 'BT /' . ($bold ? 'F2' : 'F1') . ' ' . $this->f($size) . ' Tf ' . $this->rgb($color) . ' rg '
            . $this->f($x) . ' ' . $this->f($this->H - $yTop) . ' Td (' . $this->esc($enc) . ") Tj ET\n";
    }

    public function rect(float $x, float $yTop, float $w, float $h, array $fill): void
    {
        $this->cur .= $this->rgb($fill) . ' rg ' . $this->f($x) . ' ' . $this->f($this->H - $yTop - $h) . ' ' . $this->f($w) . ' ' . $this->f($h) . " re f\n";
    }

    public function hline(float $x1, float $x2, float $yTop, array $color = [226, 232, 240], float $lw = 0.5): void
    {
        $this->cur .= $this->rgb($color) . ' RG ' . $this->f($lw) . ' w ' . $this->f($x1) . ' ' . $this->f($this->H - $yTop) . ' m '
            . $this->f($x2) . ' ' . $this->f($this->H - $yTop) . " l S\n";
    }

    public function newPage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
        $this->y = $this->top;
        if (count($this->pages) > 0 || !$this->first) {
            $this->first = false;
        }
    }

    public function width(): float
    {
        return $this->W - 2 * $this->mx;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function setY(float $y): void
    {
        $this->y = $y;
    }

    public function need(float $h): bool        // saute de page si besoin ; retourne true si une page a été créée
    {
        if ($this->y + $h > $this->H - $this->bot) {
            $this->newPage();
            return true;
        }
        return false;
    }

    /* ---- coupe un texte en lignes selon la largeur ---- */
    public function wrap(string $enc, float $maxw, float $size, bool $bold = false): array
    {
        $enc = trim($enc);
        if ($enc === '') {
            return [''];
        }
        $lines = [];
        $line = '';
        foreach (explode(' ', $enc) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->tw($try, $size, $bold) <= $maxw) {
                $line = $try;
                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }
            while ($this->tw($word, $size, $bold) > $maxw && strlen($word) > 1) {   // mot trop long : coupure dure
                $cut = strlen($word) - 1;
                while ($cut > 1 && $this->tw(substr($word, 0, $cut), $size, $bold) > $maxw) {
                    $cut--;
                }
                $lines[] = substr($word, 0, $cut);
                $word = substr($word, $cut);
            }
            $line = $word;
        }
        $lines[] = $line;
        return $lines;
    }

    /* ---- bandeau de titre (première page) ---- */
    public function banner(string $title, string $subtitle, string $date): void
    {
        $this->rect(0, 0, $this->W, 64, [15, 23, 42]);
        $this->rect(0, 64, $this->W, 3, [59, 130, 246]);
        $this->text($this->mx, 32, $this->enc('BERTIN'), 20, true, [255, 255, 255]);
        $x = $this->mx + $this->tw($this->enc('BERTIN '), 20, true);
        $this->text($x, 32, $this->enc('MOVIE'), 20, true, [96, 165, 250]);
        $this->text($this->mx, 49, $this->enc('Rapports & statistiques'), 9.5, false, [203, 213, 225]);
        $d = $this->enc($date);
        $this->text($this->W - $this->mx - $this->tw($d, 9, false), 49, $d, 9, false, [203, 213, 225]);

        $this->y = 92;
        $this->text($this->mx, $this->y, $this->enc($title), 17, true, [30, 58, 138]);
        $this->y += 17;
        if ($subtitle !== '') {
            foreach ($this->wrap($this->enc($subtitle), $this->width(), 10) as $l) {
                $this->text($this->mx, $this->y, $l, 10, false, [100, 116, 139]);
                $this->y += 14;
            }
        }
        $this->y += 8;
    }

    public function heading(string $t): void
    {
        $this->need(60);
        $this->text($this->mx, $this->y + 10, $this->enc($t), 12.5, true, [59, 130, 246]);
        $this->y += 22;
    }

    public function paragraph(string $t, array $color = [100, 116, 139], float $size = 9): void
    {
        foreach ($this->wrap($this->enc($t), $this->width(), $size) as $l) {
            $this->need(14);
            $this->text($this->mx, $this->y + 9, $l, $size, false, $color);
            $this->y += 13;
        }
        $this->y += 4;
    }

    /* ---- tableau ---- */
    public function table(array $head, array $rows, ?array $total = null): void
    {
        $nc = count($head);
        foreach ($rows as $r) {
            $nc = max($nc, count($r));
        }
        if ($nc === 0) {
            return;
        }
        $fs = 9;
        $pad = 5;
        $lh = 12;
        $avail = $this->width();

        // largeur naturelle de chaque colonne
        $nat = array_fill(0, $nc, 30.0);
        $encHead = [];
        foreach ($head as $i => $h) {
            $encHead[$i] = $this->enc($h);
            $nat[$i] = max($nat[$i], min(150, $this->tw($encHead[$i], $fs, true) + 2 * $pad));
        }
        $encRows = [];
        $isNum = array_fill(0, $nc, true);
        $all = $rows;
        if ($total) {
            $all[] = $total;
        }
        foreach ($all as $k => $r) {
            $r = array_values($r);
            for ($i = 0; $i < $nc; $i++) {
                $v = $r[$i] ?? '';
                $e = $this->enc($v);
                $encRows[$k][$i] = $e;
                $nat[$i] = max($nat[$i], min(260, $this->tw($e, $fs, false) + 2 * $pad));
                if ($v !== '' && $v !== '-' && !bm_ex_is_num($v) && !preg_match('/^[+-]?[0-9.,]+ ?%$/', (string) $v) && $v !== 'nouveau') {
                    $isNum[$i] = false;
                }
            }
        }
        $isNum[0] = false;                               // la 1re colonne (libellé) reste alignée à gauche
        if (!$rows) {
            $isNum = array_fill(0, $nc, false);
        }
        $sum = array_sum($nat);
        $w = [];
        foreach ($nat as $i => $n) {
            $w[$i] = $n * $avail / $sum;                 // proportionnel, remplit toute la largeur
        }

        $drawHead = function () use ($encHead, $w, $nc, $fs, $pad, $isNum) {
            $hl = [];
            $maxl = 1;
            for ($i = 0; $i < $nc; $i++) {
                $hl[$i] = $this->wrap($encHead[$i] ?? '', $w[$i] - 2 * $pad, $fs, true);
                $maxl = max($maxl, count($hl[$i]));
            }
            $h = $maxl * 12 + 10;
            $this->need($h + 30);
            $this->rect($this->mx, $this->y, $this->width(), $h, [59, 130, 246]);
            $x = $this->mx;
            for ($i = 0; $i < $nc; $i++) {
                $ly = $this->y + 6 + 9;
                foreach ($hl[$i] as $l) {
                    $tx = $x + $pad;
                    $tww = $this->tw($l, $fs, true);
                    if ($isNum[$i]) {
                        $tx = $x + $w[$i] - $pad - $tww;
                    }
                    $this->text($tx, $ly, $l, $fs, true, [255, 255, 255]);
                    $ly += 12;
                }
                $x += $w[$i];
            }
            $this->y += $h;
        };

        $drawHead();
        $count = count($rows);
        foreach ($all as $k => $_) {
            $isTotal = ($total && $k === $count);
            $cells = [];
            $maxl = 1;
            for ($i = 0; $i < $nc; $i++) {
                $cells[$i] = $this->wrap($encRows[$k][$i], $w[$i] - 2 * $pad, $fs, $isTotal);
                $maxl = max($maxl, count($cells[$i]));
            }
            $h = $maxl * $lh + 8;
            if ($this->y + $h > $this->H - $this->bot) {
                $this->newPage();
                $drawHead();
            }
            if ($isTotal) {
                $this->rect($this->mx, $this->y, $this->width(), $h, [219, 234, 254]);
            } elseif ($k % 2 === 1) {
                $this->rect($this->mx, $this->y, $this->width(), $h, [241, 245, 249]);
            }
            $x = $this->mx;
            for ($i = 0; $i < $nc; $i++) {
                $ly = $this->y + 4 + 9;
                foreach ($cells[$i] as $l) {
                    $tx = $x + $pad;
                    if ($isNum[$i]) {
                        $tx = $x + $w[$i] - $pad - $this->tw($l, $fs, $isTotal);
                    }
                    $this->text($tx, $ly, $l, $fs, $isTotal, [15, 23, 42]);
                    $ly += $lh;
                }
                $x += $w[$i];
            }
            $this->y += $h;
            $this->hline($this->mx, $this->mx + $this->width(), $this->y);
        }
        if (!$rows) {
            $this->need(24);
            $this->text($this->mx + $pad, $this->y + 16, $this->enc('Aucune donnée pour cette sélection.'), 9.5, false, [100, 116, 139]);
            $this->y += 24;
        }
        $this->y += 12;
    }

    /* ---- assemblage du fichier ---- */
    public function output(): string
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
            $this->cur = '';
        }
        $n = count($this->pages);
        $date = date('d/m/Y H:i');
        foreach ($this->pages as $i => &$p) {
            $p .= $this->rgb([203, 213, 225]) . ' RG 0.5 w ' . $this->f($this->mx) . ' ' . $this->f(34) . ' m '
                . $this->f($this->W - $this->mx) . ' ' . $this->f(34) . " l S\n";
            $left = $this->enc($this->footerLeft . '  |  ' . $date);
            $right = $this->enc('Page ' . ($i + 1) . ' / ' . $n);
            $p .= 'BT /F1 8 Tf ' . $this->rgb([100, 116, 139]) . ' rg ' . $this->f($this->mx) . ' 22 Td (' . $this->esc($left) . ") Tj ET\n";
            $p .= 'BT /F1 8 Tf ' . $this->rgb([100, 116, 139]) . ' rg ' . $this->f($this->W - $this->mx - $this->tw($right, 8)) . ' 22 Td (' . $this->esc($right) . ") Tj ET\n";
        }
        unset($p);

        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($this->pages as $i => $content) {
            $po = 5 + $i * 2;
            $co = $po + 1;
            $objs[$po] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->f($this->W) . ' ' . $this->f($this->H) . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $co . ' 0 R >>';
            if (function_exists('gzcompress')) {
                $z = gzcompress($content, 6);
                $objs[$co] = '<< /Length ' . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream";
            } else {
                $objs[$co] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            }
        }
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $off = [];
        ksort($objs);
        foreach ($objs as $id => $body) {
            $off[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $max = max(array_keys($objs));
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $out .= sprintf("%010d 00000 n \n", $off[$id]);
        }
        $out .= 'trailer << /Size ' . ($max + 1) . " /Root 1 0 R /Info << /Title (" . $this->esc($this->enc('Rapport BERTIN MOVIE')) . ") /Producer (BERTIN MOVIE) >> >>\nstartxref\n" . $xref . "\n%%EOF";
        return $out;
    }
}

function bm_pdf_build(array $rapport): string
{
    $sections = $rapport['sections'] ?? [];
    $maxCols = 0;
    foreach ($sections as $s) {
        $maxCols = max($maxCols, count($s['head'] ?? []));
    }
    $pdf = new BM_SimplePdf($maxCols > 5);               // paysage si le tableau est large
    $pdf->footerLeft = 'BERTIN MOVIE - ' . ($rapport['title'] ?? 'Rapport');
    $pdf->banner((string) ($rapport['title'] ?? 'Rapport'), (string) ($rapport['subtitle'] ?? ''), 'Généré le ' . date('d/m/Y à H:i'));
    foreach ($sections as $s) {
        if (!empty($s['heading'])) {
            $pdf->heading($s['heading']);
        }
        $pdf->table($s['head'] ?? [], $s['rows'] ?? [], $s['total'] ?? null);
        if (!empty($s['note'])) {
            $pdf->paragraph($s['note']);
        }
    }
    return $pdf->output();
}

function bm_pdf_output(string $filename, array $rapport, bool $inline = false): void
{
    $data = bm_pdf_build($rapport);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $data;
    exit();
}