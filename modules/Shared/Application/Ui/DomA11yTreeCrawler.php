<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Pemindai pohon DOM HTML untuk kepatuhan pedoman aksesibilitas WCAG 2.1 Level AA.
 */
class DomA11yTreeCrawler
{
    /**
     * Jalankan inspeksi aksesibilitas mendalam atas string dokumen atau cuplikan HTML.
     *
     * @return array{passed: bool, total_issues: int, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, statistics: array<string, int>}
     */
    public function inspect(string $html): array
    {
        $cleanHtml = trim($html);
        if ($cleanHtml === '') {
            return [
                'passed' => true,
                'total_issues' => 0,
                'errors' => [],
                'warnings' => [],
                'statistics' => [
                    'headings' => 0,
                    'images' => 0,
                    'inputs' => 0,
                    'buttons' => 0,
                    'links' => 0,
                    'tables' => 0,
                ],
            ];
        }

        $dom = new DOMDocument();
        $previousState = libxml_use_internal_errors(true);
        // Bungkus dengan div penampung dan UTF-8 meta
        $wrapped = '<?xml encoding="utf-8" ?><div>' . $cleanHtml . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $xpath = new DOMXPath($dom);

        $errors = [];
        $warnings = [];

        // 1. Validasi Heading Hierarchy (h1 .. h6)
        $this->validateHeadings($xpath, $errors, $warnings);

        // 2. Validasi Gambar dan Atribut Alt (WCAG 1.1.1)
        $this->validateImages($xpath, $errors, $warnings);

        // 3. Validasi Form Inputs & Labels (WCAG 1.3.1 / 3.3.2)
        $this->validateFormControls($xpath, $errors, $warnings);

        // 4. Validasi Tombol & Tautan Kosong (WCAG 4.1.2)
        $this->validateInteractiveElements($xpath, $errors, $warnings);

        // 5. Validasi Struktur Tabel (WCAG 1.3.1)
        $this->validateTables($xpath, $errors, $warnings);

        // 6. Validasi Landmark dan Navigasi
        $this->validateLandmarks($xpath, $errors, $warnings);

        $stats = [
            'headings' => $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6')->count(),
            'images' => $xpath->query('//img')->count(),
            'inputs' => $xpath->query('//input | //select | //textarea')->count(),
            'buttons' => $xpath->query('//button')->count(),
            'links' => $xpath->query('//a')->count(),
            'tables' => $xpath->query('//table')->count(),
        ];

        return [
            'passed' => count($errors) === 0,
            'total_issues' => count($errors) + count($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
            'statistics' => $stats,
        ];
    }

    /**
     * Periksa lompatan urutan heading (misal h1 langsung ke h4 tanpa h2 dan h3).
     */
    protected function validateHeadings(DOMXPath $xpath, array &$errors, array &$warnings): void
    {
        $headingNodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $previousLevel = 0;

        foreach ($headingNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $currentLevel = (int) substr($node->nodeName, 1);

            if ($previousLevel > 0 && $currentLevel > $previousLevel + 1) {
                $errors[] = [
                    'rule' => 'HEADING_LEVEL_SKIPPED',
                    'message' => "Urutan heading melompat dari <h{$previousLevel}> ke <h{$currentLevel}>.",
                    'node' => $node->nodeName,
                    'text' => trim($node->textContent),
                    'severity' => 'error',
                ];
            }

            $previousLevel = $currentLevel;
        }
    }

    /**
     * Periksa keberadaan atribut alt atau role presentasi pada gambar.
     */
    protected function validateImages(DOMXPath $xpath, array &$errors, array &$warnings): void
    {
        $images = $xpath->query('//img');

        foreach ($images as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }

            if (!$img->hasAttribute('alt')) {
                $role = $img->getAttribute('role');
                $ariaHidden = $img->getAttribute('aria-hidden');

                if ($role !== 'presentation' && $ariaHidden !== 'true') {
                    $errors[] = [
                        'rule' => 'IMAGE_ALT_MISSING',
                        'message' => 'Elemen <img> wajib memiliki atribut alt atau role="presentation".',
                        'src' => $img->getAttribute('src'),
                        'severity' => 'error',
                    ];
                }
            }
        }
    }

