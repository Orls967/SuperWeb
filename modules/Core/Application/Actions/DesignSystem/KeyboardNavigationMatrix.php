<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Matriks dan pemeriksa navigasi keyboard (WAI-ARIA Keyboard Navigation & WCAG 2.1.1 / 2.4.3).
 */
class KeyboardNavigationMatrix
{
    /**
     * Analisis markup komponen untuk kepatuhan navigasi keyboard.
     *
     * @return array{compliant: bool, violations: array<int, array<string, mixed>>, focusable_count: int, positive_tabindex_count: int, escape_handlers_count: int}
     */
    public function analyze(string $html): array
    {
        $dom = new DOMDocument;
        $previousState = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8" ?><div>'.trim($html).'</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $xpath = new DOMXPath($dom);
        $violations = [];

        // 1. Deteksi Tabindex Positif (> 0 merupakan pelanggaran WCAG 2.4.3)
        $positiveTabindexes = $xpath->query('//*[@tabindex and @tabindex > 0]');
        $posCount = $positiveTabindexes->count();

        foreach ($positiveTabindexes as $node) {
            if ($node instanceof DOMElement) {
                $violations[] = [
                    'rule' => 'POSITIVE_TABINDEX_PROHIBITED',
                    'message' => "Penggunaan tabindex positif [tabindex={$node->getAttribute('tabindex')}] merusak urutan fokus alami pembaca layar.",
                    'element' => $node->nodeName,
                    'severity' => 'error',
                ];
            }
        }

        // 2. Deteksi Elemen Dialog tanpa Escape Handler atau Focus Trap
        $dialogs = $xpath->query('//*[@role="dialog" or @role="alertdialog"]');
        foreach ($dialogs as $dialog) {
            if ($dialog instanceof DOMElement) {
                $hasEscape = $this->hasEscapeHandler($dialog, $html);
                if (! $hasEscape) {
                    $violations[] = [
                        'rule' => 'DIALOG_ESCAPE_HANDLER_MISSING',
                        'message' => 'Elemen dialog harus dapat ditutup dengan tombol Escape (WCAG 2.1.2 No Keyboard Trap).',
                        'element' => $dialog->nodeName,
                        'severity' => 'warning',
                    ];
                }

                $hasTrap = $dialog->hasAttribute('x-trap') || str_contains($html, 'x-trap') || str_contains($html, 'focus-trap');
                if (! $hasTrap) {
                    $violations[] = [
                        'rule' => 'DIALOG_FOCUS_TRAP_MISSING',
                        'message' => 'Elemen modal dialog wajib mengurung fokus keyboard (focus trap) agar fokus tidak bocor ke latar belakang.',
                        'element' => $dialog->nodeName,
                        'severity' => 'warning',
                    ];
                }
            }
        }

        // 3. Deteksi Tablist tanpa Roving Tabindex
        $tablists = $xpath->query('//*[@role="tablist"]');
        foreach ($tablists as $tablist) {
            $tabs = $xpath->query('.//*[@role="tab"]', $tablist);
            $hasNegativeIndex = false;
            foreach ($tabs as $tab) {
                if ($tab instanceof DOMElement && $tab->getAttribute('tabindex') === '-1') {
                    $hasNegativeIndex = true;
                    break;
                }
            }

            if ($tabs->count() > 1 && ! $hasNegativeIndex && ! str_contains($html, 'keydown.arrow')) {
                $violations[] = [
                    'rule' => 'TABLIST_ROVING_TABINDEX_MISSING',
                    'message' => 'Tablist harus mengimplementasikan roving tabindex (tab non-aktif memiliki tabindex="-1") dan navigasi tombol panah.',
                    'severity' => 'warning',
                ];
            }
        }

        // 4. Hitung elemen yang dapat menerima fokus
        $focusables = $xpath->query('//a[@href] | //button[not(@disabled)] | //input[not(@disabled) and not(@type="hidden")] | //select[not(@disabled)] | //textarea[not(@disabled)] | //*[@tabindex="0"]');

        $escapeMatches = preg_match_all('/(keydown\.escape|@keyup\.escape|onkeydown.*Escape)/i', $html);

        return [
            'compliant' => count($violations) === 0,
            'violations' => $violations,
            'focusable_count' => $focusables->count(),
            'positive_tabindex_count' => $posCount,
            'escape_handlers_count' => (int) $escapeMatches,
        ];
    }

    /**
     * Periksa apakah dialog memiliki handler Escape.
     */
    protected function hasEscapeHandler(DOMElement $element, string $fullHtml): bool
    {
        if ($element->hasAttribute('@keydown.escape') || $element->hasAttribute('@keydown.escape.window')) {
            return true;
        }

        if (str_contains($fullHtml, 'keydown.escape') || str_contains($fullHtml, 'keyup.escape')) {
            return true;
        }

        return false;
    }

    /**
     * Deteksi konflik duplikasi accesskey pada dokumen.
     *
     * @return array<string, array<int, string>> Peta kunci yang mengalami konflik
     */
    public function detectAccessKeyCollisions(string $html): array
    {
        $dom = new DOMDocument;
        $previousState = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8" ?><div>'.trim($html).'</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $xpath = new DOMXPath($dom);
        $elements = $xpath->query('//*[@accesskey]');

        $keyMap = [];
        foreach ($elements as $elem) {
            if ($elem instanceof DOMElement) {
                $key = strtolower(trim($elem->getAttribute('accesskey')));
                if ($key !== '') {
                    $keyMap[$key][] = $elem->nodeName.($elem->hasAttribute('id') ? '#'.$elem->getAttribute('id') : '');
                }
            }
        }

        $collisions = [];
        foreach ($keyMap as $key => $nodeNames) {
            if (count($nodeNames) > 1) {
                $collisions[$key] = $nodeNames;
            }
        }

        return $collisions;
    }
}
