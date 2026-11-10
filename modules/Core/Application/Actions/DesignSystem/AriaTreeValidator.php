<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Pemvalidasi relasi dan integritas pohon aksesibilitas ARIA pada komponen UI interaktif.
 */
class AriaTreeValidator
{
    /**
     * Pola komponen yang didukung: 'dialog', 'tabs', 'combobox', 'accordion', 'toast', 'stepper'.
     *
     * @return array{valid: bool, issues: array<int, array<string, mixed>>, pattern: string}
     */
    public function validatePattern(string $pattern, string $html): array
    {
        $dom = new DOMDocument();
        $previousState = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8" ?><div>' . trim($html) . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $xpath = new DOMXPath($dom);
        $issues = [];

        switch ($pattern) {
            case 'dialog':
                $this->validateDialogPattern($xpath, $issues);
                break;
            case 'tabs':
                $this->validateTabsPattern($xpath, $issues);
                break;
            case 'combobox':
                $this->validateComboboxPattern($xpath, $issues);
                break;
            case 'accordion':
                $this->validateAccordionPattern($xpath, $issues);
                break;
            case 'toast':
                $this->validateToastPattern($xpath, $issues);
                break;
            default:
                $this->validateGeneralAria($xpath, $issues);
                break;
        }

        return [
            'valid' => count($issues) === 0,
            'issues' => $issues,
            'pattern' => $pattern,
        ];
    }

    /**
     * Validasi pola WAI-ARIA Modal Dialog.
     */
    protected function validateDialogPattern(DOMXPath $xpath, array &$issues): void
    {
        $dialogs = $xpath->query('//*[@role="dialog" or @role="alertdialog"]');

        if ($dialogs->count() === 0) {
            $issues[] = [
                'rule' => 'DIALOG_ROLE_MISSING',
                'message' => 'Komponen modal dialog wajib menyertakan role="dialog" atau role="alertdialog".',
                'severity' => 'error',
            ];
            return;
        }

        foreach ($dialogs as $dialog) {
            if (!$dialog instanceof DOMElement) {
                continue;
            }

            if ($dialog->getAttribute('aria-modal') !== 'true') {
                $issues[] = [
                    'rule' => 'ARIA_MODAL_ATTRIBUTE_MISSING',
                    'message' => 'Elemen dialog wajib menyertakan atribut aria-modal="true" untuk menandai focus trapping.',
                    'severity' => 'error',
                ];
            }

            $hasLabel = $dialog->hasAttribute('aria-label') || $dialog->hasAttribute('aria-labelledby');
            if (!$hasLabel) {
                $issues[] = [
                    'rule' => 'DIALOG_LABEL_MISSING',
                    'message' => 'Elemen dialog wajib memiliki judul aksesibel melalui aria-labelledby atau aria-label.',
                    'severity' => 'error',
                ];
            }
        }
    }

    /**
     * Validasi pola WAI-ARIA Tabs (tablist -> tab -> tabpanel).
     */
    protected function validateTabsPattern(DOMXPath $xpath, array &$issues): void
    {
        $tablists = $xpath->query('//*[@role="tablist"]');
        if ($tablists->count() === 0) {
            $issues[] = [
                'rule' => 'TABLIST_ROLE_MISSING',
                'message' => 'Komponen Tabs wajib memiliki kontainer dengan role="tablist".',
                'severity' => 'error',
            ];
            return;
        }

        $tabs = $xpath->query('//*[@role="tab"]');
        if ($tabs->count() === 0) {
            $issues[] = [
                'rule' => 'TAB_ROLE_MISSING',
                'message' => 'Komponen Tabs wajib memiliki setidaknya satu elemen anak dengan role="tab".',
                'severity' => 'error',
            ];
        }

        $panels = $xpath->query('//*[@role="tabpanel"]');
        if ($panels->count() === 0) {
            $issues[] = [
                'rule' => 'TABPANEL_ROLE_MISSING',
                'message' => 'Komponen Tabs wajib memiliki panel konten dengan role="tabpanel".',
                'severity' => 'error',
            ];
        }

        foreach ($tabs as $tab) {
            if (!$tab instanceof DOMElement) {
                continue;
            }

            if (!$tab->hasAttribute('aria-selected')) {
                $issues[] = [
                    'rule' => 'TAB_ARIA_SELECTED_MISSING',
                    'message' => "Tab [{$tab->textContent}] tidak memiliki atribut status aria-selected=\"true|false\".",
                    'severity' => 'warning',
                ];
            }

            if (!$tab->hasAttribute('aria-controls')) {
                $issues[] = [
                    'rule' => 'TAB_ARIA_CONTROLS_MISSING',
                    'message' => "Tab [{$tab->textContent}] tidak memiliki atribut aria-controls yang mengaitkannya ke id tabpanel.",
                    'severity' => 'warning',
                ];
            }
        }
    }

