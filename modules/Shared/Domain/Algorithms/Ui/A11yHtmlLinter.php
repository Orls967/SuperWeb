<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * A11yHtmlLinter (Algoritma A3 — Fase 240)
 *
 * Memindai dokumen HTML (DOMDocument + nomor baris sumber) terhadap aturan aksesibilitas WCAG:
 * - IMG_ALT: <img> tanpa atribut alt (alt="" diizinkan dekoratif)
 * - INPUT_LABEL: input/select/textarea selain hidden/submit/button/reset/image tanpa label/aria
 * - BUTTON_NAME: <button> tanpa teks konten dan tanpa aria-label/aria-labelledby
 * - LINK_NAME: <a href> tanpa teks konten dan tanpa aria-label/aria-labelledby
 * - TABINDEX_POSITIVE: tabindex > 0 yang merusak urutan fokus alami keyboard
 *
 * Kompleksitas Waktu: O(N) terhadap jumlah node HTML.
 * Kompleksitas Ruang: O(N)
 */
class A11yHtmlLinter
{
    public const RULE_IMG_ALT = 'IMG_ALT';

    public const RULE_INPUT_LABEL = 'INPUT_LABEL';

    public const RULE_BUTTON_NAME = 'BUTTON_NAME';

    public const RULE_LINK_NAME = 'LINK_NAME';

    public const RULE_TABINDEX_POSITIVE = 'TABINDEX_POSITIVE';

    /**
     * Memindai string HTML dan mengembalikan daftar pelanggaran terurut (baris, aturan).
     *
     * @return array{
     *   violations: list<array{line: int, rule: string, element: string}>,
     *   total_violations: int,
     *   certificate: string
     * }
     */
    public function lint(string $html): array
    {
        $violations = [];

        if (trim($html) === '') {
            $payload = [
                'violations' => [],
                'total_violations' => 0,
            ];
            $payload['certificate'] = A11yLintCertificateVerifier::sign($payload);

            return $payload;
        }

        $dom = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        // Muat fragmen HTML tanpa doctype wrapper otomatis
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $xpath = new DOMXPath($dom);

        // Kumpulkan seluruh ID yang memiliki label for="{id}"
        $labeledIds = [];
        $labels = $xpath->query('//label[@for]');
        if ($labels !== false) {
            /** @var DOMElement $label */
            foreach ($labels as $label) {
                $for = trim($label->getAttribute('for'));
                if ($for !== '') {
                    $labeledIds[$for] = true;
                }
            }
        }

        // 1. Periksa IMG_ALT
        $images = $xpath->query('//img');
        if ($images !== false) {
            /** @var DOMElement $img */
            foreach ($images as $img) {
                if (! $img->hasAttribute('alt')) {
                    $violations[] = [
                        'line' => $img->getLineNo(),
                        'rule' => self::RULE_IMG_ALT,
                        'element' => 'img',
                    ];
                }
            }
        }

        // 2. Periksa INPUT_LABEL (input, select, textarea)
        $formControls = $xpath->query('//input | //select | //textarea');
        if ($formControls !== false) {
            /** @var DOMElement $ctrl */
            foreach ($formControls as $ctrl) {
                $tag = strtolower($ctrl->nodeName);
                if ($tag === 'input') {
                    $type = strtolower($ctrl->getAttribute('type') ?: 'text');
                    if (in_array($type, ['hidden', 'submit', 'button', 'reset', 'image'], true)) {
                        continue;
                    }
                }

                $hasAriaLabel = trim($ctrl->getAttribute('aria-label')) !== '';
                $hasAriaLabelledBy = trim($ctrl->getAttribute('aria-labelledby')) !== '';
                $id = trim($ctrl->getAttribute('id'));
                $hasMatchingLabel = $id !== '' && isset($labeledIds[$id]);

                // Juga periksa apakah dibungkus langsung oleh <label>...</label>
                $isWrappedByLabel = $ctrl->parentNode && strtolower($ctrl->parentNode->nodeName) === 'label';

                if (! $hasAriaLabel && ! $hasAriaLabelledBy && ! $hasMatchingLabel && ! $isWrappedByLabel) {
                    $violations[] = [
                        'line' => $ctrl->getLineNo(),
                        'rule' => self::RULE_INPUT_LABEL,
                        'element' => $tag,
                    ];
                }
            }
        }

        // 3. Periksa BUTTON_NAME (<button>)
        $buttons = $xpath->query('//button');
        if ($buttons !== false) {
            /** @var DOMElement $btn */
            foreach ($buttons as $btn) {
                $hasAriaLabel = trim($btn->getAttribute('aria-label')) !== '';
                $hasAriaLabelledBy = trim($btn->getAttribute('aria-labelledby')) !== '';
                $hasTitle = trim($btn->getAttribute('title')) !== '';
                $textContent = trim($btn->textContent);

                if (! $hasAriaLabel && ! $hasAriaLabelledBy && ! $hasTitle && $textContent === '') {
                    $violations[] = [
                        'line' => $btn->getLineNo(),
                        'rule' => self::RULE_BUTTON_NAME,
                        'element' => 'button',
                    ];
                }
            }
        }

        // 4. Periksa LINK_NAME (<a href>)
        $links = $xpath->query('//a[@href]');
        if ($links !== false) {
            /** @var DOMElement $link */
            foreach ($links as $link) {
                $hasAriaLabel = trim($link->getAttribute('aria-label')) !== '';
                $hasAriaLabelledBy = trim($link->getAttribute('aria-labelledby')) !== '';
                $hasTitle = trim($link->getAttribute('title')) !== '';
                $textContent = trim($link->textContent);
                $hasImgAlt = false;
                $nestedImgs = $link->getElementsByTagName('img');
                if ($nestedImgs->length > 0) {
                    /** @var DOMElement $nImg */
                    foreach ($nestedImgs as $nImg) {
                        if ($nImg->hasAttribute('alt') && trim($nImg->getAttribute('alt')) !== '') {
                            $hasImgAlt = true;
                            break;
                        }
                    }
                }

                if (! $hasAriaLabel && ! $hasAriaLabelledBy && ! $hasTitle && $textContent === '' && ! $hasImgAlt) {
                    $violations[] = [
                        'line' => $link->getLineNo(),
                        'rule' => self::RULE_LINK_NAME,
                        'element' => 'a',
                    ];
                }
            }
        }

        // 5. Periksa TABINDEX_POSITIVE (setiap elemen dengan tabindex > 0)
        $tabElements = $xpath->query('//*[@tabindex]');
        if ($tabElements !== false) {
            /** @var DOMElement $elem */
            foreach ($tabElements as $elem) {
                $tabindex = trim($elem->getAttribute('tabindex'));
                if (is_numeric($tabindex) && ((int) $tabindex) > 0) {
                    $violations[] = [
                        'line' => $elem->getLineNo(),
                        'rule' => self::RULE_TABINDEX_POSITIVE,
                        'element' => strtolower($elem->nodeName),
                    ];
                }
            }
        }

        // Urutkan pelanggaran menurut nomor baris menaik, lalu nama aturan
        usort($violations, function (array $a, array $b): int {
            if ($a['line'] !== $b['line']) {
                return $a['line'] <=> $b['line'];
            }

            return strcmp($a['rule'], $b['rule']);
        });

        $payload = [
            'violations' => $violations,
            'total_violations' => count($violations),
        ];

        $payload['certificate'] = A11yLintCertificateVerifier::sign($payload);

        return $payload;
    }
}