    /**
     * Periksa asosiasi label pada setiap input, select, dan textarea.
     */
    protected function validateFormControls(DOMXPath $xpath, array &$errors, array &$warnings): void
    {
        $controls = $xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button") and not(@type="image")] | //select | //textarea');

        foreach ($controls as $control) {
            if (!$control instanceof DOMElement) {
                continue;
            }

            $id = $control->getAttribute('id');
            $ariaLabel = $control->getAttribute('aria-label');
            $ariaLabelledBy = $control->getAttribute('aria-labelledby');

            $hasDirectLabel = !empty($ariaLabel) || !empty($ariaLabelledBy);

            if (!$hasDirectLabel && !empty($id)) {
                $matchingLabel = $xpath->query("//label[@for='{$id}']");
                if ($matchingLabel->count() > 0) {
                    $hasDirectLabel = true;
                }
            }

            // Periksa juga apakah input berada di dalam <label>...</label>
            if (!$hasDirectLabel) {
                $parent = $control->parentNode;
                while ($parent !== null) {
                    if ($parent->nodeName === 'label') {
                        $hasDirectLabel = true;
                        break;
                    }
                    $parent = $parent->parentNode;
                }
            }

            if (!$hasDirectLabel) {
                $errors[] = [
                    'rule' => 'FORM_CONTROL_LABEL_MISSING',
                    'message' => "Elemen input <{$control->nodeName}> [name='{$control->getAttribute('name')}'] tidak memiliki label atau aria-label.",
                    'name' => $control->getAttribute('name'),
                    'id' => $id,
                    'severity' => 'error',
                ];
            }
        }
    }

    /**
     * Periksa tombol atau tautan yang kosong tanpa teks atau aria-label.
     */
    protected function validateInteractiveElements(DOMXPath $xpath, array &$errors, array &$warnings): void
    {
        $buttons = $xpath->query('//button');
        foreach ($buttons as $btn) {
            if (!$btn instanceof DOMElement) {
                continue;
            }

            $text = trim($btn->textContent);
            $ariaLabel = $btn->getAttribute('aria-label');
            $ariaLabelledBy = $btn->getAttribute('aria-labelledby');
            $title = $btn->getAttribute('title');

            if ($text === '' && empty($ariaLabel) && empty($ariaLabelledBy) && empty($title)) {
                $errors[] = [
                    'rule' => 'EMPTY_BUTTON',
                    'message' => 'Tombol <button> tidak memiliki teks atau atribut nama aksesibel (aria-label/title).',
                    'severity' => 'error',
                ];
            }
        }

        $links = $xpath->query('//a');
        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }

            $text = trim($link->textContent);
            $ariaLabel = $link->getAttribute('aria-label');
            $title = $link->getAttribute('title');

            if ($text === '' && empty($ariaLabel) && empty($title)) {
                // Periksa apakah berisi gambar yang memiliki alt
                $childImgs = $xpath->query('.//img[@alt and not(@alt="")]', $link);
                if ($childImgs->count() === 0) {
                    $warnings[] = [
                        'rule' => 'EMPTY_LINK',
                        'message' => "Tautan <a href='{$link->getAttribute('href')}'> tidak memiliki teks deskriptif.",
                        'href' => $link->getAttribute('href'),
                        'severity' => 'warning',
                    ];
                }
            }
        }
    }

    /**
     * Periksa struktur tabel dan header kolom/baris.
     */
    protected function validateTables(DOMXPath $xpath, array &$errors, array &$warnings): void
    {
        $tables = $xpath->query('//table');

        foreach ($tables as $table) {
            if (!$table instanceof DOMElement) {
                continue;
            }

            // Jika role presentation, abaikan
            if ($table->getAttribute('role') === 'presentation') {
                continue;
            }

            $headers = $xpath->query('.//th', $table);
            if ($headers->count() === 0) {
                $warnings[] = [
                    'rule' => 'TABLE_HEADER_MISSING',
                    'message' => 'Tabel data tidak memiliki header <th> untuk mendeskripsikan kolom atau baris data.',
                    'severity' => 'warning',
                ];
            }
        }
    }

    /**
     * Periksa duplikasi landmark dan penamaan landmark kembar.
     */
    protected function validateLandmarks(DOMXPath $xpath, array &$errors, array &$warnings): void
    {
        $navs = $xpath->query('//nav');
        if ($navs->count() > 1) {
            foreach ($navs as $nav) {
                if ($nav instanceof DOMElement && !$nav->hasAttribute('aria-label') && !$nav->hasAttribute('aria-labelledby')) {
                    $warnings[] = [
                        'rule' => 'MULTIPLE_NAV_WITHOUT_LABEL',
                        'message' => 'Terdapat lebih dari satu elemen <nav>; sertakan aria-label pembeda pada masing-masing navigasi.',
                        'severity' => 'warning',
                    ];
                    break;
                }
            }
        }
    }
}