    /**
     * Validasi pola WAI-ARIA Combobox / Dropdown Autocomplete.
     */
    protected function validateComboboxPattern(DOMXPath $xpath, array &$issues): void
    {
        $comboboxes = $xpath->query('//*[@role="combobox"]');
        if ($comboboxes->count() === 0) {
            $issues[] = [
                'rule' => 'COMBOBOX_ROLE_MISSING',
                'message' => 'Elemen input combobox wajib memiliki role="combobox".',
                'severity' => 'error',
            ];
            return;
        }

        foreach ($comboboxes as $box) {
            if (!$box instanceof DOMElement) {
                continue;
            }

            if (!$box->hasAttribute('aria-expanded')) {
                $issues[] = [
                    'rule' => 'COMBOBOX_EXPANDED_MISSING',
                    'message' => 'Elemen combobox wajib memiliki atribut status aria-expanded.',
                    'severity' => 'error',
                ];
            }

            if (!$box->hasAttribute('aria-controls')) {
                $issues[] = [
                    'rule' => 'COMBOBOX_CONTROLS_MISSING',
                    'message' => 'Elemen combobox wajib memiliki aria-controls yang mereferensikan listbox hasil.',
                    'severity' => 'error',
                ];
            }
        }
    }

    /**
     * Validasi pola WAI-ARIA Accordion / Disclosure.
     */
    protected function validateAccordionPattern(DOMXPath $xpath, array &$issues): void
    {
        $triggers = $xpath->query('//button[@aria-expanded]');
        if ($triggers->count() === 0) {
            $issues[] = [
                'rule' => 'ACCORDION_TRIGGER_MISSING',
                'message' => 'Accordion membutuhkan tombol pemantik dengan atribut aria-expanded="true|false".',
                'severity' => 'error',
            ];
            return;
        }

        foreach ($triggers as $trigger) {
            if (!$trigger instanceof DOMElement) {
                continue;
            }

            $controlsId = $trigger->getAttribute('aria-controls');
            if (empty($controlsId)) {
                $issues[] = [
                    'rule' => 'ACCORDION_CONTROLS_MISSING',
                    'message' => 'Tombol pemantik accordion wajib mereferensikan id panel via aria-controls.',
                    'severity' => 'error',
                ];
            } else {
                $target = $xpath->query("//*[@id='{$controlsId}']");
                if ($target->count() === 0) {
                    $issues[] = [
                        'rule' => 'ACCORDION_TARGET_NOT_FOUND',
                        'message' => "Panel target dengan id='{$controlsId}' tidak ditemukan di dokumen.",
                        'severity' => 'error',
                    ];
                }
            }
        }
    }

    /**
     * Validasi pola WAI-ARIA Toast / Status Alert (Live Region).
     */
    protected function validateToastPattern(DOMXPath $xpath, array &$issues): void
    {
        $liveRegions = $xpath->query('//*[@role="status" or @role="alert" or @aria-live]');

        if ($liveRegions->count() === 0) {
            $issues[] = [
                'rule' => 'TOAST_LIVE_REGION_MISSING',
                'message' => 'Komponen notifikasi/toast wajib memiliki role="status", role="alert", atau aria-live="polite|assertive".',
                'severity' => 'error',
            ];
        }
    }

    /**
     * Validasi umum referensi ID pada atribut ARIA (aria-describedby, aria-labelledby, aria-controls).
     */
    protected function validateGeneralAria(DOMXPath $xpath, array &$issues): void
    {
        $elementsWithRefs = $xpath->query('//*[@aria-labelledby or @aria-describedby or @aria-controls]');

        foreach ($elementsWithRefs as $elem) {
            if (!$elem instanceof DOMElement) {
                continue;
            }

            foreach (['aria-labelledby', 'aria-describedby', 'aria-controls'] as $attr) {
                if ($elem->hasAttribute($attr)) {
                    $refIds = preg_split('/\s+/', trim($elem->getAttribute($attr)));
                    foreach ($refIds as $id) {
                        if ($id !== '' && $xpath->query("//*[@id='{$id}']")->count() === 0) {
                            $issues[] = [
                                'rule' => 'BROKEN_ARIA_REFERENCE',
                                'message' => "Atribut {$attr}='{$id}' menunjuk ke ID yang tidak ada pada dokumen.",
                                'severity' => 'warning',
                            ];
                        }
                    }
                }
            }
        }
    }
}
