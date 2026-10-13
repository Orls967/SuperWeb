<?php

declare(strict_types=1);

namespace App\Quality\Docs;

/**
 * Resolves references such as `DECISIONS.md#2026-10-10-baseline-awal` against the
 * headings of docs/DECISIONS.md, using GitHub's heading-anchor rules, so a
 * baseline increase or a `keputusan:` evidence line cannot cite a decision that
 * does not exist.
 */
final class DecisionLog
{
    /** @var list<string> */
    private array $anchors;

    public function __construct(string $markdown)
    {
        $this->anchors = self::anchorsOf($markdown);
    }

    public static function fromFile(string $path): self
    {
        return new self(is_file($path) ? (string) file_get_contents($path) : '');
    }

    /**
     * GitHub-style anchor of a heading text.
     */
    public static function slug(string $heading): string
    {
        $text = mb_strtolower(trim($heading));
        $text = (string) preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $text);

        return str_replace(' ', '-', $text);
    }

    /**
     * Accepts `DECISIONS.md#anchor`, `docs/DECISIONS.md#anchor`, `#anchor` or `anchor`.
     */
    public function has(string $reference): bool
    {
        $anchor = str_contains($reference, '#') ? substr($reference, strrpos($reference, '#') + 1) : $reference;

        return $anchor !== '' && in_array(strtolower($anchor), $this->anchors, true);
    }

    /**
     * @return list<string>
     */
    private static function anchorsOf(string $markdown): array
    {
        $anchors = [];
        $seen = [];
        $inFence = false;

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = ! $inFence;

                continue;
            }

            if ($inFence || preg_match('/^#{1,6}\s+(.+?)\s*#*\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $slug = self::slug($matches[1]);
            $anchors[] = isset($seen[$slug]) ? $slug.'-'.$seen[$slug] : $slug;
            $seen[$slug] = ($seen[$slug] ?? 0) + 1;
        }

        return $anchors;
    }
}
